<?php
/**
 * LazyLoaderTest.php
 * 测试 LazyLoader 懒加载代理名称生成
 */
declare (strict_types=1);

namespace Vzina\Attributes\Tests\Ast\LazyLoader;

use PHPUnit\Framework\TestCase;
use Vzina\Attributes\Ast\LazyLoader\LazyLoader;

class LazyLoaderTest extends TestCase
{
    // ==================== lazyName ====================

    public function testLazyNamePrependsNamespace(): void
    {
        $result = LazyLoader::lazyName('App\\Service\\UserService');
        $this->assertEquals('LazyProxy\\App\\Service\\UserService', $result);
    }

    public function testLazyNameWithSimpleClass(): void
    {
        $result = LazyLoader::lazyName('UserService');
        $this->assertEquals('LazyProxy\\UserService', $result);
    }

    public function testLazyNameIsConsistent(): void
    {
        $r1 = LazyLoader::lazyName('App\\Test');
        $r2 = LazyLoader::lazyName('App\\Test');

        $this->assertEquals($r1, $r2);
    }
}
