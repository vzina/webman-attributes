<?php
/**
 * TransactionalAspect — 数据库事务切面。
 *
 * 拦截 @Transactional 方法，包裹数据库事务。支持死锁重试。
 * 事务处理器：容器 TransactionHandlerContract → LaravelTransactionHandler 默认。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use Closure;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Annotation\Transactional;
use Vzina\Attributes\Attribute\Contract\TransactionHandlerContract;
use Vzina\Attributes\Attribute\Default\LaravelTransactionHandler;
use Vzina\Attributes\Attribute\AspectInterface;
use Vzina\Attributes\Attribute\DebugLog;

class TransactionalAspect implements AspectInterface
{
    use DebugLog;

    public array $attributes = [Transactional::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Transactional|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Transactional::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $execute = fn() => $point->process();
        return $this->runTransaction($attr->connection, $execute, $attr->attempts);
    }

    private function runTransaction(string $connection, Closure $callback, int $attempts): mixed
    {
        $handler = $this->resolveHandler();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $handler($connection, $callback);
            } catch (\Throwable $e) {
                if ($attempt >= $attempts || ! $this->isDeadlock($e)) throw $e;
                usleep(random_int(10000, 100000));
            }
        }

        return null;
    }

    protected function resolveHandler(): TransactionHandlerContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $handler = \support\Container::get(TransactionHandlerContract::class);
            } catch (\Throwable $e) {
                $this->log('[TransactionalAspect] Container::get(TransactionHandlerContract) failed: ' . $e->getMessage());
                $handler = null;
            }
            if ($handler instanceof TransactionHandlerContract) return $handler;
        }
        return new LaravelTransactionHandler();
    }


    private static function containsAny(string $haystack, array $needles): bool {
        foreach ($needles as $n) { if (str_contains($haystack, $n)) return true; }
        return false;
    }
    private function isDeadlock(\Throwable $e): bool
    {
        $code     = $e->getCode();
        $msg      = $e->getMessage();
        $prev     = $e->getPrevious();
        $prevCode = $prev ? $prev->getCode() : null;

        $codes  = [1213, '40001', '40P01', 'SQLITE_BUSY', 5];
        $needle = ['deadlock', 'Deadlock', 'try restarting transaction', 'database is locked'];

        return in_array($code, $codes, true)
            || in_array($prevCode, $codes, true)
            || self::containsAny($msg, $needle)
            || ($prev && self::containsAny($prev->getMessage(), $needle));
    }
}