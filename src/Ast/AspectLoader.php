<?php
/**
 * AspectLoader.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use ReflectionException;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Reflection\ReflectionManager;

class AspectLoader
{
    public static function load(string $className): array
    {
        $propertyNames = ['classes', 'attributes', 'priority'];
        $result = [];
        foreach ($propertyNames as $propertyName) {
            try {
                $property = ReflectionManager::reflectProperty($className, $propertyName);
                $result[$propertyName] = $property->getDefaultValue();
            } catch (ReflectionException $e) {
                continue;
            }
        }
        return $result;
    }

    public static function collect(string $className, array $default = []): void
    {
        $aspect = self::load($className);
        $classes = $aspect['classes'] ?? $default['classes'] ?? [];
        $attributes = $aspect['attributes'] ?? $default['attributes'] ?? [];
        $priority = $aspect['priority'] ?? $default['priority'] ?? null;

        AspectCollector::setAround($className, $classes, $attributes, $priority);
    }
}