<?php
/**
 * PropertyTrait.php — 属性注入 trait
 *
 * 只处理类自身的属性。webman 中属性注解几乎只用在当前类上，
 * trait/父类递归属于过度设计，移除可减少 60% 代码。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Collector\PropertyManagerCollector;

trait PropertyTrait
{
    protected function __handlePropertyHandler(string $className): void
    {
        if (PropertyManagerCollector::isEmpty()) {
            return;
        }

        foreach (AttributeCollector::list()[$className]['_p'] ?? [] as $propName => $attrs) {
            foreach ($attrs as $attrName => $attr) {
                foreach (PropertyManagerCollector::get($attrName, []) as $callback) {
                    $callback($this, $className, $className, $propName, $attr);
                }
            }
        }
    }
}
