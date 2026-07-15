<?php
/**
 * ConstantsCollectorTest.php
 * 测试 ConstantsCollector 常量消息值解析
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Collector;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vzina\Attributes\Collector\ConstantsCollector;

class ConstantsCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConstantsCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ConstantsCollector::clear();
    }

    // ==================== getValue ====================

    public function testGetValue(): void
    {
        // 直接写入静态容器
        ConstantsCollector::set('App\\Enums\\Status.1.message', 'Active');
        ConstantsCollector::set('App\\Enums\\Status.0.message', 'Inactive');

        $this->assertEquals('Active', ConstantsCollector::getValue('App\\Enums\\Status', 1, 'message'));
        $this->assertEquals('Inactive', ConstantsCollector::getValue('App\\Enums\\Status', 0, 'message'));
    }

    public function testGetValueDefaultForMissing(): void
    {
        $this->assertEquals('', ConstantsCollector::getValue('NonExistent', 'code', 'key'));
    }

    // ==================== getTransValue — 基础（无翻译） ====================

    public function testGetTransValueWithStringCode(): void
    {
        ConstantsCollector::set('App\\Enums\\Status.1.message', 'Active');

        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'message', [1]);
        $this->assertEquals('Active', $result);
    }

    public function testGetTransValueWithSprintfFormat(): void
    {
        ConstantsCollector::set('App\\Enums\\Status.404.message', 'Resource %s not found');

        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'message', [404, ['User']]);
        $this->assertEquals('Resource User not found', $result);
    }

    public function testGetTransValueWithEmptyArguments(): void
    {
        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'message', []);
        $this->assertNull($result);
    }

    public function testGetTransValueMissingMessage(): void
    {
        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'missing', [999]);
        $this->assertEquals('', $result);
    }

    // ==================== getTransValue — 对象 code ====================

    public function testGetTransValueWithObjectHavingGetConstantsCode(): void
    {
        $obj = new class {
            public function __getConstantsCode(): int
            {
                return 1;
            }
        };

        ConstantsCollector::set('App\\Enums\\Status.1.message', 'Active');

        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'message', [$obj]);
        $this->assertEquals('Active', $result);
    }

    public function testGetTransValueWithObjectNoMethodReturnsNull(): void
    {
        $obj = new \stdClass();

        $result = ConstantsCollector::getTransValue('App\\Enums\\Status', 'message', [$obj]);
        $this->assertNull($result);
    }

    // ==================== getValue 多 key 场景 ====================

    public function testGetValueMultipleKeys(): void
    {
        ConstantsCollector::set('App\\Enums\\Status.1.message', 'Active');
        ConstantsCollector::set('App\\Enums\\Status.1.description', 'Account is active');

        $this->assertEquals('Active', ConstantsCollector::getValue('App\\Enums\\Status', 1, 'message'));
        $this->assertEquals('Account is active', ConstantsCollector::getValue('App\\Enums\\Status', 1, 'description'));
    }

    // ==================== 与 MetadataCollector 继承功能 ====================

    public function testClear(): void
    {
        ConstantsCollector::set('App\\Enums\\Status.1.message', 'Active');
        ConstantsCollector::clear();

        $this->assertEquals('', ConstantsCollector::getValue('App\\Enums\\Status', 1, 'message'));
    }
}
