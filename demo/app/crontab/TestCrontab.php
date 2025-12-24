<?php
/**
 * TestCrontab.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace app\crontab;

#[Crontab('*/1 * * * * *')]
class TestCrontab
{
    public function handle()
    {
        var_dump(__METHOD__);
    }
}