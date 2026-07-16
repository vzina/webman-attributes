<?php
/**
 * Middleware — 控制器中间件注解。
 *
 * 在 Controller 类或方法上标记要应用的中间件。可重复使用。
 * DispatcherFactory 注册路由时自动合并到 webman Route。
 *
 * @param string $name 中间件类名
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware extends AbstractAttribute
{
    public function __construct(
        public string $name,
        public int $priority = 0,
    ) {
    }
}
