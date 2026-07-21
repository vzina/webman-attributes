<?php
/**
 * Retry — 失败自动重试注解。
 *
 * 方法抛异常时，按配置自动重试。支持指数/线性退避和异常过滤。
 *
 * @param int    $maxAttempts 最大重试次数（含首次），默认 3
 * @param int    $delayMs     基础延迟毫秒，默认 100
 * @param float  $backoff     退避倍率，默认 2.0
 * @param array  $on          仅重试这些异常类名，空数组=所有异常
 * @param string $strategy     退避策略：'exponential'（指数）| 'linear'（线性），默认 exponential
 * @param string $strategy    退避策略：'exponential'（指数）| 'linear'（线性），默认 exponential
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Retry extends AbstractAttribute
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $delayMs = 100,
        public float $backoff = 2.0,
        public array $on = [],
        public string $strategy = 'exponential',
    ) {
    }
}
