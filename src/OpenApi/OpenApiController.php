<?php
/**
 * OpenApiController — OpenAPI 可视化页面。
 *
 * 提供 Swagger UI 交互式 API 文档，以及 OpenAPI JSON API 端点。
 * OpenAPI 规范由 Generator 从路由注解自动生成。
 *
 * 路由（可在 app.php openapi.enable 关闭）：
 *
 *   GET /openapi      → Swagger UI 页面
 *   GET /openapi/json → OpenAPI 3.0 JSON（含 CORS 头）
 */
declare (strict_types=1);

namespace Vzina\Attributes\OpenApi;

use Webman\Http\Request;
use Webman\Http\Response;

class OpenApiController
{
    /** @var array|null 进程级缓存，避免每次请求重新扫描 */
    private static ?array $cachedSpec = null;

    /** Swagger UI HTML 页面 */
    public function index(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .info .title { font-size: 28px; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: '/openapi/json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                plugins: [SwaggerUIBundle.plugins.DownloadUrl],
                layout: 'StandaloneLayout',
            });
        };
    </script>
</body>
</html>
HTML;
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /** OpenAPI JSON 端点 */
    public function json(Request $request): Response
    {
        $spec = self::$cachedSpec ??= Generator::generate([
            'title'   => config('plugin.vzina.attributes.app.openapi.title', 'API Documentation'),
            'version' => config('plugin.vzina.attributes.app.openapi.version', '1.0.0'),
        ]);

        $json = json_encode($spec,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return new Response(200, [
            'Content-Type'                     => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'GET, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type',
            'Cache-Control'                    => 'public, max-age=300',
        ], $json);
    }
}