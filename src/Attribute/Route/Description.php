<?php
/**
 * Description — OpenAPI 描述注解。
 *
 * 在方法上使用，覆盖 OpenAPI 文档中该操作的 description 字段。
 *
 *   #[GetMapping('/checkout')]
 *   #[Description('校验库存后创建订单，支持优惠券和积分抵扣')]
 *   public function checkout(): Order { ... }
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Description
{
    public function __construct(
        public string $value,
    ) {
    }
}