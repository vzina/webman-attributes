<?php
/**
 * TraceContext — 分布式追踪上下文。
 *
 * 包含 W3C Trace Context 标准的 traceId、spanId 和 parentSpanId。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

class TraceContext
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
    ) {
    }
}
