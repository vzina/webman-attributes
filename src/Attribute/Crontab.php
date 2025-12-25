<?php
/**
 * Crontab.php
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

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Crontab extends AbstractAttribute
{
    public function __construct(
        public string $rule,
        public ?string $name = null,
    ) {
    }

    public function collectClass(string $className): void
    {
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }
}