<?php
/**
 * AstVisitorManager.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

class AstVisitorManager
{
    protected static ?SplPriorityQueue $queue = null;

    protected static array $values = [];

    public static function __callStatic($name, $arguments)
    {
        return static::getQueue()->{$name}(...$arguments);
    }

    public static function insert($value, $priority = 0)
    {
        static::$values[] = $value;
        static::getQueue()->insert($value, $priority);
    }

    public static function exists($value): bool
    {
        return in_array($value, static::$values, true);
    }

    public static function getQueue(): SplPriorityQueue
    {
        return static::$queue ??= new SplPriorityQueue();
    }
}