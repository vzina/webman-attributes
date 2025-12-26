<?php
/**
 * AttributeReader.php
 * PHP version 7
 *
 * @package attributes
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use BackedEnum;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Reflector;
use RuntimeException;

class AttributeReader
{
    public function getAttributes(Reflector $reflection, array $ignores = []): array
    {
        $result = [];
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            if (in_array($attribute->getName(), $ignores, true)) {
                continue;
            }

            /** @var ReflectionAttribute $attribute */
            if (! class_exists($attribute->getName())) {
                throw new RuntimeException(sprintf(
                    "No attribute class found for '%s' in %s",
                    $attribute->getName(),
                    match (true) {
                        $reflection instanceof ReflectionClass => $reflection->getName(),
                        $reflection instanceof ReflectionMethod => $reflection->getDeclaringClass()->getName() . sprintf('->%s() method', $reflection->getName()),
                        $reflection instanceof ReflectionProperty => $reflection->getDeclaringClass()->getName() . sprintf('::$%s property', $reflection->getName()),
                        $reflection instanceof ReflectionClassConstant => $reflection->getDeclaringClass()->getName() . sprintf('::%s class constant', $reflection->getName()),
                        default => '',
                    }
                ));
            }
            $result[] = $attribute->newInstance();
        }

        return $result;
    }

    public function getConstants(ReflectionClass $reflection): array
    {
        $result = [];
        $classConstants = $reflection->getReflectionConstants();
        foreach ($classConstants as $classConstant) {
            $code = $classConstant->getValue();
            if ($classConstant->isEnumCase()) {
                $code = $code instanceof BackedEnum ? $code->value : $code->name;
            }

            $docComment = $classConstant->getDocComment();
            if ($docComment && (is_int($code) || is_string($code))) {
                $result[$code] = $this->parse($docComment, $result[$code] ?? []);
            }
        }
        return $result;
    }

    protected function parse(string $doc, array $previous): array
    {
        $pattern = '/@(\w+)\("(.+)"\)/U';
        if (preg_match_all($pattern, $doc, $result)) {
            [, $keys, $values] = $result;
            foreach ($keys as $i => $key) {
                if (isset($values[$i])) {
                    $previous[Str::lower($key)] = $values[$i];
                }
            }
        }

        return $previous;
    }
}