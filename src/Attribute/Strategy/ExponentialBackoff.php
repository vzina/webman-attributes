<?php
/**
 * ExponentialBackoff — 指数退避策略。
 *
 * delay_{n+1} = delay_n * backoff + jitter，上限封顶。
 */
declare(strict_types=1);

namespace Vzina\Attributes\Attribute\Strategy;

use Vzina\Attributes\Attribute\Contract\BackoffStrategy;

class ExponentialBackoff implements BackoffStrategy
{
    public function nextDelay(int $currentDelay, float $backoff, int $maxDelay): int
    {
        return min((int) round($currentDelay * $backoff + random_int(0, 100)), $maxDelay);
    }
}