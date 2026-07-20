<?php
/**
 * RetryAspect — 失败自动重试切面。
 *
 * 拦截 @Retry 方法：方法抛异常时，按 maxAttempts/delayMs/backoff 配置自动重试。
 * 支持 on 异常过滤，仅指定异常类触发重试。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\ProceedingJoinPoint;

class RetryAspect implements AspectInterface
{
    public array $attributes = [Retry::class];

    /** 单次延迟上限（毫秒），防止退避溢出 */
    private const MAX_DELAY_MS = 60_000;

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Retry|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Retry::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $maxAttempts = min($attr->maxAttempts, 100);
        $attempt = 0;
        $delay   = $attr->delayMs;

        while (true) {
            $attempt++;
            try {
                return $point->process();
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts || ! $this->shouldRetry($e, $attr->on)) {
                    throw $e;
                }

                if ($delay > 0) {
                    usleep(min($delay, self::MAX_DELAY_MS) * 1000);
                }
                // 指数退避 + 随机抖动，上限防溢出
                $delay = min((int) round($delay * $attr->backoff + random_int(0, 100)), self::MAX_DELAY_MS);
            }
        }
    }

    /** 判断异常是否应该触发重试 */
    private function shouldRetry(\Throwable $e, array $on): bool
    {
        if (empty($on)) {
            return true;
        }

        foreach ($on as $class) {
            if (class_exists($class) && $e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
