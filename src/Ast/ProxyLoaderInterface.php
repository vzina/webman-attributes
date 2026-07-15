<?php
/**
 * ProxyLoaderInterface.php
 * @version 8.1
 *
 * @package webman-demo
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Scan\Options;

interface ProxyLoaderInterface
{
    public function __invoke(Options $option, array &$classMap): void;
}