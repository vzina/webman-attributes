<?php
/**
 * TestListener.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\listeners;

#[Listener]
class TestListener
{
    public function listen()
    {
        return [
            'test.models'
        ];
    }

    public function handle()
    {
        var_dump(__METHOD__);
    }
}