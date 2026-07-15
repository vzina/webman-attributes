<?php
/**
 * LazyProxyTrait.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

use support\Container;

trait LazyProxyTrait
{
    /**
     * Cached resolved instance to avoid repeated Container::get() calls.
     */
    private ?object $__instance = null;

    public function __construct()
    {
        // Lazy proxy — no initialization needed; instance resolved on first access.
    }

    public function __call($method, $arguments)
    {
        $obj = $this->getInstance();
        return call_user_func([$obj, $method], ...$arguments);
    }

    public function __get($name)
    {
        return $this->getInstance()->{$name};
    }

    public function __set($name, $value)
    {
        $this->getInstance()->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->getInstance()->{$name});
    }

    public function __unset($name)
    {
        unset($this->getInstance()->{$name});
    }

    public function __wakeup()
    {
        $this->__instance = null;
    }

    /**
     * Return The Proxy Target.
     * Instance is cached after first resolution.
     * @return mixed
     */
    public function getInstance()
    {
        return $this->__instance ??= Container::get(self::PROXY_TARGET);
    }
}