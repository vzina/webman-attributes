<?php
/**
 * AstVisitorMetadata.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use PhpParser\Node;

class AstVisitorMetadata
{
    public bool $hasConstructor = false;
    public ?Node\Stmt\ClassMethod $constructorNode = null;
    public ?bool $hasExtends = null;
    public ?string $classLike = null;

    public function __construct(public string $className)
    {
    }
}