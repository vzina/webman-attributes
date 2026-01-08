<?php

declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use ArrayAccess;
use ReflectionNamedType;
use RuntimeException;
use support\Container;

class ServiceInjector
{

    public static function inject(string|array $key, $value = null): void
    {
        if ((empty($key) && empty($value)) || empty($container = Container::instance())) {
            return;
        }

        $definitions = array_map([static::class, 'define'], is_array($key) ? $key : [$key => $value]);
        if (is_a($container, \Webman\Container::class)) {
            $container->addDefinitions($definitions);
        } else {
            foreach ($definitions as $id => $definition) {
                if (method_exists($container, 'set')) {
                    $container->set($id, $definition);
                } elseif (method_exists($container, 'bind')) {
                    $container->bind($id, $definition);
                } elseif ($container instanceof ArrayAccess) {
                    $container->offsetSet($id, $definition);
                }
            }
        }
    }

    public static function define($definition): callable
    {
        if (is_callable($definition)) {
            return $definition;
        }

        return static function ($container) use ($definition) {
            $ref = ReflectionManager::reflectClass($definition);
            if ($ref->hasMethod('__invoke')) {
                $invokeMethod = $ref->getMethod('__invoke');
                $args = self::resolveDependencies($invokeMethod->getParameters(), $container);

                return $invokeMethod->invokeArgs($ref->newInstance(), $args);
            }

            $constructor = $ref->getConstructor();
            if ($constructor && $constructor->isPublic()) {
                return $ref->newInstanceArgs(self::resolveDependencies($constructor->getParameters(), $container));
            }

            return $ref->newInstance();
        };
    }

    protected static function resolveDependencies(array $parameters, $container): array
    {
        $args = [];
        foreach ($parameters as $param) {
            $paramType = $param->getType();
            $paramName = $param->getName();

            if (! ($paramType instanceof ReflectionNamedType) || $paramType->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException("参数 \${$paramName} 缺少类型提示且无默认值，无法解析依赖");
            }

            $paramClassName = $paramType->getName();
            if (is_a($container, $paramClassName)) {
                $args[] = $container;
            } elseif ($container->has($paramClassName)) {
                $args[] = $container->get($paramClassName);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException("无法解析参数 \${$paramName} 的依赖 {$paramClassName}：容器中无该依赖且无默认值");
            }
        }
        return $args;
    }
}