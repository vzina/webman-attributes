<?php
/**
 * ScanHandlerInterface.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

interface ScanHandlerInterface
{
    public function scan(): Scanned;

    public function finish(): void;
}