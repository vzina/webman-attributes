<?php
/**
 * MetadataCollector.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

use Closure;
use Illuminate\Support\Arr;

abstract class MetadataCollector implements MetadataCollectorInterface
{
    protected static array $container = [];

    public static function get(string $key, $default = null)
    {
        return Arr::get(static::$container, $key) ?? $default;
    }

    public static function set(string $key, $value): void
    {
        Arr::set(static::$container, $key, $value);
    }

    public static function has(string $key): bool
    {
        return Arr::has(static::$container, $key);
    }

    public static function clear($key = null): void
    {
        if ($key) {
            Arr::forget(static::$container, $key);
        } else {
            static::$container = [];
        }
    }

    public static function serialize(): string
    {
        return serialize(static::$container);
    }

    public static function deserialize(string $metadata): bool
    {
        static::$container = unserialize($metadata);
        return true;
    }

    public static function list(): array
    {
        return static::$container;
    }

    public static function override(string $key, Closure $closure): void
    {
        $value = null;
        if (self::has($key)) {
            $value = self::get($key);
        }
        $value = $closure($value);

        static::set($key, $value);
    }
}