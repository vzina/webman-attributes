<?php
/**
 * W3CTracer — W3C Trace Context 默认追踪器。
 *
 * 生成符合规范的 32-hex traceId / 16-hex spanId，
 * 通过 support\Context 传播，支持 traceparent 头注入/提取。
 * 支持 Span::setAttribute() 写入自定义数据。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Trace;

use Closure;
use Vzina\Attributes\Attribute\TraceContext;

class W3CTracer implements TracerContract
{
    private const CONTEXT_KEY = 'trace.ctx';
    private const ATTRS_KEY   = 'trace.attrs';

    // ---- trace / span lifecycle ----

    public function trace(string $name, Closure $next): mixed
    {
        $parent = $this->currentContext();

        $ctx = new TraceContext(
            traceId:      $parent?->traceId ?? $this->generateTraceId(),
            spanId:       $this->generateSpanId(),
            parentSpanId: $parent?->spanId,
            isSampled:    $parent?->isSampled ?? true,
        );

        $this->storeContext($ctx);
        $this->initAttrs($ctx->spanId);

        $start = microtime(true);
        try {
            $result = $next();
            $this->log($ctx, $name, 'ok', round((microtime(true) - $start) * 1000, 2));
            return $result;
        } catch (\Throwable $e) {
            $this->log($ctx, $name, 'error',
                round((microtime(true) - $start) * 1000, 2),
                $e->getMessage()
            );
            throw $e;
        } finally {
            $this->restoreContext($parent);
        }
    }

    public function currentContext(): ?TraceContext
    {
        if (! class_exists(\support\Context::class)) {
            return null;
        }
        try {
            return \support\Context::get(self::CONTEXT_KEY);
        } catch (\Throwable $e) {
            error_log('[W3CTracer] Context::get failed: ' . $e->getMessage());
            return null;
        }
    }

    // ---- W3C traceparent 传播 ----

    /** 从 traceparent header 应用上游追踪上下文 */
    public function applyTraceparent(?string $traceparent): void
    {
        if ($traceparent === null || $traceparent === '') {
            return;
        }

        $ctx = TraceContext::fromTraceparent($traceparent);
        if ($ctx !== null) {
            $this->storeContext($ctx);
        }
    }

    /** 获取当前 span 的 traceparent header，用于向下游传播 */
    public function getTraceparent(): ?string
    {
        $ctx = $this->currentContext();
        return $ctx?->toTraceparent();
    }

    // ---- 自定义属性 ----

    public function setAttribute(string $key, mixed $value): void
    {
        $ctx = $this->currentContext();
        if (! $ctx || ! class_exists(\support\Context::class)) {
            return;
        }
        try {
            $attrs = \support\Context::get(self::ATTRS_KEY, []);
            $attrs[$ctx->spanId][$key] = $value;
            \support\Context::set(self::ATTRS_KEY, $attrs);
        } catch (\Throwable $e) {
            error_log('[W3CTracer] setAttribute failed: ' . $e->getMessage());
        }
    }

    // ---- internal ----

    private function initAttrs(string $spanId): void
    {
        if (class_exists(\support\Context::class)) {
            try {
                $attrs = \support\Context::get(self::ATTRS_KEY, []);
                $attrs[$spanId] = [];
                \support\Context::set(self::ATTRS_KEY, $attrs);
            } catch (\Throwable $e) {
                error_log('[W3CTracer] initAttrs failed: ' . $e->getMessage());
            }
        }
    }

    private function storeContext(TraceContext $ctx): void
    {
        if (class_exists(\support\Context::class)) {
            try {
                \support\Context::set(self::CONTEXT_KEY, $ctx);
            } catch (\Throwable $e) {
                error_log('[W3CTracer] storeContext failed: ' . $e->getMessage());
            }
        }
    }

    private function restoreContext(?TraceContext $parent): void
    {
        if (class_exists(\support\Context::class)) {
            try {
                \support\Context::set(self::CONTEXT_KEY, $parent);
            } catch (\Throwable $e) {
                error_log('[W3CTracer] restoreContext failed: ' . $e->getMessage());
            }
        }
    }

    private function log(TraceContext $ctx, string $name, string $status, float $ms, ?string $error = null): void
    {
        $spanData = [
            'trace_id'    => $ctx->traceId,
            'span_id'     => $ctx->spanId,
            'parent_id'   => $ctx->parentSpanId,
            'name'        => $name,
            'status'      => $status,
            'duration_ms' => $ms,
            'start'       => microtime(true) - $ms / 1000,
        ];
        if ($error !== null) {
            $spanData['error'] = $error;
        }
        if (class_exists(\support\Context::class)) {
            $attrs = \support\Context::get(self::ATTRS_KEY, [])[$ctx->spanId] ?? [];
            if ($attrs) {
                $spanData['attrs'] = $attrs;
            }
        }

        if (class_exists(\support\Log::class)) {
            try {
                \support\Log::channel('default')->info("[trace] {$name}", $spanData);
            } catch (\Throwable $e) {
                error_log('[W3CTracer] log failed: ' . $e->getMessage());
            }
        }
    }

    /** traceId: 16 字节 → 32 hex（W3C 规范） */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** spanId: 8 字节 → 16 hex（W3C 规范） */
    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}