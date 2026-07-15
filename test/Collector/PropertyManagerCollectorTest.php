<?php
/**
 * PropertyManagerCollectorTest.php
 * 测试 PropertyManagerCollector 属性处理器注册与查询
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\PropertyManagerCollector;

class PropertyManagerCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PropertyManagerCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        PropertyManagerCollector::clear();
    }

    // ==================== register ====================

    public function testRegisterSingleHandler(): void
    {
        $handler = fn(object $obj, string $cls, string $target, string $prop, $attr) => null;
        PropertyManagerCollector::register('App\\Attribute\\Inject', $handler);

        $this->assertFalse(PropertyManagerCollector::isEmpty());
    }

    public function testRegisterMultipleHandlersForSameAttribute(): void
    {
        $handler1 = fn() => 'first';
        $handler2 = fn() => 'second';

        PropertyManagerCollector::register('App\\Attribute\\Inject', $handler1);
        PropertyManagerCollector::register('App\\Attribute\\Inject', $handler2);

        // 两个都存在
        $container = PropertyManagerCollector::list();
        $this->assertCount(2, $container['App\\Attribute\\Inject']);
    }

    public function testRegisterDifferentAttributes(): void
    {
        PropertyManagerCollector::register('App\\Attribute\\Inject', fn() => null);
        PropertyManagerCollector::register('App\\Attribute\\Value', fn() => null);

        $container = PropertyManagerCollector::list();
        $this->assertArrayHasKey('App\\Attribute\\Inject', $container);
        $this->assertArrayHasKey('App\\Attribute\\Value', $container);
    }

    // ==================== isEmpty ====================

    public function testIsEmptyInitiallyTrue(): void
    {
        $this->assertTrue(PropertyManagerCollector::isEmpty());
    }

    public function testIsEmptyAfterRegister(): void
    {
        PropertyManagerCollector::register('App\\Attribute\\Inject', fn() => null);
        $this->assertFalse(PropertyManagerCollector::isEmpty());
    }

    public function testIsEmptyAfterClear(): void
    {
        PropertyManagerCollector::register('App\\Attribute\\Inject', fn() => null);
        PropertyManagerCollector::clear();
        $this->assertTrue(PropertyManagerCollector::isEmpty());
    }

    // ==================== get / get with default ====================

    public function testListReturnsAllHandlers(): void
    {
        $handler = fn() => 'test';
        PropertyManagerCollector::register('AttrA', $handler);

        $all = PropertyManagerCollector::list();
        $this->assertArrayHasKey('AttrA', $all);
        $this->assertSame($handler, $all['AttrA'][0]);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $this->assertNull(PropertyManagerCollector::get('nonexistent'));
    }

    public function testGetReturnsDefaultForMissing(): void
    {
        $this->assertEquals([], PropertyManagerCollector::get('nonexistent', []));
    }
}
