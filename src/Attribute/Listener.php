<?php
/**
 * Listener — 事件监听注解。
 *
 * 标记类或方法为 webman Event 的事件监听器。
 * 类级别使用时自动监听 'handle' 方法。
 *
 * @param string|array|null $event   事件名，支持多个
 * @param int|null          $priority 监听优先级
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Listener extends AbstractAttribute
{
    public function __construct(
        public string|array|null $event = null,
        public int|null $priority = null,
    ) {
    }

    public function collectClass(string $className): void
    {
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }
}