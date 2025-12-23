<?php
/**
 * AttributeLoader.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use support\App;
use support\Container;
use support\Log;
use Throwable;
use Vzina\Attributes\Ast\AstPropertyVisitor;
use Vzina\Attributes\Ast\AstProxyCallVisitor;
use Vzina\Attributes\Ast\AstVisitorManager;
use Vzina\Attributes\Ast\LazyLoader\LazyLoader;
use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Collector\PropertyManagerCollector;
use Vzina\Attributes\Reflection\Composer;
use Vzina\Attributes\Reflection\ReflectionManager;
use Vzina\Attributes\Scan\Options;
use Vzina\Attributes\Scan\Scanner;
use Webman\Config;

class AttributeLoader
{
    public static function init(): void
    {
        if (empty(Config::get())) {
            if (! method_exists(App::class, 'loadAllConfig')) {
                return;
            }
            App::loadAllConfig(['route']);
        }

        $option = static::initOptions();
        if ($option === null) {
            return;
        }

        AstVisitorManager::exists(AstPropertyVisitor::class) or AstVisitorManager::insert(AstPropertyVisitor::class);
        AstVisitorManager::exists(AstProxyCallVisitor::class) or AstVisitorManager::insert(AstProxyCallVisitor::class);
        self::registerProperties();

        Composer::getLoader()->addClassMap(Scanner::scan($option));
        LazyLoader::bootstrap($option->proxyPath(), $option->lazyLoader());
    }

    /**
     * @return \Webman\Container
     */
    public static function getContainer()
    {
        return Container::instance();
    }

    /**
     * @return \Monolog\Logger
     */
    public static function logger()
    {
        return Log::channel();
    }

    protected static function initOptions()
    {
        $config = (array)config('plugin.vzina.attributes');
        if (empty($config['app']) || empty($config['app']['enable'])) {
            return null;
        }

        foreach (config('plugin', []) as $firm => $projects) {
            if ($firm !== 'vzina' && isset($projects['attribute'])) {
                $config['attribute'] = array_merge_recursive($config['attribute'] ?? [], (array)$projects['attribute']);
            }
        }

        return Options::init($config['app'] + $config['attribute']);
    }

    protected static function registerProperties(): void
    {
        PropertyManagerCollector::register(
            Inject::class,
            static function ($object, $currentClassName, $targetClassName, $property, $attribute) {
                try {
                    $container = self::getContainer();
                    $reflectionProperty = ReflectionManager::reflectProperty($currentClassName, $property);
                    if ($instance = $container->get($attribute->value)) {
                        $reflectionProperty->setValue($object, $instance);
                    } elseif ($attribute->required) {
                        throw new RuntimeException("No entry or class found for '{$attribute->value}'");
                    }
                } catch (Throwable $throwable) {
                    if ($attribute->required) {
                        throw $throwable;
                    }
                }
            }
        );
    }
}