<?php
/**
 * attribute.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

return [
    'autoload' => true, // 是否自动加载
    'scan_path' => [ // 扫描目录
        app_path(),
        // 组件目录
        // base_path('vendor/vzina/attributes/src'),
        // base_path('plugin/xxx'),
    ],
    'excludes' => [ // 排除部分扫描目录/文件
        'config',
        'Install.php',
        'function.php',
        'functions.php',
    ],
    'class_map' => [], // 类映射
    'ignores' => [
        // 忽略注解
        // Vzina\Attributes\Attribute\Inject::class,
    ],
    'collectors' => [ // 注册收集器
        Vzina\Attributes\Collector\AttributeCollector::class,
        Vzina\Attributes\Collector\AspectCollector::class,
        Vzina\Attributes\Collector\ConstantsCollector::class,
    ],
    'aspects' => [ // 注册切面
        Vzina\Attributes\Attribute\InjectAspect::class,
        Vzina\Attributes\Attribute\ValueAspect::class,
        Vzina\Attributes\Attribute\CacheableAspect::class,
    ],
    'property_handlers' => [ // 注册属性注入逻辑
        Vzina\Attributes\Attribute\InjectPropertyHandler::class,
        Vzina\Attributes\Attribute\ValuePropertyHandler::class,
    ],
    'ast_visitors' => [ // 注册AST访问器
        Vzina\Attributes\Ast\AstPropertyVisitor::class,
        Vzina\Attributes\Ast\AstProxyCallVisitor::class,
    ],
];