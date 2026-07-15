<?php
/**
 * AspectCollectorTest.php
 * 测试 AspectCollector 规则存储、matchRule 匹配及 PHP 文件缓存
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\AspectCollector;

class AspectCollectorTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        AspectCollector::clear();
        $this->cacheDir = sys_get_temp_dir() . '/vzina_asc_' . uniqid();
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AspectCollector::clear();
        array_map('unlink', glob("{$this->cacheDir}/*.php") ?: []);
        @rmdir($this->cacheDir);
    }

    // ==================== setAround / getRule / getPriority ====================

    public function testSetAroundStoresClasses(): void
    {
        AspectCollector::setAround(
            'App\\Aspect\\LogAspect',
            ['App\\Service\\*'],
            [],
            10
        );

        $rules = AspectCollector::getRule('App\\Aspect\\LogAspect');
        $this->assertContains('App\\Service\\*', $rules['classes']);
        $this->assertEmpty($rules['attributes']);
        $this->assertEquals(10, AspectCollector::getPriority('App\\Aspect\\LogAspect'));
    }

    public function testSetAroundStoresAttributes(): void
    {
        AspectCollector::setAround(
            'App\\Aspect\\CacheAspect',
            [],
            ['App\\Attribute\\Cacheable'],
            5
        );

        $rules = AspectCollector::getRule('App\\Aspect\\CacheAspect');
        $this->assertContains('App\\Attribute\\Cacheable', $rules['attributes']);
        $this->assertEmpty($rules['classes']);
    }

    public function testSetAroundDefaultPriority(): void
    {
        AspectCollector::setAround(
            'App\\Aspect\\DefaultAspect',
            ['App\\*'],
            [],
            null  // null → default 0
        );

        $this->assertEquals(0, AspectCollector::getPriority('App\\Aspect\\DefaultAspect'));
    }

    public function testSetAroundMergeRules(): void
    {
        AspectCollector::setAround('App\\Aspect\\Merged', ['App\\A'], [], 10);
        AspectCollector::setAround('App\\Aspect\\Merged', ['App\\B'], [], 10);

        $rules = AspectCollector::getRule('App\\Aspect\\Merged');
        $this->assertContains('App\\A', $rules['classes']);
        $this->assertContains('App\\B', $rules['classes']);
        // 优先级保留最新
        $this->assertEquals(10, AspectCollector::getPriority('App\\Aspect\\Merged'));
    }

    public function testGetRuleForUnknownAspect(): void
    {
        $this->assertEmpty(AspectCollector::getRule('NonExistent'));
    }

    public function testGetPriorityForUnknownAspect(): void
    {
        $this->assertEquals(0, AspectCollector::getPriority('NonExistent'));
    }

    // ==================== matchRule (新增) ====================

    public function testMatchRuleExact(): void
    {
        AspectCollector::setAround('Test', ['App\\Service\\UserService'], [], 10);

        $this->assertTrue(AspectCollector::matchRule(
            'App\\Service\\UserService',
            'App\\Service\\UserService'
        ));
    }

    public function testMatchRuleExactMismatch(): void
    {
        $this->assertFalse(AspectCollector::matchRule(
            'App\\Service\\UserService',
            'App\\Service\\OtherService'
        ));
    }

    public function testMatchRuleWildcardPrefix(): void
    {
        AspectCollector::setAround('Test', [], ['App\\Attribute\\*'], 10);

        $this->assertTrue(AspectCollector::matchRule(
            'App\\Attribute\\*',
            'App\\Attribute\\Cacheable'
        ));
        $this->assertTrue(AspectCollector::matchRule(
            'App\\Attribute\\*',
            'App\\Attribute\\Inject'
        ));
    }

    public function testMatchRuleWildcardSuffix(): void
    {
        AspectCollector::setAround('Test', ['App\\Service\\*Service'], [], 10);

        $this->assertTrue(AspectCollector::matchRule(
            'App\\Service\\*Service',
            'App\\Service\\UserService'
        ));
        $this->assertFalse(AspectCollector::matchRule(
            'App\\Service\\*Service',
            'App\\Service\\UserRepository'
        ));
    }

    public function testMatchRuleWildcardMiddle(): void
    {
        AspectCollector::setAround('Test', ['App\\*Controller'], [], 10);

        $this->assertTrue(AspectCollector::matchRule(
            'App\\*Controller',
            'App\\Http\\UserController'
        ));
    }

    public function testMatchRuleNoMatch(): void
    {
        AspectCollector::setAround('Test', ['App\\Admin\\*'], [], 10);

        $this->assertFalse(AspectCollector::matchRule(
            'App\\Admin\\*',
            'App\\Public\\PageController'
        ));
    }

    // ==================== clear ====================

    public function testClearSpecificAspect(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], [], 10);
        AspectCollector::setAround('AspectB', ['ClassB'], [], 5);

        AspectCollector::clear('AspectA');

        $this->assertEmpty(AspectCollector::getRule('AspectA'));
        $this->assertNotEmpty(AspectCollector::getRule('AspectB'));
    }

    public function testClearAll(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], [], 10);

        AspectCollector::clear();

        $this->assertEmpty(AspectCollector::getRules());
        $this->assertEmpty(AspectCollector::getRule('AspectA'));
    }

    // ==================== getRules / getContainer ====================

    public function testGetRules(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], ['AttrA'], 5);

        $rules = AspectCollector::getRules();
        $this->assertArrayHasKey('AspectA', $rules);
        $this->assertEquals(5, $rules['AspectA']['priority']);
    }

    public function testGetContainer(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], [], 10);

        $container = AspectCollector::getContainer();
        $this->assertArrayHasKey('classes', $container);
        $this->assertArrayHasKey('AspectA', $container['classes']);
    }

    // ==================== exportToFile / loadFromFile (新增) ====================

    public function testExportAndLoadFromFile(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], ['AttrA'], 10);
        AspectCollector::setAround('AspectB', ['ClassB'], [], 5);

        $file = $this->cacheDir . '/AspectCollector.cache.php';
        AspectCollector::exportToFile($file);

        $this->assertFileExists($file);

        AspectCollector::clear();
        $this->assertEmpty(AspectCollector::getRules());

        AspectCollector::loadFromFile($file);

        $this->assertNotEmpty(AspectCollector::getRule('AspectA'));
        $this->assertEquals(10, AspectCollector::getPriority('AspectA'));
        $this->assertNotEmpty(AspectCollector::getRule('AspectB'));
        // 验证预编译的正则也被恢复 — 检查通配符规则匹配
        $this->assertTrue(AspectCollector::matchRule('ClassA*', 'ClassATest'));
    }

    public function testLoadFromFileNonExistent(): void
    {
        $result = AspectCollector::loadFromFile('/nonexistent/asc.cache.php');
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // [rules, container, compiledPatterns]
    }
}
