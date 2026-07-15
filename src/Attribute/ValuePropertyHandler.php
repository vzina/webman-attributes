<?php
/**
 * ValuePropertyHandler.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Reflection\ReflectionManager;
use Webman\Config;

class ValuePropertyHandler implements PropertyHandlerInterface
{
    public function __invoke(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute)
    {
        $refProp = ReflectionManager::reflectProperty($currentClass, $property);
        $refProp->setValue($object, match (true) {
            class_exists(Config::class) => Config::get((string)$attribute->key, $refProp->getDefaultValue()),
            function_exists('config') => \config((string)$attribute->key, $refProp->getDefaultValue()),
            default => null
        });
    }

    public function getAttribute(): string
    {
        return Value::class;
    }
}