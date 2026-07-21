<?php
/**
 * Cacheable — 方法缓存注解。
 *
 * 拦截方法调用，缓存返回值。支持参数化 key、防击穿锁、协程异步刷新。
 *
 * @param ?string $prefix       缓存 key 前缀
 * @param ?string $value        参数模板，如 '#{params.id}'，null 时 md5(参数)
 * @param ?int    $ttl          缓存秒数，null 时取 config('cache.ttl')
 * @param int     $offset       随机偏移秒数（防缓存雪崩）
 * @param int     $aheadSeconds 提前刷新秒数
 * @param int     $lockSeconds  Redis 刷新锁 TTL
 * @param ?string $group        缓存 store，null 时取 config('cache.default')
 * @param bool    $collect      收集 key 到 Redis SET（需 redis 驱动）
 * @param bool    $evict        清除模式：删缓存后执行方法
 * @param bool    $put          仅写入模式：跳过缓存读取直接执行并缓存
 * @param array   $tags         缓存标签组，用于批量清除关联缓存（需 tag 驱动的 cache store）
 * @param bool    $cacheNull    缓存 null 结果，防缓存穿透，默认关闭
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Cacheable extends AbstractAttribute
{
    public function __construct(
        public ?string $prefix = null,
        public ?string $value = null,
        public ?int $ttl = null,
        public int $offset = 0,
        public int $aheadSeconds = 0,
        public int $lockSeconds = 10,
        public ?string $group = null,
        public bool $collect = false,
        public bool $evict = false,
        public bool $put = false,
        public array $tags = [],
        public bool $cacheNull = false,
    ) {
    }
}