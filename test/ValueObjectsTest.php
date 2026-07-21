<?php
/**
 * ValueObjectsTest.php
 * 测试 Scanned / AttributeMetadata / AstVisitorMetadata / Options 值对象
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests;

use PHPUnit\Framework\TestCase;
use PhpParser\Node;
use Vzina\Attributes\Ast\AttributeMetadata;
use Vzina\Attributes\Ast\AstVisitorMetadata;
use Vzina\Attributes\Scan\Scanned;
use Vzina\Attributes\Scan\Options;

class ValueObjectsTest extends TestCase
{
    // ==================== Scanned ====================

    public function testScannedTrue(): void
    {
        $scanned = new Scanned(true);
        $this->assertTrue($scanned->isScanned());
    }

    public function testScannedFalse(): void
    {
        $scanned = new Scanned(false);
        $this->assertFalse($scanned->isScanned());
    }

    // ==================== AttributeMetadata ====================

    public function testAttributeMetadataWithClassAndMethodAttributes(): void
    {
        $metadata = new AttributeMetadata(
            ['App\\Attr\\Controller' => '/api'],
            ['index' => ['App\\Attr\\Get' => null]]
        );

        $this->assertEquals('/api', $metadata->class['App\\Attr\\Controller']);
        $this->assertArrayHasKey('index', $metadata->method);
        $this->assertArrayHasKey('App\\Attr\\Get', $metadata->method['index']);
    }

    public function testAttributeMetadataEmpty(): void
    {
        $metadata = new AttributeMetadata([], []);
        $this->assertEmpty($metadata->class);
        $this->assertEmpty($metadata->method);
    }

    // ==================== AstVisitorMetadata ====================

    public function testAstVisitorMetadataDefaultValues(): void
    {
        $metadata = new AstVisitorMetadata('App\\Test\\Demo');

        $this->assertEquals('App\\Test\\Demo', $metadata->className);
        $this->assertFalse($metadata->hasConstructor);
        $this->assertNull($metadata->constructorNode);
        $this->assertNull($metadata->hasExtends);
        $this->assertNull($metadata->classLike);
    }

    public function testAstVisitorMetadataMutable(): void
    {
        $metadata = new AstVisitorMetadata('App\\Test\\Demo');

        $metadata->hasConstructor = true;
        $metadata->classLike = Node\Stmt\Class_::class;
        $metadata->constructorNode = new Node\Stmt\ClassMethod('__construct');

        $this->assertTrue($metadata->hasConstructor);
        $this->assertEquals(Node\Stmt\Class_::class, $metadata->classLike);
        $this->assertInstanceOf(Node\Stmt\ClassMethod::class, $metadata->constructorNode);
        $this->assertEquals('__construct', $metadata->constructorNode->name->toString());
    }

    // ==================== Options ====================

    public function testOptionsCacheable(): void
    {
        $opt = Options::init(['cacheable' => true]);
        $this->assertTrue($opt->cacheable());

        $opt2 = Options::init(['cacheable' => false]);
        $this->assertFalse($opt2->cacheable());

        $opt3 = Options::init([]);
        $this->assertFalse($opt3->cacheable());
    }

    public function testOptionsCollectors(): void
    {
        $opt = Options::init([
            'collectors' => ['App\\CollectorA', 'App\\CollectorB'],
        ]);

        $this->assertEquals(['App\\CollectorA', 'App\\CollectorB'], $opt->collectors());
    }

    public function testOptionsCollectorsDefault(): void
    {
        $opt = Options::init([]);
        $this->assertEmpty($opt->collectors());
    }

    public function testOptionsAspects(): void
    {
        $opt = Options::init([
            'aspects' => ['App\\Aspect\\CacheAspect' => 10],
        ]);

        $this->assertArrayHasKey('App\\Aspect\\CacheAspect', $opt->aspects());
    }

    public function testOptionsAstVisitorsDeduplicates(): void
    {
        $opt = Options::init([
            'ast_visitors' => ['VisitorA', 'VisitorA', 'VisitorB'],
        ]);

        $visitors = $opt->astVisitors();
        $this->assertCount(2, $visitors);
        $this->assertContains('VisitorA', $visitors);
        $this->assertContains('VisitorB', $visitors);
    }

    public function testOptionsPropertyHandlers(): void
    {
        $opt = Options::init([
            'property_handlers' => ['App\\Handler\\InjectHandler'],
        ]);

        $this->assertCount(1, $opt->propertyHandlers());
        $this->assertEquals('App\\Handler\\InjectHandler', $opt->propertyHandlers()[0]);
    }

    public function testOptionsClassMap(): void
    {
        $opt = Options::init([
            'class_map' => ['App\\Test' => '/path/to/Test.php'],
        ]);

        $this->assertArrayHasKey('App\\Test', $opt->classMap());
    }

    public function testOptionsIgnores(): void
    {
        $opt = Options::init([
            'ignores' => ['DeprecatedAttribute'],
        ]);

        $this->assertContains('DeprecatedAttribute', $opt->ignores());
    }

    public function testOptionsAstProxyLoaders(): void
    {
        $opt = Options::init([
            'ast_proxy_loaders' => ['App\\Loader\\AspectLoader'],
        ]);

        $this->assertContains('App\\Loader\\AspectLoader', $opt->astProxyLoaders());
    }

    public function testOptionsScanPathFiltersExistingDirs(): void
    {
        $tmpDir = sys_get_temp_dir();
        $opt = Options::init([
            'scan_path' => [$tmpDir, '/nonexistent/path/12345'],
        ]);

        $paths = $opt->scanPath();
        $this->assertContains($tmpDir, $paths);
        $this->assertNotContains('/nonexistent/path/12345', $paths);
    }

    public function testOptionsCachePath(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vzina_opt_' . uniqid();
        $opt = Options::init(['cache_path' => $tmpDir]);

        $cachePath = $opt->cachePath();
        $this->assertStringStartsWith($tmpDir, $cachePath);
        $this->assertDirectoryExists($tmpDir);

        @rmdir($tmpDir);
    }

    // ==================== AbstractAttribute::__set_state (var_export 重建) ====================

    public function testSetStateReconstructsAttributeObject(): void
    {
        // 使用 Inject 属性测试（有构造函数属性提升 + 额外属性）
        $original = new \Vzina\Attributes\Attribute\Annotation\Inject(
            value: 'App\\Service\\UserService',
            required: true,
            lazy: true
        );
        $original->targetValue = 'LazyProxy\\App\\Service\\UserService';

        // 模拟 var_export 导出的数组
        $state = [
            'value' => 'App\\Service\\UserService',
            'required' => true,
            'lazy' => true,
            'targetValue' => 'LazyProxy\\App\\Service\\UserService',
        ];

        $restored = \Vzina\Attributes\Attribute\Annotation\Inject::__set_state($state);

        $this->assertInstanceOf(\Vzina\Attributes\Attribute\Annotation\Inject::class, $restored);
        $this->assertEquals('App\\Service\\UserService', $restored->value);
        $this->assertTrue($restored->required);
        $this->assertTrue($restored->lazy);
        $this->assertEquals('LazyProxy\\App\\Service\\UserService', $restored->targetValue);
    }

    public function testSetStateVarExportRoundtrip(): void
    {
        $original = new \Vzina\Attributes\Attribute\Annotation\Value(
            key: 'app.name'
        );

        // var_export 导出为 PHP 代码，再 eval 重建
        $exported = var_export($original, true);
        $restored = eval("return {$exported};");

        $this->assertInstanceOf(\Vzina\Attributes\Attribute\Annotation\Value::class, $restored);
        $this->assertEquals('app.name', $restored->key);
    }

    public function testSetStateWithCacheableAttribute(): void
    {
        $original = new \Vzina\Attributes\Attribute\Annotation\Cacheable(
            prefix: 'user',
            value: '#{params.id}',
            ttl: 3600,
            offset: 60,
            aheadSeconds: 300,
            lockSeconds: 10,
            group: 'redis',
            collect: true,
            evict: false,
            put: false
        );

        $exported = var_export($original, true);
        $restored = eval("return {$exported};");

        $this->assertInstanceOf(\Vzina\Attributes\Attribute\Annotation\Cacheable::class, $restored);
        $this->assertEquals('user', $restored->prefix);
        $this->assertEquals('#{params.id}', $restored->value);
        $this->assertEquals(3600, $restored->ttl);
        $this->assertEquals(60, $restored->offset);
        $this->assertEquals(300, $restored->aheadSeconds);
        $this->assertTrue($restored->collect);
        $this->assertFalse($restored->evict);
    }
}
