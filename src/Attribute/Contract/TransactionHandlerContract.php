<?php
/**
 * TransactionHandlerContract — 事务处理器接口。
 *
 * 实现此接口即可替换默认 illuminate/database 事务处理器，
 * 或扩展为其他 ORM/DBAL 的事务实现。
 *
 * @method mixed __invoke(string $connection, \Closure $callback) 在事务中执行回调
 */
declare(strict_types=1);

namespace Vzina\Attributes\Attribute\Contract;

use Closure;

interface TransactionHandlerContract
{
    public function __invoke(string $connection, Closure $callback): mixed;
}