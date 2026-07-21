<?php
/**
 * AttributeReaderTest.php
 * 测试 AttributeReader — PHP 8 属性读取、常量解析、PHPDoc 类型推断
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Reflection;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Tests\Fixtures\AttributedClass;
use Vzina\Attributes\Tests\Fixtures\PlainClass;
use Vzina\Attributes\Tests\Fixtures\TestClassAttr;
use Vzina\Attributes\Tests\Fixtures\TestMethodAttr;
use Vzina\Attributes\Tests\Fixtures\TestPropertyAttr;
use Vzina\Attributes\Tests\Fixtures\TestConstAttr;
use Vzina\Attributes\Tests\Fixtures\ConstantsClass;
use Vzina\Attributes\Tests\Fixtures\TestStatus;

class AttributeReaderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // 手动加载 fixture 文件（多个类在同一文件中，不符合 PSR-4）
        require_once __DIR__ . '/../Fixtures/TestFixtures.php';
    }
    // ==================== getAttributes — 类级别 ====================

    public function testGetClassAttributes(): void
    {
        $ref = new ReflectionClass(AttributedClass::class);
        $attributes = AttributeReader::getAttributes($ref);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(TestClassAttr::class, $attributes[0]);
    }

    public function testGetClassAttributesWithIgnored(): void
    {
        $ref = new ReflectionClass(AttributedClass::class);
        $attributes = AttributeReader::getAttributes($ref, [TestClassAttr::class]);

        $this->assertCount(0, $attributes);
    }

    public function testGetClassAttributesOnPlainClass(): void
    {
        $ref = new ReflectionClass(PlainClass::class);
        $attributes = AttributeReader::getAttributes($ref);

        $this->assertEmpty($attributes);
    }

    // ==================== getAttributes — 方法级别 ====================

    public function testGetMethodAttributes(): void
    {
        $ref = new ReflectionClass(AttributedClass::class);
        $method = $ref->getMethod('testMethod');
        $attributes = AttributeReader::getAttributes($method);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(TestMethodAttr::class, $attributes[0]);
    }

    // ==================== getAttributes — 属性级别 ====================

    public function testGetPropertyAttributes(): void
    {
        $ref = new ReflectionClass(AttributedClass::class);
        $prop = $ref->getProperty('testProp');
        $attributes = AttributeReader::getAttributes($prop);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(TestPropertyAttr::class, $attributes[0]);
    }

    // ==================== getAttributes — 常量级别 ====================

    public function testGetConstantAttributes(): void
    {
        $ref = new ReflectionClass(AttributedClass::class);
        $const = $ref->getReflectionConstant('STATUS_ACTIVE');
        $attributes = AttributeReader::getAttributes($const);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(TestConstAttr::class, $attributes[0]);
    }

    // ==================== getConstants — 类常量属性解析 ====================

    public function testGetConstantsExtractsAttributes(): void
    {
        $ref = new ReflectionClass(ConstantsClass::class);
        $constants = AttributeReader::getConstants($ref);

        // 有 TestConstAttr 属性的常量被收集
        // 没有 Message 属性所以不会生成 message key
        // 但常量本身会被遍历（getConstants 只在属性是 Message 时才收集）
        $this->assertEmpty($constants);
    }

    public function testGetConstantsWithTestConstAttr(): void
    {
        // getConstants 只收集 Message 实例，TestConstAttr 在反射级别可用
        $ref = new ReflectionClass(ConstantsClass::class);
        $const = $ref->getReflectionConstant('ACTIVE');
        $attributes = $const->getAttributes();

        $this->assertCount(1, $attributes);
        $this->assertEquals(TestConstAttr::class, $attributes[0]->getName());
    }

    public function testGetConstantsSkipsConstantsWithoutAttributes(): void
    {
        $ref = new ReflectionClass(ConstantsClass::class);
        $const = $ref->getReflectionConstant('NO_ATTR');

        $this->assertEmpty($const->getAttributes());
    }

    // ==================== getConstants — 枚举属性解析 ====================

    public function testGetConstantsWithBackedEnum(): void
    {
        $ref = new ReflectionClass(TestStatus::class);
        $classConstants = $ref->getReflectionConstants();

        $this->assertCount(3, $classConstants); // 三个 case
        $this->assertTrue($classConstants[0]->isEnumCase());
    }

    // ==================== resolveClassName — grouped imports ====================

    private function tmpFile(string $content): string
    {
        $file = sys_get_temp_dir() . '/vzina_attr_test_' . uniqid() . '.php';
        file_put_contents($file, "<?php\n" . $content);
        return $file;
    }

    public function testResolveGroupedImports(): void
    {
        $content = <<<'PHP'
namespace App\GroupedTest;

use Symfony\Component\Finder\{Finder, SplFileInfo as FileInfo};
use Illuminate\Support\{Arr, Str};

class TestClass {}
PHP;
        $file = $this->tmpFile($content);
        require_once $file;

        try {
            $ref = new \ReflectionClass('App\GroupedTest\TestClass');
            $this->assertEquals(
                'Symfony\Component\Finder\Finder',
                AttributeReader::resolveClassName('Finder', $ref)
            );
            $this->assertEquals(
                'Symfony\Component\Finder\SplFileInfo',
                AttributeReader::resolveClassName('FileInfo', $ref)
            );
            $this->assertEquals(
                'Illuminate\Support\Arr',
                AttributeReader::resolveClassName('Arr', $ref)
            );
            $this->assertEquals(
                'Illuminate\Support\Str',
                AttributeReader::resolveClassName('Str', $ref)
            );
        } finally {
            @unlink($file);
        }
    }

    public function testResolveMixedImports(): void
    {
        $content = <<<'PHP'
namespace App\MixedImportTest;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\{SplFileInfo, Iterator\RecursiveDirectoryIterator as Rdi};

class TestClass {}
PHP;
        $file = $this->tmpFile($content);
        require_once $file;

        try {
            $ref = new \ReflectionClass('App\MixedImportTest\TestClass');
            $this->assertEquals(
                'Symfony\Component\Finder\Finder',
                AttributeReader::resolveClassName('Finder', $ref)
            );
            $this->assertEquals(
                'Symfony\Component\Finder\SplFileInfo',
                AttributeReader::resolveClassName('SplFileInfo', $ref)
            );
            $this->assertEquals(
                'Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator',
                AttributeReader::resolveClassName('Rdi', $ref)
            );
        } finally {
            @unlink($file);
        }
    }

    public function testResolveSingleGroupItem(): void
    {
        $content = <<<'PHP'
namespace App\SingleGroupTest;

use Vzina\Attributes\Attribute\Annotation\{Trace};

class TestClass {}
PHP;
        $file = $this->tmpFile($content);
        require_once $file;

        try {
            $ref = new \ReflectionClass('App\SingleGroupTest\TestClass');
            $this->assertEquals(
                'Vzina\Attributes\Attribute\Annotation\Trace',
                AttributeReader::resolveClassName('Trace', $ref)
            );
        } finally {
            @unlink($file);
        }
    }
}
