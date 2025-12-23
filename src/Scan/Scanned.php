<?php
/**
 * Scanned.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
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