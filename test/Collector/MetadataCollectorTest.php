<?php
/**
 * MetadataCollectorTest.php
 * 测试 MetadataCollector 核心 CRUD 方法及 PHP 文件缓存
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\AttributeCollector;

class MetadataCollectorTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        AttributeCollector::clear();
        $this->cacheDir = sys_get_temp_dir() . '/vzina_test_' . uniqid();
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AttributeCollector::clear();
        // 清理临时文件
        array_map('unlink', glob("{$this->cacheDir}/*.php") ?: []);
        @rmdir($this->cacheDir);
    }

    // ==================== get / set 基础操作 ====================

    public function testSetAndGet(): void
    {
        AttributeCollector::set('_c.TestAttr', 'value');
        $this->assertEquals('value', AttributeCollector::get('_c.TestAttr'));
    }

    public function testGetWithDefault(): void
    {
        $this->assertNull(AttributeCollector::get('nonexistent.key'));
        $this->assertEquals('fallback', AttributeCollector::get('nonexistent.key', 'fallback'));
    }

    public function testGetNestedKey(): void
    {
        AttributeCollector::set('_c.Nested.Key', 'deep');
        $this->assertEquals('deep', AttributeCollector::get('_c.Nested.Key'));
    }

    // ==================== has 方法 ====================

    public function testHasExistingKey(): void
    {
        AttributeCollector::set('_c.Test', 'val');
        $this->assertTrue(AttributeCollector::has('_c.Test'));
    }

    public function testHasMissingKey(): void
    {
        $this->assertFalse(AttributeCollector::has('nonexistent'));
    }

    // ==================== clear 方法 ====================

    public function testClearSpecificKey(): void
    {
        AttributeCollector::set('_c.Keep', 'keep');
        AttributeCollector::set('_c.Remove', 'remove');

        AttributeCollector::clear('_c.Remove');

        $this->assertTrue(AttributeCollector::has('_c.Keep'));
        $this->assertFalse(AttributeCollector::has('_c.Remove'));
    }

    public function testClearAll(): void
    {
        AttributeCollector::set('_c.A', 'a');
        AttributeCollector::set('_c.B', 'b');

        AttributeCollector::clear();

        $this->assertFalse(AttributeCollector::has('_c.A'));
        $this->assertFalse(AttributeCollector::has('_c.B'));
    }

    // ==================== list 方法 ====================

    public function testList(): void
    {
        AttributeCollector::set('_c.A', 'a');
        AttributeCollector::set('_c.B', 'b');

        $all = AttributeCollector::list();

        $this->assertIsArray($all);
        $this->assertEquals('a', $all['_c']['A']);
        $this->assertEquals('b', $all['_c']['B']);
    }

    public function testListEmpty(): void
    {
        AttributeCollector::clear();
        $this->assertEmpty(AttributeCollector::list());
    }

    // ==================== override 方法 ====================

    public function testOverrideExisting(): void
    {
        AttributeCollector::set('_c.Test', 'old');
        AttributeCollector::override('_c.Test', fn($v) => $v . '-new');

        $this->assertEquals('old-new', AttributeCollector::get('_c.Test'));
    }

    public function testOverrideNewKey(): void
    {
        AttributeCollector::override('_c.NewKey', fn($v) => 'fresh');

        $this->assertEquals('fresh', AttributeCollector::get('_c.NewKey'));
    }

    // ==================== serialize / deserialize ====================

    public function testSerializeAndDeserialize(): void
    {
        AttributeCollector::set('_c.A', 'alpha');
        AttributeCollector::set('_c.B', ['nested' => true]);

        $serialized = AttributeCollector::serialize();
        AttributeCollector::clear();

        AttributeCollector::deserialize($serialized);

        $this->assertEquals('alpha', AttributeCollector::get('_c.A'));
        $this->assertEquals(['nested' => true], AttributeCollector::get('_c.B'));
    }

    public function testDeserializeWithDisallowedClasses(): void
    {
        // 模拟注入对象类名的恶意缓存数据
        $malicious = serialize(['_c.evil' => new \stdClass()]);

        // 应用修复后的 deserialize 方法
        AttributeCollector::deserialize($malicious);

        // 对象被转换为 __PHP_Incomplete_Class，不再是有效的 stdClass
        $value = AttributeCollector::get('_c.evil');
        // 由于反序列化了无效类，值可能是 __PHP_Incomplete_Class 或空数组
        $this->assertTrue(
            $value instanceof \__PHP_Incomplete_Class || ! is_object($value)
        );
    }

    // ==================== exportToFile / loadFromFile (新增) ====================

    public function testExportToFile(): void
    {
        AttributeCollector::set('_c.X', 'exported');
        AttributeCollector::set('_c.Y', ['a' => 1]);

        $file = $this->cacheDir . '/MetadataCollector.cache.php';
        AttributeCollector::exportToFile($file);

        $this->assertFileExists($file);
        $this->assertStringContainsString('<?php', file_get_contents($file));
    }

    public function testLoadFromFile(): void
    {
        AttributeCollector::set('_c.X', 'fromFile');
        $file = $this->cacheDir . '/MetadataCollector.cache.php';
        AttributeCollector::exportToFile($file);

        AttributeCollector::clear();
        $this->assertNull(AttributeCollector::get('_c.X'));

        AttributeCollector::loadFromFile($file);

        $this->assertEquals('fromFile', AttributeCollector::get('_c.X'));
    }

    public function testLoadFromFileNonExistent(): void
    {
        $result = AttributeCollector::loadFromFile('/nonexistent/path.cache.php');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExportToFileWithNestedData(): void
    {
        $data = [
            'App\\Test\\A' => ['_c' => ['AttrA' => new \stdClass()]],
            'App\\Test\\B' => ['_m' => ['test' => ['AttrB' => 'val']]],
        ];
        foreach ($data as $key => $value) {
            AttributeCollector::set($key, $value);
        }

        $file = $this->cacheDir . '/nested.cache.php';
        AttributeCollector::exportToFile($file);

        AttributeCollector::clear();
        AttributeCollector::loadFromFile($file);

        $this->assertIsArray(AttributeCollector::get('App\\Test\\A'));
        $this->assertArrayHasKey('_c', AttributeCollector::get('App\\Test\\A'));
    }
}
