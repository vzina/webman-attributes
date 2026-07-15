<?php
/**
 * SplPriorityQueueTest.php
 * 测试自定义 SplPriorityQueue 稳定排序特性
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Ast\SplPriorityQueue;

class SplPriorityQueueTest extends TestCase
{
    // ==================== 基本操作 ====================

    public function testInsertAndExtract(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('A', 10);
        $queue->insert('B', 20);

        $this->assertEquals('B', $queue->extract()); // 高优先级先出
        $this->assertEquals('A', $queue->extract());
    }

    public function testIsEmpty(): void
    {
        $queue = new SplPriorityQueue();
        $this->assertTrue($queue->isEmpty());

        $queue->insert('X', 1);
        $this->assertFalse($queue->isEmpty());
    }

    public function testValid(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('X', 1);

        $queue->rewind();
        $this->assertTrue($queue->valid());

        $queue->extract();
        $this->assertFalse($queue->valid());
    }

    // ==================== 稳定排序（同优先级 FIFO） ====================

    public function testStableOrderOnSamePriority(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('First', 10);
        $queue->insert('Second', 10);
        $queue->insert('Third', 10);

        $this->assertEquals('First', $queue->extract());
        $this->assertEquals('Second', $queue->extract());
        $this->assertEquals('Third', $queue->extract());
    }

    public function testPriorityTakesPrecedenceOverInsertionOrder(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('Low', 1);
        $queue->insert('High', 100);
        $queue->insert('Mid', 50);

        $this->assertEquals('High', $queue->extract());
        $this->assertEquals('Mid', $queue->extract());
        $this->assertEquals('Low', $queue->extract());
    }

    public function testMixedPrioritiesWithStableOrder(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('A1', 5);
        $queue->insert('B', 10);
        $queue->insert('A2', 5);
        $queue->insert('C', 10);

        // 同优先级保持插入顺序
        $this->assertEquals('B', $queue->extract()); // prio 10, first
        $this->assertEquals('C', $queue->extract()); // prio 10, second
        $this->assertEquals('A1', $queue->extract()); // prio 5, first
        $this->assertEquals('A2', $queue->extract()); // prio 5, second
    }

    // ==================== 遍历 ====================

    public function testCurrentAndNext(): void
    {
        $queue = new SplPriorityQueue();
        $queue->insert('A', 10);
        $queue->insert('B', 5);

        $queue->rewind();
        $this->assertEquals('A', $queue->current());

        $queue->next();
        $this->assertEquals('B', $queue->current());
    }
}
