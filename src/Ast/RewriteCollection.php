<?php
/**
 * RewriteCollection.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Illuminate\Support\Str;

class RewriteCollection
{
    public const CLASS_LEVEL = 1;
    public const METHOD_LEVEL = 2;

    protected array $methods = [];
    protected array $pattern = [];
    protected int $level = self::METHOD_LEVEL;
    protected array $shouldNotRewriteMethods = [
        '__construct',
    ];

    public function __construct(protected string $class)
    {
    }

    public function add(string|array $methods): self
    {
        foreach ((array)$methods as $method) {
            $this->methods[] = str_contains($method, '*')
                ? sprintf("/^%s$/", str_replace(['*', '\\'], ['.*', '\\\\'], $method))
                : $method;
        }

        return $this;
    }

    public function shouldRewrite(string $method): bool
    {
        return match (true) {
            $this->level === self::CLASS_LEVEL => ! in_array($method, $this->shouldNotRewriteMethods, true),
            in_array($method, $this->methods, true) || Str::isMatch($this->pattern, $method) => true,
            default => false,
        };
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Return the methods that should not rewrite.
     */
    public function getShouldNotRewriteMethods(): array
    {
        return $this->shouldNotRewriteMethods;
    }
}