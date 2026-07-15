<?php
/**
 * AttributeCollectorTest.php
 * 测试 AttributeCollector 属性收集与查询方法
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\AttributeCollector;

class AttributeCollectorTest extends TestCase
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

    // ==================== collectClass ====================

    public function testCollectClass(): void
    {
        AttributeCollector::collectClass('App\\Test\\Demo', 'App\\Attr\\Route', '/api');

        $attr = AttributeCollector::getClassAttribute('App\\Test\\Demo', 'App\\Attr\\Route');
        $this->assertEquals('/api', $attr);
    }

    public function testCollectClassOverwrite(): void
    {
        AttributeCollector::collectClass('App\\Test\\Demo', 'App\\Attr\\Route', '/v1');
        AttributeCollector::collectClass('App\\Test\\Demo', 'App\\Attr\\Route', '/v2');

        $attr = AttributeCollector::getClassAttribute('App\\Test\\Demo', 'App\\Attr\\Route');
        $this->assertEquals('/v2', $attr);
    }

    // ==================== collectMethod ====================

    public function testCollectMethod(): void
    {
        AttributeCollector::collectMethod('App\\Test\\Demo', 'index', 'App\\Attr\\Get', '/index');

        $attr = AttributeCollector::getClassMethodAttribute('App\\Test\\Demo', 'index');
        $this->assertArrayHasKey('App\\Attr\\Get', $attr);
        $this->assertEquals('/index', $attr['App\\Attr\\Get']);
    }

    // ==================== collectProperty ====================

    public function testCollectProperty(): void
    {
        AttributeCollector::collectProperty('App\\Test\\Demo', 'service', 'App\\Attr\\Inject', 'App\\Service');

        $attr = AttributeCollector::getClassPropertyAttribute('App\\Test\\Demo', 'service');
        $this->assertEquals('App\\Service', $attr['App\\Attr\\Inject']);
    }

    // ==================== collectClassConstant ====================

    public function testCollectClassConstant(): void
    {
        AttributeCollector::collectClassConstant('App\\Enums\\Status', 'ACTIVE', 'App\\Attr\\Message', 'Active');

        $all = AttributeCollector::list();
        $this->assertArrayHasKey('App\\Enums\\Status', $all);
        $this->assertArrayHasKey('_cc', $all['App\\Enums\\Status']);
    }

    // ==================== getClassesByAttribute ====================

    public function testGetClassesByAttribute(): void
    {
        AttributeCollector::collectClass('App\\A', 'App\\Attr\\Controller', '/a');
        AttributeCollector::collectClass('App\\B', 'App\\Attr\\Controller', '/b');
        AttributeCollector::collectClass('App\\C', 'App\\Attr\\Other', 'other');

        $classes = AttributeCollector::getClassesByAttribute('App\\Attr\\Controller');

        $this->assertCount(2, $classes);
        $this->assertArrayHasKey('App\\A', $classes);
        $this->assertArrayHasKey('App\\B', $classes);
        $this->assertArrayNotHasKey('App\\C', $classes);
    }

    public function testGetClassesByAttributeEmpty(): void
    {
        $this->assertEmpty(AttributeCollector::getClassesByAttribute('NonExistent'));
    }

    // ==================== getMethodsByAttribute ====================

    public function testGetMethodsByAttribute(): void
    {
        AttributeCollector::collectMethod('App\\A', 'index', 'App\\Attr\\Get', true);
        AttributeCollector::collectMethod('App\\A', 'show', 'App\\Attr\\Get', true);
        AttributeCollector::collectMethod('App\\B', 'store', 'App\\Attr\\Post', true);

        $methods = AttributeCollector::getMethodsByAttribute('App\\Attr\\Get');

        $this->assertCount(2, $methods);
        $this->assertEquals('App\\A', $methods[0]['class']);
        $this->assertEquals('index', $methods[0]['method']);
    }

    // ==================== getPropertiesByAttribute ====================

    public function testGetPropertiesByAttribute(): void
    {
        AttributeCollector::collectProperty('App\\A', 'db', 'App\\Attr\\Inject', 'DB');
        AttributeCollector::collectProperty('App\\B', 'cache', 'App\\Attr\\Inject', 'Cache');
        AttributeCollector::collectProperty('App\\C', 'name', 'App\\Attr\\Value', 'app.name');

        $props = AttributeCollector::getPropertiesByAttribute('App\\Attr\\Inject');

        $this->assertCount(2, $props);
        $this->assertEquals('App\\A', $props[0]['class']);
        $this->assertEquals('db', $props[0]['property']);
        $this->assertEquals('DB', $props[0]['attribute']);
    }

    // ==================== get / getClassAttribute 边界 ====================

    public function testGetClassAttributeNonExistent(): void
    {
        $this->assertNull(AttributeCollector::getClassAttribute('App\\None', 'Attr\\None'));
    }

    public function testGetClassMethodAttributeNonExistent(): void
    {
        $this->assertNull(AttributeCollector::getClassMethodAttribute('App\\None', 'none'));
    }

    public function testGetClassPropertyAttributeNonExistent(): void
    {
        $this->assertNull(AttributeCollector::getClassPropertyAttribute('App\\None', 'none'));
    }
}
