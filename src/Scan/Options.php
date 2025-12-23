<?php
/**
 * Options.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use Symfony\Component\Finder\Finder;

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
        $class = $this->options['scan_handler'] ?? ProcScanHandler::class;
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

    public function lazyLoader(): array
    {
        return (array)($this->options['lazy_loader'] ?? []);
    }

    public function scanPath(): array
    {
        $directories = [];
        $path = (array)($this->options['scan_path'] ?? []);
        if (! empty($path)) {
            foreach (Finder::create()->in($path)->directories()->depth(0)->sortByName() as $dir) {
                $directories[] = $dir->getPathname();
            }
        }
        return $directories;
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

    public function classMap(): array
    {
        return (array)($this->options['class_map'] ?? []);
    }
}