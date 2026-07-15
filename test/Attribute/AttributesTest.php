<?php
/**
 * AttributesTest.php
 * 端到端测试所有注解类的核心逻辑：创建、属性读取、collect 方法行为
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Attribute\Cacheable;
use Vzina\Attributes\Attribute\Constants;
use Vzina\Attributes\Attribute\ConstantsTrait;
use Vzina\Attributes\Attribute\Crontab;
use Vzina\Attributes\Attribute\Depend;
use Vzina\Attributes\Attribute\Inject;
use Vzina\Attributes\Attribute\Listener;
use Vzina\Attributes\Attribute\Message;
use Vzina\Attributes\Attribute\Process;
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

    public function testCrontabCollectClassChecksHandleMethod(): void
    {
        // collectClass 检查类是否有 handle 方法
        // 当前测试类没有 handle 方法 → 不存储
        $cron = new Crontab(rule: '* * * * *');
        $cron->collectClass(__CLASS__);

        $classes = AttributeCollector::getClassesByAttribute(Crontab::class);
        $this->assertArrayNotHasKey(__CLASS__, $classes);
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
    }
}
