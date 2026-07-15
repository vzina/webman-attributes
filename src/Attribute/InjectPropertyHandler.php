<?php
/**
 * InjectPropertyHandler.php
 * @version 8.1
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

        $targetValue = $attribute->targetValue;
        $instance = Container::get($targetValue);

        // webman Container 不会对未注册类做 auto-make，这里手动兜底
        if ($instance === null && class_exists($targetValue)) {
            $instance = new $targetValue();
        }

        if ($instance !== null) {
            $refProp->setValue($object, $instance);
            return;
        }

        throw new RuntimeException(sprintf(
            "No entry or class found for '%s' (target: '%s', class_exists: %s) in %s::%s",
            $attribute->value,
            $targetValue,
            var_export(class_exists($targetValue), true),
            $currentClass,
            $property
        ));
    }

    public function getAttribute(): string
    {
        return Inject::class;
    }
}