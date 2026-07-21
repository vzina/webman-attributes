<?php
/**
 * ScannerIntegrationTest.php
 * 集成测试：Scanner 扫描流程、缓存读写、属性收集
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Scan;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\ConstantsCollector;
use Vzina\Attributes\Scan\DirectHandler;
use Vzina\Attributes\Scan\Options;
use Vzina\Attributes\Scan\Scanner;

class ScannerIntegrationTest extends TestCase
{
    private string $tmpDir;
    private string $scanDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir   = sys_get_temp_dir() . '/vzina_attr_scan_' . uniqid();
        $this->scanDir  = $this->tmpDir . '/src';
        $this->cacheDir = $this->tmpDir . '/cache';
        @mkdir($this->cacheDir, 0755, true);
        @mkdir($this->scanDir, 0755, true);

        AttributeCollector::clear();
        AspectCollector::clear();
        ConstantsCollector::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->rmDir($this->tmpDir);
        AttributeCollector::clear();
        AspectCollector::clear();
        ConstantsCollector::clear();
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

    private function writeFixture(string $relativePath, string $content): string
    {
        $full = $this->scanDir . '/' . $relativePath;
        @mkdir(dirname($full), 0755, true);
        file_put_contents($full, "<?php\n" . $content);
        return $full;
    }

    private function makeOptions(array $overrides = []): Options
    {
        return Options::init(array_merge([
            'scan_path'    => [$this->scanDir],
            'cache_path'   => $this->cacheDir,
            'cacheable'    => false,
            'scan_handler' => DirectHandler::class,
            'collectors'   => [
                AttributeCollector::class,
                AspectCollector::class,
                ConstantsCollector::class,
            ],
            'ast_proxy_loaders' => [
                \Vzina\Attributes\Ast\AspectProxyLoader::class,
            ],
        ], $overrides));
    }

    // ==================== 基础扫描 ====================

    public function testScannerCollectsClassLevelAttribute(): void
    {
        $this->writeFixture('S1/OrderS1.php',
            "namespace App\\S1;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Listener;\n" .
            "#[Listener(event: 'order.created')]\n" .
            "class OrderS1 {}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $classes = AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Listener');
        $this->assertArrayHasKey('App\S1\OrderS1', $classes);
    }

    public function testScannerCollectsMethodLevelAttribute(): void
    {
        $this->writeFixture('S2/PaymentS2.php',
            "namespace App\\S2;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Cacheable;\n" .
            "class PaymentS2 {\n" .
            "    #[Cacheable(prefix: 'pay', ttl: 60)]\n" .
            "    public function process(): bool { return true; }\n" .
            "}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $methods = AttributeCollector::getClassMethodAttribute('App\S2\PaymentS2', 'process');
        $this->assertArrayHasKey('Vzina\Attributes\Attribute\Annotation\Cacheable', $methods);
    }

    public function testScannerCollectsPropertyLevelAttribute(): void
    {
        $this->writeFixture('S3/HomeS3.php',
            "namespace App\\S3;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Inject;\n" .
            "class HomeS3 {\n" .
            "    #[Inject(value: 'App\\\\Service\\\\Order')]\n" .
            "    private \$orderService;\n" .
            "}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $prop = AttributeCollector::getClassPropertyAttribute('App\S3\HomeS3', 'orderService');
        $this->assertArrayHasKey('Vzina\Attributes\Attribute\Annotation\Inject', $prop);
    }

    // ==================== 缓存 ====================

    public function testScannerWritesCacheFiles(): void
    {
        $this->writeFixture('S4/UserS4.php',
            "namespace App\\S4;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Depend;\n" .
            "#[Depend(id: 'user.s4', singleton: true)]\n" .
            "class UserS4 {}\n"
        );

        $scanner = new Scanner($this->makeOptions(['cacheable' => false]));
        $scanner->scan([]);

        $this->assertFileExists($this->cacheDir . '/classmap.cache.php');
        $this->assertFileExists($this->cacheDir . '/scan.cache.php');
        $map = include $this->cacheDir . '/classmap.cache.php';
        $this->assertIsArray($map);
        $this->assertArrayHasKey('App\S4\UserS4', $map);
    }

    public function testScannerLoadsFromCacheWhenCacheable(): void
    {
        $cacheMap = ['App\S5\HelperS5' => $this->scanDir . '/S5/HelperS5.php'];
        file_put_contents($this->cacheDir . '/classmap.cache.php',
            "<?php\nreturn " . var_export($cacheMap, true) . ";\n");
        file_put_contents($this->cacheDir . '/scan.cache.php',
            "<?php return " . time() . ";\n");

        $scanner = new Scanner($this->makeOptions(['cacheable' => true]));
        $result = $scanner->scan([]);
        $this->assertArrayHasKey('App\S5\HelperS5', $result);
    }

    // ==================== 多注解、目录、重复扫描 ====================

    public function testScannerCollectsMultipleAttributesOnSameClass(): void
    {
        $this->writeFixture('S6/MultiS6.php',
            "namespace App\\S6;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Crontab;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Listener;\n" .
            "#[Listener(event: 's6')]\n" .
            "#[Crontab(rule: '* * * * *')]\n" .
            "class MultiS6 { public function handle(): void {} }\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $this->assertArrayHasKey('App\S6\MultiS6',
            AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Listener'));
        $this->assertArrayHasKey('App\S6\MultiS6',
            AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Crontab'));
    }

    public function testScannerHandlesEmptyDirectory(): void
    {
        $scanner = new Scanner($this->makeOptions());
        $result = $scanner->scan([]);
        $this->assertIsArray($result);
    }

    public function testScannerRescanPreservesOldAttributes(): void
    {
        $this->writeFixture('S8/AlphaS8.php',
            "namespace App\\S8;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Listener;\n" .
            "#[Listener(event: 'a8')]\n" .
            "class AlphaS8 {}\n"
        );

        $scanner = new Scanner($this->makeOptions(['cacheable' => false]));
        $scanner->scan([]);

        touch($this->scanDir . '/S8/AlphaS8.php', time() - 3600);

        $this->writeFixture('S8/BetaS8.php',
            "namespace App\\S8;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Listener;\n" .
            "#[Listener(event: 'b8')]\n" .
            "class BetaS8 {}\n"
        );

        $scanner2 = new Scanner($this->makeOptions(['cacheable' => false]));
        $scanner2->scan([]);

        $classes = AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Listener');
        $this->assertArrayHasKey('App\S8\AlphaS8', $classes, 'Old class should persist');
        $this->assertArrayHasKey('App\S8\BetaS8', $classes, 'New class should be collected');
    }

    public function testScannerClearsRemovedClasses(): void
    {
        $this->writeFixture('S9/GoneS9.php',
            "namespace App\\S9;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Listener;\n" .
            "#[Listener(event: 'g9')]\n" .
            "class GoneS9 {}\n"
        );

        $scanner = new Scanner($this->makeOptions(['cacheable' => false]));
        $scanner->scan([]);

        $this->assertArrayHasKey('App\S9\GoneS9',
            AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Listener'));

        unlink($this->scanDir . '/S9/GoneS9.php');

        $scanner2 = new Scanner($this->makeOptions(['cacheable' => false]));
        $scanner2->scan([]);

        $this->assertArrayNotHasKey('App\S9\GoneS9',
            AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Listener'),
            'Deleted class should be removed from collectors');
    }

    public function testScannerCollectsValidateAttribute(): void
    {
        $this->writeFixture('S11/ValidateS11.php',
            "namespace App\\S11;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Validate;\n" .
            "class ValidateS11 {\n" .
            "    #[Validate(rules: ['email' => 'required|email'], messages: ['email.required' => '必填'])]\n" .
            "    public function store(): void {}\n" .
            "}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $methods = AttributeCollector::getClassMethodAttribute('App\S11\ValidateS11', 'store');
        $this->assertArrayHasKey('Vzina\Attributes\Attribute\Annotation\Validate', $methods);
        $this->assertEquals(['email' => 'required|email'], $methods['Vzina\Attributes\Attribute\Annotation\Validate']->rules);
        $this->assertEquals(['email.required' => '必填'], $methods['Vzina\Attributes\Attribute\Annotation\Validate']->messages);
    }

    public function testScannerCollectsRetryAttribute(): void
    {
        $this->writeFixture('S12/RetryS12.php',
            "namespace App\\S12;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Retry;\n" .
            "class RetryS12 {\n" .
            "    #[Retry(maxAttempts: 5, delayMs: 200, backoff: 2.0, on: ['RuntimeException'])]\n" .
            "    public function callApi(): string { return 'ok'; }\n" .
            "}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $methods = AttributeCollector::getClassMethodAttribute('App\S12\RetryS12', 'callApi');
        $this->assertArrayHasKey('Vzina\Attributes\Attribute\Annotation\Retry', $methods);
        $this->assertEquals(5, $methods['Vzina\Attributes\Attribute\Annotation\Retry']->maxAttempts);
        $this->assertSame(2.0, $methods['Vzina\Attributes\Attribute\Annotation\Retry']->backoff);
    }

    public function testScannerCollectsTraceAttribute(): void
    {
        $this->writeFixture('S13/TraceS13.php',
            "namespace App\\S13;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Trace;\n" .
            "class TraceS13 {\n" .
            "    #[Trace(spanName: 'api.checkout')]\n" .
            "    public function checkout(): string { return 'ok'; }\n" .
            "}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $methods = AttributeCollector::getClassMethodAttribute('App\S13\TraceS13', 'checkout');
        $this->assertArrayHasKey('Vzina\Attributes\Attribute\Annotation\Trace', $methods);
        $this->assertEquals('api.checkout', $methods['Vzina\Attributes\Attribute\Annotation\Trace']->spanName);
    }

    public function testScannerCollectsCommandAttribute(): void
    {
        $this->writeFixture('S14/GreetS14.php',
            "namespace App\\S14;\n" .
            "use Vzina\\Attributes\\Attribute\\Annotation\\Command;\n" .
            "#[Command(name: 'app:greet', description: 'Say hello')]\n" .
            "class GreetS14 {}\n"
        );

        $scanner = new Scanner($this->makeOptions());
        $scanner->scan([]);

        $classes = AttributeCollector::getClassesByAttribute('Vzina\Attributes\Attribute\Annotation\Command');
        $this->assertArrayHasKey('App\S14\GreetS14', $classes);
        $this->assertEquals('app:greet', $classes['App\S14\GreetS14']->name);
        $this->assertEquals('Say hello', $classes['App\S14\GreetS14']->description);
    }
}
