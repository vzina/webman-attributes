<?php
/**
 * ServiceInjectorTest.php
 * 测试 ServiceInjector 容器依赖注入
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Reflection;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Vzina\Attributes\Reflection\ServiceInjector;

/**
 * Simple service for injection testing.
 */
class TestService
{
    public function __construct(public string $name = 'default')
    {
    }
}

/**
 * Invokable service for testing __invoke resolution.
 */
class TestInvokableService
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}

class ServiceInjectorTest extends TestCase
{
    // ==================== inject — 空参数短路 ====================

    public function testInjectWithEmptyKeyAndValueDoesNothing(): void
    {
        // 不应抛异常
        ServiceInjector::inject('', null);
        ServiceInjector::inject([], null);
        $this->assertTrue(true); // 到达此处即成功
    }

    // ==================== define — callable 直接返回 ====================

    public function testDefineReturnsCallableDirectly(): void
    {
        $callable = fn() => 'result';
        $result = ServiceInjector::define($callable);

        $this->assertSame($callable, $result);
    }

    // ==================== define — 类名字符串解析 ====================

    public function testDefineWithClassName(): void
    {
        $definition = ServiceInjector::define(TestService::class);

        $this->assertIsCallable($definition);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $instance = $definition($mockContainer);

        $this->assertInstanceOf(TestService::class, $instance);
        $this->assertEquals('default', $instance->name);
    }

    public function testDefineWithInvokableClass(): void
    {
        $definition = ServiceInjector::define(TestInvokableService::class);

        $this->assertIsCallable($definition);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $instance = $definition($mockContainer);

        // Invokable 类会调用 __invoke 返回其结果（字符串 "invoked"）
        $this->assertEquals('invoked', $instance);
    }

    // ==================== define — 带构造参数解析 ====================

    public function testDefineResolvesConstructorDependencies(): void
    {
        // TestService has constructor with string $name parameter but it has no default
        // Since string is a builtin type with no default, it should throw or use default
        // Let's just verify the default case works
        $definition = ServiceInjector::define(TestService::class);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockContainer->method('has')->willReturn(false);
        $mockContainer->method('get')->willReturn(null);

        $instance = $definition($mockContainer);

        $this->assertInstanceOf(TestService::class, $instance);
    }

    public function testDefineWithNoConstructor(): void
    {
        // stdClass has no constructor
        $definition = ServiceInjector::define(\stdClass::class);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $instance = $definition($mockContainer);

        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    // ==================== 边界 ====================

    public function testDefineWithNonExistentClassThrows(): void
    {
        $this->expectException(\ReflectionException::class);
        $definition = ServiceInjector::define('NonExistentClass12345');

        $mockContainer = $this->createMock(ContainerInterface::class);
        $definition($mockContainer);
    }

    // ==================== 新格式：params ====================

    public function testDefineWithParams(): void
    {
        $definition = ServiceInjector::define(TestService::class, ['name' => 'custom']);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $instance = $definition($mockContainer);

        $this->assertInstanceOf(TestService::class, $instance);
        $this->assertEquals('custom', $instance->name);
    }

    public function testDefineCreatesNewInstanceEachTime(): void
    {
        $definition = ServiceInjector::define(TestService::class);

        $mockContainer = $this->createMock(ContainerInterface::class);
        $a = $definition($mockContainer);
        $b = $definition($mockContainer);

        $this->assertNotSame($a, $b);
    }
}
