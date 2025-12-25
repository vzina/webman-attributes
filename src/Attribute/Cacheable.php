<?php
/**
 * Cacheable.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Cacheable extends AbstractAttribute
{
    public function __construct(
        public ?string $prefix = null,
        public ?string $value = null,
        public ?int $ttl = null,
        public int $offset = 0,
        public int $aheadSeconds = 0,
        public int $lockSeconds = 10,
        public ?string $group = null,
        public bool $collect = false,
        public bool $evict = false,
        public bool $put = false,
    ) {
    }
}