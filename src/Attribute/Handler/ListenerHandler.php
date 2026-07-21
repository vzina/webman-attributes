<?php
/**
 * ListenerHandler.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Handler;

use support\Container;
use Illuminate\Support\Arr;
use Vzina\Attributes\Ast\SplPriorityQueue;
use Vzina\Attributes\Collector\AttributeCollector;
use Webman\Bootstrap;
use Webman\Event\Event;
use Workerman\Worker;
use Vzina\Attributes\Attribute\Annotation\Listener;

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
                    $handler = [$instance, $listener['method']];

                    // when 条件过滤：格式为 "key=value"，支持点分隔嵌套 key（如 order.status=paid）
                    if ($attribute->when !== null) {
                        [$whenKey, $whenVal] = explode('=', $attribute->when) + [null, null];
                        $handler = static function ($data) use ($handler, $whenKey, $whenVal) {
                            if ($whenKey === null) return;
                            $actual = is_array($data) || $data instanceof \ArrayAccess
                                ? Arr::get($data, $whenKey)
                                : (is_object($data) ? Arr::get(get_object_vars($data), $whenKey) : null);
                            if ((string) $actual !== $whenVal) return;
                            return call_user_func($handler, $data);
                        };
                    }

                    $queue->insert([$event, $handler], (int)$attribute->priority);
                }
            }
        }

        while ($queue->valid()) {
            Event::on(...$queue->current());
            $queue->next();
        }
    }
}