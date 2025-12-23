<?php
/**
 * InterfaceLazyProxyBuilder.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast\LazyLoader;

class InterfaceLazyProxyBuilder extends AbstractLazyProxyBuilder
{
    public function addClassRelationship(): self
    {
        if (! str_starts_with($this->originalClassName, '\\')) {
            $originalClassName = '\\' . $this->originalClassName;
        } else {
            $originalClassName = $this->originalClassName;
        }
        $this->builder = $this->builder->implement($originalClassName);
        return $this;
    }
}