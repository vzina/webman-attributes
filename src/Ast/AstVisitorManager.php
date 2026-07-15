<?php
/**
 * AstVisitorManager.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

class AstVisitorManager
{
    protected static ?SplPriorityQueue $queue = null;

    protected static array $values = [];

    /**
     * Cached visitor list to avoid cloning SplPriorityQueue on every proxy generation.
     */
    protected static ?array $cachedVisitors = null;

    public static function __callStatic($name, $arguments)
    {
        return static::getQueue()->{$name}(...$arguments);
    }

    public static function insert($value, $priority = 0)
    {
        static::$values[] = $value;
        static::getQueue()->insert($value, $priority);
        static::$cachedVisitors = null; // Invalidate cache on insert
    }

    public static function exists($value): bool
    {
        return in_array($value, static::$values, true);
    }

    public static function getQueue(): SplPriorityQueue
    {
        return static::$queue ??= new SplPriorityQueue();
    }

    /**
     * Get visitors as a plain array in priority order, avoiding SplPriorityQueue clone overhead.
     * Results are cached until the next insert() call.
     */
    public static function getVisitors(): array
    {
        if (static::$cachedVisitors !== null) {
            return static::$cachedVisitors;
        }

        $queue = clone (static::$queue ?? new SplPriorityQueue());
        $visitors = [];
        foreach ($queue as $item) {
            $visitors[] = $item;
        }
        return static::$cachedVisitors = $visitors;
    }
}