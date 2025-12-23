<?php
/**
 * MetadataCollectorInterface.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

interface MetadataCollectorInterface
{
    public static function get(string $key, $default = null);

    public static function set(string $key, $value): void;

    public static function has(string $key): bool;

    public static function clear($key = null): void;

    public static function serialize(): string;

    public static function deserialize(string $metadata): bool;

    public static function list(): array;
}