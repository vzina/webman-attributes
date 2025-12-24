<?php
/**
 * AspectParser.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class AspectParser
{
    public static function isMatchClassRule(string $target, string $rule): array
    {
        /*
         * e.g. Foo/Bar
         * e.g. Foo/B*
         * e.g. F*o/Bar
         * e.g. Foo/Bar::method
         * e.g. Foo/Bar::met*
         */
        $ruleMethod = null;
        $ruleClass = $rule;
        $method = null;
        $class = $target;

        if (str_contains($rule, '::')) {
            [$ruleClass, $ruleMethod] = explode('::', $rule);
        }
        if (str_contains($target, '::')) {
            [$class, $method] = explode('::', $target);
        }

        if ($method === null) {
            if (! str_contains($ruleClass, '*')) {
                /*
                 * Match [rule] Foo/Bar::ruleMethod [target] Foo/Bar [return] true,ruleMethod
                 * Match [rule] Foo/Bar [target] Foo/Bar [return] true,null
                 * Match [rule] FooBar::rule*Method [target] Foo/Bar [return] true,rule*Method
                 */
                if ($ruleClass === $class) {
                    return [true, $ruleMethod];
                }

                return [false, null];
            }

            /**
             * Match [rule] Foo*Bar::ruleMethod [target] Foo/Bar [return] true,ruleMethod
             * Match [rule] Foo*Bar [target] Foo/Bar [return] true,null.
             */
            $preg = str_replace(['*', '\\'], ['.*', '\\\\'], $ruleClass);
            $pattern = "#^{$preg}$#";

            if (preg_match($pattern, $class)) {
                return [true, $ruleMethod];
            }

            return [false, null];
        }

        if (! str_contains($rule, '*')) {
            /*
             * Match [rule] Foo/Bar::ruleMethod [target] Foo/Bar::ruleMethod [return] true,ruleMethod
             * Match [rule] Foo/Bar [target] Foo/Bar::ruleMethod [return] false,null
             */
            if ($ruleClass === $class && ($ruleMethod === null || $ruleMethod === $method)) {
                return [true, $method];
            }

            return [false, null];
        }

        /*
         * Match [rule] Foo*Bar::ruleMethod [target] Foo/Bar::ruleMethod [return] true,ruleMethod
         * Match [rule] FooBar::rule*Method [target] Foo/Bar::ruleMethod [return] true,rule*Method
         */
        $preg = str_replace(['*', '\\'], ['.*', '\\\\'], $rule);
        $pattern = "#^{$preg}$#";
        if ($ruleMethod) {
            if (preg_match($pattern, $target)) {
                return [true, $method];
            }
            return [false, null];
        }

        /**
         * Match [rule] Foo*Bar [target] Foo/Bar::ruleMethod [return] true,null.
         */
        return preg_match($pattern, $class) ? [true, $method] : [false, null];
    }

    public static function isMatch(string $class, string $method, string $rule): bool
    {
        [$isMatch,] = self::isMatchClassRule($class . '::' . $method, $rule);

        return $isMatch;
    }

    public static function parse(string $class): RewriteCollection
    {
        $rewriteCollection = new RewriteCollection($class);
        $container = AspectCollector::getContainer();
        foreach ($container as $type => $collection) {
            match ($type) {
                'classes' => static::parseClasses($collection, $class, $rewriteCollection),
                'attributes' => static::parseAttributes($collection, $class, $rewriteCollection),
                default => null,
            };
        }
        return $rewriteCollection;
    }

    private static function parseAttributes(array $collection, string $class, RewriteCollection $rewriteCollection): void
    {
        // Get the attributes of class and method.
        $attributes = AttributeCollector::get($class);
        $classMapping = $attributes['_c'] ?? [];
        $methodMapping = [];
        foreach ($attributes['_m'] ?? [] as $method => $targetAttributes) {
            foreach ($targetAttributes as $key => $_) {
                $methodMapping[$key][] = $method;
            }
        }

        foreach ($collection as $aspect => $_) {
            $rules = AspectCollector::getRule($aspect);
            foreach ($rules['attributes'] ?? [] as $rule) {
                // If exist class level attribute, then all methods should rewrite, so return an empty array directly.
                if (isset($classMapping[$rule])) {
                    $rewriteCollection->setLevel(RewriteCollection::CLASS_LEVEL);
                    return;
                }
                if (isset($methodMapping[$rule])) {
                    $rewriteCollection->add($methodMapping[$rule]);
                }
            }
        }
    }

    private static function parseClasses(array $collection, string $class, RewriteCollection $rewriteCollection): void
    {
        foreach ($collection as $aspect => $_) {
            $rules = AspectCollector::getRule($aspect);
            foreach ($rules['classes'] ?? [] as $rule) {
                [$isMatch, $method] = static::isMatchClassRule($class, $rule);
                if ($isMatch) {
                    if ($method === null) {
                        $rewriteCollection->setLevel(RewriteCollection::CLASS_LEVEL);
                        return;
                    }
                    $rewriteCollection->add($method);
                }
            }
        }
    }
}