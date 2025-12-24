<?php
/**
 * Crontab.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\crontab;

use Attribute;
use support\Container;
use Vzina\Attributes\Attribute\AbstractAttribute;
use Vzina\Attributes\Collector\AttributeCollector;
use Webman\Event\Event;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Crontab extends AbstractAttribute
{
    public function __construct(
        public ?string $rule = null,
        public ?string $name = null,
    ) {
    }

    public function collectClass(string $className): void
    {
        if (method_exists($className, 'handle')) {
            $this->collectMethod($className, 'handle');
        }
    }

    public function onWorkerStart()
    {
        $container = Container::instance();
        $methodAttributes = AttributeCollector::getMethodsByAttribute(self::class);
        foreach ($methodAttributes as $methodAttribute) {
            // ['class' => $class, 'method' => $method, 'attribute' => $value]
            /** @var self $attribute */
            $attribute = $methodAttribute['attribute'];
            if ($attribute->rule && ($instance = $container->get($methodAttribute['class']))) {
                new \Workerman\Crontab\Crontab(
                    $attribute->rule,
                    [$instance, $methodAttribute['method']],
                    (string)$attribute->name
                );
            }
        }
    }
}