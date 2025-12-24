<?php
/**
 * ChatController.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\controller;

use app\attributes\Bar;
use support\Request;
use Vzina\Attributes\Attribute\Inject;
use Webman\Event\Event;

class FooController
{
    #[Inject(lazy: true)]
    private ?Bar $bar = null;

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