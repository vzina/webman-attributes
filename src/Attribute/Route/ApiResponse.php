<?php
/**
 * ApiResponse — OpenAPI 响应注解。
 *
 * 在方法上声明自定义响应，自动添加到 OpenAPI 文档的 responses 中。
 * 可重复使用以声明多个状态码的响应。
 *
 *   #[GetMapping('/order/{id}')]
 *   #[ApiResponse(200, '订单详情')]
 *   #[ApiResponse(404, '订单不存在')]
 *   #[ApiResponse(422, '参数校验失败')]
 *   public function show(int $id): Order { ... }
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public function __construct(
        public int $statusCode,
        public string $description,
    ) {
    }
}