<?php
/**
 * ReflectionManager.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use PhpDocReader\PhpDocReader;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Vzina\Attributes\Collector\MetadataCollector;

class ReflectionManager extends MetadataCollector
{
    protected static array $container = [];

    public static function reflectClass(string $className): ReflectionClass
    {
        return static::$container['c'][$className] ??= new ReflectionClass($className);
    }

    public static function reflectMethod(string $className, string $method): ReflectionMethod
    {
        return static::$container['m']["{$className}::{$method}"] ??= static::reflectClass($className)->getMethod($method);
    }

    public static function reflectProperty(string $className, string $property): ReflectionProperty
    {
        return static::$container['p']["{$className}->{$property}"] ??= static::reflectClass($className)->getProperty($property);
    }

    public static function reflectPropertyNames(string $className): array
    {
        return static::$container['p_names'][$className] ??= array_map(fn($p) => $p->getName(), static::reflectClass($className)->getProperties());
    }

    public static function getPhpDocReader(): PhpDocReader
    {
        return static::$container['php_doc'] ??= new PHPDocReader();
    }
}