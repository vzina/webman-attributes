<?php
/**
 * Options — 配置值对象。
 *
 * 从 attribute.php 合并 DEFAULTS 后构造，对外暴露类型安全的 getter。
 * 内置默认值在 AttributeLoader::DEFAULTS 常量中定义。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

class Options
{
    public function __construct(protected array $options) {}

    public static function init(array $config = []): self
    {
        return new self($config);
    }

    /** 是否启用缓存（跳过扫描，直接从 .cache.php 加载） */
    public function cacheable(): bool
    {
        return (bool) ($this->options['cacheable'] ?? false);
    }

    /** 扫描处理器：pcntl 可用时用子进程隔离，否则直接扫描 */
    public function scanHandler(): ScanHandlerInterface
    {
        $class = $this->options['scan_handler'] ?? null;
        if ($class === null) {
            $class = function_exists('pcntl_fork') ? PcntlHandler::class : DirectHandler::class;
        }
        return new $class;
    }

    /** 缓存根目录，不存在则自动创建 */
    public function cachePath(string $sub = ''): string
    {
        $path = path_combine((string) ($this->options['cache_path'] ?? runtime_path('attributes')), $sub);
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }

    /** 代理文件目录 */
    public function proxyPath(): string
    {
        return $this->cachePath('proxy');
    }

    /** 扫描目录列表（仅返回存在的目录） */
    public function scanPath(): array
    {
        $dirs = [];
        foreach ((array) ($this->options['scan_path'] ?? []) as $dir) {
            file_exists($dir) and $dirs[] = $dir;
        }
        return $dirs;
    }

    /** 排除路径（相对于 scanPath 的拼接结果） */
    public function excludes(): array
    {
        $base = $this->scanPath();
        $combine = fn($front, $back) => $front . ($back ? (DIRECTORY_SEPARATOR . ltrim($back, DIRECTORY_SEPARATOR)) : $back);
        return array_reduce(
            array_map(fn($e) => array_map(fn($p) => $combine($p, $e), $base), (array) ($this->options['excludes'] ?? [])),
            'array_merge',
            []
        );
    }

    public function collectors(): array    { return (array) ($this->options['collectors'] ?? []); }
    public function ignores(): array       { return (array) ($this->options['ignores'] ?? []); }
    public function aspects(): array       { return (array) ($this->options['aspects'] ?? []); }
    public function astVisitors(): array   { return array_unique((array) ($this->options['ast_visitors'] ?? [])); }
    public function propertyHandlers(): array { return (array) ($this->options['property_handlers'] ?? []); }
    public function astProxyLoaders(): array  { return (array) ($this->options['ast_proxy_loaders'] ?? []); }
    public function classMap(): array      { return (array) ($this->options['class_map'] ?? []); }
}
