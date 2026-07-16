<?php
/**
 * ValidateException — 校验异常。
 *
 * 携带字段级错误信息，webman 异常处理器可捕获并渲染 JSON 错误响应。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Exception;

use RuntimeException;

class ValidateException extends RuntimeException
{
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Validation failed', 422);
    }
}
