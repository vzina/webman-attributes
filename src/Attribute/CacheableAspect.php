<?php
/**
 * CacheableAspect.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use support\Cache;
use support\Redis;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Webman\Config;
use Workerman\Coroutine;

class CacheableAspect implements AspectInterface
{
    public array $attributes = [
        Cacheable::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var Cacheable|null $attribute */
        $attribute = $proceedingJoinPoint->getAnnotationMetadata()->method[Cacheable::class] ?? null;
        if ($attribute === null) {
            return $proceedingJoinPoint->process();
        }

        $arguments = $proceedingJoinPoint->arguments['keys'];
        $prefix = Config::get('cache.prefix', '') . $attribute->prefix;
        $cacheKey = $this->getFormattedKey($prefix, $arguments, $attribute->value);
        $group = $attribute->group ?? Config::get('cache.default');
        $config = Config::get("cache.stores.{$group}", []);

        $redis = null;
        if (isset($config['driver']) && $config['driver'] === 'redis') {
            $redis = Redis::connection($config['connection'] ?? 'default');
        }

        $collectKey = $attribute->collect ? $prefix . '.MEMBERS' : null;
        $cache = Cache::store($group);

        if ($attribute->evict) { // 缓存清除
            if ($collectKey && $redis) {
                $cache->deleteMultiple((array)$redis->sMembers($collectKey));
                $redis->del($collectKey);
            } else {
                $cache->delete($cacheKey);
            }

            return $proceedingJoinPoint->process();
        }

        $now = time();
        $ttl = ($attribute->ttl ?? Config::get("cache.ttl", 3600)) + $this->getRandomOffset($attribute->offset);

        $callback = static function () use (
            $proceedingJoinPoint, $cache, $attribute, $cacheKey, $now, $ttl, $collectKey, $redis
        ) {
            $result = $proceedingJoinPoint->process();
            $cache->set($cacheKey, [
                'expired_time' => $now + $ttl - $attribute->aheadSeconds,
                'data' => $result,
            ], $ttl);

            if ($collectKey && $redis) {
                $redis->sAdd($collectKey, $cacheKey);
            }

            return $result;
        };

        if (! $attribute->put) {
            $result = $cache->get($cacheKey);
            if ($result !== false && isset($result['expired_time'], $result['data'])) {
                if ($now > $result['expired_time'] &&
                    // 仅支持redis驱动加锁更新
                    ($redis === null || $redis->set($cacheKey . '.lock', '1', ['NX', 'EX' => $attribute->lockSeconds]))
                ) {
                    Coroutine::create($callback());
                }

                return $result['data'];
            }
        }

        return $callback();
    }

    protected function getFormattedKey(string $prefix, array $arguments, ?string $value = null): string
    {
        if ($value !== null) {
            if (preg_match_all('/#\{[\w.]+}/', $value, $matches)) {
                foreach ($matches[0] as $search) {
                    [$key, $subKey] = explode('.', str_replace(['#{', '}'], '', $search)) + [null, null];
                    $val = Arr::get($arguments, $key);
                    if ($subKey) {
                        if (is_array($val)) {
                            $val = (string)Arr::get($val, $subKey);
                        } elseif (is_object($val)) {
                            if (property_exists($val, $subKey)) {
                                $val = (string)$val->$subKey;
                            } elseif (! method_exists($val, '__toString')) {
                                $val = spl_object_hash($val);
                            }
                        }
                    }
                    $value = Str::replaceFirst($search, (string)$val, $value);
                }
            }
        } else {
            $value = md5(serialize($arguments));
        }

        return $prefix . '.' . $value;
    }

    protected function getRandomOffset(int $offset): int
    {
        return $offset > 0 ? random_int(0, $offset) : 0;
    }
}