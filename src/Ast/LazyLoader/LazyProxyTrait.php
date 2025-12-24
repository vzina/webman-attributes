<?php
/**
 * LazyProxyTrait.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

use Vzina\Attributes\AttributeLoader;

trait LazyProxyTrait
{
    public function __construct()
    {
        $vars = get_object_vars($this);
        foreach (array_keys($vars) as $var) {
            unset($this->{$var});
        }
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
        $vars = get_object_vars($this);
        foreach (array_keys($vars) as $var) {
            unset($this->{$var});
        }
    }

    /**
     * Return The Proxy Target.
     * @return mixed
     */
    public function getInstance()
    {
        return AttributeLoader::getContainer()->get(self::PROXY_TARGET);
    }
}