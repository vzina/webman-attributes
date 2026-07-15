<?php
/**
 * AspectManagerCollectorTest.php
 * 测试 AspectManagerCollector 切面管道缓存
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\AspectManagerCollector;

class AspectManagerCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AspectManagerCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AspectManagerCollector::clear();
    }

    // ==================== has / get / set / insert ====================

    public function testHasReturnsFalseForNewEntry(): void
    {
        $this->assertFalse(AspectManagerCollector::has('App\\Service', 'test'));
    }

    public function testSetAndGet(): void
    {
        AspectManagerCollector::set('App\\Service', 'test', ['AspectA', 'AspectB']);

        $this->assertTrue(AspectManagerCollector::has('App\\Service', 'test'));
        $this->assertEquals(['AspectA', 'AspectB'], AspectManagerCollector::get('App\\Service', 'test'));
    }

    public function testGetWithoutMethodReturnsDefaultForClass(): void
    {
        AspectManagerCollector::set('App\\Service', 'index', ['AspectA']);
        AspectManagerCollector::set('App\\Service', 'show', ['AspectB']);

        // get without method → returns default []
        $all = AspectManagerCollector::get('App\\Service');
        $this->assertEquals([], $all);
    }

    public function testInsertAppendsToArray(): void
    {
        AspectManagerCollector::insert('App\\Service', 'test', 'AspectA');
        AspectManagerCollector::insert('App\\Service', 'test', 'AspectB');
        AspectManagerCollector::insert('App\\Service', 'test', 'AspectC');

        $result = AspectManagerCollector::get('App\\Service', 'test');
        $this->assertCount(3, $result);
        $this->assertEquals(['AspectA', 'AspectB', 'AspectC'], $result);
    }

    public function testSetOverwritesInsert(): void
    {
        AspectManagerCollector::insert('App\\Service', 'test', 'AspectA');
        AspectManagerCollector::set('App\\Service', 'test', ['Replaced']);

        $result = AspectManagerCollector::get('App\\Service', 'test');
        $this->assertEquals(['Replaced'], $result);
    }

    public function testGetDefaultForNonExistent(): void
    {
        $this->assertEquals([], AspectManagerCollector::get('App\\None', 'none'));
    }

    // ==================== clear ====================

    public function testClearRemovesAll(): void
    {
        AspectManagerCollector::insert('App\\A', 'test', 'AspectX');

        AspectManagerCollector::clear();

        $this->assertFalse(AspectManagerCollector::has('App\\A', 'test'));
    }

    // ==================== 多类/多方法隔离 ====================

    public function testClassIsolation(): void
    {
        AspectManagerCollector::insert('App\\A', 'test', 'AspectA');
        AspectManagerCollector::insert('App\\B', 'test', 'AspectB');

        $this->assertNotEquals(
            AspectManagerCollector::get('App\\A', 'test'),
            AspectManagerCollector::get('App\\B', 'test')
        );
    }

    public function testMethodIsolation(): void
    {
        AspectManagerCollector::insert('App\\Service', 'index', 'AspectA');
        AspectManagerCollector::insert('App\\Service', 'store', 'AspectB');

        $this->assertNotEquals(
            AspectManagerCollector::get('App\\Service', 'index'),
            AspectManagerCollector::get('App\\Service', 'store')
        );
    }
}
