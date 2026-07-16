<?php
/**
 * ValidateAspect — 请求参数校验切面。
 *
 * 拦截 @Validate 方法：从方法参数中自动发现 Request，调用校验器，通过则继续，失败则抛 ValidateException。
 * 默认校验器尝试 illuminate/validation；用户可通过 Validate(validator: $fn) 注入自定义实现。
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

use Closure;
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
            return $point->process();
        }

        $data = method_exists($request, 'all') ? $request->all() : [];
        $validator = $attr->validator && is_callable($attr->validator) ? $attr->validator : $this->defaultValidator();
        $errors    = $validator($data, $attr->rules, $attr->messages);

        if (! empty($errors)) {
            throw new ValidateException($errors);
        }

        return $point->process();
    }

    /** 从方法参数中查找 Request 对象 */
    private function findRequest(ProceedingJoinPoint $point, ?string $hint): ?object
    {
        if ($hint !== null) {
            return $point->arguments['keys'][$hint] ?? null;
        }

        try {
            foreach ($point->getReflectMethod()->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType &&
                    in_array($type->getName(), ['support\Request', 'Webman\Http\Request'], true)) {
                    return $point->arguments['keys'][$param->getName()] ?? null;
                }
            }
        } catch (\ReflectionException) {
            // class not found, skip
        }

        return null;
    }

    /** 默认校验器：尝试 illuminate/validation */
    private function defaultValidator(): Closure
    {
        return static function (array $data, array $rules, array $messages): array {
            if (! class_exists(\Illuminate\Validation\Factory::class) || ! class_exists(\Illuminate\Translation\ArrayLoader::class)) {
                throw new \RuntimeException(
                    'ValidateAspect requires illuminate/validation and illuminate/translation. Install them or pass a validator callable to the #[Validate] attribute.'
                );
            }

            $loader    = new \Illuminate\Translation\ArrayLoader();
            $translator = new \Illuminate\Translation\Translator($loader, 'en');
            $factory   = new \Illuminate\Validation\Factory($translator);
            $validator = $factory->make($data, $rules, $messages);

            if ($validator->fails()) {
                return $validator->errors()->toArray();
            }

            return [];
        };
    }
}
