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
use Vzina\Attributes\Collector\MetadataCollector;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Reflection\Composer;

class Scanner
{
    private Filesystem $filesystem;
    private AttributeReader $reader;

    public function __construct(protected Options $option)
    {
        $this->filesystem = new Filesystem();
        $this->reader = new AttributeReader();
    }

    public function scan(array $classMap = []): array
    {
        $proxyDir = $this->option->proxyPath();
        $paths = $this->option->scanPath();
        $collectors = $this->option->collectors();
        if (! $paths) {
            return [];
        }

        $cacheFile = $this->option->cachePath() . '/scan.cache';
        $lastCacheModified = file_exists($cacheFile) ? $this->filesystem->lastModified($cacheFile) : 0;
        if ($lastCacheModified > 0 && $this->option->cacheable()) {
            return $this->deserializeCachedScanData($cacheFile, $collectors);
        }

        $scanner = $this->option->scanHandler();
        if ($scanner->scan()->isScanned()) {
            return $this->deserializeCachedScanData($cacheFile, $collectors);
        }

        $this->deserializeCachedScanData($cacheFile, $collectors);

        $classes = AstParser::getInstance()->getAllClassesByPath($paths);

        $this->clearRemovedClasses($collectors, $classes);

        $reflectionClassMap = [];
        foreach ($classes as $className => $reflectionClass) {
            $reflectionClassMap[$className] = $reflectionClass->getFileName();
            if ($this->filesystem->lastModified($reflectionClass->getFileName()) >= $lastCacheModified) {
                /** @var MetadataCollector $collector */
                foreach ($collectors as $collector) {
                    $collector::clear($className);
                }

                $this->collect($reflectionClass);
            }
        }

        $this->loadAspects($lastCacheModified);

        $data = [];
        /** @var MetadataCollector|string $collector */
        foreach ($collectors as $collector) {
            $data[$collector] = $collector::serialize();
        }

        // Get the class map of Composer loader
        $classMap = array_merge($reflectionClassMap, $classMap);
        $proxyManager = new ProxyManager($classMap, $proxyDir);
        $proxies = $proxyManager->getProxies();

        $this->filesystem->put($cacheFile, serialize([$data, $proxies]));
        $scanner->finish();

        return $proxies;
    }

    public function collect(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();
        if (($path = $this->option->classMap()[$className] ?? null) && $reflection->getFileName() !== $path) {
            return;
        }

        foreach ($this->reader->getAttributes($reflection) as $classAttribute) {
            if ($classAttribute instanceof AttributeInterface) {
                $classAttribute->collectClass($className);
            }
        }

        foreach ($reflection->getProperties() as $property) {
            foreach ($this->reader->getAttributes($property) as $propertyAttribute) {
                if ($propertyAttribute instanceof AttributeInterface) {
                    $propertyAttribute->collectProperty($className, $property->getName());
                }
            }
        }

        foreach ($reflection->getMethods() as $method) {
            foreach ($this->reader->getAttributes($method) as $methodAttribute) {
                if ($methodAttribute instanceof AttributeInterface) {
                    $methodAttribute->collectMethod($className, $method->getName());
                }
            }
        }

        foreach ($reflection->getReflectionConstants() as $classConstant) {
            foreach ($this->reader->getAttributes($classConstant) as $constantAttribute) {
                if ($constantAttribute instanceof AttributeInterface) {
                    $constantAttribute->collectClassConstant($className, $classConstant->getName());
                }
            }
        }
    }

    protected function deserializeCachedScanData(string $cacheFile, array $collectors)
    {
        if (! file_exists($cacheFile)) {
            return [];
        }

        [$data, $proxies] = unserialize(file_get_contents($cacheFile));
        foreach ($data as $collector => $deserialized) {
            /** @var MetadataCollector $collector */
            if (in_array($collector, $collectors)) {
                $collector::deserialize($deserialized);
            }
        }

        return $proxies;
    }

    protected function clearRemovedClasses(array $collectors, array $reflections): void
    {
        $path = $this->option->cachePath() . '/classes.cache';
        $classes = array_keys($reflections);

        $data = [];
        if ($this->filesystem->exists($path)) {
            $data = unserialize($this->filesystem->get($path));
        }

        $this->filesystem->put($path, serialize($classes));

        $removed = array_diff($data, $classes);

        foreach ($removed as $class) {
            /** @var MetadataCollector $collector */
            foreach ($collectors as $collector) {
                $collector::clear($class);
            }
        }
    }

    protected function loadAspects(int $lastCacheModified): void
    {
        $aspects = $this->option->aspects();

        [$removed, $changed] = $this->getChangedAspects($aspects, $lastCacheModified);
        foreach ($removed as $aspect) {
            AspectCollector::clear($aspect);
        }

        foreach ($aspects as $key => $value) {
            [$aspect, $priority] = is_numeric($key) ? [$value, null] : [$key, (int)$value];
            if (! in_array($aspect, $changed, true)) {
                continue;
            }

            AspectLoader::collect($aspect, ['priority' => $priority]);
        }
    }

    protected function getChangedAspects(array $aspects, int $lastCacheModified): array
    {
        $path = $this->option->cachePath() . '/aspects.cache';
        $classes = [];
        foreach ($aspects as $key => $value) {
            $classes[] = is_numeric($key) ? $value : $key;
        }

        $data = [];
        if ($this->filesystem->exists($path)) {
            $data = unserialize($this->filesystem->get($path));
        }

        $this->filesystem->put($path, serialize($classes));

        $diff = array_diff($data, $classes);
        $changed = array_diff($classes, $data);
        $removed = [];
        foreach ($diff as $item) {
            $annotation = AttributeCollector::getClassAttribute($item, Aspect::class);
            if (is_null($annotation)) {
                $removed[] = $item;
            }
        }

        $loader = Composer::getLoader();
        foreach ($classes as $class) {
            if (($file = $loader->findFile($class)) && $lastCacheModified <= $this->filesystem->lastModified($file)) {
                $changed[] = $class;
            }
        }

        return [
            array_values(array_unique($removed)),
            array_values(array_unique($changed)),
        ];
    }
}