<?php
/**
 * Inject — 依赖注入注解。
 *
 * 在属性或构造器参数上标记需要从容器中注入的服务。
 * 属性：通过 AstPropertyVisitor 在构造时注入。
 * 构造器参数：通过 AstPropertyVisitor 在构造器体内调用 Container::get() 解析。
 * 支持懒加载代理模式，生成代理类延迟第一次访问时才解析。
 *
 * @param ?string $value   服务类名，默认从属性/参数类型自动解析
 * @param bool    $required 未找到服务时是否抛异常
 * @param bool    $lazy    懒加载模式，生成代理类延迟第一次访问时才解析
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Annotation;

use Attribute;
use PhpDocReader\AnnotationException;
use Throwable;
use Vzina\Attributes\Ast\LazyLoader\LazyLoader;
use Vzina\Attributes\Reflection\AttributeReader;
use Vzina\Attributes\Attribute\AbstractAttribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject extends AbstractAttribute
{
    public string $targetValue = '';

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
                $this->value = AttributeReader::getPropertyClass($className, $target);
            }

            if (empty($this->value)) {
                throw new AnnotationException("The @Inject value is invalid for {$className}->{$target}");
            }

            $this->targetValue = $this->lazy ? LazyLoader::lazyName($this->value) : $this->value;

            parent::collectProperty($className, $target);
        } catch (AnnotationException $e) {
            if ($this->required) throw $e;
            $this->value = '';
        } catch (Throwable $t) {
            throw new AnnotationException("The @Inject value is invalid for {$className}->{$target}. Because {$t->getMessage()}");
        }
    }
}