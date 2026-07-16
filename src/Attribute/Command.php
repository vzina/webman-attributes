<?php
/**
 * Command — CLI 命令注解。
 *
 * 标记类为 CLI 命令，通过 CommandHandler 自动注册到 webman 控制台。
 * 被标记的类需继承 Symfony\Component\Console\Command\Command。
 *
 * @param string  $name        命令名称，如 'app:greet'
 * @param ?string $description 命令描述
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Command extends AbstractAttribute
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {
    }
}
