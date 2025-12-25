<?php
/**
 * Options.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

class Options
{
    public function __construct(protected array $options)
    {
    }

    public static function init(array $config = []): self
    {
        return new self($config);
    }

    public function cacheable(): bool
    {
        return (bool)($this->options['cacheable'] ?? false);
    }

    public function scanHandler(): ScanHandlerInterface
    {
        $class = $this->options['scan_handler'] ?? PcntlHandler::class;
        return new $class;
    }

    public function cachePath(string $back = ''): string
    {
        $path = path_combine((string)($this->options['cache_path'] ?? runtime_path('attributes')), $back);
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }

    public function proxyPath(): string
    {
        return $this->cachePath('proxy');
    }

    public function scanPath(): array
    {
        $directories = [];
        $path = (array)($this->options['scan_path'] ?? []);
        foreach ($path as $dir) {
            is_dir($dir) and $directories[] = $dir;
        }
        return $directories;
    }

    public function excludes(): array
    {
        $scanPath = $this->scanPath();
        $pathCombine = fn($front, $back) => $front . ($back ? (DIRECTORY_SEPARATOR . ltrim($back, DIRECTORY_SEPARATOR)) : $back);

        return array_reduce(
            array_map(fn($e) => array_map(fn($p) => $pathCombine($p, $e), $scanPath), (array)($this->options['excludes'] ?? [])),
            'array_merge',
            []
        );
    }

    public function collectors(): array
    {
        return (array)($this->options['collectors'] ?? []);
    }

    public function ignores(): array
    {
        return (array)($this->options['ignores'] ?? []);
    }

    public function aspects(): array
    {
        return (array)($this->options['aspects'] ?? []);
    }

    public function astVisitors(): array
    {
        return array_unique((array)($this->options['ast_visitors'] ?? []));
    }

    public function propertyHandlers(): array
    {
        return array_unique((array)($this->options['property_handlers'] ?? []));
    }

    public function classMap(): array
    {
        return (array)($this->options['class_map'] ?? []);
    }
}