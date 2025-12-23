<?php
/**
 * Scanner2.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
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
use Vzina\Attributes\AttributeLoader;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Reflection\Composer;

class Scanner
{
    private Filesystem $files;
    private AttributeReader $attributeReader;

    public function __construct(protected Options $options)
    {
        $this->files = new Filesystem();
        $this->attributeReader = new AttributeReader();
    }

    /**
     * 执行扫描核心逻辑
     */
    public static function scan(Options $options): array
    {
        $scanner = new self($options);

        // 扫描路径为空时直接返回空数组
        if (empty($scanPaths = $options->scanPath())) {
            return [];
        }

        // 初始化基础变量
        $cacheFile = $scanner->getCacheFilePath('scan.cache');
        $cacheLastModifiedTime = $scanner->getCacheLastModifiedTime($cacheFile);
        $collectors = $options->collectors();
        $scanHandler = $options->scanHandler();

        // 缓存有效或已完成扫描，直接加载缓存
        if ($scanner->isCacheValid($cacheLastModifiedTime, $scanHandler)) {
            return $scanner->loadCachedData($cacheFile, $collectors);
        }

        // 加载缓存数据
        $scanner->loadCachedData($cacheFile, $collectors);

        // 核心扫描流程
        $refClassMap = $scanner->scanClassesAndCollectAttributes($scanPaths, $cacheLastModifiedTime, $collectors);
        $scanner->loadAndProcessAspects($cacheLastModifiedTime);
        $proxies = $scanner->generateProxies($refClassMap);

        // 持久化缓存并标记扫描完成
        $scanner->saveCachedData($cacheFile, $collectors, $proxies);
        $scanHandler->finish();

        return $proxies;
    }

    private function getCacheFilePath(string $fileName): string
    {
        return $this->options->cachePath() . DIRECTORY_SEPARATOR . $fileName;
    }

    private function getCacheLastModifiedTime(string $cacheFile): int
    {
        return $this->files->exists($cacheFile) ? $this->files->lastModified($cacheFile) : 0;
    }

    private function isCacheValid(int $cacheMtime, $scanHandler): bool
    {


        return ($cacheMtime > 0 && $this->options->cacheable()) || $scanHandler->scan()->isScanned();
    }

    private function loadCachedData(string $cacheFile, array $collectors): array
    {
        if (!file_exists($cacheFile)) {
            return [];
        }

        [$collectorData, $proxies] = (array)unserialize($this->files->get($cacheFile)) ?: [[], []];
        foreach ($collectorData as $collectorClass => $data) {
            if (in_array($collectorClass, $collectors, true)) {
                $collectorClass::deserialize($data);
            }
        }

        return $proxies;
    }

    private function saveCachedData(string $cacheFile, array $collectors, array $proxies): void
    {
        $collectorData = array_reduce($collectors, function ($carry, $collectorClass) {
            $carry[$collectorClass] = $collectorClass::serialize();
            return $carry;
        }, []);

        $this->files->put($cacheFile, serialize([$collectorData, $proxies]));
    }

    private function scanClassesAndCollectAttributes(
        array $scanPaths,
        int $cacheMtime,
        array $collectors
    ): array {
        $astParser = AstParser::getInstance();
        $classes = $astParser->getAllClassesByPath($scanPaths);

        // 清理已删除的类数据
        $this->clearRemovedClasses($classes, $collectors);

        $refClassMap = [];
        $customClassMap = $this->options->classMap();

        foreach ($classes as $className => $reflection) {
            $refClassMap[$className] = $reflection->getFileName();

            // 文件有变更时重新收集属性
            if ($this->files->lastModified($refClassMap[$className]) >= $cacheMtime) {
                $this->clearCollectorDataForClass($className, $collectors);

                // 自定义类映射未覆盖原文件时才收集
                if (empty($customClassMap[$className]) ||
                    $refClassMap[$className] === $customClassMap[$className]
                ) {
                    $this->collectAttributesForReflection($reflection);
                }
            }
        }

        return $refClassMap;
    }

    private function clearCollectorDataForClass(string $className, array $collectors): void
    {
        foreach ($collectors as $collectorClass) {
            $collectorClass::clear($className);
        }
    }

    private function clearRemovedClasses(array $currentClasses, array $collectors): void
    {
        $classCacheFile = $this->getCacheFilePath('classes.cache');
        $cachedClasses = $this->files->exists($classCacheFile)
            ? (array)unserialize($this->files->get($classCacheFile))
            : [];

        $currentClassNames = array_keys($currentClasses);
        $this->files->put($classCacheFile, serialize($currentClassNames));

        // 计算已移除的类并清理收集器
        $removedClasses = array_diff($cachedClasses, $currentClassNames);
        if (!empty($removedClasses)) {
            foreach ($collectors as $collectorClass) {
                $collectorClass::clear($removedClasses);
            }
        }
    }

    private function collectAttributesForReflection(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();

        // 收集类注解
        $this->collectAttributes(
            $reflection,
            fn(AttributeInterface $attr) => $attr->collectClass($className)
        );

        // 收集属性注解
        foreach ($reflection->getProperties() as $property) {
            $this->collectAttributes(
                $property,
                fn(AttributeInterface $attr) => $attr->collectProperty($className, $property->getName())
            );
        }

        // 收集方法注解
        foreach ($reflection->getMethods() as $method) {
            $this->collectAttributes(
                $method,
                fn(AttributeInterface $attr) => $attr->collectMethod($className, $method->getName())
            );
        }

        // 收集常量注解
        foreach ($reflection->getReflectionConstants() as $constant) {
            $this->collectAttributes(
                $constant,
                fn(AttributeInterface $attr) => $attr->collectClassConstant($className, $constant->getName())
            );
        }
    }

    private function collectAttributes($ref, callable $collectCallback): void
    {
        foreach ($this->attributeReader->getAttributes($ref, $this->options->ignores()) as $attribute) {
            if ($attribute instanceof AttributeInterface) {
                $collectCallback($attribute);
            }
        }
    }

    private function loadAndProcessAspects(int $cacheMtime): void
    {
        $aspects = $this->options->aspects();
        [$removedAspects, $changedAspects] = $this->getChangedAspects($aspects, $cacheMtime);

        // 清理已移除的切面
        foreach ($removedAspects as $aspectClass) {
            AspectCollector::clear($aspectClass);
        }

        $loadedAspects = [];
        foreach ($aspects as $key => $value) {
            [$aspectClass, $priority] = is_numeric($key) ? [$value, null] : [$key, (int)$value];

            if (isset($loadedAspects[$aspectClass]) || !in_array($aspectClass, $changedAspects, true)) {
                continue;
            }

            AspectLoader::collect($aspectClass, ['priority' => $priority]);
            $loadedAspects[$aspectClass] = true;
        }
    }

    private function getChangedAspects(array $aspects, int $cacheMtime): array
    {
        $aspectCacheFile = $this->getCacheFilePath('aspects.cache');
        $currentAspects = array_map(function ($key, $value) {
            return is_numeric($key) ? $value : $key;
        }, array_keys($aspects), $aspects);

        $cachedAspects = $this->files->exists($aspectCacheFile)
            ? (array)unserialize($this->files->get($aspectCacheFile))
            : [];

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
                AttributeLoader::logger()->debug(sprintf(
                    'Skip aspect %s: not found in composer class loader',
                    $aspectClass
                ));
                continue;
            }

            if ($cacheMtime <= $this->files->lastModified($filePath)) {
                $changedAspects[] = $aspectClass;
            }
        }

        return [array_unique($removedAspects), array_unique($changedAspects)];
    }

    private function generateProxies(array $refClassMap): array
    {
        $astParser = AstParser::getInstance();
        $classMap = array_merge($refClassMap, $this->options->classMap());
        $proxyManager = new ProxyManager($astParser, $classMap, $this->options->proxyPath());

        return $proxyManager->getProxies();
    }
}