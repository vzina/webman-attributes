<?php
/**
 * Inject.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Attribute;
use PhpDocReader\AnnotationException;
use Throwable;
use Vzina\Attributes\Ast\LazyLoader\LazyLoader;
use Vzina\Attributes\Reflection\ReflectionManager;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject extends AbstractAttribute
{
    public function __construct(
        public ?string $value = null,
        public bool $required = true,
        public bool $lazy = false
    ) {
    }

    public function collectProperty(string $className, ?string $target): void
    {
        try {
            if (is_null($this->value)) {
                $reflectionClass = ReflectionManager::reflectClass($className);

                $reflectionProperty = $reflectionClass->getProperty($target);

                if (method_exists($reflectionProperty, 'hasType') && $reflectionProperty->hasType()) {
                    /* @phpstan-ignore-next-line */
                    $this->value = $reflectionProperty->getType()?->getName();
                } else {
                    $this->value = ReflectionManager::getPhpDocReader()->getPropertyClass($reflectionProperty);
                }
            }

            if (empty($this->value)) {
                throw new AnnotationException("The @Inject value is invalid for {$className}->{$target}");
            }

            if ($this->lazy) {
                $this->value = LazyLoader::fmt($this->value);
            }

            parent::collectProperty($className, $target);
        } catch (AnnotationException $e) {
            if ($this->required) {
                throw $e;
            }
            $this->value = '';
        } catch (Throwable $t) {
            throw new AnnotationException("The @Inject value is invalid for {$className}->{$target}. Because {$t->getMessage()}");
        }
    }
}