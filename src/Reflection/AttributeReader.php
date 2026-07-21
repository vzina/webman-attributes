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
     * Supports PHP 7.0+ grouped imports: use Foo\{Bar, Baz as Qux};
     * Results are cached by file path with mtime validation.
     */
    private static function parseUseStatements(string $file): array
    {
        static $cache = [];

        $mtime = @filemtime($file);
        if ($mtime !== false && isset($cache[$file]['mtime']) && $cache[$file]['mtime'] === $mtime) {
            return $cache[$file]['map'];
        }

        $map    = [];
        $source = file_get_contents($file);
        if ($source === false) {
            return $map;
        }
        $tokens = token_get_all($source);
        if (! is_array($tokens)) {
            return $map;
        }

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_USE) {
                continue;
            }

            // Skip closure use (...) — 检查前一个非空白 token 是否为 )，表示 function() use($var)
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                $prev--;
            }
            if ($prev >= 0 && $tokens[$prev] === ')') {
                continue;
            }

            $groupPrefix = null;
            $fqcn       = '';
            $alias      = null;
            $aliasMode  = false;

            for ($j = $i + 1; $j < $count; $j++) {
                // ---- single-char tokens ----
                if (! is_array($tokens[$j])) {
                    if ($tokens[$j] === ';') {
                        $fqcn !== '' && self::addUseStatement($map, $fqcn, $alias, $groupPrefix);
                        break;
                    }
                    if ($tokens[$j] === ',') {
                        $fqcn !== '' && self::addUseStatement($map, $fqcn, $alias, $groupPrefix);
                        $fqcn = '';
                        $alias = null;
                        $aliasMode = false;
                        continue;
                    }
                    if ($tokens[$j] === '{') {
                        $groupPrefix = $fqcn;
                        $fqcn = '';
                        $alias = null;
                        $aliasMode = false;
                        continue;
                    }
                    if ($tokens[$j] === '}') {
                        $fqcn !== '' && self::addUseStatement($map, $fqcn, $alias, $groupPrefix);
                        $fqcn = '';
                        $alias = null;
                        $aliasMode = false;
                        continue;
                    }
                    continue;
                }

                $tokenType = $tokens[$j][0];
                $tokenVal  = $tokens[$j][1];

                // Qualified name (PHP 8.0+): T_NAME_QUALIFIED / T_NAME_FULLY_QUALIFIED
                if ($tokenType === T_NAME_QUALIFIED || $tokenType === T_NAME_FULLY_QUALIFIED) {
                    if ($aliasMode) {
                        $alias = ltrim($tokenVal, '\\');
                        $aliasMode = false;
                    } else {
                        $fqcn = ltrim($tokenVal, '\\');
                    }
                    continue;
                }

                // T_STRING: class name fragment, alias, or part of qualified name
                if ($tokenType === T_STRING) {
                    if ($aliasMode) {
                        $alias = $tokenVal;
                        $aliasMode = false;
                    } elseif ($fqcn === '') {
                        $fqcn = $tokenVal;
                    }
                    continue;
                }

                // T_AS: alias follows
                if ($tokenType === T_AS) {
                    $aliasMode = true;
                    continue;
                }
            }

            // trailing fallback (shouldn't normally reach here)
            if ($fqcn !== '') {
                self::addUseStatement($map, $fqcn, $alias, $groupPrefix);
            }
        }

        if ($mtime !== false) {
            $cache[$file] = ['mtime' => $mtime, 'map' => $map];
        }

        return $map;
    }

    /** Add a parsed use statement to the map, handling group prefix */
    private static function addUseStatement(array &$map, string $fqcn, ?string $alias, ?string $groupPrefix): void
    {
        $fullName = $groupPrefix !== null ? $groupPrefix . '\\' . $fqcn : $fqcn;
        $shortName = $alias ?? substr($fullName, (int) strrpos($fullName, '\\') + 1);
        $map[$shortName] = $fullName;
    }
}