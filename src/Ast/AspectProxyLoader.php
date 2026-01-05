<?php

declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use PhpParser\NodeTraverser;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\Composer;
use Vzina\Attributes\Scan\Options;

class AspectProxyLoader implements ProxyLoaderInterface
{

    public function __invoke(Options $option, array &$classMap): void
    {
        $astParser = AstParser::getInstance();
        $originalClassMap = $classMap;
        $proxyDir = $option->proxyPath();

        foreach ($this->getProxyClasses($originalClassMap) as $className) {
            $proxyFile = path_combine($proxyDir, str_replace('\\', '_', $className) . '.proxy.php');

            if (! file_exists($proxyFile) ||
                (isset($originalClassMap[$className]) && filemtime($proxyFile) < filemtime($originalClassMap[$className]))
            ) {
                file_put_contents($proxyFile, $this->proxy($astParser, $className), LOCK_EX);
            }

            $classMap[$className] = $proxyFile;
        }
    }

    /**
     * 生成代理类代码
     */
    protected function proxy(AstParser $astParser, string $className): string
    {
        $code = Composer::getCodeByClassName($className);
        $stmts = $astParser->parse($code);

        $traverser = new NodeTraverser();
        $visitorMetadata = new AstVisitorMetadata($className);

        // 遍历并应用所有AST访问器
        foreach (clone AstVisitorManager::getQueue() as $visitorClass) {
            $traverser->addVisitor(new $visitorClass($visitorMetadata));
        }

        $modifiedStmts = $traverser->traverse($stmts);

        return $astParser->prettyPrintFile($modifiedStmts);
    }

    protected function getProxyClasses(array $originalClassMap): array
    {
        $proxies = [];
        $classesAspects = AspectCollector::get('classes', []);
        $attributeAspects = AspectCollector::get('attributes', []);
        foreach ($classesAspects as $rules) {
            foreach ($rules as $rule) {
                foreach ($originalClassMap as $class => $path) {
                    if (! $this->isMatch($rule, $class)) {
                        continue;
                    }
                    $proxies[$class] = true;
                }
            }
        }

        foreach ($originalClassMap as $className => $path) {
            $class = $this->retrieveAttributes($className . '._c');
            $method = $this->retrieveAttributes($className . '._m');
            $property = $this->retrieveAttributes($className . '._p');

            $attributes = array_unique(array_merge($class, $method, $property));
            if ($attributes) {
                foreach ($attributeAspects as $rules) {
                    foreach ($rules as $rule) {
                        foreach ($attributes as $attribute) {
                            if ($this->isMatch($rule, $attribute)) {
                                $proxies[$className] = true;
                            }
                        }
                    }
                }
            }
        }

        return array_keys($proxies);
    }

    protected function retrieveAttributes(string $key): array
    {
        $defined = [];
        $attributes = AttributeCollector::get($key, []);

        foreach ($attributes as $name => $attribute) {
            if (is_object($attribute)) {
                $defined[] = $name;
            } else {
                $defined = array_merge($defined, array_keys($attribute));
            }
        }
        return $defined;
    }

    protected function isMatch(string $rule, string $target): bool
    {
        if (strpos($rule, '::') !== false) {
            [$rule,] = explode('::', $rule);
        }
        if (strpos($rule, '*') === false && $rule === $target) {
            return true;
        }
        $preg = str_replace(['*', '\\'], ['.*', '\\\\'], $rule);
        $pattern = "/^{$preg}$/";

        if (preg_match($pattern, $target)) {
            return true;
        }

        return false;
    }
}