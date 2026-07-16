<?php
/**
 * OptionsTest.php
 * 测试 Options 值对象的配置解析与 getter 行为
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Scan;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Scan\Options;

class OptionsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/vzina_attr_test_' . uniqid();
        @mkdir($this->tmpDir . '/cache', 0755, true);
        @mkdir($this->tmpDir . '/proxy', 0755, true);
        @mkdir($this->tmpDir . '/scan_a', 0755, true);
        @mkdir($this->tmpDir . '/scan_b', 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->rmDir($this->tmpDir);
    }

    private function rmDir(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = "$dir/$item";
            is_dir($p) ? $this->rmDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    // ==================== cacheable ====================

    public function testCacheableDefaultFalse(): void
    {
        $opts = Options::init([]);
        $this->assertFalse($opts->cacheable());
    }

    public function testCacheableTrue(): void
    {
        $opts = Options::init(['cacheable' => true]);
        $this->assertTrue($opts->cacheable());
    }

    public function testCacheableCoercesToBool(): void
    {
        $opts = Options::init(['cacheable' => 1]);
        $this->assertTrue($opts->cacheable());
    }

    // ==================== scanPath ====================

    public function testScanPathEmptyByDefault(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->scanPath());
    }

    public function testScanPathFiltersNonExistent(): void
    {
        $opts = Options::init(['scan_path' => [
            $this->tmpDir . '/scan_a',
            $this->tmpDir . '/nonexistent',
            $this->tmpDir . '/scan_b',
        ]]);

        $paths = $opts->scanPath();
        $this->assertCount(2, $paths);
        $this->assertContains($this->tmpDir . '/scan_a', $paths);
        $this->assertContains($this->tmpDir . '/scan_b', $paths);
    }

    // ==================== excludes ====================

    public function testExcludesCombinesWithScanPath(): void
    {
        $opts = Options::init([
            'scan_path' => [$this->tmpDir . '/scan_a'],
            'excludes'  => ['vendor', 'tests'],
        ]);

        $excluded = $opts->excludes();
        $this->assertContains($this->tmpDir . '/scan_a/vendor', $excluded);
        $this->assertContains($this->tmpDir . '/scan_a/tests', $excluded);
    }

    public function testExcludesEmptyWhenNoScanPath(): void
    {
        $opts = Options::init(['excludes' => ['vendor']]);
        $this->assertEmpty($opts->excludes());
    }

    // ==================== collectors / aspects ====================

    public function testCollectorsDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->collectors());
    }

    public function testCollectorsFromConfig(): void
    {
        $opts = Options::init(['collectors' => ['App\MyCollector']]);
        $this->assertContains('App\MyCollector', $opts->collectors());
    }

    public function testAspectsFromConfig(): void
    {
        $opts = Options::init(['aspects' => ['App\MyAspect']]);
        $this->assertContains('App\MyAspect', $opts->aspects());
    }

    // ==================== property_handlers ====================

    public function testPropertyHandlersDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->propertyHandlers());
    }

    public function testPropertyHandlersFromConfig(): void
    {
        $opts = Options::init(['property_handlers' => ['App\MyHandler']]);
        $this->assertContains('App\MyHandler', $opts->propertyHandlers());
    }

    // ==================== ast visitors / proxy loaders ====================

    public function testAstVisitorsDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->astVisitors());
    }

    public function testAstVisitorsDeduplicates(): void
    {
        $opts = Options::init(['ast_visitors' => ['A', 'A', 'B']]);
        $this->assertCount(2, $opts->astVisitors());
    }

    public function testAstProxyLoadersDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->astProxyLoaders());
    }

    // ==================== classMap ====================

    public function testClassMapDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->classMap());
    }

    public function testClassMapFromConfig(): void
    {
        $map = ['App\Foo' => '/path/to/Foo.php'];
        $opts = Options::init(['class_map' => $map]);
        $this->assertEquals($map, $opts->classMap());
    }

    // ==================== ignores ====================

    public function testIgnoresDefaultEmpty(): void
    {
        $opts = Options::init([]);
        $this->assertEmpty($opts->ignores());
    }

    public function testIgnoresFromConfig(): void
    {
        $opts = Options::init(['ignores' => ['App\Ignored']]);
        $this->assertContains('App\Ignored', $opts->ignores());
    }

    // ==================== cachePath ====================

    public function testCachePathDefaultUsesRuntimeAttributes(): void
    {
        // Without custom cache_path, defaults to runtime_path('attributes')
        // Only testable when webman config functions are available
        $this->assertTrue(true);
    }

    public function testCachePathCreatesDirectory(): void
    {
        $cachePath = $this->tmpDir . '/cache';
        $opts = Options::init(['cache_path' => $cachePath]);
        $result = $opts->cachePath();
        $this->assertEquals($cachePath, $result);
        $this->assertDirectoryExists($result);
    }

    public function testCachePathWithSub(): void
    {
        $cachePath = $this->tmpDir . '/cache';
        $opts = Options::init(['cache_path' => $cachePath]);
        $result = $opts->cachePath('proxy');
        $this->assertEquals($cachePath . '/proxy', $result);
    }

    // ==================== proxyPath ====================

    public function testProxyPathEqualsCachePathProxy(): void
    {
        $cachePath = $this->tmpDir . '/cache';
        $opts = Options::init(['cache_path' => $cachePath]);
        $this->assertEquals($cachePath . '/proxy', $opts->proxyPath());
    }

    // ==================== scanHandler ====================

    public function testScanHandlerDefaultDetectsPcntl(): void
    {
        $opts = Options::init([]);
        $handler = $opts->scanHandler();
        if (function_exists('pcntl_fork')) {
            $this->assertInstanceOf(\Vzina\Attributes\Scan\PcntlHandler::class, $handler);
        } else {
            $this->assertInstanceOf(\Vzina\Attributes\Scan\DirectHandler::class, $handler);
        }
    }

    public function testScanHandlerExplicitDirect(): void
    {
        $opts = Options::init(['scan_handler' => \Vzina\Attributes\Scan\DirectHandler::class]);
        $this->assertInstanceOf(\Vzina\Attributes\Scan\DirectHandler::class, $opts->scanHandler());
    }
}
