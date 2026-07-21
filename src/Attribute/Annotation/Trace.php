<?php
/**
 * Trace — 分布式追踪注解。
 *
 * 为方法调用创建追踪 span，记录耗时和状态。W3C Trace Context 标准。
 *
 * @param ?string $spanName span 名称，null 时自动 ClassName::methodName
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Trace extends AbstractAttribute
{
    public function __construct(
        public ?string $spanName = null,
    ) {
    }
}
