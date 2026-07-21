<?php
/**
 * Aspect.php
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
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Aspect extends AbstractAttribute
{
    public function __construct(
        public array $classes = [],
        public array $attributes = [],
        public ?int $priority = null
    ) {
    }

    public function collectClass(string $className): void
    {
        parent::collectClass($className);

        // 读取切面类（$className）的默认属性值，而非 #[Aspect] 属性实例的构造函数参数
        $props = (new \ReflectionClass($className))->getDefaultProperties();
        AspectCollector::setAround(
            $className,
            $props['classes'] ?? [],
            $props['attributes'] ?? [],
            $props['priority'] ?? null,
        );
    }
}