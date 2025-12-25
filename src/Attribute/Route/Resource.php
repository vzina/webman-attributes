<?php
/**
 * Controller.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Resource extends AbstractAttribute
{
    public function __construct(
        public string $prefix = '',
        public array|string $options = [],
    ) {
    }
}