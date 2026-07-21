<?php
/**
 * Summary — OpenAPI 摘要注解。
 *
 * 在方法上使用，覆盖 OpenAPI 文档中该操作的 summary 字段。
 *
 *   #[GetMapping('/checkout')]
 *   #[Summary('创建订单并扣款')]
 *   public function checkout(): Order { ... }
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Summary
{
    public function __construct(
        public string $value,
    ) {
    }
}