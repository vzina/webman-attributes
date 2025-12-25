<?php
/**
 * AbstractLazyProxyBuilder.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;

abstract class AbstractLazyProxyBuilder
{
    public $builder;

    public BuilderFactory $factory;

    protected ?string $namespace = null;

    protected ?string $proxyClassName = null;

    protected ?string $originalClassName = null;

    public function __construct()
    {
        $this->factory = new BuilderFactory();
        $this->builder = $this->factory;
    }

    abstract public function addClassRelationship(): self;

    public function addClassBoilerplate(string $proxyClassName, string $originalClassName): self
    {
        $proxyClassNameArr = explode('\\', $proxyClassName);
        $namespace = implode('\\', array_slice($proxyClassNameArr, 0, -1));
        $this->namespace = $namespace;
        $this->proxyClassName = $proxyClassName;
        $this->originalClassName = $originalClassName;
        $this->builder = $this->factory->class(last($proxyClassNameArr))
            ->addStmt(new ClassConst([new Const_('PROXY_TARGET', new String_($originalClassName))]))
            ->addStmt($this->factory->useTrait('\\' . __NAMESPACE__ . '\\LazyProxyTrait'))
            ->setDocComment(
                "/**
                              * Be careful: This is a lazy proxy, not the real {$originalClassName} from container.
                              *
                              * {@inheritdoc}
                              */"
            );
        return $this;
    }

    public function addNodes(array $nodes): self
    {
        foreach ($nodes as $stmt) {
            $this->builder = $this->builder->addStmt($stmt);
        }
        return $this;
    }

    public function getNode(): Node
    {
        if ($this->namespace) {
            return $this->factory
                ->namespace($this->namespace)
                ->addStmt($this->builder)
                ->getNode();
        }
        return $this->builder->getNode();
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getProxyClassName(): string
    {
        return $this->proxyClassName;
    }

    public function getOriginalClassName(): string
    {
        return $this->originalClassName;
    }
}