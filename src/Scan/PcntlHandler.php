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

    /** 子进程扫描超时（秒） */
    private const FORK_TIMEOUT = 30;

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
            // 父进程：轮询等待子进程完成，超时则杀子
            $elapsed = 0;
            while ($elapsed < self::FORK_TIMEOUT) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res === $pid) {
                    return new Scanned(true);
                }
                if ($res === -1) {
                    break;
                }
                usleep(100000); // 100ms
                $elapsed += 0.1;
            }
            // 超时 → 杀子进程
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
            throw new RuntimeException(
                'Child scan process timed out after ' . self::FORK_TIMEOUT . ' seconds'
            );
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