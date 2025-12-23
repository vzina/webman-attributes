<?php
/**
 * BootAttribute.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes;

use Webman\Bootstrap;
use Workerman\Worker;

class BootAttribute implements Bootstrap
{
    public static function start(?Worker $worker)
    {
        AttributeLoader::init();

        if (Worker::$onMasterReload) {
            Worker::$onMasterReload = function () {

            };
        }

    }
}