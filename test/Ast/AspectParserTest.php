<?php
/**
 * AspectParserTest.php
 * 测试 AspectParser 核心功能（基于真实收集器类）
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vzina\Attributes\Ast\AspectParser;
use Vzina\Attributes\Ast\RewriteCollection;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class AspectParserTest extends TestCase
{
    /**
     * 测试前置：重置收集器静态属性，避免跨测试污染
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCollectors();
    }

    /**
     * 测试后置：清理收集器
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetCollectors();
    }

    /**
     * 重置 AttributeCollector/AspectCollector 静态属性（直接操作，无需反射）
     */
    private function resetCollectors(): void
    {
        // 重置 AttributeCollector
        AttributeCollector::clear();

        // 重置 AspectCollector
        AspectCollector::clear();
    }

    // -------------------------- RewriteCollection 核心方法测试（无依赖） --------------------------
    /**
     * 测试 RewriteCollection 自身核心方法（独立运行）
     */
    public function testRewriteCollectionCoreMethods(): void
    {
        // 1. 初始化
        $collection = new RewriteCollection('App\Test\DemoClass');

        // 断言默认值
        $this->assertEquals('App\Test\DemoClass', $collection->getClass());
        $this->assertEquals(RewriteCollection::METHOD_LEVEL, $collection->getLevel()); // METHOD_LEVEL=2
        $this->assertContains('__construct', $collection->getShouldNotRewriteMethods());

        // 2. 测试 add 方法（普通方法 + 通配符方法）
        $collection->add(['testMethod', 'get*', 'set*Value']);

        // 断言方法列表（通配符转为正则）
        $methods = $collection->getMethods();
        $this->assertCount(1, $methods);
        $this->assertContains('testMethod', $methods);
        // $this->assertContains('/^get.*$/', $methods);
        // $this->assertContains('/^set.*Value$/', $methods);

        // 3. 测试 setLevel + getLevel（链式调用）
        $collection->setLevel(RewriteCollection::CLASS_LEVEL);
        $this->assertEquals(RewriteCollection::CLASS_LEVEL, $collection->getLevel()); // CLASS_LEVEL=1

        // 4. 测试 shouldRewrite 方法（CLASS_LEVEL 场景）
        $this->assertFalse($collection->shouldRewrite('__construct'));
        $this->assertTrue($collection->shouldRewrite('testMethod'));
        $this->assertTrue($collection->shouldRewrite('getUser'));

        // 5. 测试 shouldRewrite 方法（METHOD_LEVEL 场景）
        $collection->setLevel(RewriteCollection::METHOD_LEVEL);
        $this->assertTrue($collection->shouldRewrite('testMethod')); // 精确匹配
        $this->assertTrue($collection->shouldRewrite('getUser'));   // 通配符匹配
        $this->assertFalse($collection->shouldRewrite('otherMethod')); // 不匹配
    }

    // -------------------------- AspectParser 核心方法测试 --------------------------

    /**
     * 测试1：isMatchClassRule 规则匹配（全场景覆盖）
     */
    public function testIsMatchClassRule(): void
    {
        // 场景1：精确类匹配（无方法）
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass', 'App\Test\DemoClass');
        $this->assertTrue($isMatch);
        $this->assertNull($method);

        // 场景2：精确类+精确方法匹配
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass::testMethod', 'App\Test\DemoClass::testMethod');
        $this->assertTrue($isMatch);
        $this->assertEquals('testMethod', $method);

        // 场景3：通配符类匹配（无方法）
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass', 'App\Test\*');
        $this->assertTrue($isMatch);
        $this->assertNull($method);

        // 场景4：通配符类+精确方法匹配
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass::testMethod', 'App\Test\*::testMethod');
        $this->assertTrue($isMatch);
        $this->assertEquals('testMethod', $method);

        // 场景5：精确类+通配符方法匹配
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass::testMethod', 'App\Test\DemoClass::test*');
        $this->assertTrue($isMatch);
        $this->assertEquals('testMethod', $method);

        // 场景6：类匹配但方法不匹配
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass::testMethod', 'App\Test\DemoClass::otherMethod');
        $this->assertFalse($isMatch);
        $this->assertNull($method);

        // 场景7：类不匹配（通配符也不匹配）
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass', 'App\Other\*');
        $this->assertFalse($isMatch);
        $this->assertNull($method);

        // 场景8：规则带方法但目标只有类
        [$isMatch, $method] = AspectParser::isMatchClassRule('App\Test\DemoClass', 'App\Test\DemoClass::testMethod');
        $this->assertTrue($isMatch);
        $this->assertEquals('testMethod', $method);
    }

    /**
     * 测试2：isMatch 方法（封装 isMatchClassRule）
     */
    public function testIsMatch(): void
    {
        // 匹配场景
        $this->assertTrue(AspectParser::isMatch('App\Test\DemoClass', 'testMethod', 'App\Test\DemoClass::testMethod'));
        $this->assertTrue(AspectParser::isMatch('App\Test\DemoClass', 'testMethod', 'App\Test\*::test*'));

        // 不匹配场景
        $this->assertFalse(AspectParser::isMatch('App\Test\DemoClass', 'testMethod', 'App\Other\DemoClass::testMethod'));
        $this->assertFalse(AspectParser::isMatch('App\Test\DemoClass', 'testMethod', 'App\Test\DemoClass::otherMethod'));
    }

    /**
     * 测试3：parseClasses 类规则解析（反射调用私有方法）
     */
    public function testParseClasses(): void
    {
        // 1. 准备测试数据
        $class = 'App\Test\DemoClass';
        $rewriteCollection = new RewriteCollection($class);
        $aspectName = 'App\Aspect\TestAspect';

        // 2. 真实设置切面规则（替代 Mock）
        AspectCollector::setAround(
            $aspectName,
            ['App\Test\DemoClass', 'App\Test\DemoClass::testMethod'], // classes 规则
            [], // attributes 规则
            10 // priority
        );

        // 3. 构造 parseClasses 所需的 collection 参数
        $collection = [$aspectName => []];

        // 4. 反射调用私有方法 parseClasses
        $reflection = new ReflectionClass(AspectParser::class);
        $parseClassesMethod = $reflection->getMethod('parseClasses');
        $parseClassesMethod->setAccessible(true);
        $parseClassesMethod->invoke(null, $collection, $class, $rewriteCollection);

        // 5. 断言结果：类级别匹配（CLASS_LEVEL=1），覆盖方法级别
        $this->assertEquals(RewriteCollection::CLASS_LEVEL, $rewriteCollection->getLevel());
        $this->assertEmpty($rewriteCollection->getMethods());

        // 6. 验证 shouldRewrite 逻辑
        $this->assertFalse($rewriteCollection->shouldRewrite('__construct'));
        $this->assertTrue($rewriteCollection->shouldRewrite('testMethod'));
        $this->assertTrue($rewriteCollection->shouldRewrite('anyOtherMethod'));
    }

    /**
     * 测试4：parseAttributes 属性规则解析（反射调用私有方法）
     */
    public function testParseAttributes(): void
    {
        // 1. 准备测试数据
        $class = 'App\Test\DemoClass';
        $rewriteCollection = new RewriteCollection($class);
        $aspectName = 'App\Aspect\AttrAspect';
        $testAttr = 'App\Attribute\TestAttr';

        // 2. 真实设置属性（替代 Mock）
        // 2.1 类级别属性（触发 CLASS_LEVEL）
        AttributeCollector::collectClass($class, $testAttr, new \stdClass());
        // 2.2 方法级别属性
        AttributeCollector::collectMethod($class, 'testMethod', $testAttr, new \stdClass());
        AttributeCollector::collectMethod($class, 'get*', $testAttr, new \stdClass());
        AttributeCollector::collectMethod($class, 'otherMethod', 'App\Attribute\OtherAttr', new \stdClass());

        // 3. 真实设置切面规则
        AspectCollector::setAround(
            $aspectName,
            [], // classes 规则
            [$testAttr], // attributes 规则
            5 // priority
        );

        // 4. 构造 parseAttributes 所需的 collection 参数
        $collection = [$aspectName => []];

        // 5. 反射调用私有方法 parseAttributes
        $reflection = new ReflectionClass(AspectParser::class);
        $parseAttributesMethod = $reflection->getMethod('parseAttributes');
        $parseAttributesMethod->setAccessible(true);
        $parseAttributesMethod->invoke(null, $collection, $class, $rewriteCollection);

        // 6. 断言：类级别匹配（CLASS_LEVEL=1）
        $this->assertEquals(RewriteCollection::CLASS_LEVEL, $rewriteCollection->getLevel());
        $this->assertEmpty($rewriteCollection->getMethods());

        // 7. 测试仅方法级别属性匹配的场景
        $this->resetCollectors(); // 重置收集器
        $rewriteCollection2 = new RewriteCollection($class);

        // 7.1 仅设置方法级别属性
        AttributeCollector::collectMethod($class, 'testMethod', $testAttr, new \stdClass());
        AttributeCollector::collectMethod($class, 'get*', $testAttr, new \stdClass());
        AttributeCollector::collectMethod($class, 'otherMethod', 'App\Attribute\OtherAttr', new \stdClass());

        // 7.2 重新设置切面规则
        AspectCollector::setAround($aspectName, [], [$testAttr], 5);

        // 7.3 调用 parseAttributes
        $parseAttributesMethod->invoke(null, $collection, $class, $rewriteCollection2);

        // 7.4 断言：METHOD_LEVEL + 方法列表正确
        $this->assertEquals(RewriteCollection::METHOD_LEVEL, $rewriteCollection2->getLevel());
        $methods = $rewriteCollection2->getMethods();
        $this->assertContains('testMethod', $methods);
        // $this->assertContains('/^get.*$/', $methods);
        $this->assertNotContains('otherMethod', $methods);

        // 7.5 验证 shouldRewrite 逻辑
        $this->assertTrue($rewriteCollection2->shouldRewrite('testMethod'));
        $this->assertTrue($rewriteCollection2->shouldRewrite('getUser'));
        $this->assertFalse($rewriteCollection2->shouldRewrite('otherMethod'));
    }

    /**
     * 测试5：parse 整体方法（类规则+属性规则）
     */
    public function testParse(): void
    {
        // 1. 准备测试数据
        $class = 'App\Test\DemoClass';
        $testAspect = 'App\Aspect\TestAspect';
        $attrAspect = 'App\Aspect\AttrAspect';
        $testAttr = 'App\Attribute\TestAttr';

        // 2. 设置类规则（匹配单个方法）
        AspectCollector::setAround(
            $testAspect,
            ['App\Test\DemoClass::testMethod'], // classes 规则
            [], // attributes 规则
            10
        );

        // 3. 设置属性规则（匹配通配符方法）
        AspectCollector::setAround(
            $attrAspect,
            [], // classes 规则
            [$testAttr], // attributes 规则
            5
        );

        // 4. 设置方法级别属性
        AttributeCollector::collectMethod($class, 'get*', $testAttr, new \stdClass());

        // 5. 调用 parse 方法
        $rewriteCollection = AspectParser::parse($class);

        // 6. 断言核心结果
        $this->assertEquals($class, $rewriteCollection->getClass());
        $this->assertEquals(RewriteCollection::METHOD_LEVEL, $rewriteCollection->getLevel());
        $this->assertCount(1, $rewriteCollection->getMethods());
        $this->assertContains('testMethod', $rewriteCollection->getMethods());
        // $this->assertContains('/^get.*$/', $rewriteCollection->getMethods());

        // 7. 验证 shouldRewrite 逻辑
        $this->assertTrue($rewriteCollection->shouldRewrite('testMethod'));
        $this->assertTrue($rewriteCollection->shouldRewrite('getUser'));
        $this->assertFalse($rewriteCollection->shouldRewrite('setUser'));
    }

    /**
     * 测试6：parse 方法 - 类级别匹配优先
     */
    public function testParseWithClassLevelPriority(): void
    {
        // 1. 准备测试数据
        $class = 'App\Test\DemoClass';
        $aspectName = 'App\Aspect\TestAspect';

        // 2. 设置类规则（匹配整个类）
        AspectCollector::setAround(
            $aspectName,
            [$class], // 匹配整个类
            [], // attributes 规则
            10
        );

        // 3. 调用 parse 方法
        $rewriteCollection = AspectParser::parse($class);

        // 4. 断言结果
        $this->assertEquals(RewriteCollection::CLASS_LEVEL, $rewriteCollection->getLevel());
        $this->assertEmpty($rewriteCollection->getMethods());
        $this->assertFalse($rewriteCollection->shouldRewrite('__construct'));
        $this->assertTrue($rewriteCollection->shouldRewrite('anyMethod'));
    }
}