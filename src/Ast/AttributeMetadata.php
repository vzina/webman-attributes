<?php
/**
 * AttributeMetadata.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
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