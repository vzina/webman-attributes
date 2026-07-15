<?php
/**
 * ReflectionManagerTest.php
 * 测试 ReflectionManager 反射缓存及 clearReflectionCache 内存清理
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Reflection;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use PhpDocReader\PhpDocReader;
use Vzina\Attributes\Reflection\ReflectionManager;

class ReflectionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ReflectionManager::clearReflectionCache();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ReflectionManager::clearReflectionCache();
    }

    // ==================== reflectClass ====================

    public function testReflectClass(): void
    {
        $ref = ReflectionManager::reflectClass(__CLASS__);
        $this->assertInstanceOf(ReflectionClass::class, $ref);
        $this->assertEquals(__CLASS__, $ref->getName());
    }

    public function testReflectClassIsCached(): void
    {
        $r1 = ReflectionManager::reflectClass(__CLASS__);
        $r2 = ReflectionManager::reflectClass(__CLASS__);

        $this->assertSame($r1, $r2); // 同一个实例
    }

    public function testReflectClassUnknown(): void
    {
        $this->expectException(\ReflectionException::class);
        ReflectionManager::reflectClass('NonExistentClass12345');
    }

    // ==================== reflectMethod ====================

    public function testReflectMethod(): void
    {
        $ref = ReflectionManager::reflectMethod(__CLASS__, 'testReflectMethod');
        $this->assertInstanceOf(ReflectionMethod::class, $ref);
        $this->assertEquals('testReflectMethod', $ref->getName());
    }

    public function testReflectMethodIsCached(): void
    {
        $m1 = ReflectionManager::reflectMethod(__CLASS__, 'testReflectMethodIsCached');
        $m2 = ReflectionManager::reflectMethod(__CLASS__, 'testReflectMethodIsCached');

        $this->assertSame($m1, $m2);
    }

    // ==================== reflectProperty ====================

    public function testReflectPropertyIsCached(): void
    {
        // Use a class that has a property we know about
        $p1 = ReflectionManager::reflectProperty(ReflectionManagerTest::class, 'testProperty');
        $p2 = ReflectionManager::reflectProperty(ReflectionManagerTest::class, 'testProperty');

        $this->assertSame($p1, $p2);
    }

    // ==================== reflectPropertyNames ====================

    public function testReflectPropertyNames(): void
    {
        $names = ReflectionManager::reflectPropertyNames(ReflectionManagerTest::class);
        $this->assertIsArray($names);
        $this->assertContains('testProperty', $names);
    }

    // ==================== getPhpDocReader ====================

    public function testGetPhpDocReader(): void
    {
        $reader = ReflectionManager::getPhpDocReader();
        $this->assertInstanceOf(PhpDocReader::class, $reader);
    }

    public function testGetPhpDocReaderIsSingleton(): void
    {
        $r1 = ReflectionManager::getPhpDocReader();
        $r2 = ReflectionManager::getPhpDocReader();

        $this->assertSame($r1, $r2);
    }

    // ==================== clearReflectionCache (新增) ====================

    public function testClearReflectionCacheRemovesReflections(): void
    {
        // 先创建一些缓存
        ReflectionManager::reflectClass(__CLASS__);
        ReflectionManager::reflectMethod(__CLASS__, 'testClearReflectionCacheRemovesReflections');

        // 清理
        ReflectionManager::clearReflectionCache();

        // PhpDocReader 保留
        $reader = ReflectionManager::getPhpDocReader();
        $this->assertInstanceOf(PhpDocReader::class, $reader);

        // 其他反射应该重新创建（非同一个实例）
        $ref = ReflectionManager::reflectClass(__CLASS__);
        $this->assertInstanceOf(ReflectionClass::class, $ref);
    }

    // ==================== 辅助 ====================

    /** @var string 测试用的属性 */
    public string $testProperty = 'test';
}
