<?php
/**
 * ProxyManager.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class ProxyManager
{
    /**
     * 代理类映射（类名 => 代理文件路径）
     */
    private array $proxyClassMap = [];

    /**
     * 原始类映射（类名 => 原文件路径）
     */
    private array $originalClassMap;

    /**
     * 代理文件存放目录
     */
    private string $proxyDirectory;

    public function __construct(
        AstParser $astParser,
        array $originalClassMap = [],
        string $proxyDirectory = ''
    ) {
        $this->originalClassMap = $originalClassMap;
        $this->proxyDirectory = $proxyDirectory;

        // 初始化需要代理的类并生成代理文件
        $needProxyClasses = $this->collectNeedProxyClasses();
        $this->proxyClassMap = $this->generateProxyFiles($astParser, $needProxyClasses);
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
        $classAspectRules = AspectCollector::get('classes', []);

        foreach ($classAspectRules as $aspectClass => $rules) {
            foreach ($rules as $rule) {
                if (isset($this->proxyClassMap[$rule])) {
                    $aspectProxyMap[$aspectClass][$rule] = $this->proxyClassMap[$rule];
                }
            }
        }

        return $aspectProxyMap;
    }

    // ------------------------------ 核心逻辑：收集需要代理的类 ------------------------------

    /**
     * 收集所有需要生成代理的类
     */
    private function collectNeedProxyClasses(): array
    {
        if (empty($this->originalClassMap)) {
            return [];
        }

        // 1. 从切面类规则收集需要代理的类
        $proxyClasses = $this->collectProxiesByAspectClassRules();

        // 2. 从属性规则补充收集需要代理的类
        $proxyClasses = $this->collectProxiesByAttributeRules($proxyClasses);

        return $proxyClasses;
    }

    /**
     * 从切面类规则收集需要代理的类
     */
    private function collectProxiesByAspectClassRules(): array
    {
        $proxyClasses = [];
        $classAspectRules = AspectCollector::get('classes', []);

        foreach ($classAspectRules as $rules) {
            foreach ($rules as $rule) {
                foreach ($this->originalClassMap as $className => $filePath) {
                    if ($this->isRuleMatch($rule, $className)) {
                        $proxyClasses[$className] = $filePath;
                    }
                }
            }
        }

        return $proxyClasses;
    }

    /**
     * 从属性规则补充收集需要代理的类
     */
    private function collectProxiesByAttributeRules(array $existingProxies): array
    {
        $attributeAspectRules = AspectCollector::get('attributes', []);

        foreach ($this->originalClassMap as $className => $filePath) {
            // 已收集的类跳过
            if (isset($existingProxies[$className])) {
                continue;
            }

            // 提取类的所有属性注解
            $classAttributes = $this->getClassAttributeNames($className);
            if (empty($classAttributes)) {
                continue;
            }

            // 匹配属性规则
            foreach ($attributeAspectRules as $rules) {
                foreach ($rules as $rule) {
                    foreach ($classAttributes as $attribute) {
                        if ($this->isRuleMatch($rule, $attribute)) {
                            $existingProxies[$className] = $filePath;
                            break 3; // 跳出三层循环，避免重复匹配
                        }
                    }
                }
            }
        }

        return $existingProxies;
    }

    // ------------------------------ 核心逻辑：生成代理文件 ------------------------------

    /**
     * 批量生成代理文件
     */
    private function generateProxyFiles(AstParser $astParser, array $needProxyClasses): array
    {
        $proxyFileMap = [];

        foreach ($needProxyClasses as $className => $_) {
            $proxyFileMap[$className] = $this->generateSingleProxyFile($astParser, $className);
        }

        return $proxyFileMap;
    }

    /**
     * 生成单个类的代理文件（仅文件变更时重新生成）
     */
    private function generateSingleProxyFile(AstParser $astParser, string $className): string
    {
        $proxyFilePath = $this->buildProxyFilePath($className);

        // 文件不存在或原文件更新时间晚于代理文件，重新生成
        if (!file_exists($proxyFilePath) || $this->isOriginalFileModified($className, $proxyFilePath)) {
            file_put_contents($proxyFilePath, $astParser->proxy($className), LOCK_EX);
        }

        return $proxyFilePath;
    }

    /**
     * 判断原文件是否比代理文件新
     */
    private function isOriginalFileModified(string $className, string $proxyFilePath): bool
    {
        $originalFilePath = $this->originalClassMap[$className];
        return filemtime($proxyFilePath) < filemtime($originalFilePath);
    }

    /**
     * 构建代理文件的完整路径
     */
    private function buildProxyFilePath(string $className): string
    {
        $proxyFileName = str_replace('\\', '_', $className) . '.proxy.php';
        return rtrim($this->proxyDirectory, '/') . '/' . $proxyFileName;
    }

    // ------------------------------ 辅助逻辑：规则匹配 & 属性提取 ------------------------------

    /**
     * 规则匹配（支持通配符*，处理::方法后缀）
     */
    private function isRuleMatch(string $rule, string $target): bool
    {
        // 移除方法后缀（如 App\User::getName → App\User）
        $pureRule = str_contains($rule, '::') ? explode('::', $rule)[0] : $rule;

        // 精确匹配（无通配符时）
        if ($pureRule === $target && !str_contains($pureRule, '*')) {
            return true;
        }

        // 通配符匹配（转换为正则）
        $regexPattern = '/^' . str_replace(['*', '\\'], ['.*', '\\\\'], $pureRule) . '$/';
        return preg_match($regexPattern, $target) === 1;
    }

    /**
     * 提取类的所有属性注解名称（去重）
     */
    private function getClassAttributeNames(string $className): array
    {
        $attributes = AttributeCollector::get($className, []);
        $attributeNames = [];

        foreach ($attributes as $attributeGroup) {
            foreach ($attributeGroup as $name => $attribute) {
                if (is_object($attribute)) {
                    $attributeNames[] = $name;
                } else {
                    $attributeNames = array_merge($attributeNames, array_keys($attribute));
                }
            }
        }

        return array_unique($attributeNames);
    }
}