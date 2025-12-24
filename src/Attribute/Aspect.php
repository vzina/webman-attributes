<?php
/**
 * Aspect.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;
use Vzina\Attributes\Ast\AspectLoader;

#[Attribute(Attribute::TARGET_CLASS)]
class Aspect extends AbstractAttribute
{
    public function __construct(
        public array $classes = [],
        public array $attributes = [],
        public ?int $priority = null
    ) {
    }

    public function collectClass(string $className): void
    {
        parent::collectClass($className);
        AspectLoader::collect($className, get_object_vars($this));
    }
}