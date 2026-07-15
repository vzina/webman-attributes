<?php
/**
 * ProxyTrait — AOP 切面代理核心 trait。
 *
 * 被代理的类通过 __proxyCall() 将所有方法调用路由到 aspect 管道。
 * 首次调用时解析匹配的切面并缓存到 AspectManagerCollector。
 * 管道使用 Laravel Pipeline 按优先级执行切面的 process() 方法。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Closure;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AspectManagerCollector;
use Vzina\Attributes\Collector\AttributeCollector;

trait ProxyTrait
{
    /** 管道单例，避免每请求创建匿名类 */
    protected static ?ProxyPipeline $pipeline = null;

    /** 代理入口：由 AstProxyCallVisitor 生成的方法体调用 */
    protected static function __proxyCall(
        string $className, string $method, array $arguments, Closure $closure
    ) {
        $point = new ProceedingJoinPoint($closure, $className, $method, $arguments);
        $result = self::handleAround($point);
        unset($point);
        return $result;
    }

    /** 解析并执行切面管道 */
    protected static function handleAround(ProceedingJoinPoint $point)
    {
        $class  = $point->className;
        $method = $point->methodName;

        // 首次调用 → 解析切面列表并缓存
        if (! AspectManagerCollector::has($class, $method)) {
            $queue = new SplPriorityQueue();
            foreach (self::resolveAspects($class, $method) as $aspect) {
                $queue->insert($aspect, AspectCollector::getPriority($aspect));
            }
            while ($queue->valid()) {
                AspectManagerCollector::insert($class, $method, $queue->current());
                $queue->next();
            }
        }

        $aspects = AspectManagerCollector::get($class, $method);
        if (empty($aspects)) {
            return $point->processOriginalMethod();
        }

        return self::pipeline()
            ->via('process')->through($aspects)->send($point)
            ->then(fn(ProceedingJoinPoint $p) => $p->processOriginalMethod());
    }

    private static function pipeline(): ProxyPipeline
    {
        return self::$pipeline ??= new ProxyPipeline();
    }

    /** 解析类+方法匹配的全部切面（class 规则 + attribute 规则） */
    private static function resolveAspects(string $class, string $method): array
    {
        $matched = [];

        // class 规则
        foreach (AspectCollector::get('classes', []) as $aspect => $rules) {
            foreach ($rules as $rule) {
                if (AspectParser::isMatch($class, $method, $rule)) { $matched[] = $aspect; break; }
            }
        }

        // attribute 规则
        $attrs = array_keys(array_merge(
            AttributeCollector::get($class . '._c', []),
            AttributeCollector::get($class . '._m.' . $method, [])
        ));
        if ($attrs) {
            foreach (AspectCollector::get('attributes', []) as $aspect => $rules) {
                foreach ($rules as $rule) {
                    foreach ($attrs as $attr) {
                        if (AspectCollector::matchRule($rule, $attr)) { $matched[] = $aspect; continue 2; }
                    }
                }
            }
        }

        return array_unique($matched);
    }
}
