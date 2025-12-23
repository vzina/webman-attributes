<?php
/**
 * PropertyManagerCollector.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

class PropertyManagerCollector extends MetadataCollector
{
    protected static array $container = [];

    public static function register(string $attribute, callable $callback): void
    {
        static::$container[$attribute][] = $callback;
    }

    public static function isEmpty(): bool
    {
        return empty(static::$container);
    }
}