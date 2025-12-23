<?php
/**
 * NullScanHandler.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

class NullScanHandler implements ScanHandlerInterface
{
    public function scan(): Scanned
    {
        return new Scanned(false);
    }

    public function finish(): void
    {
        // TODO: Implement finish() method.
    }
}