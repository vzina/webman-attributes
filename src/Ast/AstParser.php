<?php
/**
 * AstParser.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\Finder\Finder;
use Vzina\Attributes\Reflection\ReflectionManager;

class AstParser
{
    /** PHP 内建类型，类型名 → 内置标记 */
    private const BUILTIN_TYPES = [
        'int', 'float', 'bool', 'string', 'void', 'never', 'mixed',
        'null', 'true', 'false', 'array', 'object', 'callable', 'iterable',
        'self', 'static', 'parent',
    ];

    protected Parser $parser;
    protected PrettyPrinterAbstract $printer;

    protected static $instance;

    public function __construct()
    {
        $this->printer = new Standard();
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /**
     * 单例模式获取实例
     */
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * 解析PHP代码为AST节点
     */
    public function parse(string $code): ?array
    {
        return $this->parser->parse($code);
    }

    public function prettyPrintFile(array $modifiedStmts): string
    {
        return $this->printer->prettyPrintFile($modifiedStmts);
    }

    /**
     * 从AST节点解析完整类名（命名空间+类名）
     */
    public function parseClassByStmts(array $stmts): string
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Namespace_ && $stmt->name) {
                $namespace = $stmt->name->toString();
                $className = $this->extractClassNameFromNamespaceStmt($stmt);

                if ($className) {
                    return sprintf('%s\\%s', $namespace, $className);
                }
            }
        }

        return '';
    }

    /**
     * 从反射类获取AST节点
     */
    public function getNodesFromReflectionClass(ReflectionClass $reflectionClass): ?array
    {
        return $this->parse(file_get_contents($reflectionClass->getFileName()));
    }

    /**
     * 从AST节点提取所有方法（支持Trait）
     */
    public function getAllMethodsFromStmts(array $stmts, bool $withTrait = false): array
    {
        $methods = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Namespace_) {
                continue;
            }

            $useAliases = $this->extractUseAliasesFromNamespace($stmt);
            $methods = array_merge(
                $methods,
                $this->extractMethodsFromNamespaceClasses($stmt, $useAliases, $withTrait)
            );
        }

        return $methods;
    }

    /**
     * 从反射类型生成AST类型节点
     */
    public function getNodeFromReflectionType(ReflectionType $reflection): ComplexType|Identifier|Name
    {
        if ($reflection instanceof ReflectionUnionType) {
            return $this->createUnionTypeNode($reflection);
        }

        return $this->createNamedTypeNode($reflection);
    }

    /**
     * 从反射参数生成AST参数节点
     */
    public function getNodeFromReflectionParameter(ReflectionParameter $parameter): Param
    {
        $paramNode = new Param(new Expr\Variable($parameter->getName()));

        // 设置默认值
        if ($parameter->isDefaultValueAvailable()) {
            $paramNode->default = $this->getExprFromValue($parameter->getDefaultValue());
        }

        // 设置参数类型
        if ($parameter->hasType()) {
            $paramNode->type = $this->getNodeFromReflectionType($parameter->getType());
        }

        // 设置引用传递
        if ($parameter->isPassedByReference()) {
            $paramNode->byRef = true;
        }

        // 设置可变参数
        if ($parameter->isVariadic()) {
            $paramNode->variadic = true;
        }

        return $paramNode;
    }

    /**
     * 从值生成AST表达式节点
     */
    public function getExprFromValue($value): Expr
    {
        return match (gettype($value)) {
            'array' => $this->createArrayExpr($value),
            'string' => new Node\Scalar\String_($value),
            'integer' => new Node\Scalar\Int_($value),
            'double' => new Node\Scalar\Float_($value),
            'NULL' => new Expr\ConstFetch(new Name('null')),
            'boolean' => new Expr\ConstFetch(new Name($value ? 'true' : 'false')),
            'object' => $this->createObjectExpr($value),
            default => throw new InvalidArgumentException(sprintf('Unsupported value type: %s', $value)),
        };
    }

    /**
     * 从指定路径扫描所有PHP类并返回反射类映射
     */
    public function getAllClassesByPath($path, array $excludes = []): array
    {
        $finder = Finder::create()->files()->in($path)->filter(function ($file) use ($excludes) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            return ! Str::contains($file->getPathname(), $excludes);
        })->name('*.php');

        return $this->getAllClassesByFinder($finder);
    }

    /**
     * 从Finder实例扫描所有PHP类并返回类名到文件路径的映射。
     * 不触发类自动加载，避免扫描阶段加载原始类后代理文件无法替换。
     *
     * @return array<string, string> className => filePath
     */
    public function getAllClassesByFinder(Finder $finder): array
    {
        $classMap = [];

        foreach ($finder as $file) {
            $stmts = $this->parse($file->getContents());
            $className = $this->parseClassByStmts($stmts);

            if ($className) {
                $classMap[$className] = $file->getRealPath();
            }
        }

        return $classMap;
    }

    /**
     * 从命名空间节点提取类名
     */
    private function extractClassNameFromNamespaceStmt(Namespace_ $namespaceStmt): string
    {
        foreach ($namespaceStmt->stmts as $node) {
            if ($node instanceof ClassLike && $node->name) {
                return $node->name->toString();
            }
        }

        return '';
    }

    /**
     * 从命名空间节点提取use别名映射
     */
    private function extractUseAliasesFromNamespace(Namespace_ $namespaceStmt): array
    {
        $aliases = [];

        foreach ($namespaceStmt->stmts as $node) {
            if ($node instanceof Stmt\Use_) {
                foreach ($node->uses as $use) {
                    $aliases[$use->name->getLast()] = $use->name->toString();
                }
            }
        }

        return $aliases;
    }

    /**
     * 从命名空间的类/接口/Trait中提取方法
     */
    private function extractMethodsFromNamespaceClasses(
        Namespace_ $namespaceStmt,
        array $useAliases,
        bool $withTrait
    ): array {
        $methods = [];

        foreach ($namespaceStmt->stmts as $node) {
            // 跳过非类/接口/Trait节点
            if (! $node instanceof Class_ && ! $node instanceof Interface_ && ! $node instanceof Trait_) {
                continue;
            }

            // 提取当前类的方法
            $methods = array_merge($methods, $node->getMethods());

            // 处理Trait方法（如果开启）
            if ($withTrait) {
                $methods = array_merge(
                    $methods,
                    $this->extractMethodsFromTraitUse($node, $useAliases, $namespaceStmt->name?->toString() ?? '')
                );
            }
        }

        return $methods;
    }

    /**
     * 从TraitUse节点提取Trait方法
     */
    private function extractMethodsFromTraitUse(
        Class_|Interface_|Trait_ $classLikeNode,
        array $useAliases,
        string $namespace
    ): array {
        $methods = [];

        foreach ($classLikeNode->stmts as $stmt) {
            if (! $stmt instanceof TraitUse) {
                continue;
            }

            foreach ($stmt->traits as $traitNode) {
                $traitName = $this->resolveTraitName($traitNode, $useAliases, $namespace);

                try {
                    $traitReflection = new ReflectionClass($traitName);
                    $traitStmts = $this->getNodesFromReflectionClass($traitReflection);

                    if ($traitStmts) {
                        $methods = array_merge(
                            $methods,
                            $this->getAllMethodsFromStmts($traitStmts, true)
                        );
                    }
                } catch (ReflectionException) {
                    continue; // 忽略不存在的Trait
                }
            }
        }

        return $methods;
    }

    /**
     * 解析Trait的完整类名
     */
    private function resolveTraitName(Name $traitNode, array $useAliases, string $namespace): string
    {
        // 优先使用use别名
        if (isset($useAliases[$traitNode->getFirst()])) {
            return $useAliases[$traitNode->getFirst()] . substr($traitNode->toString(), strlen($traitNode->getFirst()));
        }

        // 单部分名称，拼接当前命名空间
        if (count($traitNode->getParts()) === 1) {
            return sprintf('%s\\%s', $namespace, $traitNode->toString());
        }

        // 完整命名空间名称
        return $traitNode->toString();
    }

    /**
     * 创建联合类型节点
     */
    private function createUnionTypeNode(ReflectionUnionType $reflection): UnionType
    {
        $types = [];

        foreach ($reflection->getTypes() as $type) {
            $typeName = $type->getName();

            if (in_array($typeName, self::BUILTIN_TYPES)) {
                $types[] = new Identifier($typeName);
            } else {
                $types[] = new Name('\\' . $typeName);
            }
        }

        return new UnionType($types);
    }

    /**
     * 创建命名类型节点（支持可空）
     */
    private function createNamedTypeNode(ReflectionType $reflection): ComplexType|Identifier|Name
    {
        if (! $reflection instanceof ReflectionNamedType) {
            throw new ReflectionException('ReflectionType must be instance of ReflectionNamedType.');
        }

        $typeName = $reflection->getName();
        $normalizedType = $this->normalizeTypeName($typeName);

        // 处理可空类型
        if ($typeName !== 'mixed' && $reflection->allowsNull()) {
            return new Node\NullableType(new Name($normalizedType));
        }

        return in_array($typeName, self::BUILTIN_TYPES)
            ? new Identifier($typeName)
            : new Name($normalizedType);
    }

    /**
     * 标准化类型名称（添加命名空间前缀）
     */
    private function normalizeTypeName(string $typeName): string
    {
        return in_array($typeName, self::BUILTIN_TYPES) ? $typeName : '\\' . $typeName;
    }

    /**
     * 从数组值创建AST数组表达式
     */
    private function createArrayExpr(array $value): Expr\Array_
    {
        $isList = ! Arr::isAssoc($value);
        $items = [];

        foreach ($value as $key => $item) {
            $arrayKey = $this->createArrayKeyNode($key, $isList);
            $items[] = new Node\ArrayItem($this->getExprFromValue($item), $arrayKey);
        }

        return new Expr\Array_($items, ['kind' => Expr\Array_::KIND_SHORT]);
    }

    /**
     * 创建数组键节点（仅非索引数组时生成）
     */
    private function createArrayKeyNode($key, bool $isList): ?Node\Scalar
    {
        if ($isList) {
            return null;
        }

        return is_int($key)
            ? new Node\Scalar\Int_($key)
            : new Node\Scalar\String_((string)$key);
    }

    /**
     * 从对象创建AST表达式
     */
    private function createObjectExpr(object $value): Expr
    {
        $reflection = new ReflectionClass($value);

        // 处理枚举类型
        if (method_exists($reflection, 'isEnum') && $reflection->isEnum()) {
            return new Expr\ClassConstFetch(
                new Name('\\' . $value::class),
                $value->name
            );
        }

        // 处理普通对象（实例化）
        return new Expr\New_(new Name\FullyQualified($value::class));
    }
}