<?php
/**
 * AspectManagerCollector.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

class AspectManagerCollector extends MetadataCollector
{
    protected static array $container = [];

    public static function get(string $class, $method = null)
    {
        return static::$container[$class][$method] ?? [];
    }

    public static function has(string $class, $method = null): bool
    {
        return isset(static::$container[$class][$method]);
    }

    public static function set(string $class, $method, $value = null): void
    {
        static::$container[$class][$method] = $value;
    }

    public static function insert($class, $method, $value): void
    {
        static::$container[$class][$method][] = $value;
    }
}