<?php
/**
 * TransactionalAspect — 数据库事务切面。
 *
 * 拦截 @Transactional 方法，包裹数据库事务。内置 handler 尝试
 * illuminate/database DB；用户可通过 Transactional(transactionHandler: $fn) 注入自定义实现。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use Closure;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Annotation\Transactional;
use Vzina\Attributes\Attribute\AspectInterface;

class TransactionalAspect implements AspectInterface
{
    /** @var array<Transactional> */
    public array $attributes = [Transactional::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Transactional|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Transactional::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $execute = function () use ($point) {
            return $point->process();
        };

        return $this->runTransaction($attr->connection, $execute, $attr->attempts, $attr->transactionHandler);
    }

    /** 执行事务，支持死锁重试 */
    private function runTransaction(string $connection, Closure $callback, int $attempts, $transactionHandler = null): mixed
    {
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->executeTransaction($connection, $callback, $transactionHandler);
            } catch (\Throwable $e) {
                if ($attempt >= $attempts || ! $this->isDeadlock($e)) {
                    throw $e;
                }
                usleep(random_int(10000, 100000));
            }
        }

        return null; // unreachable, satisfies static analysis
    }

    /** 执行单次事务 */
    private function executeTransaction(string $connection, Closure $callback, $transactionHandler = null): mixed
    {
        $handler = $transactionHandler && is_callable($transactionHandler) ? $transactionHandler : $this->defaultHandler();
        return $handler($connection, $callback);
    }

    /** 内置事务处理器：尝试 illuminate/database DB */
    private function defaultHandler(): Closure
    {
        return static function (string $connection, Closure $callback) {
            if (! class_exists(\Illuminate\Support\Facades\DB::class)) {
                throw new \RuntimeException(
                    'TransactionalAspect requires illuminate/database. Install it or pass a transactionHandler to the #[Transactional] attribute.'
                );
            }
            return \Illuminate\Support\Facades\DB::connection($connection)->transaction($callback);
        };
    }

    /** 判断是否为死锁异常 */
    private function isDeadlock(\Throwable $e): bool
    {
        $code   = $e->getCode();
        $msg    = $e->getMessage();
        $prev   = $e->getPrevious();
        $prevCode = $prev ? $prev->getCode() : null;

        // MySQL error 1213, PostgreSQL error 40P01, SQLite error 5
        $codes  = [1213, '40001', '40P01', 'SQLITE_BUSY', 5];
        $needle = ['deadlock', 'Deadlock', 'try restarting transaction', 'database is locked'];

        return in_array($code, $codes, true)
            || in_array($prevCode, $codes, true)
            || str_contains($msg, ...$needle)
            || ($prev && str_contains($prev->getMessage(), ...$needle));
    }
}
