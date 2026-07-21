<?php
/**
 * Value — 配置注入注解。
 *
 * 将 webman 配置中的值注入到属性上，属性默认值作为 fallback。
 *
 * @param string $key 配置键名，如 'app.name'、'cache.ttl'
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Value extends AbstractAttribute
{
    public function __construct(public string $key)
    {
    }
}