<?php
/**
 * ProxyTrait.php
 * PHP version 7
 *
 * 代理类核心切面处理Trait，负责方法调用的切面拦截、优先级排序和管道执行
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact yeweijian@eyugame.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Ast;

use Closure;
use Illuminate\Pipeline\Pipeline;
use InvalidArgumentException;
use Vzina\Attributes\AttributeLoader;
use Vzina\Attributes\Collector\AspectCollector;
use Vzina\Attributes\Collector\AspectManagerCollector;
use Vzina\Attributes\Collector\AttributeCollector;

trait ProxyTrait
{
    protected static function __proxyCall(
        string $className,
        string $method,
        array $arguments,
        Closure $closure
    ) {
        $proceedingJoinPoint = new ProceedingJoinPoint($closure, $className, $method, $arguments);
        $result = self::handleAround($proceedingJoinPoint);
        unset($proceedingJoinPoint);
        return $result;
    }

    protected static function handleAround(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $className = $proceedingJoinPoint->className;
        $methodName = $proceedingJoinPoint->methodName;
var_dump(4444);
        if (! AspectManagerCollector::has($className, $methodName)) {
            $aspects = array_unique(array_merge(
                static::getClassesAspects($className, $methodName),
                static::getAttributeAspects($className, $methodName)
            ));
            $queue = new SplPriorityQueue();
            foreach ($aspects as $aspect) {
                $queue->insert($aspect, AspectCollector::getPriority($aspect));
            }

            while ($queue->valid()) {
                AspectManagerCollector::insert($className, $methodName, $queue->current());
                $queue->next();
            }
            unset($aspects, $queue);
        }

        $aspectList = AspectManagerCollector::get($className, $methodName);
        if (empty($aspectList)) {
            return $proceedingJoinPoint->processOriginalMethod();
        }

        return static::createPipeline()
            ->via('process')
            ->through($aspectList)
            ->send($proceedingJoinPoint)
            ->then(fn(ProceedingJoinPoint $p) => $p->processOriginalMethod());
    }

    protected static function createPipeline(): Pipeline
    {
        return new class extends Pipeline {
            protected function carry(): Closure
            {
                return function ($stack, $pipe) {
                    return function ($passable) use ($stack, $pipe) {
                        if (! ($passable instanceof ProceedingJoinPoint)) {
                            throw new InvalidArgumentException('$passable must be a ProceedingJoinPoint object.');
                        }

                        if (is_string($pipe) && class_exists($pipe)) {
                            $pipe = AttributeLoader::getContainer()->get($pipe);
                        }
                        $passable->pipe = $stack;

                        return method_exists($pipe, $this->method)
                            ? $pipe->{$this->method}($passable)
                            : $pipe($passable);
                    };
                };
            }
        };
    }

    protected static function getClassesAspects(string $className, string $method): array
    {
        $matchedAspects = [];
        $classAspects = AspectCollector::get('classes', []);
        foreach ($classAspects as $aspect => $rules) {
            foreach ($rules as $rule) {
                if (AspectParser::isMatch($className, $method, $rule)) {
                    $matchedAspects[] = $aspect;
                    break;
                }
            }
        }

        return $matchedAspects;
    }

    protected static function getAttributeAspects(string $className, string $method): array
    {
        $allAttributes = array_merge(
            AttributeCollector::get($className . '._c', []),
            AttributeCollector::get($className . '._m.' . $method, [])
        );

        if (empty($allAttributes)) {
            return [];
        }

        $matchedAspects = [];
        $attrAspects = AspectCollector::get('attributes', []);
        $attributeNames = array_keys($allAttributes);

        foreach ($attrAspects as $aspect => $rules) {
            foreach ($rules as $rule) {
                foreach ($attributeNames as $attribute) {
                    if (str_contains($rule, '*')) {
                        $pattern = "/^" . str_replace(['*', '\\'], ['.*', '\\\\'], $rule) . "$/";
                        if (! preg_match($pattern, $attribute)) {
                            continue;
                        }
                    } elseif ($rule !== $attribute) {
                        continue;
                    }
                    $matchedAspects[] = $aspect;
                }
            }
        }

        return $matchedAspects;
    }
}