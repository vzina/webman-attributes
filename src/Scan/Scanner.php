<?php
/**
 * Scanner — 类扫描与属性收集。
 *
 * cacheable + 缓存存在 → 直接加载缓存，零开销。
 * 否则 → fork 子进程隔离扫描 → 写缓存 → 父进程加载缓存。
 * fork 隔离确保原始类仅在子进程加载，代理文件可正确替换。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Vzina\Attributes\Ast\AstParser;
use Vzina\Attributes\Ast\ProxyLoaderInterface;
use Vzina\Attributes\Attribute\AttributeInterface;
use Vzina\Attributes\Reflection\AttributeReader;

class Scanner
{
    public function __construct(
        protected Options $option,
        protected Filesystem $filesystem = new Filesystem(),
    ) {}

    // ===================================================================
    // Public API
    // ===================================================================

    /** @return array className => filePath（含代理路径） */
    public function scan(array $classMap = []): array
    {
        $paths = $this->option->scanPath();
        if (! $paths) {
            return [];
        }

        $dir       = $this->option->cachePath();
        $cacheFile = $dir . '/scan.cache.php';
        $collectors = $this->option->collectors();

        // 快速路径：缓存命中
        if ($this->option->cacheable() && file_exists($cacheFile)) {
            return $this->readCache($dir, $cacheFile, $collectors);
        }

        // 读取旧类映射作为基线（含代理路径）
        $cachedMap   = $this->readEvalFile($dir . '/classmap.cache.php');
        $classMap    = $cachedMap ? array_merge($classMap, $cachedMap) : $classMap;
        $threshold   = $this->option->cacheable()
            ? (file_exists($cacheFile) ? $this->filesystem->lastModified($cacheFile) : 0)
            : 0;

        // fork：父进程加载缓存，子进程扫描后退出
        $handler = $this->option->scanHandler();
        if ($handler->scan()->isScanned()) {
            return $this->readCache($dir, $cacheFile, $collectors);
        }

        // 子进程
        try {
            return $this->scanClasses($paths, $dir, $cacheFile, $collectors, $cachedMap, $threshold);
            // PcntlHandler 在 finally → exit(0) 截断
        } finally {
            $handler->finish();
        }
    }

    /** 收集一个类的全部属性到对应 Collector */
    public function collect(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();
        if (($map = $this->option->classMap()[$className] ?? null) && $reflection->getFileName() !== $map) {
            return;
        }

        foreach (AttributeReader::getAttributes($reflection) as $attr) {
            if ($attr instanceof AttributeInterface) { $attr->collectClass($className); }
        }
        foreach ($reflection->getProperties() as $prop) {
            foreach (AttributeReader::getAttributes($prop) as $attr) {
                if ($attr instanceof AttributeInterface) { $attr->collectProperty($className, $prop->getName()); }
            }
        }
        foreach ($reflection->getMethods() as $method) {
            foreach (AttributeReader::getAttributes($method) as $attr) {
                if ($attr instanceof AttributeInterface) { $attr->collectMethod($className, $method->getName()); }
            }
        }
        foreach ($reflection->getReflectionConstants() as $const) {
            foreach (AttributeReader::getAttributes($const) as $attr) {
                if ($attr instanceof AttributeInterface) { $attr->collectClassConstant($className, $const->getName()); }
            }
        }
    }

    // ===================================================================
    // Cache I/O
    // ===================================================================

    /** 从缓存文件加载收集器数据与类映射 */
    private function readCache(string $dir, string $cacheFile, array $collectors): array
    {
        if (! file_exists($cacheFile)) {
            return [];
        }

        foreach ($collectors as $c) {
            if (method_exists($c, 'loadFromFile')) {
                $c::loadFromFile($dir . '/' . str_replace('\\', '_', $c) . '.cache.php');
            }
        }

        return $this->readEvalFile($dir . '/classmap.cache.php');
    }

    /** 写入全部缓存文件 */
    private function writeCache(string $dir, string $cacheFile, array $collectors, array $map): void
    {
        foreach ($collectors as $c) {
            if (method_exists($c, 'exportToFile')) {
                $c::exportToFile($dir . '/' . str_replace('\\', '_', $c) . '.cache.php');
            }
        }

        $this->filesystem->put("{$dir}/classmap.cache.php",
            "<?php\nreturn " . var_export($map, true) . ";\n", true);
        $this->filesystem->put($cacheFile, '<?php return ' . time() . ";\n", true);
    }

    /** 使用闭包 include 读取 PHP 缓存文件，绕过 OPcache 文件缓存 */
    private function readEvalFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        clearstatcache(true, $path);
        $data = (static function () use ($path) {
            return include $path;
        })();

        return is_array($data) ? $data : [];
    }

    // ===================================================================
    // Scan
    // ===================================================================

    private function scanClasses(
        array $paths,
        string $dir,
        string $cacheFile,
        array $collectors,
        array $cachedMap,
        int $threshold,
    ): array {
        $this->preloadCollectors($dir, $cacheFile, $collectors);

        $classPaths = AstParser::getInstance()->getAllClassesByPath($paths);
        $this->clearRemoved($collectors, array_keys($classPaths));

        foreach ($classPaths as $className => $filePath) {
            if ($this->filesystem->lastModified($filePath) >= $threshold) {
                foreach ($collectors as $c) { $c::clear($className); }
                require_once $filePath;
                $this->collect(new ReflectionClass($className));
            }
        }

        // $classPaths 在后：源文件路径优先，ProxyLoader 以此为基准做 mtime 比较
        $map = array_merge($cachedMap, $classPaths);
        foreach ($this->option->astProxyLoaders() as $loader) {
            if (class_exists($loader) && ($instance = new $loader) instanceof ProxyLoaderInterface) {
                $instance($this->option, $map);
            }
        }

        $this->writeCache($dir, $cacheFile, $collectors, $map);

        return $map;
    }

    /** 预加载已有缓存作为基线，后续逐类清除后再重新收集 */
    private function preloadCollectors(string $dir, string $cacheFile, array $collectors): void
    {
        if (! file_exists($cacheFile) && ! $this->filesystem->exists("{$dir}/classmap.cache.php")) {
            return;
        }

        foreach ($collectors as $c) {
            $cachePath = $dir . '/' . str_replace('\\', '_', $c) . '.cache.php';
            if (method_exists($c, 'loadFromFile') && $this->filesystem->exists($cachePath)) {
                $c::loadFromFile($cachePath);
            }
        }
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function clearRemoved(array $collectors, array $current): void
    {
        $file = $this->option->cachePath() . '/classes.cache.php';
        $old  = $this->filesystem->exists($file) ? $this->readEvalFile($file) : [];

        $this->filesystem->put($file, "<?php\nreturn " . var_export($current, true) . ";\n", true);

        foreach (array_diff($old, $current) as $class) {
            foreach ($collectors as $c) { $c::clear($class); }
        }
    }
}
