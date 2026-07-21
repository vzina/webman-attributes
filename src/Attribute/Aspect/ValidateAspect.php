<?php
/**
 * ValidateAspect — 请求参数校验切面。
 *
 * 拦截 @Validate 方法：
 *   1. 自动发现 Request → 提取请求数据
 *   2. 若指定 dto → spatie/laravel-data 实例化 + 自动校验
 *   3. 若指定 rules → ValidatorContract 校验
 *   4. 两者可并存（DTO 类型转换 + 补充规则校验）
 *
 * 校验器解析优先级：容器 ValidatorContract 绑定 → LaravelValidator 默认实现。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute\Aspect;

use InvalidArgumentException;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Exception\ValidateException;
use Vzina\Attributes\Attribute\Annotation\Validate;
use Vzina\Attributes\Attribute\Contract\ValidatorContract;
use Vzina\Attributes\Attribute\Default\LaravelValidator;
use Vzina\Attributes\Attribute\AspectInterface;
use Vzina\Attributes\Attribute\DebugLog;

class ValidateAspect implements AspectInterface
{
    use DebugLog;

    public array $attributes = [Validate::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Validate|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Validate::class] ?? null;
        if (! $attr) {
            return $point->process();
        }

        // 无 dto 且无 rules → 透传
        if ($attr->dto === null && empty($attr->rules)) {
            return $point->process();
        }

        $request = $this->findRequest($point, $attr->requestParam);
        if (! $request) {
            if ($attr->requestParam !== null) {
                throw new InvalidArgumentException(sprintf(
                    'ValidateAspect: Request parameter "%s" not found in %s::%s().',
                    $attr->requestParam, $point->className, $point->methodName
                ));
            }
            $this->log(sprintf(
                'ValidateAspect: No Request object found for %s::%s(), validation skipped.',
                $point->className, $point->methodName
            ));
            return $point->process();
        }

        $data = method_exists($request, 'all') ? $request->all() : [];

        // 1. DTO 实例化 + 校验（spatie/laravel-data 内置规则优先）
        if ($attr->dto !== null && $this->isDtoAvailable()) {
            $data = $this->resolveDto($point, $attr->dto, $data);
        }

        // 2. 显式 rules 校验（可配合 DTO 做补充校验）
        if (! empty($attr->rules)) {
            $this->validateRules($data, $attr->rules, $attr->messages);
        }

        return $point->process();
    }

    /** 从方法参数中查找 Request 对象（支持子类继承） */
    private function findRequest(ProceedingJoinPoint $point, ?string $hint): ?object
    {
        if ($hint !== null) {
            return $point->arguments['keys'][$hint] ?? null;
        }

        try {
            foreach ($point->getReflectMethod()->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()
                    && $this->isRequestType($type->getName())) {
                    return $point->arguments['keys'][$param->getName()] ?? null;
                }
            }
        } catch (\ReflectionException) {
            // class not found, skip
        }

        return null;
    }

    private function isRequestType(string $typeName): bool
    {
        return in_array($typeName, ['support\Request', 'Webman\Http\Request'], true)
            || (class_exists($typeName) && is_a($typeName, 'support\Request', true));
    }

    /** 解析校验器：容器 ValidatorContract → LaravelValidator */
    protected function resolveValidator(): ValidatorContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $validator = \support\Container::get(ValidatorContract::class);
            } catch (\Throwable $e) {
                $this->log('[ValidateAspect] Container::get(ValidatorContract) failed: ' . $e->getMessage());
                $validator = null;
            }
            if ($validator instanceof ValidatorContract) {
                return $validator;
            }
        }
        return new LaravelValidator();
    }

    /** 执行规则校验 */
    private function validateRules(array $data, array $rules, array $messages): void
    {
        $errors = $this->resolveValidator()($data, $rules, $messages);
        if (! empty($errors)) {
            throw new ValidateException($errors);
        }
    }

    /** spatie/laravel-data DTO 解析：实例化 → 校验 → 替换方法参数 */
    private function resolveDto(ProceedingJoinPoint $point, string $dtoClass, array $data): array
    {
        try {
            $dto = $dtoClass::from($data);

            // 替换方法签名中匹配的 DTO 参数
            foreach ($point->getReflectMethod()->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()
                    && is_a($dtoClass, $type->getName(), true)
                    && isset($point->arguments['keys'][$param->getName()])
                ) {
                    $point->arguments['keys'][$param->getName()] = $dto;
                    break;
                }
            }

            return method_exists($dto, 'toArray') ? $dto->toArray() : $data;
        } catch (\Spatie\LaravelData\Exceptions\ValidationException $e) {
            throw new ValidateException($e->validator->errors()->toArray());
        }
    }

    private function isDtoAvailable(): bool
    {
        return class_exists(\Spatie\LaravelData\Data::class);
    }
}