<?php
/**
 * InjectPropertyHandler.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use RuntimeException;
use support\Container;
use Vzina\Attributes\Reflection\ReflectionManager;

class InjectPropertyHandler implements PropertyHandlerInterface
{
    public function __invoke(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute)
    {
        $refProp = ReflectionManager::reflectProperty($currentClass, $property);

        // 处理懒加载代理类名
        if ($instance = Container::get($attribute->targetValue)) {
            $refProp->setValue($object, $instance);
        } elseif ($attribute->required) {
            throw new RuntimeException("No entry or class found for '{$attribute->value}'");
        }
    }

    public function getAttribute(): string
    {
        return Inject::class;
    }
}