<?php
/**
 * InjectAspect.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Annotation\Inject;
use Vzina\Attributes\Attribute\AspectInterface;

class InjectAspect implements AspectInterface
{
    public array $attributes = [
        Inject::class,
    ];

    public function process(ProceedingJoinPoint $point)
    {
        return $point->process();
    }
}