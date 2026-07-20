<?php
/**
 * TracerContract — 追踪器接口。
 *
 * 实现此接口即可替换默认 W3C 追踪器，或扩展为 OpenTelemetry、Jaeger 等。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Trace;

use Vzina\Attributes\Attribute\TraceContext;

interface TracerContract
{
    /** 在 span 内执行 $next，返回其结果 */
    public function trace(string $name, \Closure $next): mixed;

    /** 当前 span 上下文，用于跨进程/跨请求传播 */
    public function currentContext(): ?\Vzina\Attributes\Attribute\TraceContext;

    /** 向当前 span 写入自定义属性 */
    public function setAttribute(string $key, mixed $value): void;

    /** 从 traceparent header 应用上游追踪上下文（W3C Trace Context） */
    public function applyTraceparent(?string $traceparent): void;

    /** 获取当前 span 的 traceparent header 值，用于向下游传播 */
    public function getTraceparent(): ?string;
}
