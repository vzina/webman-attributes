<?php
/**
 * W3CTracer — 默认 W3C Trace Context 追踪器。
 *
 * 生成 traceId/spanId，通过 support\Context 传播，输出到日志。
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

    public function trace(string $name, Closure $next): mixed
    {
        $parent = $this->currentContext();

        $ctx = new TraceContext(
            traceId:      $parent?->traceId ?? $this->generateId(),
            spanId:       $this->generateId(),
            parentSpanId: $parent?->spanId,
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

        // 日志
        if (class_exists(\support\Log::class)) {
            try {
                \support\Log::channel('default')->info("[trace] {$name}", $spanData);
            } catch (\Throwable $e) {
                error_log('[W3CTracer] log failed: ' . $e->getMessage());
            }
        }
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
