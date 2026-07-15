<?php
/**
 * AttributeReader.php
 * @version 8.1
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
use Vzina\Attributes\Attribute\Message;

class AttributeReader
{
    public static function getAttributes(Reflector $reflection, array $ignores = []): array
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

    public static function getConstants(ReflectionClass $reflection): array
    {
        $result = [];
        $classConstants = $reflection->getReflectionConstants();
        foreach ($classConstants as $classConstant) {
            $code = $classConstant->getValue();
            if ($classConstant->isEnumCase()) {
                $code = $code instanceof BackedEnum ? $code->value : $code->name;
            }

            foreach ($classConstant->getAttributes() as $ref) {
                $attribute = $ref->newInstance();
                if ($attribute instanceof Message) {
                    $result[$code][$attribute->getLowerCaseKey()] = $attribute->value;
                }
            }
        }
        return $result;
    }

    public static function getPropertyClass(string $class, string $property): ?string
    {
        $reflectionClass = ReflectionManager::reflectClass($class);
        $reflectionProperty = $reflectionClass->getProperty($property);

        if (method_exists($reflectionProperty, 'hasType') && $reflectionProperty->hasType()) {
            /* @phpstan-ignore-next-line */
            $type = $reflectionProperty->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                $name = $type->getName();
                // PHP 反射只返回源码中写下的短类名，需解析为 FQCN
                return self::resolveClassName($name, $reflectionClass);
            }
            return $type?->getName();
        }

        return ReflectionManager::getPhpDocReader()->getPropertyClass($reflectionProperty);
    }

    /**
     * Resolve a short class name to FQCN using the declaring class's namespace and use imports.
     */
    public static function resolveClassName(string $name, \ReflectionClass $context): string
    {
        // Already FQCN (starts with \)
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        // Same namespace as declaring class
        $ns = $context->getNamespaceName();
        if ($ns !== '') {
            $fqcn = $ns . '\\' . $name;
            if (class_exists($fqcn) || interface_exists($fqcn)) {
                return $fqcn;
            }
        }

        // Parse use imports from the file to resolve short name
        $file = $context->getFileName();
        if ($file !== false && is_file($file)) {
            $useMap = self::parseUseStatements($file);
            if (isset($useMap[$name])) {
                return $useMap[$name];
            }
        }

        // Fallback: try root namespace
        if (class_exists($name) || interface_exists($name)) {
            return $name;
        }

        // Last resort: return the short name as-is
        return $name;
    }

    /**
     * Parse PHP use statements from a file and return [shortName => FQCN] map.
     */
    private static function parseUseStatements(string $file): array
    {
        $map = [];
        $tokens = @token_get_all((string) file_get_contents($file));
        if (! is_array($tokens)) {
            return $map;
        }

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_USE) {
                continue;
            }

            // Skip closure use (...) — T_USE inside a function body is a closure
            // Simple heuristic: skip if preceded by ) or }
            $skip = false;
            for ($j = $i - 1; $j >= 0 && $j >= $i - 3; $j--) {
                if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_DOUBLE_COLON, T_OBJECT_OPERATOR], true)) {
                    continue 2; // Trait use, skip
                }
                if ($tokens[$j] === ')' || $tokens[$j] === '}') {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $alias = null;
            $fqcn = '';
            $inFqcn = false;
            for ($j = $i + 1; $j < $count; $j++) {
                if (! is_array($tokens[$j])) {
                    if ($tokens[$j] === ';') break;
                    if ($tokens[$j] === ',') {
                        $inFqcn = false;
                        $alias = null;
                        $fqcn = '';
                        continue;
                    }
                    continue;
                }

                if ($tokens[$j][0] === T_NAME_QUALIFIED || $tokens[$j][0] === T_NAME_FULLY_QUALIFIED) {
                    $fqcn = ltrim($tokens[$j][1], '\\');
                    $inFqcn = true;
                    continue;
                }

                if ($tokens[$j][0] === T_STRING) {
                    if (! $inFqcn) {
                        $fqcn = $tokens[$j][1];
                        $inFqcn = true;
                    }
                    continue;
                }

                if ($tokens[$j][0] === T_AS) {
                    continue;
                }

                if ($tokens[$j][0] === T_NAME_FULLY_QUALIFIED || $tokens[$j][0] === T_STRING) {
                    if ($fqcn !== '') {
                        $alias = $tokens[$j][1];
                    }
                    continue;
                }

                if ($tokens[$j] === ',' || $tokens[$j] === ';') {
                    if ($fqcn !== '') {
                        $shortName = $alias ?? substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
                        $map[$shortName] = $fqcn;
                    }
                    if ($tokens[$j] === ';') break;
                    $fqcn = '';
                    $alias = null;
                    $inFqcn = false;
                }
            }

            if ($fqcn !== '') {
                $shortName = $alias ?? substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
                $map[$shortName] = $fqcn;
            }
        }

        return $map;
    }
}