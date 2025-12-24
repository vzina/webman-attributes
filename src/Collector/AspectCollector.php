<?php
/**
 * AspectCollector.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionProperty;

class AspectCollector extends MetadataCollector
{
    protected static array $container = [];
    protected static array $aspectRules = [];

    public static function setAround(string $aspect, array $classes, array $attributes, ?int $priority = null): void
    {
        if (! is_int($priority)) {
            $priority = 0;
        }

        static::override('classes.' . $aspect, fn($v) => array_merge((array)$v, $classes));
        static::override('attributes.' . $aspect, fn($v) => array_merge((array)$v, $attributes));

        static::$aspectRules[$aspect] = [
            'priority' => $priority,
            'classes' => array_merge(static::$aspectRules[$aspect]['classes'] ?? [], $classes),
            'attributes' => array_merge(static::$aspectRules[$aspect]['attributes'] ?? [], $attributes),
        ];
    }

    public static function clear($key = null): void
    {
        if ($key) {
            Arr::forget(static::$aspectRules, $key);
            empty(static::$container['classes']) or Arr::forget(static::$container['classes'], $key);
            empty(static::$container['attributes']) or Arr::forget(static::$container['attributes'], $key);
        } else {
            static::$container = [];
            static::$aspectRules = [];
        }
    }

    public static function getRule(string $aspect): array
    {
        return static::$aspectRules[$aspect] ?? [];
    }

    public static function getPriority(string $aspect): int
    {
        return static::$aspectRules[$aspect]['priority'] ?? 0;
    }

    public static function getRules(): array
    {
        return static::$aspectRules;
    }

    public static function serialize(): string
    {
        return serialize([static::$aspectRules, static::$container]);
    }

    public static function deserialize(string $metadata): bool
    {
        [$rules, $container] = (array)unserialize($metadata);
        static::$aspectRules = $rules;
        static::$container = $container;
        return true;
    }

    public static function load(string $className): array
    {
        $refClass = new ReflectionClass($className);
        $properties = $refClass->getProperties(ReflectionProperty::IS_PUBLIC);

        $keys = ['classes', 'attributes', 'priority'];
        $ret = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            if (in_array($name, $keys)) {
                $ret[$name] = $property->getDefaultValue();
            }
        }

        return $ret;
    }

    public static function getContainer(): array
    {
        return self::$container;
    }
}