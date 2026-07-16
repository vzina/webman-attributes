<?php
/**
 * AttributesTest.php
 * 端到端测试所有注解类的核心逻辑：创建、属性读取、collect 方法行为
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Attribute\Cacheable;
use Vzina\Attributes\Attribute\Constants;
use Vzina\Attributes\Attribute\ConstantsTrait;
use Vzina\Attributes\Attribute\Crontab;
use Vzina\Attributes\Attribute\Depend;
use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Attribute\Listener;
use Vzina\Attributes\Attribute\Message;
use Vzina\Attributes\Attribute\Middleware;
use Vzina\Attributes\Attribute\Process;
use Vzina\Attributes\Attribute\Retry;
use Vzina\Attributes\Attribute\RetryAspect;
use Vzina\Attributes\Attribute\Trace;
use Vzina\Attributes\Attribute\TraceAspect;
use Vzina\Attributes\Attribute\TraceContext;
use Vzina\Attributes\Trace\W3CTracer;
use Vzina\Attributes\Attribute\Transactional;
use Vzina\Attributes\Attribute\TransactionalAspect;
use Vzina\Attributes\Attribute\Validate;
use Vzina\Attributes\Attribute\ValidateAspect;
use Vzina\Attributes\Attribute\Command;
use Vzina\Attributes\Attribute\CommandHandler;
use Vzina\Attributes\Attribute\Value;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Collector\ConstantsCollector;
use Vzina\Attributes\Collector\PropertyManagerCollector;

class AttributesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../Fixtures/TestFixtures.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        AttributeCollector::clear();
        ConstantsCollector::clear();
        PropertyManagerCollector::clear();
    }

    // ==================== Inject ====================

    public function testInjectDefaultValues(): void
    {
        $inject = new Inject();

        $this->assertNull($inject->value);
        $this->assertTrue($inject->required);
        $this->assertFalse($inject->lazy);
        $this->assertEquals('', $inject->targetValue);
    }

    public function testInjectLazyMode(): void
    {
        $inject = new Inject(value: 'App\Service\Bar', lazy: true);

        $this->assertEquals('App\Service\Bar', $inject->value);
        $this->assertTrue($inject->lazy);
    }

    public function testInjectOptional(): void
    {
        $inject = new Inject(required: false);
        $this->assertFalse($inject->required);
    }

    public function testInjectCollectPropertyStoresInAttributeCollector(): void
    {
        $inject = new Inject(value: 'App\Service\Logger');
        $inject->collectProperty('App\Controller\TestController', 'logger');

        $attr = AttributeCollector::getClassPropertyAttribute('App\Controller\TestController', 'logger');
        $this->assertArrayHasKey(Inject::class, $attr);
        $this->assertSame($inject, $attr[Inject::class]);
    }

    // ==================== Value ====================

    public function testValueAttribute(): void
    {
        $value = new Value(key: 'app.name');

        $this->assertEquals('app.name', $value->key);
    }

    public function testValueCollectProperty(): void
    {
        $value = new Value(key: 'mail.host');
        $value->collectProperty('App\Service\Mailer', 'host');

        $attr = AttributeCollector::getClassPropertyAttribute('App\Service\Mailer', 'host');
        $this->assertArrayHasKey(Value::class, $attr);
    }

    // ==================== Cacheable ====================

    public function testCacheableDefaultValues(): void
    {
        $cache = new Cacheable();

        $this->assertNull($cache->prefix);
        $this->assertNull($cache->value);
        $this->assertNull($cache->ttl);
        $this->assertEquals(0, $cache->offset);
        $this->assertEquals(0, $cache->aheadSeconds);
        $this->assertEquals(10, $cache->lockSeconds);
        $this->assertNull($cache->group);
        $this->assertFalse($cache->collect);
        $this->assertFalse($cache->evict);
        $this->assertFalse($cache->put);
    }

    public function testCacheableEvictMode(): void
    {
        $cache = new Cacheable(prefix: 'order', evict: true);
        $this->assertTrue($cache->evict);
    }

    public function testCacheableCollectMode(): void
    {
        $cache = new Cacheable(prefix: 'user', collect: true, ttl: 600);
        $this->assertTrue($cache->collect);
        $this->assertEquals(600, $cache->ttl);
    }

    public function testCacheableCollectMethod(): void
    {
        $cache = new Cacheable(prefix: 'data', ttl: 300);
        $cache->collectMethod('App\Service\OrderService', 'find');

        $attr = AttributeCollector::getClassMethodAttribute('App\Service\OrderService', 'find');
        $this->assertArrayHasKey(Cacheable::class, $attr);
    }

    // ==================== Listener ====================

    public function testListenerSingleEvent(): void
    {
        $listener = new Listener(event: 'user.registered');

        $this->assertEquals('user.registered', $listener->event);
        $this->assertEquals(0, $listener->priority);
    }

    public function testListenerWithPriority(): void
    {
        $listener = new Listener(event: 'order.created', priority: 10);

        $this->assertEquals(10, $listener->priority);
    }

    public function testListenerCollectMethod(): void
    {
        $listener = new Listener(event: 'user.login');
        $listener->collectMethod('App\Listener\LoginListener', 'handle');

        $attr = AttributeCollector::getClassMethodAttribute('App\Listener\LoginListener', 'handle');
        $this->assertArrayHasKey(Listener::class, $attr);
    }

    // ==================== Depend ====================

    public function testDependDefaultValues(): void
    {
        $depend = new Depend();

        $this->assertNull($depend->id);
        $this->assertEquals(0, $depend->priority);
        $this->assertEquals([], $depend->params);
        $this->assertFalse($depend->singleton);
    }

    public function testDependWithParams(): void
    {
        $depend = new Depend(id: 'mailer', params: ['host' => 'smtp.example.com']);

        $this->assertEquals('mailer', $depend->id);
        $this->assertEquals(['host' => 'smtp.example.com'], $depend->params);
    }

    public function testDependSingleton(): void
    {
        $depend = new Depend(id: 'cache', singleton: true);
        $this->assertTrue($depend->singleton);
    }

    public function testDependCollectClass(): void
    {
        $depend = new Depend(id: 'logger');
        $depend->collectClass('App\Service\FileLogger');

        $classes = AttributeCollector::getClassesByAttribute(Depend::class);
        $this->assertArrayHasKey('App\Service\FileLogger', $classes);
    }

    // ==================== Crontab ====================

    public function testCrontabAttribute(): void
    {
        $cron = new Crontab(rule: '*/5 * * * *', name: 'sync');

        $this->assertEquals('*/5 * * * *', $cron->rule);
        $this->assertEquals('sync', $cron->name);
    }

    public function testCrontabDefaultName(): void
    {
        $cron = new Crontab(rule: '* * * * *');
        $this->assertNull($cron->name);
    }

    public function testCrontabCollectMethod(): void
    {
        $cron = new Crontab(rule: '0 * * * *');
        $cron->collectMethod('App\Crontab\TestTask', 'handle');

        $attr = AttributeCollector::getClassMethodAttribute('App\Crontab\TestTask', 'handle');
        $this->assertArrayHasKey(Crontab::class, $attr);
    }

    public function testCrontabCollectClassStoresClassAttribute(): void
    {
        // 修复后 collectClass 总是存储类级别属性
        $cron = new Crontab(rule: '* * * * *');
        $cron->collectClass(__CLASS__);

        $classes = AttributeCollector::getClassesByAttribute(Crontab::class);
        $this->assertArrayHasKey(__CLASS__, $classes);

        // 没有 handle 方法 → 不存储方法级别属性
        $methods = AttributeCollector::get('__CLASS__._m');
        $this->assertEmpty($methods);
    }

    // ==================== Constants ====================

    public function testConstantsCollectClass(): void
    {
        $class = \Vzina\Attributes\Tests\Fixtures\ConstantsClass::class;

        $constants = new Constants();
        $constants->collectClass($class);

        // Constants::collectClass() 直接写 ConstantsCollector，不写 AttributeCollector
        $result = ConstantsCollector::get($class);
        $this->assertNotNull($result);
    }

    // ==================== Message ====================

    public function testMessageAttribute(): void
    {
        $msg = new Message('Active status');
        $this->assertEquals('Active status', $msg->value);
        $this->assertEquals('message', $msg->key);
    }

    public function testMessageCustomKey(): void
    {
        $msg = new Message('Active', key: 'description');
        $this->assertEquals('description', $msg->key);
    }

    public function testMessageLowerCaseKey(): void
    {
        $msg = new Message('test', key: 'User_Name');
        $this->assertEquals('username', $msg->getLowerCaseKey());

        $msg2 = new Message('test', key: 'message');
        $this->assertEquals('message', $msg2->getLowerCaseKey());
    }

    // ==================== Process ====================

    public function testProcessAttribute(): void
    {
        $process = new Process(name: 'metrics', count: 2);

        $this->assertEquals('metrics', $process->name);
        $this->assertEquals(2, $process->count);
    }

    // ==================== ConstantsTrait ====================

    public function testConstantsTraitResolvesMessage(): void
    {
        ConstantsCollector::set('App\Enums\Status.1.message', 'Active');

        // 模拟 ConstantsTrait::__callStatic('getMessage', [1])
        $result = ConstantsCollector::getTransValue('App\Enums\Status', 'message', [1]);
        $this->assertEquals('Active', $result);
    }

    public function testConstantsTraitMissingMessageReturnsEmpty(): void
    {
        $result = ConstantsCollector::getTransValue('App\Enums\Status', 'message', [999]);
        $this->assertEquals('', $result);
    }

    // ==================== Transactional ====================

    public function testTransactionalDefaultValues(): void
    {
        $attr = new Transactional();

        $this->assertEquals('default', $attr->connection);
        $this->assertEquals(1, $attr->attempts);
    }

    public function testTransactionalCustomConnection(): void
    {
        $attr = new Transactional(connection: 'mysql_writer', attempts: 3);

        $this->assertEquals('mysql_writer', $attr->connection);
        $this->assertEquals(3, $attr->attempts);
    }

    public function testTransactionalCollectMethodStoresInAttributeCollector(): void
    {
        $attr = new Transactional(connection: 'db');
        $attr->collectMethod('App\Service\OrderService', 'placeOrder');

        $methods = AttributeCollector::getClassMethodAttribute('App\Service\OrderService', 'placeOrder');
        $this->assertArrayHasKey(Transactional::class, $methods);
        $this->assertSame($attr, $methods[Transactional::class]);
    }

    public function testTransactionalAspectHasAttributes(): void
    {
        $aspect = new TransactionalAspect();
        $this->assertContains(Transactional::class, $aspect->attributes);
    }

    public function testTransactionalAspectNoAttrPassesThrough(): void
    {
        $aspect = new TransactionalAspect();
        $called = false;

        $point = $this->createJoinPoint(function () use (&$called) {
            $called = true;
            return 'ok';
        });

        // clear 后无 Transactional 属性 → 直接透传
        $result = $aspect->process($point);

        $this->assertTrue($called);
        $this->assertEquals('ok', $result);
    }

    public function testTransactionalAspectCommitsOnSuccess(): void
    {
        $committed = false;

        $handler = static function (string $conn, \Closure $cb) use (&$committed) {
            $result = $cb();
            $committed = true;
            return $result;
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Transactional::class,
            new Transactional(transactionHandler: $handler)
        );

        $aspect = new TransactionalAspect();
        $point  = $this->createJoinPoint(fn() => 'success');
        $result = $aspect->process($point);

        $this->assertTrue($committed);
        $this->assertEquals('success', $result);
    }

    public function testTransactionalAspectRollbackOnFailure(): void
    {
        $rollbacked = false;

        $handler = static function (string $conn, \Closure $cb) use (&$rollbacked) {
            try {
                return $cb();
            } catch (\Throwable) {
                $rollbacked = true;
                throw new \RuntimeException('rolled back');
            }
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Transactional::class,
            new Transactional(transactionHandler: $handler)
        );

        $aspect = new TransactionalAspect();
        $point  = $this->createJoinPoint(fn() => throw new \InvalidArgumentException('boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rolled back');
        $aspect->process($point);
    }

    public function testTransactionalAspectDeadlockRetry(): void
    {
        $attempts  = 0;
        $committed = false;

        $handler = static function (string $conn, \Closure $cb) use (&$attempts, &$committed) {
            $attempts++;
            if ($attempts < 3) {
                $e = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
                $e->errorInfo = [null, '40001'];
                throw new \RuntimeException('Deadlock', 1213, $e);
            }
            $result = $cb();
            $committed = true;
            return $result;
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Transactional::class,
            new Transactional(attempts: 3, transactionHandler: $handler)
        );

        $aspect = new TransactionalAspect();
        $point  = $this->createJoinPoint(fn() => 'retry_success');
        $result = $aspect->process($point);

        $this->assertEquals(3, $attempts, 'Should retry 3 times');
        $this->assertTrue($committed);
        $this->assertEquals('retry_success', $result);
    }

    // ==================== Validate ====================

    public function testValidateDefaultValues(): void
    {
        $v = new Validate();

        $this->assertEmpty($v->rules);
        $this->assertEmpty($v->messages);
        $this->assertNull($v->requestParam);
    }

    public function testValidateWithRules(): void
    {
        $v = new Validate(
            rules: ['name' => 'required|min:3'],
            messages: ['name.required' => '必填'],
            requestParam: 'req'
        );

        $this->assertEquals(['name' => 'required|min:3'], $v->rules);
        $this->assertEquals(['name.required' => '必填'], $v->messages);
        $this->assertEquals('req', $v->requestParam);
    }

    public function testValidateAspectHasAttributes(): void
    {
        $aspect = new ValidateAspect();
        $this->assertContains(Validate::class, $aspect->attributes);
    }

    public function testValidateAspectPassesThroughWhenNoAttribute(): void
    {
        $aspect  = new ValidateAspect();
        $called  = false;
        $point   = $this->createJoinPoint(function () use (&$called) { $called = true; });
        $aspect->process($point);
        $this->assertTrue($called);
    }

    public function testValidateAspectSkipsWhenNoRequest(): void
    {
        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Validate::class,
            new Validate(rules: ['name' => 'required'])
        );

        $aspect  = new ValidateAspect();
        $called  = false;
        $point   = $this->createJoinPoint(function () use (&$called) { $called = true; });
        $aspect->process($point);
        $this->assertTrue($called);
    }

    public function testValidateAspectThrowsOnValidationFailure(): void
    {
        $handler = static function (array $data, array $rules, array $msgs): array {
            return ['name' => ['The name field is required.']];
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Validate::class,
            new Validate(rules: ['name' => 'required'], requestParam: 'request', validator: $handler)
        );

        $aspect = new ValidateAspect();
        $points   = $this->createJoinPoint(fn() => 'ok');
        $ref = new \ReflectionProperty($points, 'arguments');
        $ref->setValue($points, ['keys' => ['request' => new \stdClass], 'order' => []]);

        $this->expectException(\Vzina\Attributes\Exception\ValidateException::class);
        $aspect->process($points);
    }

    public function testValidateAspectPassesOnValidationSuccess(): void
    {
        $handler = static function (array $data, array $rules, array $msgs): array {
            return []; // validation passed
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Validate::class,
            new Validate(rules: ['name' => 'required'], requestParam: 'request', validator: $handler)
        );

        $aspect  = new ValidateAspect();
        $called  = false;
        $points   = $this->createJoinPoint(function () use (&$called) { $called = true; return 'ok'; });
        $ref = new \ReflectionProperty($points, 'arguments');
        $ref->setValue($points, ['keys' => ['request' => new \stdClass], 'order' => []]);

        $result = $aspect->process($points);
        $this->assertTrue($called);
        $this->assertEquals('ok', $result);
    }

    // ==================== Retry ====================

    public function testRetryDefaultValues(): void
    {
        $r = new Retry();

        $this->assertEquals(3, $r->maxAttempts);
        $this->assertEquals(100, $r->delayMs);
        $this->assertSame(1.0, $r->backoff);
        $this->assertEmpty($r->on);
    }

    public function testRetryCustomValues(): void
    {
        $r = new Retry(maxAttempts: 5, delayMs: 500, backoff: 2.0, on: [\RuntimeException::class]);

        $this->assertEquals(5, $r->maxAttempts);
        $this->assertEquals(500, $r->delayMs);
        $this->assertSame(2.0, $r->backoff);
        $this->assertEquals([\RuntimeException::class], $r->on);
    }

    public function testRetryAspectHasAttributes(): void
    {
        $aspect = new RetryAspect();
        $this->assertContains(Retry::class, $aspect->attributes);
    }

    public function testRetryAspectSucceedsOnFirstAttempt(): void
    {
        AttributeCollector::collectMethod('TestClass', 'testMethod', Retry::class, new Retry());

        $aspect  = new RetryAspect();
        $called  = 0;
        $point   = $this->createJoinPoint(function () use (&$called) { $called++; return 'ok'; });
        $result  = $aspect->process($point);

        $this->assertEquals(1, $called);
        $this->assertEquals('ok', $result);
    }

    public function testRetryAspectRetriesAndSucceeds(): void
    {
        AttributeCollector::collectMethod('TestClass', 'testMethod', Retry::class,
            new Retry(maxAttempts: 3, delayMs: 0));

        $called = 0;
        $aspect = new RetryAspect();
        $point  = $this->createJoinPoint(function () use (&$called) {
            $called++;
            if ($called < 3) throw new \RuntimeException('fail');
            return 'retry_ok';
        });

        $result = $aspect->process($point);

        $this->assertEquals(3, $called);
        $this->assertEquals('retry_ok', $result);
    }

    public function testRetryAspectExhaustsAttempts(): void
    {
        AttributeCollector::collectMethod('TestClass', 'testMethod', Retry::class,
            new Retry(maxAttempts: 2, delayMs: 0));

        $called = 0;
        $aspect = new RetryAspect();
        $point  = $this->createJoinPoint(function () use (&$called) {
            $called++;
            throw new \RuntimeException('always fail');
        });

        try {
            $aspect->process($point);
            $this->fail('Should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('always fail', $e->getMessage());
        }
        $this->assertEquals(2, $called);
    }

    public function testRetryAspectFiltersExceptions(): void
    {
        AttributeCollector::collectMethod('TestClass', 'testMethod', Retry::class,
            new Retry(maxAttempts: 3, delayMs: 0, on: [\InvalidArgumentException::class]));

        $called = 0;
        $aspect = new RetryAspect();
        $point  = $this->createJoinPoint(function () use (&$called) {
            $called++;
            throw new \RuntimeException('not on list');
        });

        try {
            $aspect->process($point);
            $this->fail('Should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('not on list', $e->getMessage());
        }
        $this->assertEquals(1, $called, 'Should not retry for non-matching exception');
    }

    // ==================== Middleware ====================

    public function testMiddlewareStoresClassName(): void
    {
        $m = new Middleware(name: 'App\Middleware\Auth');

        $this->assertEquals('App\Middleware\Auth', $m->name);
        $this->assertEquals(0, $m->priority);
    }

    public function testMiddlewareWithPriority(): void
    {
        $m = new Middleware(name: 'App\Middleware\Auth', priority: 10);
        $this->assertEquals(10, $m->priority);
    }

    public function testMiddlewareCollectClass(): void
    {
        $m = new Middleware(name: 'App\Middleware\Throttle');
        $m->collectClass('App\Controller\ApiController');

        $attr = AttributeCollector::getClassAttribute('App\Controller\ApiController', Middleware::class);
        $this->assertNotNull($attr);
        $this->assertInstanceOf(Middleware::class, $attr);
        $this->assertEquals('App\Middleware\Throttle', $attr->name);
    }

    public function testMiddlewareCollectMethod(): void
    {
        $m = new Middleware(name: 'App\Middleware\Cors');
        $m->collectMethod('App\Controller\ApiController', 'index');

        $attr = AttributeCollector::getClassMethodAttribute('App\Controller\ApiController', 'index');
        $this->assertArrayHasKey(Middleware::class, $attr);
    }

    // ==================== Trace ====================

    public function testTraceDefaultValues(): void
    {
        $t = new Trace();

        $this->assertNull($t->spanName);
        $this->assertNull($t->tracer);
    }

    public function testTraceCustomSpanName(): void
    {
        $t = new Trace(spanName: 'payment.process');

        $this->assertEquals('payment.process', $t->spanName);
    }

    public function testTraceContextValueObject(): void
    {
        $ctx = new TraceContext(
            traceId: 'abc123',
            spanId: 'span001',
            parentSpanId: 'parent001'
        );

        $this->assertEquals('abc123', $ctx->traceId);
        $this->assertEquals('span001', $ctx->spanId);
        $this->assertEquals('parent001', $ctx->parentSpanId);
    }

    public function testTraceAspectHasAttributes(): void
    {
        $aspect = new TraceAspect();
        $this->assertContains(Trace::class, $aspect->attributes);
    }

    public function testTraceAspectPassesThroughWhenNoAttribute(): void
    {
        $aspect  = new TraceAspect();
        $called  = false;
        $point   = $this->createJoinPoint(function () use (&$called) { $called = true; return 'ok'; });
        $result  = $aspect->process($point);

        $this->assertTrue($called);
        $this->assertEquals('ok', $result);
    }

    public function testTraceAspectRecordsSuccess(): void
    {
        $spanCalls = [];
        $handler   = static function (string $name, ...$args) use (&$spanCalls) {
            $spanCalls[] = $name;
            return $args[1]();  // call the $execute closure
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Trace::class,
            new Trace(tracer: $handler)
        );

        $aspect  = new TraceAspect();
        $point   = $this->createJoinPoint(fn() => 'traced_ok');
        $result  = $aspect->process($point);

        $this->assertEquals('traced_ok', $result);
        $this->assertCount(1, $spanCalls);
        $this->assertEquals('TestClass::testMethod', $spanCalls[0]);
    }

    public function testTraceAspectPropagatesException(): void
    {
        $handler = static function (string $name, $point, \Closure $execute) {
            return $execute();
        };

        AttributeCollector::collectMethod(
            'TestClass', 'testMethod', Trace::class,
            new Trace(tracer: $handler)
        );

        $aspect = new TraceAspect();
        $point  = $this->createJoinPoint(fn() => throw new \LogicException('trace fail'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('trace fail');
        $aspect->process($point);
    }

    // ==================== Tracer ====================

    public function testW3CTracerCreatesSpanAndReturnsResult(): void
    {
        $tracer = new W3CTracer();
        $result = $tracer->trace('test.span', fn() => 'ok');

        $this->assertEquals('ok', $result);
    }

    public function testW3CTracerPropagatesException(): void
    {
        $tracer = new W3CTracer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $tracer->trace('test.fail', fn() => throw new \RuntimeException('boom'));
    }

    public function testW3CTracerGeneratesUniqueSpanIds(): void
    {
        $tracer = new W3CTracer();
        $ctx1 = null;
        $ctx2 = null;

        $tracer->trace('span1', function () use ($tracer, &$ctx1) {
            $ctx1 = $tracer->currentContext();
        });
        $tracer->trace('span2', function () use ($tracer, &$ctx2) {
            $ctx2 = $tracer->currentContext();
        });

        $this->assertNotNull($ctx1);
        $this->assertNotNull($ctx2);
        $this->assertNotEquals($ctx1->spanId, $ctx2->spanId);
    }

    public function testW3CTracerNestsSpans(): void
    {
        $tracer = new W3CTracer();
        $outer = null;
        $inner = null;

        $tracer->trace('outer', function () use ($tracer, &$outer, &$inner) {
            $outer = $tracer->currentContext();
            $tracer->trace('inner', function () use ($tracer, &$inner) {
                $inner = $tracer->currentContext();
            });
        });

        $this->assertNotNull($outer);
        $this->assertNotNull($inner);
        $this->assertEquals($outer->traceId, $inner->traceId);
        $this->assertEquals($outer->spanId, $inner->parentSpanId);
    }

    public function testW3CTracerSetAttribute(): void
    {
        $tracer = new W3CTracer();
        $attrs  = null;

        $tracer->trace('test', function () use ($tracer, &$attrs) {
            $tracer->setAttribute('order_id', 42);
            $tracer->setAttribute('amount', 99.9);
        });

        // 无异常即通过；属性在日志中输出，此处验证无副作用
        $this->assertTrue(true);
    }

    // ==================== Command ====================

    public function testCommandDefaultValues(): void
    {
        $cmd = new Command(name: 'app:test');

        $this->assertEquals('app:test', $cmd->name);
        $this->assertNull($cmd->description);
    }

    public function testCommandWithDescription(): void
    {
        $cmd = new Command(name: 'app:greet', description: 'Say hello');

        $this->assertEquals('app:greet', $cmd->name);
        $this->assertEquals('Say hello', $cmd->description);
    }

    public function testCommandCollectClassStoresInAttributeCollector(): void
    {
        $cmd = new Command(name: 'app:sync');
        $cmd->collectClass('App\Command\SyncCommand');

        $classes = AttributeCollector::getClassesByAttribute(Command::class);
        $this->assertArrayHasKey('App\Command\SyncCommand', $classes);
        $this->assertSame($cmd, $classes['App\Command\SyncCommand']);
    }

    public function testCommandHandlerReturnsEmptyWhenNoCommands(): void
    {
        $result = CommandHandler::init();
        $this->assertEmpty($result);
    }

    public function testCommandHandlerDiscoversCommands(): void
    {
        $cmd = new Command(name: 'app:discovered');
        $cmd->collectClass('App\Command\DiscoveredCommand');

        $result = CommandHandler::init();
        $this->assertContains('App\Command\DiscoveredCommand', $result);
    }

    public function testCommandHandlerMergesWithStaticCommands(): void
    {
        $cmd = new Command(name: 'app:dynamic');
        $cmd->collectClass('App\Command\DynamicCommand');

        $result = CommandHandler::init(['App\Command\StaticCommand']);
        $this->assertContains('App\Command\StaticCommand', $result);
        $this->assertContains('App\Command\DynamicCommand', $result);
        $this->assertCount(2, $result);
    }

    public function testCommandHandlerSkipsDuplicates(): void
    {
        $cmd = new Command(name: 'app:dup');
        $cmd->collectClass('App\Command\StaticCommand');

        $result = CommandHandler::init(['App\Command\StaticCommand']);
        $this->assertCount(1, $result);
    }

    // ==================== __set_state 往返 ====================

    public function testVarExportRoundtripAllAttributes(): void
    {
        // Inject
        $inject = new Inject(value: 'App\Service\Foo', lazy: true);
        $inject->targetValue = 'LazyProxy\App\Service\Foo';
        $restored = eval('return ' . var_export($inject, true) . ';');
        $this->assertInstanceOf(Inject::class, $restored);
        $this->assertEquals('App\Service\Foo', $restored->value);
        $this->assertTrue($restored->lazy);
        $this->assertEquals('LazyProxy\App\Service\Foo', $restored->targetValue);

        // Cacheable
        $cache = new Cacheable(prefix: 'data', ttl: 60, offset: 10);
        $restored = eval('return ' . var_export($cache, true) . ';');
        $this->assertInstanceOf(Cacheable::class, $restored);
        $this->assertEquals('data', $restored->prefix);
        $this->assertEquals(60, $restored->ttl);

        // Listener
        $listener = new Listener(event: 'test.event', priority: 5);
        $restored = eval('return ' . var_export($listener, true) . ';');
        $this->assertInstanceOf(Listener::class, $restored);
        $this->assertEquals('test.event', $restored->event);

        // Depend
        $depend = new Depend(id: 'svc', params: ['key' => 'val'], singleton: true);
        $restored = eval('return ' . var_export($depend, true) . ';');
        $this->assertInstanceOf(Depend::class, $restored);
        $this->assertEquals(['key' => 'val'], $restored->params);
        $this->assertTrue($restored->singleton);

        // Value
        $value = new Value(key: 'app.debug');
        $restored = eval('return ' . var_export($value, true) . ';');
        $this->assertInstanceOf(Value::class, $restored);
        $this->assertEquals('app.debug', $restored->key);

        // Transactional
        $tx = new Transactional(connection: 'mysql', attempts: 2);
        $restored = eval('return ' . var_export($tx, true) . ';');
        $this->assertInstanceOf(Transactional::class, $restored);
        $this->assertEquals('mysql', $restored->connection);
        $this->assertEquals(2, $restored->attempts);

        // Validate
        $v = new Validate(rules: ['email' => 'email'], messages: ['email' => '格式错误']);
        $restored = eval('return ' . var_export($v, true) . ';');
        $this->assertInstanceOf(Validate::class, $restored);
        $this->assertEquals(['email' => 'email'], $restored->rules);
        $this->assertEquals(['email' => '格式错误'], $restored->messages);

        // Retry
        $r = new Retry(maxAttempts: 5, delayMs: 200, backoff: 2.0, on: [\RuntimeException::class]);
        $restored = eval('return ' . var_export($r, true) . ';');
        $this->assertInstanceOf(Retry::class, $restored);
        $this->assertEquals(5, $restored->maxAttempts);
        $this->assertSame(2.0, $restored->backoff);

        // Middleware
        $mw = new Middleware(name: 'App\Middleware\Cors');
        $restored = eval('return ' . var_export($mw, true) . ';');
        $this->assertInstanceOf(Middleware::class, $restored);
        $this->assertEquals('App\Middleware\Cors', $restored->name);

        // Trace
        $tr = new Trace(spanName: 'api.trace');
        $restored = eval('return ' . var_export($tr, true) . ';');
        $this->assertInstanceOf(Trace::class, $restored);
        $this->assertEquals('api.trace', $restored->spanName);

        // Command
        $cmd = new Command(name: 'app:export', description: 'Export data');
        $restored = eval('return ' . var_export($cmd, true) . ';');
        $this->assertInstanceOf(Command::class, $restored);
        $this->assertEquals('app:export', $restored->name);
        $this->assertEquals('Export data', $restored->description);
    }

    // ==================== helpers ====================

    /** 创建最小 ProceedingJoinPoint，pipe 支持多次调用（用于重试测试） */
    private function createJoinPoint(\Closure $closure): ProceedingJoinPoint
    {
        $point = new ProceedingJoinPoint($closure, 'TestClass', 'testMethod', ['keys' => []]);
        $point->pipe = function (ProceedingJoinPoint $p) use ($closure) {
            return $closure(...array_values($p->arguments['keys']));
        };
        return $point;
    }
}
