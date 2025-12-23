<?php
/**
 * attribute.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

return [
    'scan_path' => [
        app_path(),
    ],
    'class_map' => [],
    'ignores' => [],
    'collectors' => [
        Vzina\Attributes\Collector\AttributeCollector::class,
        Vzina\Attributes\Collector\AspectCollector::class,
    ],
    'aspects' => [
        Vzina\Attributes\Attribute\InjectAspect::class,
    ],
];