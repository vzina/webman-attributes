<?php
/**
 * LaravelTransactionHandler — illuminate/database 默认事务处理器。
 *
 * 使用 Illuminate\Database 的 DB::connection()->transaction() 包裹回调。
 */
declare(strict_types=1);

namespace Vzina\Attributes\Attribute\Default;

use Vzina\Attributes\Attribute\Contract\TransactionHandlerContract;
use Closure;

class LaravelTransactionHandler implements TransactionHandlerContract
{
    public function __invoke(string $connection, Closure $callback): mixed
    {
        if (! class_exists(\Illuminate\Support\Facades\DB::class)) {
            throw new \RuntimeException('TransactionalAspect requires illuminate/database.');
        }
        return \Illuminate\Support\Facades\DB::connection($connection)->transaction($callback);
    }
}