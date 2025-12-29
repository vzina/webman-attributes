<?php

return [
    'monitor_dir' => array_merge([
        app_path(),
        config_path(),
        base_path() . '/process',
        base_path() . '/support',
        base_path() . '/resource',
        base_path() . '/.env',
    ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
    // Files with these suffixes will be monitored
    'monitor_extensions' => [
        'php', 'html', 'htm', 'env'
    ],
];