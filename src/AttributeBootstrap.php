<?php
/**
 * AttributeBootstrap — webman Bootstrap 入口。
 *
 * 在 Worker 启动时依次执行：事件监听注册 → 依赖注入注册 → 组件就绪通知 → 反射缓存释放。
 * 扫描和路由注册由 bootstrap.php + route.php 在此前完成。
 */
declare (strict_types=1);

namespace Vzina\Attributes;

use Vzina\Attributes\Attribute\BootAttribute;
use Vzina\Attributes\Attribute\Handler\DependHandler;
use Vzina\Attributes\Attribute\Handler\ListenerHandler;
use Vzina\Attributes\Reflection\ReflectionManager;
use Webman\Bootstrap;
use Webman\Event\Event;
use Workerman\Worker;

class AttributeBootstrap implements Bootstrap
{
    public static function start(?Worker $worker)
    {
        // 事件监听：扫描所有 @Listener 方法并注册到 webman Event
        ListenerHandler::start($worker);

        // 依赖注入：扫描所有 @Depend 类并注册到容器
        DependHandler::start($worker);

        // 通知插件层组件已就绪
        Event::dispatch(BootAttribute::class, []);

        // 扫描阶段创建的反射对象仅扫描时需要，Worker 启动后释放
        ReflectionManager::clearReflectionCache();
    }
}
