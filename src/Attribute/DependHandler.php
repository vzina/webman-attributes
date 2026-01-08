<?php

declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\SplPriorityQueue;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ServiceInjector;
use Webman\Bootstrap;
use Workerman\Worker;

class DependHandler implements Bootstrap
{

    public static function start(?Worker $worker)
    {
        $depends = AttributeCollector::getClassesByAttribute(Depend::class);
        $queue = new SplPriorityQueue();
        foreach ($depends as $class => $attribute) {
            /** @var Depend $attribute */
            $queue->insert([$attribute->id ?: $class, $class, $attribute->options], $attribute->priority);
        }

        $definitions = [];
        while (! $queue->isEmpty()) {
            [$id, $class, $options] = (array)$queue->extract();
            if (! isset($definitions[$id])) {
                $definitions[$id] = static fn() => new $class($options);
            }
        }

        ServiceInjector::inject($definitions);
    }
}