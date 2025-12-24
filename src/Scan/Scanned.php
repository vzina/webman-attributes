<?php
/**
 * Scanned.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Scan;

class Scanned
{
    public function __construct(protected bool $scanned)
    {
    }

    public function isScanned(): bool
    {
        return $this->scanned;
    }
}