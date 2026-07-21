<?php
/**
 * Validate — 请求参数校验注解。
 *
 * 拦截方法调用，在校验通过后才执行方法。失败时抛 ValidateException。
 *
 * @param array   $rules        校验规则，键为字段名，值为规则字符串（如 'required|min:3'）
 * @param array   $messages     自定义错误消息，键为 "字段.规则"
 * @param ?string $requestParam Request 参数名，null 时自动发现方法中第一个 Request 类型参数
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Validate extends AbstractAttribute
{
    public function __construct(
        public array $rules = [],
        public array $messages = [],
        public ?string $requestParam = null,
    ) {
    }
}
