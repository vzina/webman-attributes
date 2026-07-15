<?php
/**
 * AstVisitorManagerTest.php
 * 测试 AstVisitorManager 访问器管理及 getVisitors 缓存
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use Vzina\Attributes\Ast\AstVisitorManager;
use Vzina\Attributes\Ast\SplPriorityQueue;

class AstVisitorManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 重置静态状态
        $ref = new ReflectionClass(AstVisitorManager::class);

        $queueProp = $ref->getProperty('queue');
        $queueProp->setAccessible(true);
        $queueProp->setValue(null);

        $valuesProp = $ref->getProperty('values');
        $valuesProp->setAccessible(true);
        $valuesProp->setValue([]);

        $cacheProp = $ref->getProperty('cachedVisitors');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue(null);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==================== insert / exists ====================

    public function testInsertAndExists(): void
    {
        AstVisitorManager::insert('App\\Visitor\\TestVisitor', 10);

        $this->assertTrue(AstVisitorManager::exists('App\\Visitor\\TestVisitor'));
        $this->assertFalse(AstVisitorManager::exists('App\\Visitor\\OtherVisitor'));
    }

    public function testInsertMultipleVisitors(): void
    {
        AstVisitorManager::insert('App\\Visitor\\A', 10);
        AstVisitorManager::insert('App\\Visitor\\B', 5);

        $this->assertTrue(AstVisitorManager::exists('App\\Visitor\\A'));
        $this->assertTrue(AstVisitorManager::exists('App\\Visitor\\B'));
    }

    // ==================== getQueue ====================

    public function testGetQueueReturnsInstance(): void
    {
        $queue = AstVisitorManager::getQueue();
        $this->assertInstanceOf(SplPriorityQueue::class, $queue);
    }

    public function testGetQueueIsSingleton(): void
    {
        $q1 = AstVisitorManager::getQueue();
        $q2 = AstVisitorManager::getQueue();
        $this->assertSame($q1, $q2);
    }

    // ==================== getVisitors (新增) ====================

    public function testGetVisitorsReturnsArrayInPriorityOrder(): void
    {
        AstVisitorManager::insert('App\\Visitor\\High', 100);
        AstVisitorManager::insert('App\\Visitor\\Low', 1);
        AstVisitorManager::insert('App\\Visitor\\Mid', 50);

        $visitors = AstVisitorManager::getVisitors();

        $this->assertIsArray($visitors);
        $this->assertCount(3, $visitors);
        $this->assertEquals('App\\Visitor\\High', $visitors[0]); // 最高优先级
    }

    public function testGetVisitorsIsCached(): void
    {
        AstVisitorManager::insert('App\\Visitor\\A', 10);

        $v1 = AstVisitorManager::getVisitors();
        $v2 = AstVisitorManager::getVisitors();

        $this->assertSame($v1, $v2); // 同一个缓存引用
    }

    public function testGetVisitorsCacheInvalidatesOnInsert(): void
    {
        AstVisitorManager::insert('App\\Visitor\\A', 10);

        $v1 = AstVisitorManager::getVisitors();
        $this->assertCount(1, $v1);

        AstVisitorManager::insert('App\\Visitor\\B', 20);

        $v2 = AstVisitorManager::getVisitors();
        $this->assertCount(2, $v2);
        $this->assertNotEquals($v1, $v2);
    }

    public function testGetVisitorsEmptyQueue(): void
    {
        $visitors = AstVisitorManager::getVisitors();
        $this->assertIsArray($visitors);
        $this->assertEmpty($visitors);
    }

    // ==================== __callStatic 代理 ====================

    public function testCallStaticProxiesToQueue(): void
    {
        AstVisitorManager::insert('X', 10);
        $this->assertTrue(AstVisitorManager::valid());
    }
}
