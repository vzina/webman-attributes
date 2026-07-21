<?php

Vzina\Attributes\Attribute\Route\DispatcherFactory::init();

// OpenAPI 可视化（可通过 config 关闭）
if (config('plugin.vzina.attributes.app.openapi.enable', true)) {
    Webman\Route::get('/openapi', [Vzina\Attributes\OpenApi\OpenApiController::class, 'index']);
    Webman\Route::get('/openapi/json', [Vzina\Attributes\OpenApi\OpenApiController::class, 'json']);
}
