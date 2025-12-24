<?php
/**
 * Foo.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\attributes;

use app\controller\FooController;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Aspect;
use Vzina\Attributes\Attribute\AspectInterface;

#[Aspect]
class Foo implements AspectInterface
{
    public array $classes = [
        FooController::class . '::models',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        var_dump(__METHOD__);
        return $proceedingJoinPoint->process();
    }
}