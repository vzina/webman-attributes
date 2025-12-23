<?php
/**
 * AbstractAttribute.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Collector\AttributeCollector;

abstract class AbstractAttribute implements AttributeInterface
{
    public function collectClass(string $className): void
    {
        AttributeCollector::collectClass($className, static::class, $this);
    }

    public function collectClassConstant(string $className, ?string $target): void
    {
        AttributeCollector::collectClassConstant($className, $target, static::class, $this);
    }

    public function collectMethod(string $className, ?string $target): void
    {
        AttributeCollector::collectMethod($className, $target, static::class, $this);
    }

    public function collectProperty(string $className, ?string $target): void
    {
        AttributeCollector::collectProperty($className, $target, static::class, $this);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}