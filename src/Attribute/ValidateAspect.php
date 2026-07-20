<?php
/**
 * ValidateAspect — 请求参数校验切面。
 *
 * 拦截 @Validate 方法：从方法参数中自动发现 Request，调用校验器，通过则继续，失败则抛 ValidateException。
 * 校验器解析优先级：容器 ValidatorContract 绑定 → LaravelValidator 默认实现。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use InvalidArgumentException;
use Vzina\Attributes\Ast\ProceedingJoinPoint;
use Vzina\Attributes\Exception\ValidateException;

class ValidateAspect implements AspectInterface
{
    public array $attributes = [Validate::class];

    public function process(ProceedingJoinPoint $point)
    {
        /** @var Validate|null $attr */
        $attr = $point->getAnnotationMetadata()->method[Validate::class] ?? null;
        if (! $attr || empty($attr->rules)) {
            return $point->process();
        }

        $request = $this->findRequest($point, $attr->requestParam);
        if (! $request) {
            if ($attr->requestParam !== null) {
                // 用户显式指定了参数名但找不到 → 配置错误
                throw new InvalidArgumentException(sprintf(
                    'ValidateAspect: Request parameter "%s" not found in arguments of %s::%s().',
                    $attr->requestParam,
                    $point->className,
                    $point->methodName,
                ));
            }
            // 自动发现也没有 Request → 记录警告后跳过，避免静默绕过校验
            error_log(sprintf(
                'ValidateAspect: No Request object found for %s::%s(), validation skipped.',
                $point->className,
                $point->methodName,
            ));
            return $point->process();
        }

        $data = method_exists($request, 'all') ? $request->all() : [];
        // 逐方法 callable 覆盖 > 容器 ValidatorContract > LaravelValidator
        $validator = $this->resolveValidator();
        $errors    = $validator($data, $attr->rules, $attr->messages);

        if (! empty($errors)) {
            throw new ValidateException($errors);
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
                if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();
                    if ($this->isRequestType($typeName)) {
                        return $point->arguments['keys'][$param->getName()] ?? null;
                    }
                }
            }
        } catch (\ReflectionException) {
            // class not found, skip
        }

        return null;
    }

    /** 判断类型是否为 webman Request（含子类） */
    private function isRequestType(string $typeName): bool
    {
        if (in_array($typeName, ['support\Request', 'Webman\Http\Request'], true)) {
            return true;
        }
        // 支持 Request 子类（如 App\Http\Request）
        return class_exists($typeName) && is_a($typeName, 'support\Request', true);
    }

    /** 解析校验器：容器 ValidatorContract → LaravelValidator */
    protected function resolveValidator(): ValidatorContract
    {
        if (class_exists(\support\Container::class)) {
            try {
                $validator = \support\Container::get(ValidatorContract::class);
            } catch (\Throwable $e) {
                error_log('[ValidateAspect] Container::get(ValidatorContract) failed: ' . $e->getMessage());
                $validator = null;
            }
            if ($validator instanceof ValidatorContract) {
                return $validator;
            }
        }
        return new LaravelValidator();
    }
}
