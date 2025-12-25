<?php
/**
 * ListenerHandler.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use support\Container;
use Vzina\Attributes\Ast\SplPriorityQueue;
use Vzina\Attributes\Collector\AttributeCollector;
use Webman\Bootstrap;
use Webman\Event\Event;
use Workerman\Worker;

class ListenerHandler implements Bootstrap
{

    public static function start(?Worker $worker)
    {
        if (! class_exists(Event::class)) {
            return;
        }

        $container = Container::instance();
        $listeners = AttributeCollector::getMethodsByAttribute(Listener::class);

        $queue = new SplPriorityQueue();
        foreach ($listeners as $listener) {
            // @var array $listener ['class' => $class, 'method' => $method, 'attribute' => $value]
            /** @var Listener $attribute */
            $attribute = $listener['attribute'];
            if ($instance = $container->get($listener['class'])) {
                $events = (array)$attribute->event;
                if (method_exists($instance, 'listen')) {
                    $events = $instance->listen();
                }

                foreach ($events as $event) {
                    $queue->insert([$event, [$instance, $listener['method']]], (int)$attribute->priority);
                }
            }
        }

        while ($queue->valid()) {
            Event::on(...$queue->current());
            $queue->next();
        }
    }
}