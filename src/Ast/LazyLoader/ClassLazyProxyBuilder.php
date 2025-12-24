<?php
/**
 * ClassLazyProxyBuilder.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact yeweijian299@163.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

class ClassLazyProxyBuilder extends AbstractLazyProxyBuilder
{
    public function addClassRelationship(): self
    {
        if (! str_starts_with($this->originalClassName, '\\')) {
            $originalClassName = '\\' . $this->originalClassName;
        } else {
            $originalClassName = $this->originalClassName;
        }
        $this->builder = $this->builder->extend($originalClassName);
        return $this;
    }
}
