<?php
/**
 * AspectInterface.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\ProceedingJoinPoint;

/**
 * @property array|null $classes
 * @property array|null $attributes
 * @property int|null $priority
 */
interface AspectInterface
{
    public function process(ProceedingJoinPoint $proceedingJoinPoint);
}