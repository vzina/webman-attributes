<?php
/**
 * AspectProxyLoader — AOP 代理文件生成器。
 *
 * 扫描所有需要代理的类，解析源码 AST，注入 PropertyTrait + 方法改写，
 * 生成 .proxy.php 文件并更新类映射。
 */
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
        $astParser   = AstParser::getInstance();
        $proxyDir    = $option->proxyPath();
        $cacheFile   = $option->cachePath() . '/aspects.cache';

        $this->loadAspects($option->aspects(), $cacheFile);

        // 从全量 Composer classMap 中匹配代理目标（含 vendor 类）
        $fullMap = array_merge($classMap, Composer::getLoader()->getClassMap());

        foreach ($this->getProxyClasses($fullMap) as $className) {
            $proxyFile = path_combine($proxyDir, str_replace('\\', '_', $className) . '.proxy.php');
            if (! file_exists($proxyFile) ||
                (isset($classMap[$className]) && filemtime($proxyFile) < filemtime($classMap[$className]))
            ) {
                file_put_contents($proxyFile, $this->generate($astParser, $className), LOCK_EX);
            }
            $classMap[$className] = $proxyFile;
        }
    }

    /** 解析源码 → 注入 Trait/方法改写 → 输出代理类代码 */
    private function generate(AstParser $astParser, string $className): string
    {
        $stmts    = $astParser->parse(Composer::getCodeByClassName($className));
        $traverser = new NodeTraverser();
        $meta     = new AstVisitorMetadata($className);

        foreach (AstVisitorManager::getVisitors() as $visitor) {
            $traverser->addVisitor(new $visitor($meta));
        }

        return $astParser->prettyPrintFile($traverser->traverse($stmts));
    }

    /** 确定哪些类需要代理（class 规则 + attribute 规则匹配） */
    private function getProxyClasses(array $classMap): array
    {
        $proxies    = [];
        $classRules = AspectCollector::get('classes', []);
        $attrRules  = AspectCollector::get('attributes', []);

        if (empty($classRules) && empty($attrRules)) {
            return [];
        }

        foreach ($classMap as $className => $_) {
            // class 规则
            foreach ($classRules as $rules) {
                foreach ($rules as $rule) {
                    if ($this->isMatch($rule, $className)) { $proxies[$className] = true; continue 2; }
                }
            }
            // attribute 规则
            $attrs = $this->collectClassAttributes($className);
            if (empty($attrs)) continue;
            foreach ($attrRules as $rules) {
                foreach ($rules as $rule) {
                    foreach ($attrs as $attr) {
                        if (AspectCollector::matchRule($rule, $attr)) { $proxies[$className] = true; continue 3; }
                    }
                }
            }
        }

        return array_keys($proxies);
    }

    /** 提取类的所有属性名列表 */
    private function collectClassAttributes(string $className): array
    {
        $attrs = [];
        foreach (['_c', '_m', '_p'] as $suffix) {
            foreach (AttributeCollector::get($className . '.' . $suffix, []) as $name => $val) {
                $attrs[] = $name;
                if (is_array($val)) { array_push($attrs, ...array_keys($val)); }
            }
        }
        return array_unique($attrs);
    }

    private function isMatch(string $rule, string $target): bool
    {
        if (str_contains($rule, '::')) {
            [$rule] = explode('::', $rule);
        }
        return AspectCollector::matchRule($rule, $target);
    }

    /** 加载切面规则（增量检测变更） */
    private function loadAspects(array $aspects, string $cacheFile): void
    {
        [$removed, $changed] = $this->diffAspects($aspects, $cacheFile);
        foreach ($removed as $a) { AspectCollector::clear($a); }

        foreach ($aspects as $key => $value) {
            [$name, $priority] = is_numeric($key) ? [$value, null] : [$key, (int) $value];
            if (! in_array($name, $changed, true)) continue;

            try {
                $props = (new \ReflectionClass($name))->getDefaultProperties();
                AspectCollector::setAround($name,
                    $props['classes'] ?? [], $props['attributes'] ?? [], $props['priority'] ?? $priority);
            } catch (\ReflectionException) {
                AspectCollector::setAround($name, [], [], $priority);
            }
        }
    }

    /** 检测切面类的增删改 */
    private function diffAspects(array $aspects, string $cacheFile): array
    {
        $names = [];
        $cacheMtime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;
        foreach ($aspects as $k => $v) { $names[] = is_numeric($k) ? $v : $k; }

        $old = [];
        if (file_exists($cacheFile)) {
            $raw = file_get_contents($cacheFile);
            $old = ($raw !== false) ? (array) unserialize($raw, ['allowed_classes' => []]) : [];
        }
        file_put_contents($cacheFile, serialize($names));

        $removed = [];
        foreach (array_diff($old, $names) as $item) {
            if (! AttributeCollector::getClassAttribute($item, 'Vzina\Attributes\Attribute\Aspect')) {
                $removed[] = $item;
            }
        }
        $changed = array_diff($names, $old);

        // 文件修改时间发生变化的切面类也算 changed
        $loader = Composer::getLoader();
        foreach ($names as $name) {
            if (($f = $loader->findFile($name)) && $cacheMtime <= filemtime($f)) {
                $changed[] = $name;
            }
        }

        return [array_values(array_unique($removed)), array_values(array_unique($changed))];
    }
}
