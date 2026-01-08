<?php
/**
 * ServiceInjectorInterface.php
 * PHP version 7
 *
 * @package webman-demo
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

interface ServiceInjectorInterface
{
    public static function inject(string $key, $value);
}