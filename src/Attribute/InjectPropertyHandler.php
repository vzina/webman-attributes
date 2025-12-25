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
use Throwable;
use Vzina\Attributes\Ast\ProxyManager;
use Vzina\Attributes\AttributeLoader;
use Vzina\Attributes\Reflection\ReflectionManager;

class InjectPropertyHandler implements PropertyHandlerInterface
{
    public function attribute(): string
    {
        return Inject::class;
    }

    public function process(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute)
    {
        try {
            $container = AttributeLoader::getContainer();
            $refProp = ReflectionManager::reflectProperty($currentClass, $property);

            // 处理懒加载代理类名
            $injectClass = $attribute->lazy ? ProxyManager::lazyName($attribute->value) : $attribute->value;
            if ($instance = $container->get($injectClass)) {
                $refProp->setValue($object, $instance);
            } elseif ($attribute->required) {
                throw new RuntimeException("No entry or class found for '{$attribute->value}'");
            }
        } catch (Throwable $e) {
            $attribute->required && throw $e;
        }
    }
}