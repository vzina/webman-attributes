<?php
/**
 * AttributeLoader.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use support\Container;
use Vzina\Attributes\Ast\AstVisitorManager;
use Vzina\Attributes\Attribute\PropertyHandlerInterface;
use Vzina\Attributes\Collector\PropertyManagerCollector;
use Vzina\Attributes\Reflection\Composer;
use Vzina\Attributes\Scan\Options;
use Vzina\Attributes\Scan\Scanner;
use Webman\Util;

class AttributeLoader
{
    public static function init(): void
    {
        // 初始化配置和Composer加载器
        $option = static::initOptions();
        if ($option === null) return;

        $loader = Composer::getLoader();
        if (! empty($option->classMap())) {
            $loader->addClassMap($option->classMap());
        }

        // 注册AST访问器
        foreach ($option->astVisitors() as $visitor) {
            AstVisitorManager::exists($visitor) or AstVisitorManager::insert($visitor);
        }

        // 注册属性注入逻辑
        foreach ($option->propertyHandlers() as $propertyHandler) {
            if (class_exists($propertyHandler)
                && ($instance = new $propertyHandler())
                && $instance instanceof PropertyHandlerInterface
            ) {
                PropertyManagerCollector::register($instance->attribute(), [$instance, 'process']);
            }
        }

        $loader->addClassMap(Scanner::scan($option));
    }

    /**
     * @return \Webman\Container
     */
    public static function getContainer()
    {
        return Container::instance();
    }

    protected static function initOptions(): ?Options
    {
        // 加载基础配置
        $allConfig = static::loadFromDir(config_path(), ['attribute']);
        if (empty($allConfig['plugin']['vzina']['attributes']['attribute']['autoload'])) {
            return null;
        }

        // 加载插件配置
        $pluginDir = base_path() . '/plugin';
        foreach (Util::scanDir($pluginDir, false) as $name) {
            $pluginConfigDir = "$pluginDir/$name/config";
            if (is_dir($pluginConfigDir)) {
                $pluginConfig = static::loadFromDir($pluginConfigDir, ['attribute']);
                if (! empty($pluginConfig['attribute'])) {
                    $allConfig = array_merge_recursive($allConfig, $pluginConfig);
                }
            }
        }

        // 合并插件attribute配置
        $config = [];
        foreach ($allConfig['plugin'] ?? [] as $projectConfigs) {
            foreach ($projectConfigs as $project) {
                ! empty($project['attribute']) && $config = array_merge_recursive($config, $project['attribute']);
            }
        }

        return Options::init($config);
    }

    protected static function loadFromDir(string $configPath, array $onlyFiles = []): array
    {
        $allConfig = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($configPath, FilesystemIterator::FOLLOW_SYMLINKS)
        );

        foreach ($iterator as $file) {
            // 过滤非PHP文件、指定文件列表外的文件
            if ($file->isDir() || $file->getExtension() !== 'php' ||
                ($onlyFiles && ! in_array($file->getBasename('.php'), $onlyFiles))) {
                continue;
            }

            $appConfigFile = $file->getPath() . '/app.php';
            if (! is_file($appConfigFile)) continue;

            // 检查app配置是否启用
            $relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $sections = array_reverse(explode(DIRECTORY_SEPARATOR, substr($relativePath, 0, -4)));

            if (count($sections) >= 2) {
                $appConfig = include $appConfigFile;
                if (empty($appConfig['enable'])) continue;
            }

            // 解析配置并按路径层级合并
            $config = include $file;
            foreach ($sections as $section) {
                $config = [$section => $config];
            }
            $allConfig = array_replace_recursive($allConfig, $config);
        }

        return $allConfig;
    }
}