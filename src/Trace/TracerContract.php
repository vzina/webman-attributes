<?php
/**
 * TracerContract — 追踪器接口。
 *
 * 实现此接口即可替换默认 W3C 追踪器，或扩展为 OpenTelemetry、Jaeger 等。
 * 提供 span 生命周期管理、属性写入、时间线事件、异常记录和 W3C traceparent 传播。
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

    /** 向当前 span 添加时间线事件（如 cache.hit、db.query 等） */
    public function addEvent(string $name, array $attrs = []): void;

    /** 向当前 span 记录异常 */
    public function recordException(\Throwable $e): void;

    /** 从 traceparent header 应用上游追踪上下文（W3C Trace Context） */
    public function applyTraceparent(?string $traceparent): void;

    /** 获取当前 span 的 traceparent header 值，用于向下游传播 */
    public function getTraceparent(): ?string;
}
