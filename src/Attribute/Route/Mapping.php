<?php
/**
 * Mapping.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Route;

use Vzina\Attributes\Attribute\AbstractAttribute;

class Mapping extends AbstractAttribute
{
    public function __construct(
        public ?string $path = null,
        public array|string $methods = [],
        public array|string $options = []
    ) {
    }
}