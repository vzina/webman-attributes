<?php
/**
 * ChatController.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\controller;

use app\attributes\Bar;
use app\attributes\Value;
use support\Request;
use Vzina\Attributes\Attribute\Inject;
use Webman\Event\Event;

class FooController
{
    #[Inject(lazy: true)]
    private ?Bar $bar = null;

    #[Inject(lazy: false)]
    private ?Bar $bar2 = null;

    #[Value('app.default_timezone', 'Asia/Shanghai-1')]
    private string $default_timezone;

    public function models(Request $request)
    {
        // 事件触发
        Event::dispatch('test.models', $request);

        return json([
            'code' => 200,
            'msg' => 'success',
            'data' => [],
        ]);
    }
}