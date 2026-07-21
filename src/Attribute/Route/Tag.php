<?php
/**
 * Tag — OpenAPI 标签分组注解。
 *
 * 在类或方法上使用，覆盖自动从类名推导的 OpenAPI tag。
 * 可用于在 OpenAPI 文档中按业务领域分组。
 *
 *   #[Controller(prefix: '/order')]
 *   #[Tag('订单管理')]
 *   class OrderController { ... }
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Tag
{
    public function __construct(
        public string $value,
    ) {
    }
}