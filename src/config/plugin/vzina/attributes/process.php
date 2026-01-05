<?php

$process = [];
if (class_exists(Workerman\Crontab\Crontab::class)) {
    $process['crontab'] = [
        'handler' => Vzina\Attributes\Attribute\CrontabHandler::class,
    ];
}

return Vzina\Attributes\Attribute\ProcessHandler::init($process);
