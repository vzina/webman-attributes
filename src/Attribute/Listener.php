<?php
/**
 * Listener.php
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
class Listener extends AbstractAttribute
{
    public function __construct(
        public string|array|null $event = null,
        public int|null $priority = null,
    ) {
    }

    public function collectClass(string $className): void
    {
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }
}