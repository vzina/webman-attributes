<?php
/**
 * CommandHandler — CLI 命令自动注册器。
 *
 * 从 AttributeCollector 读取所有 #[Command] 注解的类，
 * 与 command.php 中静态定义的命令合并后返回。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Collector\AttributeCollector;

class CommandHandler
{
    /**
     * 合并静态命令与 #[Command] 自动发现的命令。
     *
     * @param string[] $commands 静态定义的命令类名
     * @return string[]
     */
    public static function init(array $commands = []): array
    {
        $discovered = AttributeCollector::getClassesByAttribute(Command::class);

        foreach (array_keys($discovered) as $className) {
            if (! in_array($className, $commands, true)) {
                $commands[] = $className;
            }
        }

        return $commands;
    }
}
