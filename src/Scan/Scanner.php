<?php
/**
 * Scanner — 类扫描与属性收集核心。
 *
 * 首次启动（无缓存）：
 *   通过 PcntlHandler fork 子进程扫描 → 收集属性 → 生成代理 → 写缓存 → 子进程退出。
 *   父进程加载缓存，原始类仅在子进程中加载，代理文件可正确替换。
 *
 * 后续启动（有缓存）：
 *   直接从 .cache.php 文件加载收集器和类映射，不做任何类加载。
 *
 * cacheable=false 时：
 *   每次启动都做增量扫描（仅扫描变更的文件），但代理类映射仍从缓存加载，
 *   确保代理文件路径在 ClassLoader 中正确覆盖原始文件。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Vzina\Attributes\Ast\AstParser;
use Vzina\Attributes\Ast\ProxyLoaderInterface;
use Vzina\Attributes\Attribute\AttributeInterface;
use Vzina\Attributes\Collector\MetadataCollector;
use Vzina\Attributes\Reflection\AttributeReader;

class Scanner
{
    protected Filesystem $filesystem;

    public function __construct(protected Options $option)
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @return array 含代理类条目的完整类映射
     */
    public function scan(array $classMap = []): array
    {
        $paths = $this->option->scanPath();
        if (! $paths) {
            return [];
        }

        $cacheDir   = $this->option->cachePath();
        $collectors = $this->option->collectors();
        $cacheFile  = $cacheDir . '/scan.cache.php';
        $cacheMtime = file_exists($cacheFile) ? $this->filesystem->lastModified($cacheFile) : 0;

        // 缓存有效且允许缓存 → 直接从缓存加载
        if ($cacheMtime > 0 && $this->option->cacheable()) {
            return $this->loadCache($cacheFile, $collectors);
        }

        // 类映射优先从缓存读取（即使 cacheable=false，也要保证代理文件路径生效）
        $cachedMap = $this->loadClassMap($cacheDir);
        $classMap  = $cachedMap ? array_merge($classMap, $cachedMap) : $classMap;

        // cacheable=false 时全量扫描，true 时仅扫变更文件
        $scanThreshold = $this->option->cacheable() ? $cacheMtime : 0;

        // 子进程隔离扫描，父进程加载缓存
        $handler = $this->option->scanHandler();
        if ($handler->scan()->isScanned()) {
            return $this->loadCache($cacheFile, $collectors);
        }

        // 扫描进程，加排他锁防多 Worker 并发扫描。
        // fopen 失败时降级为无锁扫描（fanout 磁盘满等极端情况）。
        $lockFile = $cacheDir . '/scan.lock';
        $fp = @fopen($lockFile, 'w');
        if ($fp && ! @flock($fp, LOCK_EX | LOCK_NB)) {
            // 另一个进程在扫描 → 等它完成 → 退出，父进程会加载其缓存
            @flock($fp, LOCK_SH);
            fclose($fp);
            $handler->finish();
        }

        try {
            // 从缓存加载已有的 collector 数据（未变更文件的属性无需重新收集）
            if ($cacheMtime > 0) {
                $this->loadCache($cacheFile, $collectors);
            }

            $classPaths = AstParser::getInstance()->getAllClassesByPath($paths);
            $this->clearRemoved($collectors, array_keys($classPaths));

            foreach ($classPaths as $className => $filePath) {
                if ($this->filesystem->lastModified($filePath) >= $scanThreshold) {
                    foreach ($collectors as $c) { $c::clear($className); }
                    require_once $filePath;
                    $this->collect(new ReflectionClass($className));
                }
            }

            // 合并已有的缓存代理路径（代理加载器只新增，不会重新加已有条目）
            $merged = array_merge($classPaths, $cachedMap);
            foreach ($this->option->astProxyLoaders() as $loader) {
                if (class_exists($loader) && ($instance = new $loader) instanceof ProxyLoaderInterface) {
                    $instance($this->option, $merged);
                }
            }

            foreach ($collectors as $c) {
                if (method_exists($c, 'exportToFile')) {
                    $c::exportToFile($cacheDir . '/' . str_replace('\\', '_', $c) . '.cache.php');
                }
            }
            // 仅缓存扫描路径 + 代理路径（不含全量 Composer map）
            $this->filesystem->put("{$cacheDir}/classmap.cache.php",
                "<?php\nreturn " . var_export($merged, true) . ";\n", true);
            $this->filesystem->put($cacheFile, '<?php return ' . time() . ";\n", true);

            // DirectHandler 返回此值；PcntlHandler 子进程被 finally 中 exit(0) 截断
            return $merged;
        } finally {
            if ($fp) {
                @flock($fp, LOCK_UN);
                @fclose($fp);
            }
            $handler->finish(); // PcntlHandler: exit(0); DirectHandler: no-op
        }
    }

    /** 仅加载类映射文件（不修改 collector 静态容器） */
    private function loadClassMap(string $cacheDir): array
    {
        $mapFile = $cacheDir . '/classmap.cache.php';
        return file_exists($mapFile) ? (array) include $mapFile : [];
    }

    /** 收集一个类的全部属性到对应的 Collector */
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

    /** 从 PHP 缓存文件加载收集器数据与类映射 */
    private function loadCache(string $scanFile, array $collectors): array
    {
        if (! file_exists($scanFile)) {
            return [];
        }
        $dir = dirname($scanFile);
        foreach ($collectors as $c) {
            if (method_exists($c, 'loadFromFile')) {
                $c::loadFromFile($dir . '/' . str_replace('\\', '_', $c) . '.cache.php');
            }
        }
        $mapFile = $dir . '/classmap.cache.php';
        return file_exists($mapFile) ? (array) include $mapFile : [];
    }

    /** 清除上次扫描存在但本次扫描不存在的类 */
    private function clearRemoved(array $collectors, array $current): void
    {
        $cacheFile = $this->option->cachePath() . '/classes.cache.php';
        $old = $this->filesystem->exists($cacheFile) ? (array) include $cacheFile : [];
        $this->filesystem->put($cacheFile,
            "<?php\nreturn " . var_export($current, true) . ";\n", true);

        foreach (array_diff($old, $current) as $class) {
            foreach ($collectors as $c) { $c::clear($class); }
        }
    }
}
