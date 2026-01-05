<?php

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
        $ref = new ReflectionClass($target);
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