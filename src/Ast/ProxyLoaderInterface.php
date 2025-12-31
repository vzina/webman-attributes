<?php
/**
 * ProxyLoaderInterface.php
 * PHP version 7
 *
 * @package webman-demo
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace vendor\vzina\attributes\src\Ast;

use Vzina\Attributes\Scan\Options;

interface ProxyLoaderInterface
{
    public function __invoke(Options $option): array;
}