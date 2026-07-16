<?php
/**
 * OpenApiTestFixtures — 用于 OpenAPI Generator 测试的桩类。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\OpenApi;

/**
 * AutoController 桩 — 模拟自动路由控制器。
 *
 * @method array index()  列表页
 * @method array show(int $id) 详情页
 * @method array store()  创建
 */
class StubAutoController
{
    /**
     * 获取用户列表
     * @param int $page 页码
     * @return array
     */
    public function index(int $page = 1): array
    {
        return [];
    }

    protected function _helper(): void {}

    public static function staticMethod(): void {}

    /** @return array */
    public function list(): array
    {
        return [];
    }
}

/**
 * Resource 桩 — 模拟 RESTful 控制器。
 */
class StubResourceController
{
    /** @return array */
    public function index(): array { return []; }

    /** @param array $data */
    public function store(array $data): array { return []; }

    /** @param int $id */
    public function show(int $id): array { return []; }

    /** @param int $id @param array $data */
    public function update(int $id, array $data): array { return []; }

    /** @param int $id */
    public function destroy(int $id): bool { return true; }
}
