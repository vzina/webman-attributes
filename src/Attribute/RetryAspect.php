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
                    usleep($delay * 1000);
                }
                $delay = (int) round($delay * $attr->backoff);
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
