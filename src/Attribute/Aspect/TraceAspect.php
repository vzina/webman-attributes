<?php
/**
 * TraceAspect — #[Trace] 切面。
 *
 * 从容器解析 TracerContract，未绑定时回退 W3CTracer。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Trace\TracerContract;
use Vzina\Attributes\Trace\W3CTracer;
use Vzina\Attributes\Attribute\Annotation\Trace;
use Vzina\Attributes\Attribute\AspectInterface;
use Vzina\Attributes\Attribute\DebugLog;

class TraceAspect implements AspectInterface
{
    use DebugLog;

    public array $attributes = [Trace::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Trace|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Trace::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        $name = $attr->spanName ?? $point->className . '::' . $point->methodName;

        // 采样率控制
        if ($attr->sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) > $attr->sampleRate) {
            return $point->process();
        }

        return $this->resolveTracer()->trace($name, fn() => $point->process());
    }

    protected function resolveTracer(): TracerContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $tracer = \support\Container::get(TracerContract::class);
            } catch (\Throwable $e) {
                $this->log('[TraceAspect] Container::get(TracerContract) failed: ' . $e->getMessage());
                $tracer = null;
            }
            if ($tracer instanceof TracerContract) {
                return $tracer;
            }
        }
        return new W3CTracer();
    }
}
