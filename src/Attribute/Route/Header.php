<?php
/**
 * Header — OpenAPI 请求头注解。
 *
 * 在类或方法上声明请求头参数，自动添加到 OpenAPI 文档的 parameters 中。
 * 可重复使用以声明多个请求头。
 *
 *   #[GetMapping('/profile')]
 *   #[Header('Authorization', description: 'Bearer token', required: true)]
 *   #[Header('X-Request-Id', description: '请求追踪 ID')]
 *   public function profile(): Response { ... }
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Header
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $required = false,
    ) {
    }
}