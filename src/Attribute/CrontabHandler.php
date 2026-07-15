<?php
/**
 * CrontabHandler.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use support\Container;
use Vzina\Attributes\Collector\AttributeCollector;
use Workerman\Crontab\Crontab as WorkermanCrontab;

class CrontabHandler
{
    public function onWorkerStart(): void
    {
        $container = Container::instance();
        $methodAttributes = AttributeCollector::getMethodsByAttribute(Crontab::class);
        foreach ($methodAttributes as $methodAttribute) {
            // ['class' => $class, 'method' => $method, 'attribute' => $value]
            /** @var Crontab $attribute */
            $attribute = $methodAttribute['attribute'];
            if ($attribute->rule && ($instance = $container->get($methodAttribute['class']))) {
                new WorkermanCrontab(
                    $attribute->rule,
                    [$instance, $methodAttribute['method']],
                    (string)$attribute->name
                );
            }
        }
    }
}