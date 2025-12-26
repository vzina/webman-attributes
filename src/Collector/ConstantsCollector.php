<?php
/**
 * ConstantsCollector.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Collector;

use BackedEnum;
use Symfony\Component\Translation\Translator;
use UnitEnum;
use Webman\Config;

class ConstantsCollector extends MetadataCollector
{
    protected static array $container = [];

    public static function getValue($className, $code, $key): string
    {
        return static::$container[$className][$code][$key] ?? '';
    }

    public static function getTransValue(string $className, string $key, array $arguments): ?string
    {
        if (empty($arguments)) {
            return null;
        }

        $code = array_shift($arguments);
        if ($code instanceof BackedEnum) {
            $code = $code->value;
        } elseif ($code instanceof UnitEnum) {
            $code = $code->name;
        }

        $message = static::getValue($className, $code, $key);
        $result = static::translate($message, $arguments);

        if ($result && $result !== $message) {
            return $result;
        }

        if (! empty($arguments)) {
            return sprintf($message, ...(array)$arguments[0]);
        }

        return $message;
    }

    protected static function translate($key, $arguments): ?string
    {
        if (! class_exists(Translator::class) || ! is_dir((string)Config::get('translation.path'))) {
            return null;
        }

        $replace = array_shift($arguments) ?? [];
        if (! is_array($replace)) {
            return null;
        }

        return trans($key, $replace);
    }
}