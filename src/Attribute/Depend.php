<?php

declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Depend extends AbstractAttribute
{
    /**
     * @param ?string $id    容器中的注册名，默认使用类名
     * @param int     $priority 注册优先级（同名取高优先）
     * @param array   $params   构造函数参数覆盖，key=参数名, value=参数值
     * @param bool    $singleton 单例模式，true 时容器缓存实例只创建一次
     */
    public function __construct(
        public ?string $id = null,
        public int $priority = 0,
        public array $params = [],
        public bool $singleton = false,
    ) {
    }
}