<?php
/**
 * AspectParser — 切面规则匹配器。
 *
 * 判断一个「类::方法」是否匹配 Aspect 定义的 class/attribute 通配符规则。
 * 支持 * 通配符，精确匹配和模式匹配。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class AspectParser
{
    /**
     * 检查 class::method 是否匹配规则。
     *
     * 规则示例: Foo/Bar, Foo/B*, Foo/Bar::method, Foo/Bar::met*
     * @return array{0: bool, 1: ?string} [是否匹配, 匹配到的方法名(如有)]
     */
    public static function isMatchClassRule(string $target, string $rule): array
    {
        $ruleClass  = $rule;
        $ruleMethod = null;
        $class      = $target;
        $method     = null;

        if (str_contains($rule, '::')) {
            [$ruleClass, $ruleMethod] = explode('::', $rule);
        }
        if (str_contains($target, '::')) {
            [$class, $method] = explode('::', $target);
        }

        // 无通配符 → 精确匹配
        if (! str_contains($rule, '*')) {
            $classOk  = $ruleClass === $class;
            $methodOk = $ruleMethod === null || $ruleMethod === $method || $method === null;
            $matched  = $classOk && $methodOk;
            return [$matched, $matched ? ($method ?? $ruleMethod) : null];
        }

        // 通配符匹配
        $pattern = '/^' . str_replace('\*', '.*', preg_quote($rule, '/')) . '$/';
        if ($ruleMethod) {
            return [preg_match($pattern, $target) === 1, $method];
        }
        return [preg_match($pattern, $class) === 1, $method];
    }

    /** 简化接口：类+方法匹配规则 */
    public static function isMatch(string $class, string $method, string $rule): bool
    {
        return self::isMatchClassRule($class . '::' . $method, $rule)[0];
    }

    /** 解析一个类的所有匹配切面规则，返回需要重写的方法集合 */
    public static function parse(string $class): RewriteCollection
    {
        $collection = new RewriteCollection($class);
        $container  = AspectCollector::getContainer();

        foreach ($container as $type => $items) {
            match ($type) {
                'classes'    => self::parseClasses($items, $class, $collection),
                'attributes' => self::parseAttributes($items, $class, $collection),
                default      => null,
            };
        }
        return $collection;
    }

    /** 类规则解析 */
    private static function parseClasses(array $collection, string $class, RewriteCollection $rw): void
    {
        foreach ($collection as $aspect => $_) {
            foreach (AspectCollector::getRule($aspect)['classes'] ?? [] as $rule) {
                [$ok, $method] = self::isMatchClassRule($class, $rule);
                if (! $ok) continue;
                if ($method === null) { $rw->setLevel(RewriteCollection::CLASS_LEVEL); return; }
                $rw->add($method);
            }
        }
    }

    /** 属性规则解析 */
    private static function parseAttributes(array $collection, string $class, RewriteCollection $rw): void
    {
        $attrs    = AttributeCollector::get($class);
        $classMap = $attrs['_c'] ?? [];
        $methodMap = [];
        foreach ($attrs['_m'] ?? [] as $method => $list) {
            foreach ($list as $attr => $_) { $methodMap[$attr][] = $method; }
        }

        foreach ($collection as $aspect => $_) {
            foreach (AspectCollector::getRule($aspect)['attributes'] ?? [] as $rule) {
                if (isset($classMap[$rule])) { $rw->setLevel(RewriteCollection::CLASS_LEVEL); return; }
                if (isset($methodMap[$rule])) { $rw->add($methodMap[$rule]); }
            }
        }
    }
}
