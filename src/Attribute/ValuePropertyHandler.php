<?php
/**
 * ValuePropertyHandler.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Reflection\ReflectionManager;
use Webman\Config;

class ValuePropertyHandler implements PropertyHandlerInterface
{
    public function attribute(): string
    {
        return Value::class;
    }

    public function process(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute)
    {
        $refProp = ReflectionManager::reflectProperty($currentClass, $property);
        $refProp->setValue($object, match (true) {
            class_exists(Config::class) => Config::get((string)$attribute->key, $attribute->default),
            function_exists('config') => \config((string)$attribute->key, $attribute->default),
            default => null
        });
    }
}