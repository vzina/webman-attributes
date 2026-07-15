<?php
/**
 * PropertyHandlerInterface.php
 * @version 8.1
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
    public function getAttribute(): string;
    public function __invoke(object $object, string $currentClass, string $targetClass, string $property, AttributeInterface $attribute);
}