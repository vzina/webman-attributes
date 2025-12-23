<?php
/**
 * AspectInterface.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\ProceedingJoinPoint;

interface AspectInterface
{
    public function process(ProceedingJoinPoint $proceedingJoinPoint);
}