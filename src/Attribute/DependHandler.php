<?php
/**
 * DependHandler — 将 @Depend 注解的类注册到 webman 容器。
 *
 * 优先级机制：priority 越高越优先注册，同名 id 只保留高优先级的定义。
 * 支持显式构造参数（params）和单例模式（singleton），通过 ServiceInjector 注入。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use support\Container;
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
        $queue = new SplPriorityQueue();

        // 静态配置：dependence.php → 数组顺序即优先级（先定义 > 后定义）
        if (Container::instance() instanceof \Webman\Container) {
            $dependence = (array) config('dependence');
            if ($dependence) {
                $count = count($dependence);
                foreach ($dependence as $id => $dependency) {
                    $queue->insert([
                        'id'        => $id,
                        'class'     => $dependency,
                        'params'    => [],
                        'singleton' => true,
                    ], $count--);
                }
            }
        }

        // 注解：#[Depend] → 显式 priority
        $depends = AttributeCollector::getClassesByAttribute(Depend::class);
        if ($depends) {
            foreach ($depends as $class => $attribute) {
                /** @var Depend $attribute */
                $queue->insert([
                    'id'        => $attribute->id ?? $class,
                    'class'     => $class,
                    'params'    => $attribute->params,
                    'singleton' => $attribute->singleton,
                ], $attribute->priority);
            }
        }

        // 同名取高优先（先 extract 者先写入 → 高优先保留）
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
