<?php
/**
 * DirectHandler.php
 * 非 fork 模式扫描处理器。在 pcntl_fork 不可用时使用。
 * 直接在当前进程执行扫描，代理文件在下次重启后生效。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

class DirectHandler implements ScanHandlerInterface
{
    public function scan(): Scanned
    {
        return new Scanned(false);
    }

    public function finish(): void
    {
        // 不退出进程，在 worker 内扫描
    }
}
