<?php
/**
 * Here is your custom functions.
 */

use support\Log;

if ( ! function_exists('log_trace')) {
    function log_trace(string $message, $context = [], $level = 'info'): void
    {
        Log::channel('trace')->log($level, $message, $context);
    }
}

