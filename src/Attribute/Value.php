<?php
/**
 * Value.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Value extends AbstractAttribute
{
    public function __construct(public string $key, public $default = null)
    {
    }
}