<?php

declare (strict_types=1);

namespace Vzina\Attributes;

use Vzina\Attributes\Attribute\BootAttribute;
use Vzina\Attributes\Attribute\DependHandler;
use Vzina\Attributes\Attribute\ListenerHandler;
use Webman\Bootstrap;
use Webman\Event\Event;
use Workerman\Worker;

class AttributeBootstrap implements Bootstrap
{
    protected static array $boots = [
        ListenerHandler::class, // 不可调整位置
        DependHandler::class,
    ];

    public static function start(?Worker $worker)
    {
        foreach (static::$boots as $boot) {
            $boot::start($worker);
        }

        // 触发组件加载完成事件
        Event::dispatch(BootAttribute::class, []);
    }
}