<?php
/**
 * ProxyPipeline.php
 *
 * Named pipeline class for aspect weaving.
 * Avoids creating anonymous classes on every proxied method call,
 * reducing GC pressure in long-running webman workers.
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Closure;
use Illuminate\Pipeline\Pipeline;
use InvalidArgumentException;
use support\Container;

class ProxyPipeline extends Pipeline
{
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (! ($passable instanceof ProceedingJoinPoint)) {
                    throw new InvalidArgumentException('$passable must be a ProceedingJoinPoint object.');
                }

                if (is_string($pipe) && class_exists($pipe)) {
                    $pipe = Container::get($pipe);
                }
                $passable->pipe = $stack;

                return method_exists($pipe, $this->method)
                    ? $pipe->{$this->method}($passable)
                    : $pipe($passable);
            };
        };
    }
}
