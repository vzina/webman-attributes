<?php
/**
 * AbstractAttribute.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
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

    /**
     * Magic method for var_export() object reconstruction.
     * Called when exporting attribute objects to PHP cache files.
     * Uses reflection to bypass constructor and set all properties directly,
     * supporting readonly promoted properties across all subclasses.
     *
     * @param array<string, mixed> $state Property name => value pairs from var_export
     * @return static Fully reconstructed attribute instance
     */
    public static function __set_state(array $state): static
    {
        $ref = new \ReflectionClass(static::class);
        $instance = $ref->newInstanceWithoutConstructor();

        foreach ($state as $key => $value) {
            if ($ref->hasProperty($key)) {
                $prop = $ref->getProperty($key);
                if (! $prop->isStatic()) {
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $value);
                }
            }
        }

        return $instance;
    }
}