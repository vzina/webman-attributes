<?php
/**
 * Depend — 容器依赖注册注解。
 *
 * 标记类需要注册到 webman 容器，支持自定义 id、优先级、构造参数和单例模式。
 * DependHandler 在 Worker 启动时收集并注入容器。
 */
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
        public bool $singleton = true,
    ) {
    }
}