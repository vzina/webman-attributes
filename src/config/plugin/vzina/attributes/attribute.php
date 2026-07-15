<?php
/**
 * attribute.php — 扫描与缓存配置
 *
 * 内置组件默认值在 AttributeLoader::DEFAULTS 中，通常无需配置此项。
 */
declare (strict_types=1);

return [
    'cacheable' => true,
    'scan_path' => [app_path()],
    'excludes' => ['config', 'Install.php', 'function.php', 'functions.php'],
    'class_map' => [],
    'ignores' => [],
];
