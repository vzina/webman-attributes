<?php
/**
 * Retry — 失败自动重试注解。
 *
 * 方法抛异常时，按配置自动重试。支持指数退避和异常过滤。
 *
 * @param int   $maxAttempts 最大重试次数（含首次），默认 3
 * @param int   $delayMs     基础延迟毫秒，默认 100
 * @param float $backoff     退避倍率：1.0=线性，2.0=指数，默认 1.0
 * @param array $on          仅重试这些异常类名，空数组=所有异常
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
        public float $backoff = 1.0,
        public array $on = [],
    ) {
    }
}
