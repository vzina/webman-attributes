<?php

declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Depend extends AbstractAttribute
{
    public function __construct(
        public ?string $id = null,
        public int $priority = 0
    ) {
    }
}