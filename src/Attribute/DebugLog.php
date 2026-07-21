<?php
/**
 * DebugLog — 调试日志辅助 trait。
 *
 * 统一 error_log 调用，通过 attribute.php debug.log_errors 开关控制。
 * 切面/处理器通过 use DebugLog 获得 log() 和 logConfig()。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

trait DebugLog
{
    private static ?bool $logEnabled = null;

    /** 输出调试日志（受 debug.log_errors 开关控制） */
    protected function log(string $message, array $context = []): void
    {
        if (! self::isLogEnabled()) {
            return;
        }
        if ($context) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        error_log($message);
    }

    private static function isLogEnabled(): bool
    {
        return self::$logEnabled ??= (bool) (config('plugin.vzina.attributes.attribute.debug.log_errors', true));
    }
}