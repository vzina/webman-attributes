<?php
/**
 * Span — 当前追踪 span 操作入口。
 *
 * 在 #[Trace] 标记的方法内部调用，向当前 span 写入自定义属性。
 *
 *   Span::setAttribute('order_id', $order->id);
 *   Span::setAttribute('amount', 99.9);
 */
declare (strict_types=1);

namespace Vzina\Attributes\Trace;

class Span
{
    /**
     * 向当前 span 写入自定义属性，span 结束时随日志输出。
     * 无活跃 span 时静默忽略。
     */
    public static function setAttribute(string $key, mixed $value): void
    {
        $tracer = self::resolve();
        if ($tracer !== null) {
            $tracer->setAttribute($key, $value);
        }
    }

    private static function resolve(): ?TracerContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $tracer = \support\Container::get(TracerContract::class);
            } catch (\Throwable) {
                return null;
            }
            if ($tracer instanceof TracerContract) {
                return $tracer;
            }
        }
        return null;
    }
}
