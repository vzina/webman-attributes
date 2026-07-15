<?php
/**
 * AspectIntegrationTest.php
 * 端到端测试：自定义 #[Aspect] 注册 → 规则收集 → 代理匹配
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Ast\AspectParser;
use Vzina\Attributes\Ast\RewriteCollection;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AttributeCollector;

class AspectIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AspectCollector::clear();
        AttributeCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AspectCollector::clear();
        AttributeCollector::clear();
    }

    // ==================== #[Aspect] 规则注册 ====================

    public function testCustomAspectClassRuleIsRegistered(): void
    {
        // 模拟 #[Aspect] collectClass 的执行路径：
        //   $props = (new ReflectionClass($className))->getDefaultProperties();
        //   AspectCollector::setAround($className, $props['classes'], $props['attributes'], $props['priority'])

        $aspectClass = 'App\Aspect\LogAspect';
        AspectCollector::setAround(
            $aspectClass,
            ['App\Service\OrderService::create'],                            // classes 规则
            [],                                                              // attributes 规则
            10                                                               // priority
        );

        $rule = AspectCollector::getRule($aspectClass);
        $this->assertNotEmpty($rule);
        $this->assertContains('App\Service\OrderService::create', $rule['classes']);
        $this->assertEquals(10, AspectCollector::getPriority($aspectClass));
    }

    public function testCustomAspectAttributeRuleIsRegistered(): void
    {
        $aspectClass = 'App\Aspect\MetricAspect';
        AspectCollector::setAround(
            $aspectClass,
            [],
            ['App\Attribute\Timed'],  // attribute 规则
            5
        );

        $rule = AspectCollector::getRule($aspectClass);
        $this->assertContains('App\Attribute\Timed', $rule['attributes']);
    }

    // ==================== 规则匹配 ====================

    public function testAspectRuleMatchesTargetClass(): void
    {
        // 注册一个切面规则
        AspectCollector::setAround(
            'App\Aspect\LogAspect',
            ['App\Service\*::*', 'App\Controller\*'],  // 通配符规则
            [],
            10
        );

        // 验证类规则匹配
        $matched = AspectParser::isMatch('App\Service\OrderService', 'create', 'App\Service\*::*');
        $this->assertTrue($matched);

        // 验证通配符类匹配（无方法）
        $matched = AspectParser::isMatch('App\Controller\UserController', 'index', 'App\Controller\*');
        $this->assertTrue($matched);

        // 验证不匹配
        $matched = AspectParser::isMatch('App\Model\User', 'save', 'App\Service\*::*');
        $this->assertFalse($matched);
    }

    // ==================== 多切面共存 ====================

    public function testMultipleAspectsCoexist(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], [], 10);
        AspectCollector::setAround('AspectB', ['ClassB'], ['AttrB'], 5);

        $rules = AspectCollector::getRules();
        $this->assertCount(2, $rules);

        // 两个切面的规则互不干扰
        $this->assertContains('ClassA', AspectCollector::getRule('AspectA')['classes']);
        $this->assertContains('ClassB', AspectCollector::getRule('AspectB')['classes']);
        $this->assertContains('AttrB', AspectCollector::getRule('AspectB')['attributes']);
    }

    public function testSameAspectAppended(): void
    {
        // 同一个切面类分两次注册（模拟内置切面 + 用户补充）
        AspectCollector::setAround('AspectX', ['ClassA'], [], 10);
        AspectCollector::setAround('AspectX', ['ClassB'], [], 10);

        $rule = AspectCollector::getRule('AspectX');
        $this->assertContains('ClassA', $rule['classes']);
        $this->assertContains('ClassB', $rule['classes']);  // 追加，不覆盖
    }

    // ==================== RewriteCollection 匹配判断 ====================

    public function testRewriteCollectionParsesCustomAspect(): void
    {
        // 注册一个切面，目标为精确方法匹配
        AspectCollector::setAround(
            'App\Aspect\SpecificAspect',
            ['App\Service\PaymentService::charge'],  // 只拦截这一个方法
            [],
            10
        );

        // 解析目标类
        $collection = AspectParser::parse('App\Service\PaymentService');

        $this->assertEquals(RewriteCollection::METHOD_LEVEL, $collection->getLevel());
        $this->assertTrue($collection->shouldRewrite('charge'));
        $this->assertFalse($collection->shouldRewrite('refund')); // 未匹配的方法
    }

    public function testRewriteCollectionClassLevelWildcard(): void
    {
        // 注册一个切面，通配符匹配整个类
        AspectCollector::setAround(
            'App\Aspect\BroadAspect',
            ['App\Service\UserService'],  // 匹配整个类
            [],
            10
        );

        $collection = AspectParser::parse('App\Service\UserService');

        $this->assertEquals(RewriteCollection::CLASS_LEVEL, $collection->getLevel());
        // 类级别重写：除了 __construct 外所有方法都重写
        $this->assertFalse($collection->shouldRewrite('__construct'));
        $this->assertTrue($collection->shouldRewrite('find'));
        $this->assertTrue($collection->shouldRewrite('update'));
    }

    // ==================== AspectCollector 序列化往返 ====================

    public function testAspectRulesSurviveSerializeRoundtrip(): void
    {
        AspectCollector::setAround('AspectA', ['ClassA'], ['AttrA'], 10);

        $serialized = AspectCollector::serialize();
        AspectCollector::clear();

        $this->assertEmpty(AspectCollector::getRules());

        AspectCollector::deserialize($serialized);

        $this->assertNotEmpty(AspectCollector::getRule('AspectA'));
        $this->assertEquals(10, AspectCollector::getPriority('AspectA'));
        $this->assertContains('ClassA', AspectCollector::getRule('AspectA')['classes']);
        $this->assertContains('AttrA', AspectCollector::getRule('AspectA')['attributes']);
    }

    // ==================== matchRule 编译缓存 ====================

    public function testMatchRuleCompilesWildcardOnTheFly(): void
    {
        // 通过 setAround 注册通配符规则
        AspectCollector::setAround('Test', [], ['App\Attribute\Cache*'], 5);

        // 预编译命中
        $this->assertTrue(AspectCollector::matchRule('App\Attribute\Cache*', 'App\Attribute\Cacheable'));
        $this->assertFalse(AspectCollector::matchRule('App\Attribute\Cache*', 'App\Attribute\Inject'));

        // 未预编译的通配符 → 即时编译后匹配
        $this->assertTrue(AspectCollector::matchRule('App\Service\*Service', 'App\Service\UserService'));
        $this->assertFalse(AspectCollector::matchRule('App\Service\*Service', 'App\Service\UserRepo'));
    }
}
