<?php
/**
 * Composer.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
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
        $file = self::getLoader()->findFile($className);
        return $file ? file_get_contents($file) : '';
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