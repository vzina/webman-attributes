<?php
/**
 * LazyLoader — 懒加载代理生成器。
 *
 * 扫描所有 #[Inject(lazy: true)] 属性，为目标类生成延迟代理：
 * 代理类在首次方法调用时才从容器解析真实实例。
 * 根据目标类型自动选择 Class/Interface/Fallback 代理构建器。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use ReflectionClass;
use Vzina\Attributes\Ast\AstParser;
use Vzina\Attributes\Ast\ProxyLoaderInterface;
use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Scan\Options;

class LazyLoader implements ProxyLoaderInterface
{
    protected const LAZY_NS = 'LazyProxy\\';

    public function __invoke(Options $option, array &$classMap): void
    {
        $astParser = AstParser::getInstance();
        $originalClassMap = $classMap;
        $proxyDir = $option->proxyPath();

        foreach (AttributeCollector::getPropertiesByAttribute(Inject::class) as $property) {
            $attr = $property['attribute'] ?? null;
            if ($attr instanceof Inject && $attr->lazy) {
                $proxyClass = static::lazyName($attr->value);
                $proxyFile = path_combine($proxyDir, str_replace('\\', '_', $proxyClass) . '.proxy.php');

                if (! file_exists($proxyFile) ||
                    (isset($originalClassMap[$proxyClass]) && filemtime($proxyFile) < filemtime($originalClassMap[$proxyClass]))
                ) {
                    file_put_contents($proxyFile, $this->lazyProxy($astParser, $proxyClass, $attr->value), LOCK_EX);
                }

                $classMap[$proxyClass] = $proxyFile;
            }
        }
    }

    public function lazyProxy(AstParser $astParser, string $proxy, string $target): string
    {
        try {
            $ref = new ReflectionClass($target);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(sprintf(
                "LazyLoader: Cannot reflect class '%s' (target value: '%s'). "
                . "The @Inject property type may not be fully qualified. "
                . "Try using the FQCN or ensure the use statement is correct. "
                . "Error: %s",
                $target,
                $target,
                $e->getMessage()
            ), 0, $e);
        }

        if ($ref->isFinal()) {
            $builder = new FallbackLazyProxyBuilder();
            return $this->buildNewCode($astParser, $builder, $proxy, $ref);
        }
        if ($ref->isInterface()) {
            $builder = new InterfaceLazyProxyBuilder();
            return $this->buildNewCode($astParser, $builder, $proxy, $ref);
        }
        $builder = new ClassLazyProxyBuilder();

        return $this->buildNewCode($astParser, $builder, $proxy, $ref);
    }

    public function buildNewCode(AstParser $astParser, AbstractLazyProxyBuilder $builder, string $proxy, ReflectionClass $ref): string
    {
        $target = $ref->getName();
        $nodes = $astParser->getNodesFromReflectionClass($ref);
        $builder->addClassBoilerplate($proxy, $target);
        $builder->addClassRelationship();
        $traverser = new NodeTraverser();
        $methods = $astParser->getAllMethodsFromStmts($nodes);
        $visitor = new PublicMethodVisitor($methods, $builder->getOriginalClassName());
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
        $builder->addNodes($visitor->nodes);

        return $astParser->prettyPrintFile([$builder->getNode()]);
    }

    public static function lazyName(string $name): string
    {
        return self::LAZY_NS . $name;
    }
}