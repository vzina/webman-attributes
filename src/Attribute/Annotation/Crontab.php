<?php
/**
 * Crontab — 定时任务注解。
 *
 * 标记类或方法为定时任务，通过 workerman/crontab 按 cron 表达式调度执行。
 * 类级别使用时自动监听 'handle' 方法。
 *
 * @param string  $rule      cron 表达式，如 '* * * * *'
 * @param ?string $name      任务名称
 * @param int     $lockSeconds  分布式锁 TTL 秒，0=不启用
 * @param string  $lockConnection Redis 连接名，默认 'default'
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Crontab extends AbstractAttribute
{
    public function __construct(
        public string $rule,
        public ?string $name = null,
        public int $lockSeconds = 0,
        public string $lockConnection = 'default',
    ) {
    }

    public function collectClass(string $className): void
    {
        parent::collectClass($className);
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }
}