<?php
/**
 * Process.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Process extends AbstractAttribute
{
    public function __construct(
        public ?string $name = null,
        public int $count = 1,
        public array $options = []
    ) {
    }
}