<?php
/**
 * Process.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

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