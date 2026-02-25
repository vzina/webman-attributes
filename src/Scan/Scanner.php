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

    public function scan(array $classMap = []): array
    {
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

        $classMap = array_merge($reflectionClassMap, $classMap);
        foreach ($this->option->astProxyLoaders() as $proxyLoader) {
            if (class_exists($proxyLoader) &&
                ($instance = new $proxyLoader) &&
                $instance instanceof ProxyLoaderInterface
            ) {
                $instance($this->option, $classMap);
            }
        }

        $data = [];
        /** @var MetadataCollector|string $collector */
        foreach ($collectors as $collector) {
            $data[$collector] = $collector::serialize();
        }

        $this->filesystem->put($cacheFile, serialize([$data, $classMap]));
        $scanner->finish();
    }

    public function collect(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();
        if (($path = $this->option->classMap()[$className] ?? null) && $reflection->getFileName() !== $path) {
            return;
        }

        foreach (AttributeReader::getAttributes($reflection) as $classAttribute) {
            if ($classAttribute instanceof AttributeInterface) {
                $classAttribute->collectClass($className);
            }
        }

        foreach ($reflection->getProperties() as $property) {
            foreach (AttributeReader::getAttributes($property) as $propertyAttribute) {
                if ($propertyAttribute instanceof AttributeInterface) {
                    $propertyAttribute->collectProperty($className, $property->getName());
                }
            }
        }

        foreach ($reflection->getMethods() as $method) {
            foreach (AttributeReader::getAttributes($method) as $methodAttribute) {
                if ($methodAttribute instanceof AttributeInterface) {
                    $methodAttribute->collectMethod($className, $method->getName());
                }
            }
        }

        foreach ($reflection->getReflectionConstants() as $classConstant) {
            foreach (AttributeReader::getAttributes($classConstant) as $constantAttribute) {
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

        [$data, $proxies] = (array)unserialize((string)$this->filesystem->get($cacheFile)) + [[], []];
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
            $data = (array)unserialize($this->filesystem->get($path));
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
}