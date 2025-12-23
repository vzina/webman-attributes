<?php
/**
 * AttributeInterface.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

interface AttributeInterface
{
    public function collectClass(string $className): void;
    public function collectClassConstant(string $className, ?string $target): void;
    public function collectMethod(string $className, ?string $target): void;
    public function collectProperty(string $className, ?string $target): void;
}