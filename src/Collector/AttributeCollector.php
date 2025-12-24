<?php
/**
 * AttributeCollector.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

class AttributeCollector extends MetadataCollector
{
    protected static array $container = [];

    public static function collectClass(string $class, string $attribute, $value): void
    {
        static::$container[$class]['_c'][$attribute] = $value;
    }

    public static function collectProperty(string $class, string $property, string $attribute, $value): void
    {
        static::$container[$class]['_p'][$property][$attribute] = $value;
    }

    public static function collectMethod(string $class, string $method, string $attribute, $value): void
    {
        static::$container[$class]['_m'][$method][$attribute] = $value;
    }

    public static function collectClassConstant(string $class, string $constant, string $attribute, $value): void
    {
        static::$container[$class]['_cc'][$constant][$attribute] = $value;
    }

    public static function getClassesByAttribute(string $attribute): array
    {
        $result = [];
        foreach (static::$container as $class => $metadata) {
            if (! isset($metadata['_c'][$attribute])) {
                continue;
            }
            $result[$class] = $metadata['_c'][$attribute];
        }
        return $result;
    }

    public static function getMethodsByAttribute(string $attribute): array
    {
        $result = [];
        foreach (static::$container as $class => $metadata) {
            foreach ($metadata['_m'] ?? [] as $method => $_metadata) {
                if ($value = $_metadata[$attribute] ?? null) {
                    $result[] = ['class' => $class, 'method' => $method, 'attribute' => $value];
                }
            }
        }
        return $result;
    }

    public static function getPropertiesByAttribute(string $attribute): array
    {
        $properties = [];
        foreach (static::$container as $class => $metadata) {
            foreach ($metadata['_p'] ?? [] as $property => $_metadata) {
                if ($value = $_metadata[$attribute] ?? null) {
                    $properties[] = ['class' => $class, 'property' => $property, 'attribute' => $value];
                }
            }
        }
        return $properties;
    }

    public static function getClassAttribute(string $class, string $attribute)
    {
        return static::get($class . '._c.' . $attribute);
    }

    public static function getClassMethodAttribute(string $class, string $method)
    {
        return static::get($class . '._m.' . $method);
    }

    public static function getClassPropertyAttribute(string $class, string $property)
    {
        return static::get($class . '._p.' . $property);
    }
}