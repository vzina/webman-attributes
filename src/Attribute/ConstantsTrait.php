<?php
/**
 * ConstantsTrait.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Vzina\Attributes\Collector\ConstantsCollector;

/**
 * @method static string getMessage(mixed $code, array $translate = null)
 */
trait ConstantsTrait
{
    public static function __callStatic(string $name, array $arguments): ?string
    {
        if (! str_starts_with($name, 'get')) {
            return null;
        }

        $name = strtolower(substr($name, 3));

        return ConstantsCollector::getTransValue(static::class, $name, $arguments);
    }
}