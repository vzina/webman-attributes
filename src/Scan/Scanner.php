<?php
/**
 * Scanner2.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Vzina\Attributes\Ast\AspectLoader;
use Vzina\Attributes\Ast\AstParser;
use Vzina\Attributes\Ast\ProxyManager;
use Vzina\Attributes\Attribute\Aspect;
use Vzina\Attributes\Attribute\AttributeInterface;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Reflection\Composer;

class Scanner
{
    private Filesystem $files;
    private AttributeReader $attributeReader;

    public function __construct(protected Options $option)
    {
        $this->files = new Filesystem();
        $this->attributeReader = new AttributeReader();
    }

    /**
     * 执行扫描核心逻辑
     */
    public static function scan(Options $option): array
    {
        $scanner = new self($option);
        $scanPaths = $option->scanPath();

        // 扫描路径为空时直接返回空数组
        if (empty($scanPaths)) return [];

        // 初始化核心变量
        $cacheFile = $scanner->getCacheFile('scan.cache');
        $cacheMtime = $scanner->getCacheMtime($cacheFile);
        $collectors = $option->collectors();
        $scanHandler = $option->scanHandler();

        // 缓存有效或已完成扫描，直接加载缓存
        if ($scanner->isCacheValid($cacheMtime, $scanHandler)) {
            return $scanner->loadCache($cacheFile, $collectors);
        }

        // 加载缓存数据
        $scanner->loadCache($cacheFile, $collectors);

        // 核心扫描流程
        $refClassMap = $scanner->scanClasses($scanPaths, $cacheMtime, $collectors);
        $scanner->processAspects($cacheMtime);
        $proxies = $scanner->generateProxies($refClassMap);

        // 持久化缓存并标记扫描完成
        $scanner->saveCache($cacheFile, $collectors, $proxies);
        $scanHandler->finish();

        return $proxies;
    }

    /**
     * 获取缓存文件完整路径
     */
    private function getCacheFile(string $fileName): string
    {
        return $this->option->cachePath() . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * 获取缓存文件最后修改时间
     */
    private function getCacheMtime(string $cacheFile): int
    {
        return $this->files->exists($cacheFile) ? $this->files->lastModified($cacheFile) : 0;
    }

    /**
     * 判断缓存是否有效
     */
    private function isCacheValid(int $cacheMtime, $scanHandler): bool
    {
        return ($cacheMtime > 0 && $this->option->cacheable()) || $scanHandler->scan()->isScanned();
    }

    /**
     * 加载缓存数据
     */
    private function loadCache(string $cacheFile, array $collectors): array
    {
        if (! $this->files->exists($cacheFile)) return [];

        [$collectorData, $proxies] = unserialize($this->files->get($cacheFile)) ?: [[], []];
        foreach ($collectorData as $class => $data) {
            if (in_array($class, $collectors, true)) {
                $class::deserialize($data);
            }
        }

        return $proxies;
    }

    /**
     * 保存缓存数据
     */
    private function saveCache(string $cacheFile, array $collectors, array $proxies): void
    {
        $collectorData = [];
        foreach ($collectors as $class) {
            $collectorData[$class] = $class::serialize();
        }

        $this->files->put($cacheFile, serialize([$collectorData, $proxies]));
    }

    /**
     * 扫描类并收集属性
     */
    private function scanClasses(array $scanPaths, int $cacheMtime, array $collectors): array
    {
        // 获取所有类并清理已删除的类数据
        $classes = AstParser::getInstance()->getAllClassesByPath($scanPaths, $this->option->excludes());
        $this->clearRemovedClasses($classes, $collectors);

        $refClassMap = [];
        $customClassMap = $this->option->classMap();

        foreach ($classes as $className => $reflection) {
            $filePath = $reflection->getFileName();
            $refClassMap[$className] = $filePath;

            // 文件有变更时重新收集属性
            if ($this->files->lastModified($filePath) >= $cacheMtime) {
                $this->clearClassCollectorData($className, $collectors);

                // 自定义类映射未覆盖原文件时才收集
                if (empty($customClassMap[$className]) || $filePath === $customClassMap[$className]) {
                    $this->collectClassAttributes($reflection);
                }
            }
        }

        return $refClassMap;
    }

    /**
     * 清理已删除类的收集器数据
     */
    private function clearRemovedClasses(array $currentClasses, array $collectors): void
    {
        $classCacheFile = $this->getCacheFile('classes.cache');
        $cachedClasses = $this->files->exists($classCacheFile) ? (array)unserialize($this->files->get($classCacheFile)) : [];
        $currentClassNames = array_keys($currentClasses);

        // 保存当前类列表
        $this->files->put($classCacheFile, serialize($currentClassNames));

        // 清理已移除类的收集器数据
        $removedClasses = array_diff($cachedClasses, $currentClassNames);
        if (! empty($removedClasses)) {
            foreach ($collectors as $class) {
                $class::clear($removedClasses);
            }
        }
    }

    /**
     * 清理单个类的收集器数据
     */
    private function clearClassCollectorData(string $className, array $collectors): void
    {
        foreach ($collectors as $class) {
            $class::clear($className);
        }
    }

    /**
     * 收集单个类的所有属性
     */
    private function collectClassAttributes(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();

        // 收集类注解
        $this->collectAttributes($reflection, fn($attr) => $attr->collectClass($className));

        // 收集属性注解
        foreach ($reflection->getProperties() as $property) {
            $this->collectAttributes($property, fn($attr) => $attr->collectProperty($className, $property->getName()));
        }

        // 收集方法注解
        foreach ($reflection->getMethods() as $method) {
            $this->collectAttributes($method, fn($attr) => $attr->collectMethod($className, $method->getName()));
        }

        // 收集常量注解
        foreach ($reflection->getReflectionConstants() as $constant) {
            $this->collectAttributes($constant, fn($attr) => $attr->collectClassConstant($className, $constant->getName()));
        }
    }

    /**
     * 通用属性收集方法
     */
    private function collectAttributes($ref, callable $collectCallback): void
    {
        foreach ($this->attributeReader->getAttributes($ref, $this->option->ignores()) as $attribute) {
            if ($attribute instanceof AttributeInterface) {
                $collectCallback($attribute);
            }
        }
    }

    /**
     * 加载并处理切面
     */
    private function processAspects(int $cacheMtime): void
    {
        $aspects = $this->option->aspects();
        [$removedAspects, $changedAspects] = $this->getChangedAspects($aspects, $cacheMtime);

        // 清理已移除的切面
        foreach ($removedAspects as $aspectClass) {
            AspectCollector::clear($aspectClass);
        }

        // 加载变更的切面
        $loadedAspects = [];
        foreach ($aspects as $key => $value) {
            [$aspectClass, $priority] = is_numeric($key) ? [$value, null] : [$key, (int)$value];

            if (isset($loadedAspects[$aspectClass]) || ! in_array($aspectClass, $changedAspects, true)) {
                continue;
            }

            AspectLoader::collect($aspectClass, ['priority' => $priority]);
            $loadedAspects[$aspectClass] = true;
        }
    }

    /**
     * 获取变更的切面（移除/新增/修改）
     */
    private function getChangedAspects(array $aspects, int $cacheMtime): array
    {
        $aspectCacheFile = $this->getCacheFile('aspects.cache');
        // 提取当前切面类列表
        $currentAspects = array_map(function ($key, $value) {
            return is_numeric($key) ? $value : $key;
        }, array_keys($aspects), $aspects);

        $cachedAspects = $this->files->exists($aspectCacheFile) ? (array)unserialize($this->files->get($aspectCacheFile)) : [];
        $this->files->put($aspectCacheFile, serialize($currentAspects));

        $removedAspects = array_filter(array_diff($cachedAspects, $currentAspects), function ($aspectClass) {
            return is_null(AttributeCollector::getClassAttribute($aspectClass, Aspect::class));
        });

        // 计算变更的切面（新增/文件修改）
        $changedAspects = array_diff($currentAspects, $cachedAspects);
        $composerLoader = Composer::getLoader();

        foreach ($currentAspects as $aspectClass) {
            $filePath = $composerLoader->findFile($aspectClass);
            if ($filePath === false) {
                continue;
            }

            if ($cacheMtime <= $this->files->lastModified($filePath)) {
                $changedAspects[] = $aspectClass;
            }
        }

        return [array_unique($removedAspects), array_unique($changedAspects)];
    }

    /**
     * 生成代理类
     */
    private function generateProxies(array $refClassMap): array
    {
        $classMap = array_merge($refClassMap, $this->option->classMap());
        return (new ProxyManager($classMap, $this->option->proxyPath()))->getProxies();
    }
}