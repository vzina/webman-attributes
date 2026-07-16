<?php
/**
 * TraceAspect — #[Trace] 切面。
 *
 * #[Trace(tracer: $fn)] → 逐方法 callable 覆盖。
 * 否则 → 从容器解析 TracerContract，未绑定时回退 W3CTracer。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Trace\TracerContract;
use Vzina\Attributes\Trace\W3CTracer;

class TraceAspect implements AspectInterface
{
    public array $attributes = [Trace::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Trace|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Trace::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $name = $attr->spanName ?? $point->className . '::' . $point->methodName;

        // 逐方法 callable 覆盖
        if ($attr->tracer && is_callable($attr->tracer)) {
            return ($attr->tracer)($name, $point, fn() => $point->process());
        }

        // 容器解析 TracerContract，未绑定则 W3C
        return $this->resolveTracer()->trace($name, fn() => $point->process());
    }

    private function resolveTracer(): TracerContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $tracer = \support\Container::get(TracerContract::class);
            } catch (\Throwable) {
                $tracer = null;
            }
            if ($tracer instanceof TracerContract) {
                return $tracer;
            }
        }
        return new W3CTracer();
    }
}
