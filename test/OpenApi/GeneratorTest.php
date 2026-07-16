<?php
/**
 * GeneratorTest.php
 * 测试 OpenAPI 文档生成器的基础功能
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\OpenApi;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Attribute\Route\AutoController;
use Vzina\Attributes\Attribute\Route\Controller;
use Vzina\Attributes\Attribute\Route\GetMapping;
use Vzina\Attributes\Attribute\Route\PostMapping;
use Vzina\Attributes\Attribute\Route\Resource;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\OpenApi\Generator;

class GeneratorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/Stubs.php';
    }

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

    public function testGeneratorReturnsValidOpenApiStructure(): void
    {
        $spec = Generator::generate(['title' => 'Test API', 'version' => '2.0']);

        $this->assertEquals('3.0.3', $spec['openapi']);
        $this->assertEquals('Test API', $spec['info']['title']);
        $this->assertEquals('2.0', $spec['info']['version']);
        $this->assertIsArray($spec['paths']);
    }

    public function testGeneratorCreatesPathFromController(): void
    {
        $ctrl = new Controller(prefix: '/api/users');
        AttributeCollector::collectClass('App\Controller\UserController', Controller::class, $ctrl);

        $get = new GetMapping(path: '');
        AttributeCollector::collectMethod('App\Controller\UserController', 'index', GetMapping::class, $get);

        $spec = Generator::generate();

        $this->assertArrayHasKey('/api/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/users']);
    }

    public function testGeneratorIncludesMultipleMethods(): void
    {
        $ctrl = new Controller(prefix: '/api/orders');
        AttributeCollector::collectClass('App\Controller\OrderController', Controller::class, $ctrl);

        // index: empty path = prefix only
        AttributeCollector::collectMethod('App\Controller\OrderController', 'index', GetMapping::class,
            new GetMapping(path: ''));
        // store: null path = auto snake_case
        AttributeCollector::collectMethod('App\Controller\OrderController', 'store', PostMapping::class,
            new PostMapping(path: null));

        $spec = Generator::generate();

        $this->assertArrayHasKey('get', $spec['paths']['/api/orders']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/orders/store']);
    }

    public function testGeneratorUsesCustomPath(): void
    {
        $ctrl = new Controller(prefix: '/api');
        AttributeCollector::collectClass('App\Controller\ReportController', Controller::class, $ctrl);

        AttributeCollector::collectMethod('App\Controller\ReportController', 'daily', GetMapping::class,
            new GetMapping(path: '/report/daily'));

        $spec = Generator::generate();

        $this->assertArrayHasKey('/report/daily', $spec['paths']);
    }

    public function testGeneratorSetsOperationIdAndTags(): void
    {
        $ctrl = new Controller(prefix: '/api/items');
        AttributeCollector::collectClass('App\Controller\ItemController', Controller::class, $ctrl);

        AttributeCollector::collectMethod('App\Controller\ItemController', 'show', GetMapping::class,
            new GetMapping(path: ''));

        $spec = Generator::generate();
        $op = $spec['paths']['/api/items']['get'];

        $this->assertEquals('App\Controller\ItemController.show', $op['operationId']);
        $this->assertContains('item', $op['tags']);
    }

    public function testGeneratorWritesToFile(): void
    {
        $tmp = sys_get_temp_dir() . '/openapi_test_' . uniqid() . '.json';

        Generator::writeToFile($tmp, ['title' => 'File Test']);

        $this->assertFileExists($tmp);
        $content = json_decode(file_get_contents($tmp), true);
        $this->assertEquals('File Test', $content['info']['title']);

        unlink($tmp);
    }

    // ==================== AutoController ====================

    public function testAutoControllerGeneratesPathForPublicMethods(): void
    {
        $auto = new AutoController(prefix: '/stub');
        AttributeCollector::collectClass(StubAutoController::class, AutoController::class, $auto);

        $spec = Generator::generate();

        // index 方法 → GET /stub/index
        $this->assertArrayHasKey('/stub/index', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/stub/index']);
        $this->assertEquals(
            StubAutoController::class . '.index',
            $spec['paths']['/stub/index']['get']['operationId']
        );

        // list 方法 → GET /stub/list
        $this->assertArrayHasKey('/stub/list', $spec['paths']);
    }

    public function testAutoControllerSkipsMagicMethods(): void
    {
        $auto = new AutoController(prefix: '/stub');
        AttributeCollector::collectClass(StubAutoController::class, AutoController::class, $auto);

        $spec = Generator::generate();

        // _helper 以 _ 开头，应跳过
        $this->assertArrayNotHasKey('/stub/_helper', $spec['paths']);
        // staticMethod 是静态方法，非公开实例方法？
        // 实际上 ReflectionMethod::IS_PUBLIC 包含 static，所以会注册
        // 这是与 DispatcherFactory 行为一致的
    }

    public function testAutoControllerRespectsCustomMethods(): void
    {
        $auto = new AutoController(prefix: '/stub', options: ['methods' => ['GET', 'POST']]);
        AttributeCollector::collectClass(StubAutoController::class, AutoController::class, $auto);

        $spec = Generator::generate();

        $this->assertArrayHasKey('get', $spec['paths']['/stub/index']);
        $this->assertArrayHasKey('post', $spec['paths']['/stub/index']);
        $this->assertArrayNotHasKey('delete', $spec['paths']['/stub/index']);
    }

    // ==================== Resource ====================

    public function testResourceGeneratesStandardRestfulRoutes(): void
    {
        $res = new Resource(prefix: '/stub-resource');
        AttributeCollector::collectClass(StubResourceController::class, Resource::class, $res);

        $spec = Generator::generate();

        // GET /stub-resource → index
        $this->assertArrayHasKey('/stub-resource', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/stub-resource']);

        // POST /stub-resource → store
        $this->assertArrayHasKey('post', $spec['paths']['/stub-resource']);

        // GET /stub-resource/{id} → show
        $this->assertArrayHasKey('/stub-resource/{id}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/stub-resource/{id}']);
        $this->assertEquals('id',
            $spec['paths']['/stub-resource/{id}']['get']['parameters'][0]['name'] ?? null);
        $this->assertEquals('path',
            $spec['paths']['/stub-resource/{id}']['get']['parameters'][0]['in'] ?? null);

        // PUT /stub-resource/{id} → update
        $this->assertArrayHasKey('put', $spec['paths']['/stub-resource/{id}']);

        // DELETE /stub-resource/{id} → destroy
        $this->assertArrayHasKey('delete', $spec['paths']['/stub-resource/{id}']);
    }

    public function testResourceOnlyMode(): void
    {
        $res = new Resource(prefix: '/stub-resource', options: ['methods' => ['only' => ['index', 'show']]]);
        AttributeCollector::collectClass(StubResourceController::class, Resource::class, $res);

        $spec = Generator::generate();

        $this->assertArrayHasKey('get', $spec['paths']['/stub-resource']);
        $this->assertArrayHasKey('get', $spec['paths']['/stub-resource/{id}']);
        $this->assertArrayNotHasKey('post', $spec['paths']['/stub-resource'] ?? []);
        $this->assertArrayNotHasKey('delete', $spec['paths']['/stub-resource/{id}'] ?? []);
    }

    public function testResourceExceptMode(): void
    {
        $res = new Resource(prefix: '/stub-resource', options: ['methods' => ['except' => ['destroy']]]);
        AttributeCollector::collectClass(StubResourceController::class, Resource::class, $res);

        $spec = Generator::generate();

        $this->assertArrayHasKey('get', $spec['paths']['/stub-resource']);
        $this->assertArrayNotHasKey('delete', $spec['paths']['/stub-resource/{id}'] ?? []);
    }
}
