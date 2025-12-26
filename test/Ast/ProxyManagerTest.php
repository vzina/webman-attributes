<?php
/**
 * ProxyManagerTest.php
 * 测试 ProxyManager 核心功能
 */
declare(strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vzina\Attributes\Ast\AstParser;
use Vzina\Attributes\Ast\ProxyManager;
use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;
use Symfony\Component\Finder\Finder;

class ProxyManagerTest extends TestCase
{
    /** @var ProxyManager */
    private $proxyManager;

    /** @var string 原始类临时目录 */
    private $originalDir;

    /** @var string 代理文件生成目录 */
    private $proxyDir;

    /** @var array 模拟的原始类映射（类名 => 文件路径） */
    private $mockOriginalClassMap;

    /**
     * 测试前置：初始化目录、模拟依赖、创建测试类文件
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. 初始化临时目录
        $this->originalDir = __DIR__ . '/../Fixtures/Proxy/original';
        $this->proxyDir = __DIR__ . '/../Fixtures/Proxy/proxy';
        foreach ([$this->originalDir, $this->proxyDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // 2. 创建测试原始类文件
        $testClass1 = 'App\Test\DemoClass';
        $testClass1File = $this->originalDir . '/DemoClass.php';
        file_put_contents($testClass1File, <<<'PHP'
<?php
namespace App\Test;
class DemoClass {}
PHP
        );

        $testClass2 = 'App\Test\LazyClass';
        $testClass2File = $this->originalDir . '/LazyClass.php';
        file_put_contents($testClass2File, <<<'PHP'
<?php
namespace App\Test;
class LazyClass {}
PHP
        );

        // 3. 模拟原始类映射
        $this->mockOriginalClassMap = [
            $testClass1 => $testClass1File,
            $testClass2 => $testClass2File,
        ];

        // 4. 重置静态收集器（避免跨测试污染）
        $this->resetStaticCollectors();

        // 5. 注册自动加载（确保反射能找到测试类）
        spl_autoload_register(function ($className) {
            $file = __DIR__ . '/../Fixtures/Proxy/original/' . str_replace('App\Test\\', '', $className) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    /**
     * 测试后置：清理临时文件、重置静态收集器
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // 1. 清理代理文件
        $finder = Finder::create()->files()->in($this->proxyDir)->name('*.proxy.php');
        foreach ($finder as $file) {
            unlink($file->getPathname());
        }

        // 2. 清理原始类文件
        $finder = Finder::create()->files()->in($this->originalDir)->name('*.php');
        foreach ($finder as $file) {
            unlink($file->getPathname());
        }

        // 3. 重置静态收集器
        $this->resetStaticCollectors();

        // 4. 注销自动加载
        spl_autoload_unregister(function ($className) {
            $file = __DIR__ . '/../Fixtures/Proxy/original/' . str_replace('App\Test\\', '', $className) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });

        // 5. 清空单例（AstParser）
        $astReflection = new ReflectionClass(AstParser::class);
        $instanceProp = $astReflection->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null);
    }

    /**
     * 重置 AspectCollector/AttributeCollector 静态状态（反射实现）
     */
    private function resetStaticCollectors(): void
    {
        // 重置 AspectCollector
        $aspectRef = new ReflectionClass(AspectCollector::class);
        if ($aspectRef->hasProperty('storage')) {
            $storageProp = $aspectRef->getProperty('storage');
            $storageProp->setAccessible(true);
            $storageProp->setValue([]);
        }

        // 重置 AttributeCollector
        $attrRef = new ReflectionClass(AttributeCollector::class);
        if ($attrRef->hasProperty('storage')) {
            $storageProp = $attrRef->getProperty('storage');
            $storageProp->setAccessible(true);
            $storageProp->setValue([]);
        }
    }

    // -------------------------- 核心方法测试 --------------------------

    /**
     * 测试1：构造函数初始化 + getProxies 获取代理类映射
     */
    public function testConstructAndGetProxies(): void
    {
        // 1. 模拟切面规则（让 DemoClass 匹配规则）
        AspectCollector::set('classes', [
            'App\Aspect\TestAspect' => ['App\Test\DemoClass']
        ]);

        // 2. 初始化 ProxyManager
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);

        // 3. 断言代理类映射包含 DemoClass
        $proxies = $this->proxyManager->getProxies();
        $this->assertArrayHasKey('App\Test\DemoClass', $proxies);
        $this->assertStringContainsString('App_Test_DemoClass.proxy.php', $proxies['App\Test\DemoClass']);
        $this->assertEquals($this->proxyDir, $this->proxyManager->getProxyDir());
    }

    /**
     * 测试2：isRuleMatch 规则匹配（精确/通配符/带方法）
     */
    public function testIsRuleMatch(): void
    {
        // 1. 反射访问私有方法 isRuleMatch
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);
        $reflection = new ReflectionClass($this->proxyManager);
        $isRuleMatchMethod = $reflection->getMethod('isRuleMatch');
        $isRuleMatchMethod->setAccessible(true);

        // 2. 测试精确匹配
        $this->assertTrue($isRuleMatchMethod->invoke($this->proxyManager, 'App\Test\DemoClass', 'App\Test\DemoClass'));

        // 3. 测试通配符匹配
        $this->assertTrue($isRuleMatchMethod->invoke($this->proxyManager, 'App\Test\*', 'App\Test\DemoClass'));
        $this->assertFalse($isRuleMatchMethod->invoke($this->proxyManager, 'App\Other\*', 'App\Test\DemoClass'));

        // 4. 测试带方法的规则（自动移除方法后缀）
        $this->assertTrue($isRuleMatchMethod->invoke($this->proxyManager, 'App\Test\DemoClass::testMethod', 'App\Test\DemoClass'));
    }

    /**
     * 测试3：buildProxyFilePath 构建代理文件路径
     */
    public function testBuildProxyFilePath(): void
    {
        // 1. 反射访问私有方法 buildProxyFilePath
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);
        $reflection = new ReflectionClass($this->proxyManager);
        $buildPathMethod = $reflection->getMethod('buildProxyFilePath');
        $buildPathMethod->setAccessible(true);

        // 2. 测试路径构建
        $className = 'App\Test\DemoClass';
        $expectedPath = rtrim($this->proxyDir, '/') . '/App_Test_DemoClass.proxy.php';
        $actualPath = $buildPathMethod->invoke($this->proxyManager, $className);

        // 3. 断言路径正确
        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * 测试4：collectNeedProxyClasses 收集需要普通代理的类
     */
    public function testCollectNeedProxyClasses(): void
    {
        // 1. 模拟切面规则和属性规则
        AspectCollector::set('classes', [
            'App\Aspect\TestAspect' => ['App\Test\DemoClass'] // 类规则匹配 DemoClass
        ]);
        AspectCollector::set('attributes', [
            'App\Aspect\AttrAspect' => ['App\Attribute\TestAttr'] // 属性规则匹配 TestAttr
        ]);

        // 模拟 LazyClass 带有 TestAttr 注解
        AttributeCollector::set('App\Test\LazyClass', [
            'class' => ['App\Attribute\TestAttr' => new \stdClass()]
        ]);

        // 2. 反射访问私有方法 collectNeedProxyClasses
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);
        $reflection = new ReflectionClass($this->proxyManager);
        $collectMethod = $reflection->getMethod('collectNeedProxyClasses');
        $collectMethod->setAccessible(true);

        // 3. 执行方法并断言结果
        $proxyClasses = $collectMethod->invoke($this->proxyManager);
        $this->assertContains('App\Test\DemoClass', $proxyClasses); // 匹配类规则
        $this->assertContains('App\Test\LazyClass', $proxyClasses); // 匹配属性规则
    }

    /**
     * 测试5：collectLazyProxyClasses 收集懒加载代理类
     */
    public function testCollectLazyProxyClasses(): void
    {
        // 1. 模拟 Inject 注解（lazy=true）
        $injectAttr = $this->createMock(Inject::class);
        $injectAttr->lazy = true;
        // $injectAttr->method('__toString')->willReturn('App\Test\LazyClass');
        $injectAttr->value = 'App\Test\LazyClass'; // 模拟 value 属性

        AttributeCollector::collectProperty(
            'App\Test\OtherClass',
            'lazyProp',
            Inject::class,
            $injectAttr
        );

        // 2. 反射访问保护方法 collectLazyProxyClasses
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);
        $reflection = new ReflectionClass($this->proxyManager);
        $collectMethod = $reflection->getMethod('collectLazyProxyClasses');
        $collectMethod->setAccessible(true);

        // 3. 执行方法并断言结果
        $lazyClasses = $collectMethod->invoke($this->proxyManager);
        $expectedLazyName = ProxyManager::lazyName('App\Test\LazyClass');

        $this->assertArrayHasKey('App\Test\LazyClass', $lazyClasses);
        $this->assertEquals($expectedLazyName, $lazyClasses['App\Test\LazyClass']);
    }

    /**
     * 测试6：generateProxyFile 生成代理文件（普通/懒加载）
     */
    public function testGenerateProxyFile(): void
    {
        // Mock AstParser 单例，支持任意类名的 proxy/lazyProxy 调用
        $mockAstParser = $this->createMock(AstParser::class);

        // 让 proxy() 方法匹配任意类名参数，返回对应类的代理代码
        $mockAstParser->method('proxy')
            ->willReturnCallback(function ($className) {
                // 根据传入的类名动态生成代理代码
                $classNameParts = explode('\\', $className);
                $shortClassName = end($classNameParts);
                return <<<PHP
<?php
namespace $className;
class $shortClassName {
    // 代理类占位
}
PHP;
            });

        // lazyProxy() 方法匹配任意两个参数，返回懒加载代理代码
        $mockAstParser->method('lazyProxy')
            ->willReturnCallback(function ($proxyClassName, $originalClassName) {
                $originalParts = explode('\\', $originalClassName);
                $shortOriginalName = end($originalParts);
                return <<<PHP
<?php
namespace $proxyClassName;
class $shortOriginalName {
    // 懒加载代理类占位
}
PHP;
            });

        // 替换 AstParser 单例为 Mock 实例
        $astReflection = new ReflectionClass(AstParser::class);
        $instanceProp = $astReflection->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue($mockAstParser);

        // 2. 初始化 ProxyManager
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);
        $reflection = new ReflectionClass($this->proxyManager);
        $generateMethod = $reflection->getMethod('generateProxyFile');
        $generateMethod->setAccessible(true);

        // 3. 生成普通代理文件（DemoClass）
        $demoClass = 'App\Test\DemoClass';
        $proxyFilePath = $generateMethod->invoke($this->proxyManager, $demoClass);

        // 断言文件存在 + 内容包含预期类名
        $this->assertFileExists($proxyFilePath);
        $proxyContent = file_get_contents($proxyFilePath);
        $this->assertStringContainsString('class DemoClass', $proxyContent);
        $this->assertStringContainsString('namespace App\Test\DemoClass', $proxyContent);

        // 4. 生成懒加载代理文件
        $lazyClassName = ProxyManager::lazyName('App\Test\LazyClass');
        $lazyProxyFilePath = $generateMethod->invoke($this->proxyManager, $lazyClassName, 'App\Test\LazyClass');

        $this->assertFileExists($lazyProxyFilePath);
        $lazyProxyContent = file_get_contents($lazyProxyFilePath);
        $this->assertStringContainsString('LazyProxy\App\Test\LazyClass', $lazyProxyContent);
        $this->assertStringContainsString('class LazyClass', $lazyProxyContent);

        // 5. 测试文件已存在时不重复生成（修改代理文件时间戳）
        touch($proxyFilePath, time() - 3600); // 设为1小时前
        $originalFile = $this->mockOriginalClassMap[$demoClass];
        touch($originalFile, time() - 7200); // 原文件更早（确保不触发重新生成）

        // 再次调用生成方法
        $newProxyFilePath = $generateMethod->invoke($this->proxyManager, $demoClass);

        // 断言文件路径相同 + 时间戳未变（未重新生成）
        $this->assertEquals($proxyFilePath, $newProxyFilePath);
        $this->assertEquals(filemtime($proxyFilePath), filemtime($newProxyFilePath));
    }

    /**
     * 测试7：getAspectClasses 获取切面关联的代理类映射
     */
    public function testGetAspectClasses(): void
    {
        // 1. 模拟切面规则
        AspectCollector::set('classes', [
            'App\Aspect\TestAspect' => ['App\Test\DemoClass']
        ]);

        // 2. 初始化 ProxyManager 并生成代理
        $this->proxyManager = new ProxyManager($this->mockOriginalClassMap, $this->proxyDir);

        // 3. 获取切面关联的代理映射
        $aspectClasses = $this->proxyManager->getAspectClasses();

        // 4. 断言结果正确
        $this->assertArrayHasKey('App\Aspect\TestAspect', $aspectClasses);
        $this->assertArrayHasKey('App\Test\DemoClass', $aspectClasses['App\Aspect\TestAspect']);
        $this->assertEquals(
            $this->proxyManager->getProxies()['App\Test\DemoClass'],
            $aspectClasses['App\Aspect\TestAspect']['App\Test\DemoClass']
        );
    }
}