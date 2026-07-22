<?php
/**
 * Generator — OpenAPI 文档生成器。
 *
 * 扫描 #[Controller] 路由注解，自动生成 OpenAPI 3.0 规范的 JSON 文档。
 */
declare (strict_types=1);

namespace Vzina\Attributes\OpenApi;

use PhpDocReader\PhpDocReader;
use ReflectionClass;
use ReflectionMethod;
use Vzina\Attributes\Attribute\Route\AutoController;
use Vzina\Attributes\Attribute\Route\Controller;
use Vzina\Attributes\Attribute\Route\DeleteMapping;
use Vzina\Attributes\Attribute\Route\Description;
use Vzina\Attributes\Attribute\Route\GetMapping;
use Vzina\Attributes\Attribute\Route\Header;
use Vzina\Attributes\Attribute\Route\Mapping;
use Vzina\Attributes\Attribute\Route\PatchMapping;
use Vzina\Attributes\Attribute\Route\PostMapping;
use Vzina\Attributes\Attribute\Route\PutMapping;
use Vzina\Attributes\Attribute\Route\RequestMapping;
use Vzina\Attributes\Attribute\Route\Resource;
use Vzina\Attributes\Attribute\Route\ApiResponse;
use Vzina\Attributes\Attribute\Route\Summary;
use Vzina\Attributes\Attribute\Route\Tag;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ReflectionManager;

class Generator
{
    private const METHOD_MAP = [
        GetMapping::class     => 'get',
        PostMapping::class    => 'post',
        PutMapping::class     => 'put',
        PatchMapping::class   => 'patch',
        DeleteMapping::class  => 'delete',
        RequestMapping::class => null, // explicit methods from attribute
    ];

    private const TYPE_MAP = [
        'int'    => 'integer',
        'float'  => 'number',
        'bool'   => 'boolean',
        'string' => 'string',
        'array'  => 'array',
        'mixed'  => 'string',
    ];

    /**
     * Generate OpenAPI 3.0 specification.
     *
     * @param array{title?: string, version?: string} $config
     */
    public static function generate(array $config = []): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'   => $config['title'] ?? 'API Documentation',
                'version' => $config['version'] ?? '1.0.0',
            ],
            'paths' => [],
        ];

        $reader = new PhpDocReader();

        foreach (AttributeCollector::list() as $className => $metadata) {
            if (isset($metadata['_c'][AutoController::class])) {
                self::handleAutoController($spec, $className, $metadata['_c'][AutoController::class], $reader);
            } elseif (isset($metadata['_c'][Resource::class])) {
                self::handleResource($spec, $className, $metadata['_c'][Resource::class], $reader, $metadata['_m'] ?? []);
            } elseif (isset($metadata['_c'][Controller::class])) {
                self::handleController($spec, $className, $metadata, $reader);
            }
        }

        return $spec;
    }

    /** 写入 openapi.json 文件 */
    public static function writeToFile(string $filePath, array $config = []): void
    {
        $spec = self::generate($config);
        file_put_contents($filePath,
            json_encode($spec,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            LOCK_EX
        );
    }

    // ==================== 控制器处理 ====================

    /** #[Controller] — 显式路由映射 */
    private static function handleController(array &$spec, string $className, array $metadata, PhpDocReader $reader): void
    {
        /** @var Controller $controller */
        $controller = $metadata['_c'][Controller::class];
        $prefix = self::getPrefix($className, $controller->prefix);

        foreach ($metadata['_m'] ?? [] as $methodName => $attrs) {
            foreach (self::METHOD_MAP as $attrClass => $httpMethod) {
                $mapping = $attrs[$attrClass] ?? null;
                if (! $mapping instanceof Mapping) {
                    continue;
                }

                $httpMethods = $httpMethod
                    ? [$httpMethod]
                    : (array) ($mapping->options['methods'] ?? ((array) $mapping->methods ?: ['get']));

                $path = self::buildPath($prefix, $mapping->path, $methodName);
                if (! isset($spec['paths'][$path])) {
                    $spec['paths'][$path] = [];
                }

                foreach ($httpMethods as $method) {
                    $spec['paths'][$path][strtolower($method)] = self::buildOperation(
                        $className, $methodName, $mapping, $reader, $attrs
                    );
                }
            }
        }
    }

    /** #[AutoController] — 自动路由所有公开方法 */
    private static function handleAutoController(array &$spec, string $className, AutoController $controller, PhpDocReader $reader): void
    {
        $prefix = self::getPrefix($className, $controller->prefix);
        $httpMethods = $controller->options['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $methodMeta = AttributeCollector::get($className . '._m') ?? [];

        try {
            $ref = ReflectionManager::reflectClass($className);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $methodName = $method->getName();
                if (str_starts_with($methodName, '__') || str_starts_with($methodName, '_')) {
                    continue;
                }

                $path = $prefix . '/' . $methodName;
                if (! isset($spec['paths'][$path])) {
                    $spec['paths'][$path] = [];
                }

                foreach ($httpMethods as $httpMethod) {
                    $spec['paths'][$path][strtolower($httpMethod)] = self::buildOperation(
                        $className, $methodName, null, $reader,
                        $methodMeta[$methodName] ?? []
                    );
                }
            }
        } catch (\ReflectionException) {
            // 类无法反射，跳过
        }
    }

    /** RESTful 资源路由定义 */
    private const RESOURCE_ROUTES = [
        'index'   => ['get', ''],
        'store'   => ['post', ''],
        'create'  => ['get', '/create'],
        'show'    => ['get', '/{id}'],
        'edit'    => ['get', '/{id}/edit'],
        'update'  => ['put', '/{id}'],
        'destroy' => ['delete', '/{id}'],
    ];

    /** #[Resource] — RESTful 资源路由 */
    private static function handleResource(array &$spec, string $className, Resource $resource, PhpDocReader $reader, array $methodMeta): void
    {
        $prefix  = self::getPrefix($className, $resource->prefix);
        $options = $resource->options['methods'] ?? [];

        // 确定启用的动作
        $enabled = array_keys(self::RESOURCE_ROUTES);
        if (isset($options['only'])) {
            $enabled = array_intersect($enabled, (array) $options['only']);
        } elseif (isset($options['except'])) {
            $enabled = array_diff($enabled, (array) $options['except']);
        }

        foreach ($enabled as $action) {
            [$httpMethod, $suffix] = self::RESOURCE_ROUTES[$action];
            $path = $prefix . $suffix;

            if (! isset($spec['paths'][$path])) {
                $spec['paths'][$path] = [];
            }

            $op = self::buildOperation($className, $action, null, $reader, $methodMeta[$action] ?? []);
            // 为 {id} 路由自动添加 path 参数
            if (str_contains($suffix, '{id}')) {
                $op['parameters'] = array_merge(
                    [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    $op['parameters'] ?? []
                );
            }
            $spec['paths'][$path][$httpMethod] = $op;
        }
    }

    // ==================== 操作构建 ====================

    /** 构建 OpenAPI Operation 对象（使用 Builder 模式） */
    private static function buildOperation(
        string $className, string $methodName, ?Mapping $mapping, PhpDocReader $reader, array $methodAttrs = []
    ): array {
        $classMeta = AttributeCollector::get($className);
        $tag = $methodAttrs[Tag::class]->value
            ?? $classMeta['_c'][Tag::class]->value
            ?? self::extractTag($className);

        $builder = (new OperationBuilder($className, $methodName, $tag))
            ->summary(
                $methodAttrs[Summary::class]->value
                ?? ($mapping !== null ? ($mapping->options['summary'] ?? null) : null)
            )
            ->headers(self::collectAttrs($classMeta['_c'] ?? [], Header::class))
            ->headers(self::collectAttrs($methodAttrs, Header::class));

        if (isset($methodAttrs[Description::class])) {
            $builder->description($methodAttrs[Description::class]->value);
        }

        // 合并类级和方法级 #[ApiResponse]
        $allResponses = array_merge(
            self::collectAttrs($classMeta['_c'] ?? [], ApiResponse::class),
            self::collectAttrs($methodAttrs, ApiResponse::class)
        );
        $builder->responseDocs($allResponses);

        try {
            $refMethod = ReflectionManager::reflectMethod($className, $methodName);
            $builder->buildParameters($refMethod, $reader);
            $builder->responseSchema(self::parseReturnType($refMethod));
            $builder->descriptionFromPhpDoc($refMethod);
        } catch (\ReflectionException) {
            // method not found, skip reflection
        }

        return $builder->build();
    }

    /** 收集可重复注解实例（Header、ApiResponse 等 IS_REPEATABLE 注解） */
    private static function collectAttrs(array $meta, string $attrClass): array
    {
        if (! isset($meta[$attrClass])) return [];
        return is_array($meta[$attrClass]) ? $meta[$attrClass] : [$meta[$attrClass]];
    }

    /** 框架内部类型，不作为 OpenAPI 参数暴露 */
    private const FRAMEWORK_TYPES = [
        'support\Request',
        'Webman\Http\Request',
        'Webman\Http\Response',
        'support\Response',
        'Workerman\Connection\TcpConnection',
    ];

    /** 从 PHPDoc @param 构建 OpenAPI Parameters */
    private static function buildParameters(ReflectionMethod $refMethod, PhpDocReader $reader): array
    {
        $params = [];
        foreach ($refMethod->getParameters() as $param) {
            try {
                $type = $reader->getParameterType($param);
            } catch (\Throwable) {
                $type = null;
            }

            // 跳过框架注入类型（Request、Response 等）
            if ($type !== null && self::isFrameworkType($type)) {
                continue;
            }

            // 反射类型也检查
            $refType = $param->getType();
            if ($refType instanceof \ReflectionNamedType && !$refType->isBuiltin() &&
                self::isFrameworkType($refType->getName())) {
                continue;
            }

            $schema = $type ? self::typeToSchema($type) : ['type' => 'string'];
            $params[] = [
                'name'     => $param->getName(),
                'in'       => 'query',
                'required' => ! $param->isOptional(),
                'schema'   => $schema,
            ];
        }
        return $params;
    }

    /** 判断类型是否为框架注入类型（不应出现在 API 文档中） */
    public static function isFrameworkType(string $typeName): bool
    {
        $typeName = ltrim($typeName, '\\?');
        foreach (self::FRAMEWORK_TYPES as $ft) {
            if ($typeName === $ft || str_ends_with($typeName, '\\' . $ft)) {
                return true;
            }
            // 反射类型仅返回短名（如 Request），也需匹配
            if (str_ends_with($ft, '\\' . $typeName) || basename(str_replace('\\', '/', $ft)) === $typeName) {
                return true;
            }
        }
        return false;
    }

    /** 解析 @return 类型 */
    private static function parseReturnType(ReflectionMethod $refMethod): ?string
    {
        $doc = $refMethod->getDocComment();
        if (! $doc) {
            return null;
        }

        if (preg_match('/@return\s+(\S+)/', $doc, $m)) {
            $type = $m[1];
            // Strip null| prefix for nullable
            if (str_starts_with($type, 'null|')) {
                $type = substr($type, 5);
            }
            return $type;
        }

        // Fallback to native return type
        $rt = $refMethod->getReturnType();
        if ($rt instanceof \ReflectionNamedType) {
            return $rt->getName();
        }

        return null;
    }

    /** 类型到 OpenAPI Schema */
    public static function typeToSchema(string $type): array
    {
        $type = ltrim($type, '?');
        $schema = [];

        if (array_key_exists($type, self::TYPE_MAP)) {
            $schema['type'] = self::TYPE_MAP[$type];
        } elseif (str_ends_with($type, '[]')) {
            $schema['type'] = 'array';
            $schema['items'] = self::typeToSchema(substr($type, 0, -2));
        } elseif ((class_exists($type) || interface_exists($type)) && ! self::isFrameworkType($type)) {
            $schema = ['$ref' => '#/components/schemas/' . self::shortName($type)];
        } else {
            $schema['type'] = 'string';
        }

        return $schema;
    }

    /** 构建路由前缀，与 DispatcherFactory::getPrefix 逻辑一致 */
    private static function getPrefix(string $className, ?string $prefix): string
    {
        return \Vzina\Attributes\Attribute\Route\DispatcherFactory::getPrefix($className, $prefix);
    }

    private static function buildPath(string $prefix, ?string $mappingPath, string $methodName): string
    {
        if ($mappingPath === null) {
            return $prefix . '/' . \Illuminate\Support\Str::snake($methodName);
        }
        if ($mappingPath === '') {
            return $prefix;
        }
        return $mappingPath[0] === '/' ? $mappingPath : rtrim($prefix, '/') . '/' . $mappingPath;
    }

    /** 从类名提取 tag */
    private static function extractTag(string $className): string
    {
        $parts = explode('\\', $className);
        $last = array_pop($parts);
        return \Illuminate\Support\Str::snake(str_replace('Controller', '', $last));
    }

    private static function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }

    /** 提取 docblock 第一行描述 */
    public static function parseDocSummary(string $docComment): string
    {
        $lines = explode("\n", trim($docComment, "/* \t\n\r\0\x0B"));
        foreach ($lines as $line) {
            $line = trim($line, " *\t");
            if ($line !== '' && ! str_starts_with($line, '@')) {
                return $line;
            }
        }
        return '';
    }
}
