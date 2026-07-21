<?php
/**
 * AstPropertyVisitor.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;
use ReflectionException;
use Vzina\Attributes\Attribute\Annotation\Inject;
use Vzina\Attributes\Reflection\ReflectionManager;

class AstPropertyVisitor extends NodeVisitorAbstract
{
    protected AstParser $astParser;
    protected array $proxyTraits = [
        PropertyTrait::class,
    ];

    public function __construct(protected AstVisitorMetadata $visitorMetadata)
    {
        $this->astParser = new AstParser();
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_ && ! $node->isAnonymous()) {
            if ($this->visitorMetadata->hasExtends === null) {
                if ($node->extends) {
                    $this->visitorMetadata->hasExtends = true;
                } else {
                    $this->visitorMetadata->hasExtends = false;
                }
            }

            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                    $this->visitorMetadata->hasConstructor = true;
                    $this->visitorMetadata->constructorNode = $stmt;
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_ && ! $node->isAnonymous()) {
            if ($this->visitorMetadata->hasConstructor) {
                // 构造器注入：在 __construct 体内首行插入 @Inject 参数解析
                $ctorStmts = $this->visitorMetadata->constructorNode->stmts ?? [];
                $this->visitorMetadata->constructorNode->stmts = array_merge(
                    $this->buildConstructorInjection(),
                    [$this->buildMethodCallStatement()],
                    $ctorStmts,
                );
                $node->stmts = array_merge([$this->buildProxyTraitUseStatement()], $node->stmts);
            } else {
                $constructor = $this->buildConstructor();
                if ($this->visitorMetadata->hasExtends) {
                    $constructor->stmts[] = $this->buildCallParentConstructorStatement();
                }
                $constructor->stmts[] = $this->buildMethodCallStatement();
                $node->stmts = array_merge([$this->buildProxyTraitUseStatement()], [$constructor], $node->stmts);
                $this->visitorMetadata->hasConstructor = true;
            }
        }

        return null;
    }

    protected function buildConstructor(): Node\Stmt\ClassMethod
    {
        if ($this->visitorMetadata->constructorNode instanceof Node\Stmt\ClassMethod) {
            // Returns the parsed constructor class method node.
            $constructor = $this->visitorMetadata->constructorNode;
        } else {
            // Create a new constructor class method node.
            $constructor = new Node\Stmt\ClassMethod('__construct');
            $reflection = ReflectionManager::reflectClass($this->visitorMetadata->className);
            try {
                $parameters = $reflection->getMethod('__construct')->getParameters();
                foreach ($parameters as $parameter) {
                    $constructor->params[] = $this->astParser->getNodeFromReflectionParameter($parameter);
                }
            } catch (ReflectionException) {
                // Cannot found __construct method in parent class or traits, do nothing.
            }
        }
        return $constructor;
    }

    protected function buildCallParentConstructorStatement(): Node\Stmt
    {
        $hasConstructor = new Node\Expr\FuncCall(new Name('method_exists'), [
            new Node\Arg(new Node\Expr\ClassConstFetch(new Name('parent'), 'class')),
            new Node\Arg(new Node\Scalar\String_('__construct')),
        ]);
        return new Node\Stmt\If_($hasConstructor, [
            'stmts' => [
                new Node\Stmt\Expression(new Node\Expr\StaticCall(new Name('parent'), '__construct', [
                    new Node\Arg(new Node\Expr\FuncCall(new Name('func_get_args')), false, true),
                ])),
            ],
        ]);
    }

    protected function buildMethodCallStatement(): Node\Stmt\Expression
    {
        return new Node\Stmt\Expression(new Node\Expr\MethodCall(new Node\Expr\Variable('this'), '__handlePropertyHandler', [
            new Node\Arg(new Node\Scalar\MagicConst\Class_()),
        ]));
    }

    /**
     * 生成 @Inject 构造器参数的容器解析代码。
     *
     * 对构造器参数中的 #[Inject] 属性，生成 \$param = Container::get(Class::class) 语句，
     * 插入到代理类构造器体最前面，实现构造器级自动装配。
     *
     * @return Node\Stmt[] 容器解析表达式列表
     */
    protected function buildConstructorInjection(): array
    {
        $stmts = [];
        $ref = ReflectionManager::reflectClass($this->visitorMetadata->className);

        try {
            $ctor = $ref->getMethod('__construct');
            foreach ($ctor->getParameters() as $param) {
                $injectAttr = null;
                foreach ($param->getAttributes() as $attr) {
                    if ($attr->getName() === Inject::class) {
                        $injectAttr = $attr->newInstance();
                        break;
                    }
                }
                if (! $injectAttr instanceof Inject) {
                    continue;
                }

                $paramName  = new Node\Expr\Variable($param->getName());
                $targetName = $injectAttr->value;
                if ($targetName === null && ($type = $param->getType()) instanceof \ReflectionNamedType) {
                    $targetName = $type->getName();
                }
                if ($targetName === null) {
                    continue;
                }

                // $paramName = \support\Container::get(Target::class);
                $stmts[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                    $paramName,
                    new Node\Expr\StaticCall(
                        new Name\FullyQualified('support\Container'), 'get', [
                            new Node\Arg(new Node\Expr\ClassConstFetch(new Name\FullyQualified($targetName), 'class')),
                        ]
                    )
                ));
            }
        } catch (ReflectionException) {
            // 无构造器，跳过
        }

        return $stmts;
    }

    /**
     * Build `use PropertyHandlerTrait;` statement.
     */
    protected function buildProxyTraitUseStatement(): TraitUse
    {
        $traits = [];
        foreach ($this->proxyTraits as $proxyTrait) {
            // Should not check the trait whether exist to avoid class autoload.
            if (! is_string($proxyTrait)) {
                continue;
            }
            // Add backslash prefix if the proxy trait does not start with backslash.
            $proxyTrait[0] !== '\\' && $proxyTrait = '\\' . $proxyTrait;
            $traits[] = new Name($proxyTrait);
        }
        return new TraitUse($traits);
    }
}
