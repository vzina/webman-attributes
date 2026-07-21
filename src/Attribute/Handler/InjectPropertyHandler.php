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

namespace Vzina\Attributes\Attribute\Handler;

use RuntimeException;
use support\Container;
use Vzina\Attributes\Reflection\ReflectionManager;
use Vzina\Attributes\Attribute\Annotation\Inject;
use Vzina\Attributes\Attribute\Contract\PropertyHandlerInterface;
use Vzina\Attributes\Attribute\AttributeInterface;

class InjectPropertyHandler implements PropertyHandlerInterface
{
    public function __invoke(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute)
    {
        $refProp = ReflectionManager::reflectProperty($currentClass, $property);

        $targetValue = $attribute->targetValue;
        $instance = Container::get($targetValue);

        // webman Container 不会对未注册类做 auto-make，这里手动兜底
        if ($instance === null && class_exists($targetValue)) {
            $instance = Container::make($targetValue);
        }

        // 接口类型：尝试从容器中重新获取（可能绑定了实现）
        if ($instance === null && interface_exists($targetValue)) {
            $instance = Container::get($targetValue);
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