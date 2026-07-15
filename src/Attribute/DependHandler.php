<?php
/**
 * DependHandler — 将 @Depend 注解的类注册到 webman 容器。
 *
 * 优先级机制：priority 越高越优先注册，同名 id 只保留高优先级的定义。
 * 支持显式构造参数（params）和单例模式（singleton），通过 ServiceInjector 注入。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\SplPriorityQueue;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ServiceInjector;
use Webman\Bootstrap;
use Workerman\Worker;

class DependHandler implements Bootstrap
{
    /**
     * Worker 启动时执行：收集所有 @Depend 类 → 按优先级排序 → 批量注入容器。
     */
    public static function start(?Worker $worker)
    {
        $depends = AttributeCollector::getClassesByAttribute(Depend::class);
        if (empty($depends)) {
            return;
        }

        $queue = new SplPriorityQueue();
        foreach ($depends as $class => $attribute) {
            /** @var Depend $attribute */
            $queue->insert([
                'id'        => $attribute->id ?: $class,
                'class'     => $class,
                'params'    => $attribute->params,
                'singleton' => $attribute->singleton,
            ], $attribute->priority);
        }

        $definitions = [];
        while (! $queue->isEmpty()) {
            $item = $queue->extract();
            $id = $item['id'];
            if (! isset($definitions[$id])) {
                $definitions[$id] = $item;
            }
        }

        ServiceInjector::inject($definitions);
    }
}
