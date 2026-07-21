<?php
/**
 * BackoffStrategy — 重试退避策略接口（Strategy 模式）。
 *
 * 实现此接口即可自定义重试延迟算法，通过 RetryAspect::registerStrategy() 注册。
 */
declare(strict_types=1);

namespace Vzina\Attributes\Attribute\Contract;

interface BackoffStrategy
{
    public function nextDelay(int $currentDelay, float $backoff, int $maxDelay): int;
}