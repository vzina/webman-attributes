<?php
/**
 * DispatcherFactory — 路由注册器。
 *
 * 从 AttributeCollector 读取 #[Controller]/#[AutoController]/#[Resource]，
 * 向 webman Route 注册路径、HTTP 方法、中间件。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Illuminate\Support\Str;
use ReflectionMethod;
use Vzina\Attributes\Attribute\Middleware;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ReflectionManager;
use Webman\Route;

class DispatcherFactory
{
    private const MAPPING_ATTRS = [
        DeleteMapping::class,
        GetMapping::class,
        PatchMapping::class,
        PostMapping::class,
        PutMapping::class,
        RequestMapping::class,
    ];

    public static function init(): void
    {
        $registered = false;

        foreach (AttributeCollector::list() as $className => $metadata) {
            if (isset($metadata['_c'][Controller::class])) {
                self::handleController($metadata['_c'][Controller::class], $className, $metadata['_m'] ?? []);
                $registered = true;
            } elseif (isset($metadata['_c'][AutoController::class])) {
                self::handleAutoController($metadata['_c'][AutoController::class], $className, $metadata['_m'] ?? []);
                $registered = true;
            } elseif (isset($metadata['_c'][Resource::class])) {
                self::handleResource($metadata['_c'][Resource::class], $className);
                $registered = true;
            }
        }

        if ($registered) {
            Route::disableDefaultRoute();
        }
    }

    // ===================================================================
    // Controller
    // ===================================================================

    protected static function handleController(Controller $controller, string $className, array $methodMetadata): void
    {
        $middlewares = array_merge(
            (array) ($controller->options['middleware'] ?? []),
            self::getClassMiddlewareNames(get_class($controller)),
        );
        $prefix = self::getPrefix($className, $controller->prefix);

        foreach ($methodMetadata as $methodName => $values) {
            foreach (self::MAPPING_ATTRS as $mappingClass) {
                /** @var Mapping|null $mapping */
                $mapping = $values[$mappingClass] ?? null;
                if ($mapping === null) {
                    continue;
                }

                $path = self::buildPath($prefix, $mapping, $methodName);

                Route::add($mapping->methods, $path, [$className, $methodName])
                    ->name($mapping->options['name'] ?? "{$className}.{$methodName}")
                    ->middleware(array_merge(
                        $middlewares,
                        (array) ($mapping->options['middleware'] ?? []),
                        self::extractMethodMiddleware($methodMetadata[$methodName] ?? []),
                    ));
            }
        }
    }

    private static function buildPath(string $prefix, Mapping $mapping, string $methodName): string
    {
        if (! isset($mapping->path)) {
            return $prefix . '/' . Str::snake($methodName);
        }
        if ($mapping->path === '') {
            return $prefix;
        }
        if ($mapping->path[0] !== '/') {
            return rtrim($prefix, '/') . '/' . $mapping->path;
        }
        return $mapping->path;
    }

    // ===================================================================
    // AutoController
    // ===================================================================

    protected static function handleAutoController(AutoController $controller, string $className, array $methodMetadata): void
    {
        $classMiddlewares = array_merge(
            (array) ($controller->options['middleware'] ?? []),
            self::getClassMiddlewareNames($className),
        );
        $prefix = self::getPrefix($className, $controller->prefix);
        $autoMethods = $controller->options['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        try {
            $ref = ReflectionManager::reflectClass($className);
        } catch (\ReflectionException) {
            return;
        }

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            if (Str::startsWith($methodName, ['__', '_'])) {
                continue;
            }

            $middlewares = array_merge(
                $classMiddlewares,
                self::extractMethodMiddleware($methodMetadata[$methodName] ?? []),
            );

            Route::add($autoMethods, $prefix . '/' . $methodName, [$className, $methodName])
                ->name("{$className}.{$methodName}")
                ->middleware($middlewares);
        }
    }

    // ===================================================================
    // Resource
    // ===================================================================

    protected static function handleResource(Resource $resource, string $className): void
    {
        $prefix = self::getPrefix($className, $resource->prefix);
        $methods = $resource->options['methods'] ?? [];

        Route::resource($prefix, $className, $methods);
    }

    // ===================================================================
    // Prefix
    // ===================================================================

    /**
     * 构建路由前缀。
     * - null:  不使用前缀（根路径）
     * - '' :   从类命名空间自动推导
     * - 其他:  直接使用，缺失前导 / 时自动补
     */
    protected static function getPrefix(string $className, ?string $prefix): string
    {
        if ($prefix === null) {
            return '';
        }

        if ($prefix === '') {
            $handled = str_replace('\\', '/', Str::replaceFirst(
                'Controller', '', Str::after($className, '\Controller\\')
            ));
            $prefix = str_replace('/_', '/', Str::snake($handled));
        }

        if ($prefix !== '' && $prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        return $prefix;
    }

    // ===================================================================
    // Middleware
    // ===================================================================

    private static function extractMethodMiddleware(array $methodAttrs): array
    {
        if (! isset($methodAttrs[Middleware::class])) {
            return [];
        }
        $list = is_array($methodAttrs[Middleware::class])
            ? $methodAttrs[Middleware::class]
            : [$methodAttrs[Middleware::class]];
        return self::sortMiddleware($list);
    }

    private static function getClassMiddlewareNames(string $className): array
    {
        $metadata = AttributeCollector::getClassAttribute($className, Middleware::class);
        return $metadata ? self::sortMiddleware((array) $metadata) : [];
    }

    private static function sortMiddleware(array $middlewares): array
    {
        usort($middlewares, fn(Middleware $a, Middleware $b) => $b->priority <=> $a->priority);
        return array_map(fn(Middleware $m) => $m->name, $middlewares);
    }
}
