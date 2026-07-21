<?php
/**
 * AttributeLoader — 插件入口，从 Composer files autoload 阶段调用。
 *
 * 职责：加载配置 → 注册内置组件 → 扫描类收集属性 → 生成代理文件 → 更新类映射。
 * 通过 PcntlHandler 子进程隔离类加载，确保代理文件可替换原始类。
 */
declare (strict_types=1);

namespace Vzina\Attributes;

use PhpParser\NodeVisitor;
use Vzina\Attributes\Ast\AstVisitorManager;
use Vzina\Attributes\Attribute\Contract\PropertyHandlerInterface;
use Vzina\Attributes\Collector\PropertyManagerCollector;
use Vzina\Attributes\Reflection\Composer;
use Vzina\Attributes\Scan\Options;
use Vzina\Attributes\Scan\Scanner;

class AttributeLoader
{
    /** 内置组件默认值。collectors/aspects 用户追加到默认之后，其余 key 覆盖。 */
    private const DEFAULTS = [
        'collectors' => [
            Collector\AttributeCollector::class,
            Collector\AspectCollector::class,
            Collector\ConstantsCollector::class,
        ],
        'aspects' => [
            Attribute\Aspect\InjectAspect::class,
            Attribute\Aspect\ValueAspect::class,
            Attribute\Aspect\CacheableAspect::class,
            Attribute\Aspect\TransactionalAspect::class,
            Attribute\Aspect\ValidateAspect::class,
            Attribute\Aspect\RetryAspect::class,
            Attribute\Aspect\TraceAspect::class,
        ],
        'property_handlers' => [
            Attribute\Handler\InjectPropertyHandler::class,
            Attribute\Handler\ValuePropertyHandler::class,
        ],
        'ast_visitors' => [
            Ast\AstPropertyVisitor::class,
            Ast\AstProxyCallVisitor::class,
        ],
        'ast_proxy_loaders' => [
            Ast\AspectProxyLoader::class,
            Ast\LazyLoader\LazyLoader::class,
        ],
    ];

    /** 配置中需将用户自定义项合并到默认项的数组 key */
    private const MERGE_ARRAY_KEYS = ['collectors', 'aspects'];

    /** 由 bootstrap.php (Composer files autoload) 调用 */
    public static function init(): void
    {
        $option = static::initOptions();
        if ($option === null) {
            return;
        }

        $loader = Composer::getLoader();

        // 手动类映射
        if ($option->classMap()) {
            $loader->addClassMap($option->classMap());
        }

        // AST 访问器（代理代码生成时使用）
        foreach ($option->astVisitors() as $visitor) {
            try {
                if (class_exists($visitor) && in_array(NodeVisitor::class, class_implements($visitor), true)) {
                    if (! AstVisitorManager::exists($visitor)) {
                        AstVisitorManager::insert($visitor);
                    }
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    'AttributeLoader: Failed to register AST visitor [%s]: %s',
                    $visitor,
                    $e->getMessage()
                ));
            }
        }

        // 属性注入处理器（构造时调用 __handlePropertyHandler）
        foreach ($option->propertyHandlers() as $handler) {
            try {
                if (class_exists($handler) && ($instance = new $handler) instanceof PropertyHandlerInterface) {
                    PropertyManagerCollector::register($instance->getAttribute(), $instance);
                }
            } catch (\Throwable $e) {
                error_log(sprintf(
                    'AttributeLoader: Failed to register property handler [%s]: %s',
                    $handler,
                    $e->getMessage()
                ));
            }
        }

        // 扫描 → 收集属性 → 生成代理 → 返回含代理的类映射
        $classMap = (new Scanner($option))->scan($loader->getClassMap());
        $loader->addClassMap($classMap);
    }

    /** 加载配置，优先用户覆盖，回退内置默认。
     *
     *  标量 key 用户值覆盖默认值；数组 key（collectors/aspects 等）合并用户自定义项。
     */
    private static function initOptions(): ?Options
    {
        $appFile = config_path('plugin/vzina/attributes/app.php');
        if (! file_exists($appFile)) {
            return null;
        }
        $app = (array) include $appFile;
        if (empty($app['enable'])) {
            return null;
        }

        $configFile = config_path('plugin/vzina/attributes/attribute.php');
        $config = file_exists($configFile) ? (array) include $configFile : [];

        // 标量 key 后者覆盖前者；数组 key 用户自定义项追加到默认之后
        $merged = array_merge(self::DEFAULTS, $config, $app);
        foreach (self::MERGE_ARRAY_KEYS as $key) {
            $merged[$key] = array_values(array_unique(
                array_merge(self::DEFAULTS[$key] ?? [], $config[$key] ?? [])
            ));
        }

        return Options::init($merged);
    }
}
