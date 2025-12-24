<?php
/**
 * PcntlHandler.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

class PcntlHandler implements ScanHandlerInterface
{
    public function scan(): Scanned
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new RuntimeException('The process fork failed');
        }
        if ($pid) {
            pcntl_wait($status);
            return new Scanned(true);
        }

        return new Scanned(false);
    }

    #[NoReturn]
    public function finish(): void
    {
        exit(0);
    }
}