<?php

$process = [];
if (class_exists(Workerman\Crontab\Crontab::class)) {
    $process['crontab'] = [
        'handler' => Vzina\Attributes\Attribute\Handler\CrontabHandler::class,
    ];
}

return Vzina\Attributes\Attribute\Handler\ProcessHandler::init($process);
