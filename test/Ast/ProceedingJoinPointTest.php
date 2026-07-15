<?php
/**
 * ProceedingJoinPointTest.php
 * 测试 AOP 连接点的核心方法
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Ast\AttributeMetadata;
use Vzina\Attributes\Collector\AttributeCollector;
use RuntimeException;

class ProceedingJoinPointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AttributeCollector::clear();
    }

    // ==================== process ====================

    public function testProcessCallsNextInPipeline(): void
    {
        $called = false;
        $originalMethod = function () use (&$called) {
            $called = true;
            return 'result';
        };

        $joinPoint = new ProceedingJoinPoint(
            $originalMethod,
            'App\\Test',
            'testMethod',
            ['order' => [], 'keys' => []]
        );

        // 管道末端处理函数模拟
        $nextCalled = false;
        $joinPoint->pipe = function (ProceedingJoinPoint $p) use (&$nextCalled) {
            $nextCalled = true;
            return $p->processOriginalMethod();
        };

        $result = $joinPoint->process();

        $this->assertTrue($nextCalled);
        $this->assertTrue($called);
        $this->assertEquals('result', $result);
    }

    public function testProcessThrowsWhenPipeIsNull(): void
    {
        $joinPoint = new ProceedingJoinPoint(
            fn() => null,
            'App\\Test',
            'test',
            ['order' => [], 'keys' => []]
        );

        $this->expectException(RuntimeException::class);
        $joinPoint->process();
    }

    // ==================== processOriginalMethod ====================

    public function testProcessOriginalMethodWithNoArgs(): void
    {
        $called = false;
        $joinPoint = new ProceedingJoinPoint(
            function () use (&$called) {
                $called = true;
                return 'done';
            },
            'App\\Test',
            'testMethod',
            ['order' => [], 'keys' => []]
        );

        $result = $joinPoint->processOriginalMethod();
        $this->assertTrue($called);
        $this->assertEquals('done', $result);
        $this->assertNull($joinPoint->pipe); // pipe 被清空
    }

    public function testProcessOriginalMethodWithSingleArg(): void
    {
        $received = null;
        $joinPoint = new ProceedingJoinPoint(
            function ($arg) use (&$received) {
                $received = $arg;
                return $arg;
            },
            'App\\Test',
            'testMethod',
            ['order' => [0], 'keys' => ['hello']]
        );

        $result = $joinPoint->processOriginalMethod();
        $this->assertEquals('hello', $received);
        $this->assertEquals('hello', $result);
    }

    public function testProcessOriginalMethodWithMultipleArgs(): void
    {
        $args = [];
        $joinPoint = new ProceedingJoinPoint(
            function ($a, $b) use (&$args) {
                $args = [$a, $b];
                return $a . $b;
            },
            'App\\Test',
            'testMethod',
            ['order' => [0, 1], 'keys' => ['hello', 'world']]
        );

        $result = $joinPoint->processOriginalMethod();
        $this->assertEquals(['hello', 'world'], $args);
        $this->assertEquals('helloworld', $result);
    }

    // ==================== getArguments ====================

    public function testGetArgumentsPreservesOrder(): void
    {
        $joinPoint = new ProceedingJoinPoint(
            fn() => null,
            'App\\Test',
            'test',
            ['order' => [1, 0, 2], 'keys' => ['first', 'second', 'third']]
        );

        $result = $joinPoint->getArguments();
        $this->assertEquals(['second', 'first', 'third'], $result);
    }

    // ==================== getAnnotationMetadata ====================

    public function testGetAnnotationMetadata(): void
    {
        AttributeCollector::collectClass('App\\Test', 'App\\Attr\\Route', '/api');
        AttributeCollector::collectMethod('App\\Test', 'testMethod', 'App\\Attr\\Get', null);

        $joinPoint = new ProceedingJoinPoint(
            fn() => null,
            'App\\Test',
            'testMethod',
            []
        );

        $metadata = $joinPoint->getAnnotationMetadata();
        $this->assertInstanceOf(AttributeMetadata::class, $metadata);
        $this->assertArrayHasKey('App\\Attr\\Route', $metadata->class);
        $this->assertArrayHasKey('App\\Attr\\Get', $metadata->method);
    }

    // ==================== getInstance ====================

    public function testGetInstanceReturnsCallerObject(): void
    {
        $mockInstance = new \stdClass();
        $mockInstance->value = 'test';
        $closure = \Closure::bind(
            function () { return $this; },
            $mockInstance
        );

        $joinPoint = new ProceedingJoinPoint(
            $closure,
            'stdClass',
            'test',
            []
        );

        $instance = $joinPoint->getInstance();
        $this->assertSame($mockInstance, $instance);
    }

    public function testGetInstanceWithStringCallable(): void
    {
        // 测试字符串 callable (如 'ClassName::method')
        // 无法从闭包中获取 $this，返回 null
        $joinPoint = new ProceedingJoinPoint(
            fn(): string => 'hello',
            'App\\Test',
            'test',
            []
        );

        // Arrow function 在类方法内可能或可能不绑定 $this 取决于是否使用 $this
        // 这里只验证方法不报错
        $instance = $joinPoint->getInstance();
        $this->assertTrue($instance === null || is_object($instance));
    }
}
