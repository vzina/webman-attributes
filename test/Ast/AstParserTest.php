<?php
/**
 * AstParserTest.php
 * 测试 AstParser 核心功能
 */
declare(strict_types=1);

namespace Vzina\Attributes\Tests\Ast;

use PHPUnit\Framework\TestCase;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionParameter;
use Vzina\Attributes\Ast\AstParser;
use Symfony\Component\Finder\Finder;
use RuntimeException;

class AstParserTest extends TestCase
{
    /** @var AstParser */
    private $astParser;

    /** @var string 测试临时文件目录 */
    private $fixtureDir;

    /**
     * 测试前置：初始化 AstParser 实例 + 创建测试临时目录
     */
    protected function setUp(): void
    {
        parent::setUp();
        // 初始化 AstParser 单例
        $this->astParser = AstParser::getInstance();
        // 定义测试临时目录
        $this->fixtureDir = __DIR__ . '/../Fixtures/Ast';
        // 创建目录（若不存在）
        if (!is_dir($this->fixtureDir)) {
            mkdir($this->fixtureDir, 0755, true);
        }
    }

    /**
     * 测试后置：清理测试临时文件
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        // 遍历并删除测试目录下的PHP文件
        $finder = Finder::create()->files()->in($this->fixtureDir)->name('*.php');
        foreach ($finder as $file) {
            unlink($file->getPathname());
        }
        // 清空单例（避免跨测试污染）
        $reflection = new ReflectionClass(AstParser::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null);
    }

    // -------------------------- 核心方法测试 --------------------------

    /**
     * 测试1：单例模式 - getInstance 始终返回同一个实例
     */
    public function testSingletonInstance(): void
    {
        $instance1 = AstParser::getInstance();
        $instance2 = AstParser::getInstance();

        // 断言两个实例是同一个对象
        $this->assertSame($instance1, $instance2);
    }

    /**
     * 测试2：parse 方法 - 解析PHP代码为AST节点
     */
    public function testParsePhpCodeToAstNodes(): void
    {
        // 测试用的简单PHP代码
        $phpCode = <<<'PHP'
<?php
namespace Vzina\Tests\Fixtures;

class TestClass {
    public function testMethod(string $name = 'test'): int {
        return 123;
    }
}
PHP;

        // 解析代码为AST节点
        $stmts = $this->astParser->parse($phpCode);

        // 断言解析结果非空且是数组
        $this->assertNotNull($stmts);
        $this->assertIsArray($stmts);
        // 断言包含命名空间节点
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Namespace_::class, $stmts[0]);
    }

    /**
     * 测试3：parseClassByStmts 方法 - 从AST节点解析完整类名
     */
    public function testParseFullClassNameFromStmts(): void
    {
        // 1. 创建测试用的PHP文件
        $testClassName = 'Vzina\Tests\Fixtures\TestParseClass';
        $testFile = $this->fixtureDir . '/TestParseClass.php';
        file_put_contents($testFile, <<<'PHP'
<?php
namespace Vzina\Tests\Fixtures;

class TestParseClass {}
PHP
        );

        // 2. 解析文件内容为AST节点
        $stmts = $this->astParser->parse(file_get_contents($testFile));
        // 3. 解析完整类名
        $className = $this->astParser->parseClassByStmts($stmts);

        // 断言解析出的类名正确
        $this->assertEquals($testClassName, $className);
    }

    /**
     * 测试4：getAllClassesByPath 方法 - 扫描路径获取类反射映射
     */
    public function testGetAllClassesByPath(): void
    {
        // 1. 创建2个测试类文件
        $class1File = $this->fixtureDir . '/TestClass1.php';
        file_put_contents($class1File, <<<'PHP'
<?php
namespace Vzina\Tests\Fixtures;

class TestClass1 {}
PHP
        );

        $class2File = $this->fixtureDir . '/TestClass2.php';
        file_put_contents($class2File, <<<'PHP'
<?php
namespace Vzina\Tests\Fixtures;

interface TestClass2 {}
PHP
        );

        // 2. 注册自动加载（确保能找到测试类）
        spl_autoload_register(function ($className) {
            $file = $this->fixtureDir . '/' . str_replace('Vzina\Tests\Fixtures\\', '', $className) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });

        // 3. 扫描指定路径的类
        $classMap = $this->astParser->getAllClassesByPath($this->fixtureDir);

        // 4. 断言结果包含2个类（返回 format: className => filePath）
        $this->assertCount(2, $classMap);
        // 断言键是完整类名，值是文件路径字符串
        $this->assertArrayHasKey('Vzina\Tests\Fixtures\TestClass1', $classMap);
        $this->assertArrayHasKey('Vzina\Tests\Fixtures\TestClass2', $classMap);
        $this->assertIsString($classMap['Vzina\Tests\Fixtures\TestClass1']);
        $this->assertStringEndsWith('.php', $classMap['Vzina\Tests\Fixtures\TestClass1']);
    }

    /**
     * 测试6：createArrayExpr 方法 - 从数组生成AST数组表达式
     */
    public function testCreateArrayExprFromArrayValue(): void
    {
        // 1. 测试数组
        $testArray = [
            'string_key' => 'test',
            123 => 456,
            'nested' => ['a' => 'b']
        ];

        // 2. 反射调用私有方法 createArrayExpr（因为是private）
        $reflection = new ReflectionClass($this->astParser);
        $createArrayExprMethod = $reflection->getMethod('createArrayExpr');
        $createArrayExprMethod->setAccessible(true);
        $arrayExpr = $createArrayExprMethod->invoke($this->astParser, $testArray);

        // 3. 断言数组表达式结构正确
        $this->assertInstanceOf(Array_::class, $arrayExpr);

        $arrayNodeReflection = new ReflectionClass($arrayExpr);
        $kindProperty = $arrayNodeReflection->getProperty('attributes');
        $kindProperty->setAccessible(true); // 解除 protected 限制
        $arrayKind = $kindProperty->getValue($arrayExpr); // 获取 $kind 值
        $this->assertEquals(Array_::KIND_SHORT, $arrayKind['kind']); // 验证是短数组 []

        // 断言第一个元素（string_key => test）
        $firstItem = $arrayExpr->items[0];
        $this->assertNotNull($firstItem->key); // 确保key节点非空
        $this->assertEquals('string_key', $firstItem->key->value);
        $this->assertEquals('test', $firstItem->value->value);

        // 断言第二个元素（123 => 456）
        $secondItem = $arrayExpr->items[1];
        $this->assertNotNull($secondItem->key);
        $this->assertEquals(123, $secondItem->key->value);
        $this->assertEquals(456, $secondItem->value->value);

        // 断言嵌套数组（验证多层数组解析正确）
        $thirdItem = $arrayExpr->items[2];
        $this->assertEquals('nested', $thirdItem->key->value);
        $this->assertInstanceOf(Array_::class, $thirdItem->value);

        // 验证嵌套数组也是短数组（同样用反射获取kind）
        $nestedArrayReflection = new ReflectionClass($thirdItem->value);
        $nestedKindProperty = $nestedArrayReflection->getProperty('attributes');
        $nestedKindProperty->setAccessible(true);
        $nestedArrayKind = $nestedKindProperty->getValue($thirdItem->value);
        $this->assertEquals(Array_::KIND_SHORT, $nestedArrayKind['kind']);
    }

}