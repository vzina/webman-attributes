<?php
/**
 * CacheableAspect — 方法级缓存切面。
 *
 * 拦截 @Cacheable 方法：命中缓存直接返回，未命中执行方法并缓存结果。
 * 支持 Redis 驱动的防击穿锁 + 协程异步刷新过期缓存。
 *
 * 缓存 key 格式: {prefix}#{paramKey}.{subKey}
 * 缓存值结构: ['expired_time' => timestamp, 'data' => mixed]
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use support\Cache;
use support\Redis;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Webman\Config;
use Workerman\Coroutine;
use Vzina\Attributes\Attribute\Annotation\Cacheable;
use Vzina\Attributes\Attribute\AspectInterface;

class CacheableAspect implements AspectInterface
{
    public array $attributes = [Cacheable::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Cacheable|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Cacheable::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $arguments = $point->arguments['keys'];
        $prefix    = Config::get('cache.prefix', '') . $attr->prefix;
        $cacheKey  = $this->buildKey($prefix, $arguments, $attr->value);
        $group     = $attr->group ?? Config::get('cache.default');
        $storeCfg  = Config::get("cache.stores.{$group}", []);

        $redis = (($storeCfg['driver'] ?? '') === 'redis')
            ? Redis::connection($storeCfg['connection'] ?? 'default') : null;

        $collectKey = $attr->collect ? $prefix . 'MEMBERS' : null;
        $tags       = $attr->tags;
        $cache      = $tags ? Cache::tags($tags) : Cache::store($group);

        // 缓存清除模式
        if ($attr->evict) {
            if ($tags) {
                $cache->flush();
            } elseif ($collectKey && $redis) {
                $cache->deleteMultiple((array) $redis->sMembers($collectKey));
                $redis->del($collectKey);
            } else {
                $cache->delete($cacheKey);
            }
            return $point->process();
        }

        $now = time();
        $ttl = ($attr->ttl ?? Config::get("cache.ttl", 3600))
             + ($attr->offset > 0 ? random_int(0, $attr->offset) : 0);

        // 缓存刷新回调
        $refresh = static function () use ($point, $cache, $attr, $cacheKey, $now, $ttl, $collectKey, $redis) {
            $result = $point->process();
            $cache->set($cacheKey, [
                'expired_time' => $now + $ttl - $attr->aheadSeconds,
                'data'         => $result,
            ], $ttl);
            if ($collectKey && $redis) { $redis->sAdd($collectKey, $cacheKey); }
            return $result;
        };

        // 非强制写入模式 → 先查缓存
        if (! $attr->put) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false && isset($cached['expired_time'], $cached['data'])) {
                // 过期但获取到锁 → 协程异步刷新；协程不可用时同步刷新
                if ($now > $cached['expired_time'] &&
                    (! $redis || $redis->set($cacheKey . '.lock', '1', ['NX', 'EX' => $attr->lockSeconds]))
                ) {
                    try {
                        Coroutine::create($refresh);
                    } catch (\Throwable $e) {
                        // 协程创建失败（环境不支持/资源不足），退化为同步刷新
                        error_log('[CacheableAspect] Coroutine::create failed, falling back to sync refresh: ' . $e->getMessage());
                        return $refresh();
                    }
                }
                if ($attr->cacheNull && $cached['data'] === null) { return null; }
                return $cached['data'];
            }
        }

        return $refresh();
    }

    /** 根据参数模板构建缓存 key */
    private function buildKey(string $prefix, array $arguments, ?string $template): string
    {
        if ($template === null) {
            return $prefix . md5(serialize($arguments));
        }

        if (! preg_match_all('/#\{[\w.]+}/', $template, $matches)) {
            return $prefix . $template;
        }

        foreach ($matches[0] as $placeholder) {
            [$key, $sub] = explode('.', str_replace(['#{', '}'], '', $placeholder)) + [null, null];
            $val = Arr::get($arguments, $key);

            if ($sub) {
                $val = match (true) {
                    is_array($val)  => (string) Arr::get($val, $sub),
                    is_object($val) => property_exists($val, $sub) ? (string) $val->$sub
                                     : (method_exists($val, '__toString') ? (string) $val : spl_object_hash($val)),
                    default         => (string) $val,
                };
            }

            $template = Str::replaceFirst($placeholder, (string) $val, $template);
        }

        return $prefix . $template;
    }
}
