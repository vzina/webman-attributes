<?php
/**
 * ScanHandlerInterface.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

interface ScanHandlerInterface
{
    public function scan(): Scanned;

    public function finish(): void;
}