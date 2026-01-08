<?php
/**
 * Message.php
 * PHP version 7
 *
 * @package webman-demo
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
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