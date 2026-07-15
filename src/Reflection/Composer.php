<?php
/**
 * Composer.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use Composer\Autoload\ClassLoader;
use RuntimeException;

class Composer
{
    /**
     * @var ClassLoader|null
     */
    private static ?ClassLoader $classLoader;

    public static function getLoader(): ClassLoader
    {
        return static::$classLoader ??= static::findLoader();
    }

    public static function setLoader(ClassLoader $classLoader): ClassLoader
    {
        return static::$classLoader = $classLoader;
    }

    public static function getCodeByClassName(string $className): string
    {
        $file = self::getPathByClassName($className);
        return $file ? file_get_contents($file) : '';
    }

    public static function getPathByClassName(string $className): string
    {
        return (string)self::getLoader()->findFile($className);
    }

    public static function getMd5ByClassName(string $className): string
    {
        return md5(self::getCodeByClassName($className));
    }

    private static function findLoader(): ClassLoader
    {
        $composerClass = '';
        foreach (get_declared_classes() as $declaredClass) {
            if (str_starts_with($declaredClass, 'ComposerAutoloaderInit')
                && method_exists($declaredClass, 'getLoader')
            ) {
                $composerClass = $declaredClass;
                break;
            }
        }
        if (! $composerClass) {
            throw new RuntimeException('Composer loader not found.');
        }

        return $composerClass::getLoader();
    }
}