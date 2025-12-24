<?php
/**
 * Listener.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\attributes;

use Attribute;
use support\Container;
use Vzina\Attributes\Attribute\AbstractAttribute;
use Vzina\Attributes\Collector\AttributeCollector;
use Webman\Bootstrap;
use Webman\Event\Event;
use Workerman\Worker;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Listener extends AbstractAttribute implements Bootstrap
{
    public function __construct(public string|array|null $event = null)
    {
    }

    public function collectClass(string $className): void
    {
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }

    public static function start(?Worker $worker)
    {
        $container = Container::instance();
        $methodAttributes = AttributeCollector::getMethodsByAttribute(self::class);
        foreach ($methodAttributes as $methodAttribute) {
            // ['class' => $class, 'method' => $method, 'attribute' => $value]
            /** @var self $attribute */
            $attribute = $methodAttribute['attribute'];
            if ($instance = $container->get($methodAttribute['class'])) {
                $events = (array)$attribute->event;
                if (method_exists($instance, 'listen')) {
                    $events = $instance->listen();
                }

                foreach ($events as $event) {
                    Event::on($event, [$instance, $methodAttribute['method']]);
                }
            }
        }
    }
}