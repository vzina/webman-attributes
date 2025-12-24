<?php
/**
 * ProxyManager.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class ProxyManager
{
    // 懒加载代理类命名空间前缀
    protected const LAZY_NS = 'LazyProxy\\';

    // 代理类映射（原类名/懒加载类名 => 代理文件路径）
    private array $proxyClassMap = [];

    // 原始类映射（类名 => 原始文件路径）
    private array $originalClassMap;

    // 代理文件存放目录
    private string $proxyDirectory;

    public function __construct(array $originalClassMap = [], string $proxyDirectory = '')
    {
        $this->originalClassMap = $originalClassMap;
        $this->proxyDirectory = $proxyDirectory;
        $this->proxyClassMap = $this->generateAllProxyFiles();
    }

    /**
     * 获取代理类映射（类名 => 代理文件路径）
     */
    public function getProxies(): array
    {
        return $this->proxyClassMap;
    }

    /**
     * 获取代理文件存放目录
     */
    public function getProxyDir(): string
    {
        return $this->proxyDirectory;
    }

    /**
     * 获取切面关联的代理类映射
     */
    public function getAspectClasses(): array
    {
        $aspectProxyMap = [];
        foreach (AspectCollector::get('classes', []) as $aspectClass => $rules) {
            foreach ($rules as $ruleClass) {
                if (isset($this->proxyClassMap[$ruleClass])) {
                    $aspectProxyMap[$aspectClass][$ruleClass] = $this->proxyClassMap[$ruleClass];
                }
            }
        }
        return $aspectProxyMap;
    }

    /**
     * 生成所有代理文件（普通代理 + 懒加载代理）
     */
    private function generateAllProxyFiles(): array
    {
        $proxyFileMap = [];

        // 生成普通代理文件
        foreach ($this->collectNeedProxyClasses() as $className) {
            $proxyFileMap[$className] = $this->generateProxyFile($className);
        }

        // 生成懒加载代理文件
        foreach ($this->collectLazyProxyClasses() as $originalClass => $lazyProxyClass) {
            $proxyFileMap[$lazyProxyClass] = $this->generateProxyFile($lazyProxyClass, $originalClass);
        }

        return $proxyFileMap;
    }

    /**
     * 收集需要生成普通代理的类
     */
    private function collectNeedProxyClasses(): array
    {
        if (empty($this->originalClassMap)) return [];

        $proxyClasses = [];
        $classRules = AspectCollector::get('classes', []);
        $attrRules = AspectCollector::get('attributes', []);

        // 1. 收集切面类规则匹配的类
        foreach ($classRules as $rules) {
            foreach ($rules as $rule) {
                foreach ($this->originalClassMap as $className => $filePath) {
                    if ($this->isRuleMatch($rule, $className)) {
                        $proxyClasses[$className] = $filePath;
                    }
                }
            }
        }

        // 2. 补充属性规则匹配的类（排除已收集的）
        foreach ($this->originalClassMap as $className => $filePath) {
            if (isset($proxyClasses[$className])) continue;

            // 匹配属性规则
            $classAttrs = $this->getClassAttributeNames($className);
            if (empty($classAttrs)) continue;

            foreach ($attrRules as $rules) {
                foreach ($rules as $rule) {
                    foreach ($classAttrs as $attr) {
                        if ($this->isRuleMatch($rule, $attr)) {
                            $proxyClasses[$className] = $filePath;
                            break 3; // 跳出三层循环，避免重复判断
                        }
                    }
                }
            }
        }

        return array_keys($proxyClasses);
    }

    /**
     * 生成代理文件（兼容普通/懒加载代理）
     */
    private function generateProxyFile(string $className, ?string $originalClass = null): string
    {
        $proxyFilePath = $this->buildProxyFilePath($className);
        $checkClass = $originalClass ?: $className;

        // 按需生成：文件不存在 或 原文件已修改
        if (!file_exists($proxyFilePath) || $this->isOriginalFileModified($checkClass, $proxyFilePath)) {
            $content = $originalClass
                ? AstParser::getInstance()->lazyProxy($className, $originalClass)
                : AstParser::getInstance()->proxy($className);
            file_put_contents($proxyFilePath, $content, LOCK_EX);
        }

        return $proxyFilePath;
    }

    /**
     * 构建代理文件路径
     */
    private function buildProxyFilePath(string $className): string
    {
        $proxyFileName = str_replace('\\', '_', $className) . '.proxy.php';
        return rtrim($this->proxyDirectory, '/') . '/' . $proxyFileName;
    }

    /**
     * 检查原始文件是否已修改（用于判断是否重新生成代理）
     */
    private function isOriginalFileModified(string $className, string $proxyFilePath): bool
    {
        $originalFilePath = $this->originalClassMap[$className] ?? '';
        return $originalFilePath && filemtime($proxyFilePath) < filemtime($originalFilePath);
    }

    /**
     * 规则匹配（支持精确匹配和通配符*）
     */
    private function isRuleMatch(string $rule, string $target): bool
    {
        // 移除方法后缀（如 App\User::getName → App\User）
        $pureRule = str_contains($rule, '::') ? explode('::', $rule)[0] : $rule;

        // 精确匹配
        if ($pureRule === $target && !str_contains($pureRule, '*')) {
            return true;
        }

        // 通配符匹配（转换为正则）
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pureRule, '/')) . '$/';
        return preg_match($regex, $target) === 1;
    }

    /**
     * 获取类的所有属性注解名称
     */
    private function getClassAttributeNames(string $className): array
    {
        $attributeNames = [];
        foreach (AttributeCollector::get($className, []) as $attrGroup) {
            foreach ($attrGroup as $name => $attr) {
                if (is_object($attr)) {
                    $attributeNames[] = $name;
                } else {
                    $attributeNames = array_merge($attributeNames, array_keys($attr));
                }
            }
        }
        return array_unique($attributeNames);
    }

    /**
     * 生成懒加载代理类名
     */
    public static function lazyName(string $name): string
    {
        return self::LAZY_NS . $name;
    }

    /**
     * 收集需要生成懒加载代理的类（基于Inject注解的lazy属性）
     */
    protected function collectLazyProxyClasses(): array
    {
        $lazyProxyClasses = [];
        foreach (AttributeCollector::getPropertiesByAttribute(Inject::class) as $property) {
            $attr = $property['attribute'] ?? null;
            if ($attr instanceof Inject && $attr->lazy) {
                $lazyProxyClasses[$attr->value] = static::lazyName($attr->value);
            }
        }
        return $lazyProxyClasses;
    }
}