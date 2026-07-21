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

namespace Vzina\Attributes\Attribute\Handler;

use support\Container;
use Vzina\Attributes\Collector\AttributeCollector;
use Workerman\Crontab\Crontab as WorkermanCrontab;
use Vzina\Attributes\Attribute\Annotation\Crontab;

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
                $callback = [$instance, $methodAttribute['method']];

                // 分布式锁：多 worker 环境下防止重复执行
                if ($attribute->lockSeconds > 0 && class_exists(\support\Redis::class)) {
                    $lockKey = 'crontab_lock:' . ($attribute->name ?: ($methodAttribute['class'] . '::' . $methodAttribute['method']));
                    $lockConn = $attribute->lockConnection;
                    $original = $callback;
                    $callback = static function () use ($original, $lockKey, $attribute, $lockConn) {
                        $redis = \support\Redis::connection($lockConn);
                        if (! $redis->set($lockKey, '1', ['NX', 'EX' => $attribute->lockSeconds])) {
                            return; // 其他 worker 已在执行
                        }
                        // 锁通过 EX TTL 自动过期，无需主动释放
                        return call_user_func($original);
                    };
                }

                new WorkermanCrontab($attribute->rule, $callback, (string)$attribute->name);
            }
        }
    }
}