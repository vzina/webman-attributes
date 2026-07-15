<?php
/**
 * Message — 常量说明注解。
 *
 * 配合 @Constants 使用，为枚举/类常量提供文本说明。
 *
 * @param string $value 说明文本
 * @param string $key   说明类型，默认 'message'
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Message extends AbstractAttribute
{
    public function __construct(
        public string $value,
        public string $key = 'message'
    ) {
    }

    public function getLowerCaseKey(): string
    {
        return strtolower(str_replace('_', '', $this->key));
    }
}