<?php
/**
 * AttributeMetadata.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

class AttributeMetadata
{
    public function __construct(
        public array $class,
        public array $method
    ) {
    }
}