<?php

declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use RuntimeException;
use support\Container;

class ServiceInjector
{
    /**
     * 向容器注册服务定义。
     *
     * @param string|array $key   服务 ID，或 ['id' => [...], ...] 批量
     * @param mixed        $value 类名 | callable | ['class' => ..., 'params' => [...], 'singleton' => bool]
     */
    public static function inject(string|array $key, $value = null): void
    {
        $container = Container::instance();
        if ((empty($key) && empty($value)) || ! $container) {
            return;
        }

        $map = is_array($key) ? $key : [$key => $value];

        foreach ($map as $id => $def) {
            $factory = self::createFactory($def);
            if ($factory === null) {
                trigger_error(
                    "ServiceInjector: cannot register '{$id}' — invalid definition: " . var_export($def, true),
                    E_USER_WARNING
                );
                continue;
            }

            if (is_a($container, \Webman\Container::class)) {
                $container->addDefinitions([$id => $factory]);
            } elseif (method_exists($container, 'set')) {
                $container->set($id, $factory);
            } elseif (method_exists($container, 'bind')) {
                $container->bind($id, $factory);
            } elseif ($container instanceof \ArrayAccess) {
                $container->offsetSet($id, $factory);
            }
        }
    }

    /**
     * 创建工厂闭包（公共接口）。
     * 支持：类名字符串、callable、['class' => ..., 'params' => [...], 'singleton' => bool]
     */
    public static function define($definition, array $options = []): callable
    {
        // callable 直接返回
        if (is_callable($definition)) {
            return $definition;
        }

        // 字符串 + options → 转为数组格式
        if (is_string($definition) && $options) {
            $definition = ['class' => $definition, 'params' => $options];
        }

        // 字符串无 options → 简单实例化
        if (is_string($definition)) {
            return static function ($container) use ($definition) {
                return self::instantiate($definition, $container, []);
            };
        }

        $factory = self::createFactory($definition);
        if ($factory === null) {
            throw new \InvalidArgumentException(
                "ServiceInjector: cannot create factory for: " . var_export($definition, true)
            );
        }

        return $factory;
    }

    /**
     * 根据定义创建工厂闭包，支持 singleton 缓存。
     * 格式：类名 | callable | ['class' => ..., 'params' => [...], 'singleton' => bool]
     */
    public static function createFactory($definition): ?callable
    {
        if (is_callable($definition) && ! is_string($definition)) {
            return $definition;
        }

        if (is_array($definition) && isset($definition['class'])) {
            $class     = $definition['class'];
            $params    = $definition['params'] ?? [];
            $singleton = $definition['singleton'] ?? false;
        } else {
            $class     = $definition;
            $params    = [];
            $singleton = false;
        }

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $factory = static function ($container) use ($class, $params) {
            return self::instantiate($class, $container, $params);
        };

        if ($singleton) {
            return static function ($container) use ($factory) {
                static $instance;
                return $instance ??= $factory($container);
            };
        }

        return $factory;
    }

    /** 实例化类并自动解析构造参数 */
    private static function instantiate(string $class, ContainerInterface $container, array $params): mixed
    {
        $ref = new \ReflectionClass($class);

        // __invoke 工厂类
        if ($ref->hasMethod('__invoke')) {
            $method = $ref->getMethod('__invoke');
            $args   = self::resolveArgs($method->getParameters(), $container, $params, $ref);
            return $method->invokeArgs($ref->newInstance(), $args);
        }

        // 构造函数注入
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->isPublic()) {
            $args = self::resolveArgs($ctor->getParameters(), $container, $params, $ref);
            return $ref->newInstanceArgs($args);
        }

        return $ref->newInstance();
    }

    /**
     * 解析方法参数：显式 params 优先 → 容器自动装配 → 默认值。
     *
     * 容器自动装配时，使用 $contextClass 的 use 导入将短类名解析为 FQCN，
     * 避免 PHP ReflectionNamedType::getName() 只返回短名的问题。
     */
    private static function resolveArgs(
        array $parameters,
        ContainerInterface $container,
        array $params,
        \ReflectionClass $contextClass
    ): array {
        $args = [];
        foreach ($parameters as $param) {
            $name = $param->getName();

            // 1. 显式传入
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            $type = $param->getType();

            // 2. 内置类型 + 有默认值 → 使用默认值
            if (! ($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException("参数 \${$name} 无默认值且未在 params 中提供");
            }

            // 3. 类类型 → 解析 FQCN → 从容器获取
            $shortName = $type->getName();
            $fqcn = AttributeReader::resolveClassName($shortName, $contextClass);

            if (is_a($container, $fqcn)) {
                $args[] = $container;
            } elseif ($container->has($fqcn)) {
                $args[] = $container->get($fqcn);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException("无法解析参数 \${$name} ({$fqcn})：容器中无该依赖且无默认值");
            }
        }
        return $args;
    }
}
