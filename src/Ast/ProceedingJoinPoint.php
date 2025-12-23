<?php
/**
 * ProceedingJoinPoint.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use Vzina\Attributes\Collector\AttributeCollector;
use Vzina\Attributes\Reflection\ReflectionManager;

class ProceedingJoinPoint
{
    public mixed $result;

    public ?Closure $pipe;

    public function __construct(
        public Closure $originalMethod,
        public string $className,
        public string $methodName,
        public array $arguments
    ) {
    }

    /**
     * Delegate to the next aspect.
     */
    public function process()
    {
        $closure = $this->pipe;
        if (! $closure instanceof Closure) {
            throw new RuntimeException('The pipe is not instanceof \Closure');
        }

        return $closure($this);
    }

    /**
     * Process the original method, this method should trigger by pipeline.
     */
    public function processOriginalMethod()
    {
        $this->pipe = null;
        $closure = $this->originalMethod;
        if (count($this->arguments['keys']) > 1) {
            $arguments = $this->getArguments();
        } else {
            $arguments = array_values($this->arguments['keys']);
        }
        return $closure(...$arguments);
    }

    public function getAnnotationMetadata(): AttributeMetadata
    {
        $metadata = AttributeCollector::get($this->className);
        return new AttributeMetadata($metadata['_c'] ?? [], $metadata['_m'][$this->methodName] ?? []);
    }

    public function getArguments()
    {
        return (function () {
            $result = [];
            foreach ($this->arguments['order'] ?? [] as $order) {
                $result[] = $this->arguments['keys'][$order];
            }
            return $result;
        })();
    }

    public function getReflectMethod(): ReflectionMethod
    {
        return ReflectionManager::reflectMethod($this->className, $this->methodName);
    }

    public function getInstance(): ?object
    {
        return (new ReflectionFunction($this->originalMethod))->getClosureThis();
    }
}