<?php
/**
 * PropertyHandlerInterface.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

interface PropertyHandlerInterface
{
    public function __invoke(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute);
}