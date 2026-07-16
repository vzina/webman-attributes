<?php
/**
 * PcntlHandler.php
 * @version 8.1
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

class PcntlHandler implements ScanHandlerInterface
{
    /** 防止嵌套 fork：fork 后子进程标记为 true，再次 scan() 直接返回 false */
    private static bool $forked = false;

    public function scan(): Scanned
    {
        if (self::$forked) {
            return new Scanned(false);
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new RuntimeException('The process fork failed');
        }
        if ($pid) {
            pcntl_wait($status);
            return new Scanned(true);
        }

        self::$forked = true;
        return new Scanned(false);
    }

    #[NoReturn]
    public function finish(): void
    {
        exit(0);
    }
}