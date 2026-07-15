<?php
/**
 * AspectInterface.php
 * @version 8.1
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
    public function process(ProceedingJoinPoint $point);
}