<?php
/**
 * ProcessHandler.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Handler;

use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Attribute\Annotation\Process;

class ProcessHandler
{
    public static function init(array $process = []): array
    {
        $attributes = AttributeCollector::getClassesByAttribute(Process::class);
        foreach ($attributes as $class => $attribute) {
            /** @var Process $attribute */
            $processName = $attribute->name ?: str_replace('\\', '-', strtolower($class));
            $process[$processName] = ['handler' => $class, 'count' => $attribute->count] + $attribute->options;
        }

        return $process;
    }
}