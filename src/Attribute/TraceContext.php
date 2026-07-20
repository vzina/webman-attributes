<?php
/**
 * TraceContext — W3C Trace Context 传播上下文。
 *
 * 符合 W3C Trace Context Level 2 规范：
 *   traceparent: {version}-{traceId}-{spanId}-{traceFlags}
 *   traceId: 32 hex (16 bytes), spanId: 16 hex (8 bytes)
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

class TraceContext
{
    /** 最低有效位 = 1 表示 sampled */
    public readonly bool $isSampled;

    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
        ?bool $isSampled = null,
    ) {
        $this->isSampled = $isSampled ?? true;
    }

    /** 序列化为 W3C traceparent header 值 */
    public function toTraceparent(): string
    {
        return sprintf('00-%s-%s-%02x', $this->traceId, $this->spanId, $this->isSampled ? 1 : 0);
    }

    /** 从 W3C traceparent header 值解析 */
    public static function fromTraceparent(string $traceparent): ?self
    {
        $parts = explode('-', $traceparent);
        if (count($parts) !== 4) {
            return null;
        }
        [$version, $traceId, $spanId, $traceFlags] = $parts;

        // 版本校验：仅支持 00
        if ($version !== '00') {
            return null;
        }

        // 格式校验
        if (strlen($traceId) !== 32 || strlen($spanId) !== 16 || strlen($traceFlags) !== 2) {
            return null;
        }

        // 全零 ID 无效
        if ($traceId === str_repeat('0', 32) || $spanId === str_repeat('0', 16)) {
            return null;
        }

        $flags = (int) hexdec($traceFlags);

        return new self(
            traceId:      $traceId,
            spanId:       $spanId,
            parentSpanId: null,
            isSampled:    ($flags & 0x01) === 1,
        );
    }
}
