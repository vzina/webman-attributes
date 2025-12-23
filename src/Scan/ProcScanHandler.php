<?php
/**
 * ProcScanHandler.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use JetBrains\PhpStorm\NoReturn;

class ProcScanHandler implements ScanHandlerInterface
{
    public const SCAN_PROC_WORKER = 'SCAN_PROC_WORKER';

    protected string $bin;

    protected string $stub;

    public function __construct(?string $bin = null, ?string $stub = null)
    {
        if ($bin === null) {
            $bin = PHP_BINARY;
        }

        if ($stub === null) {
            $stub = base_path() . '/start.php';
        }

        $this->bin = $bin;
        $this->stub = $stub;
    }

    public function scan(): Scanned
    {
        if (getenv(static::SCAN_PROC_WORKER)) {
            return new Scanned(false);
        }

        $proc = proc_open(
            [$this->bin, $this->stub],
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            null,
            [static::SCAN_PROC_WORKER => '(true)']
        );

        $output = '';
        do {
            $output .= fread($pipes[1], 8192);
        } while (! feof($pipes[1]));

        if (proc_close($proc) !== 0) {
            echo $output;
            exit(-1);
        }

        return new Scanned(true);
    }

    #[NoReturn]
    public function finish(): void
    {
        exit(0);
    }
}