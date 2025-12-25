<?php
/**
 * DispatcherFactory.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Illuminate\Support\Str;
use ReflectionMethod;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ReflectionManager;
use Webman\Route;

class DispatcherFactory
{
    public static function init()
    {
        foreach (AttributeCollector::list() as $className => $metadata) {
            if (isset($metadata['_c'][Controller::class])) {
                self::handleController($metadata['_c'][Controller::class], $className, $metadata['_m'] ?? []);
            } elseif (isset($metadata['_c'][AutoController::class])) {
                self::handleAutoController($metadata['_c'][AutoController::class], $className);
            } elseif (isset($metadata['_c'][Resource::class])) {
                self::handleResource($metadata['_c'][Resource::class], $className);
            }
        }

        Route::disableDefaultRoute();
    }

    protected static function handleController(Controller $controller, string $className, array $methodMetadata): void
    {
        $middlewares = $controller->options['middleware'] ?? [];
        $prefix = self::getPrefix($className, $controller->prefix);

        $mappingAttributes = [
            DeleteMapping::class,
            GetMapping::class,
            PatchMapping::class,
            PostMapping::class,
            PutMapping::class,
            RequestMapping::class,
        ];

        foreach ($methodMetadata as $methodName => $values) {
            foreach ($mappingAttributes as $mappingAttribute) {
                /** @var Mapping|null $mapping */
                $mapping = $values[$mappingAttribute] ?? null;
                if ($mapping === null || ! isset($mapping->methods, $mapping->options)) {
                    continue;
                }

                if (! isset($mapping->path)) {
                    $path = $prefix . '/' . Str::snake($methodName);
                } elseif ($mapping->path === '') {
                    $path = $prefix;
                } elseif ($mapping->path[0] !== '/') {
                    $path = rtrim($prefix, '/') . '/' . $mapping->path;
                } else {
                    $path = $mapping->path;
                }

                Route::add($mapping->methods, $path, [$className, $methodName])
                    ->name($mapping->options['name'] ?? "{$className}.{$methodName}")
                    ->middleware(array_merge((array)$middlewares, $mapping->options['middleware'] ?? []));
            }
        }
    }

    protected static function handleAutoController(AutoController $controller, string $className): void
    {
        $middlewares = $controller->options['middleware'] ?? [];
        $prefix = self::getPrefix($className, $controller->prefix);
        $autoMethods = $controller->options['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        $ref = ReflectionManager::reflectClass($className);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (Str::startsWith($methodName, ['__', '_'])) {
                continue;
            }

            Route::add($autoMethods, $prefix . '/' . $methodName, [$className, $methodName])
                ->name("{$className}.{$methodName}")
                ->middleware($middlewares);
        }
    }

    protected static function handleResource(Resource $resource, string $className): void
    {
        $prefix = self::getPrefix($className, $resource->prefix);
        $methods = $resource->options['methods'] ?? [];

        Route::resource($prefix, $className, $methods);
    }

    protected static function getPrefix(string $className, string $prefix = ''): string
    {
        if (! $prefix) {
            $handledNamespace = str_replace('\\', '/', Str::replaceFirst(
                'Controller', '', Str::after($className, '\Controller\\')
            ));

            $prefix = str_replace('/_', '/', Str::snake($handledNamespace));
        }

        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        return $prefix;
    }
}