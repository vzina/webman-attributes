<?php
/**
 * PropertyTrait.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use ReflectionClass;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Collector\PropertyManagerCollector;
use Vzina\Attributes\Reflection\ReflectionManager;

trait PropertyTrait
{
    /**
     * 处理类/父类/Trait的属性注解回调
     */
    protected function __handlePropertyHandler(string $className): void
    {
        // 无属性处理器时直接返回（最快路径）
        if (PropertyManagerCollector::isEmpty()) {
            return;
        }

        $refClass = ReflectionManager::reflectClass($className);
        $processedProps = [];

        // 处理当前类属性
        $processedProps = array_merge(
            $processedProps,
            $this->__processProperties($className, $className, ReflectionManager::reflectPropertyNames($className))
        );

        // 处理Trait属性（递归）
        $processedProps = $this->__processTraitProperties($refClass, $processedProps, $className);

        // 处理父类属性（递归）
        $this->__processParentClassProperties($refClass, $processedProps, $className);
    }

    /**
     * 递归处理Trait的属性
     */
    private function __processTraitProperties(ReflectionClass $refClass, array $processedProps, string $className): array
    {
        foreach ($refClass->getTraits() as $refTrait) {
            $traitName = $refTrait->getName();

            // 跳过核心Trait，避免递归处理自身
            if (in_array($traitName, [ProxyTrait::class, self::class])) {
                continue;
            }

            // 提取未处理的Trait属性
            $traitProps = array_diff(
                ReflectionManager::reflectPropertyNames($traitName),
                $processedProps
            );

            // 处理当前Trait属性并更新已处理列表
            if (!empty($traitProps)) {
                $processedProps = array_merge(
                    $processedProps,
                    $this->__processProperties($className, $traitName, $traitProps)
                );
            }

            // 递归处理Trait的Trait
            $processedProps = $this->__processTraitProperties($refTrait, $processedProps, $className);
        }

        return $processedProps;
    }

    /**
     * 递归处理父类的属性
     */
    private function __processParentClassProperties(ReflectionClass $refClass, array $processedProps, string $className): void
    {
        $parentRefClass = $refClass;
        while ($parentRefClass = $parentRefClass->getParentClass()) {
            $parentClassName = $parentRefClass->getName();

            // 提取父类中当前类已定义的、且未处理的属性
            $parentProps = array_filter(
                ReflectionManager::reflectPropertyNames($parentClassName),
                fn($prop) => $refClass->hasProperty($prop)
            );
            $parentProps = array_diff($parentProps, $processedProps);

            // 处理父类属性并更新已处理列表
            if (!empty($parentProps)) {
                $processedProps = array_merge(
                    $processedProps,
                    $this->__processProperties($className, $parentClassName, $parentProps)
                );
            }
        }
    }

    /**
     * 处理具体属性的注解回调，返回已处理的属性名
     */
    private function __processProperties(string $currentClass, string $targetClass, array $properties): array
    {
        $processed = [];
        foreach ($properties as $propName) {
            // 获取属性注解元数据，无数据则跳过
            $attrMetadata = AttributeCollector::getClassPropertyAttribute($targetClass, $propName);
            if (empty($attrMetadata)) {
                continue;
            }

            // 遍历注解并执行回调
            foreach ($attrMetadata as $attrName => $attr) {
                $callbacks = PropertyManagerCollector::get($attrName);
                if (empty($callbacks)) {
                    continue;
                }

                // 执行所有回调并标记属性为已处理
                foreach ($callbacks as $callback) {
                    $callback($this, $currentClass, $targetClass, $propName, $attr);
                }
                $processed[] = $propName;
            }
        }

        return $processed;
    }
}