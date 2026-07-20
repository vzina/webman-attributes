<?php
/**
 * LaravelValidator.php
 * PHP version 7
 *
 * @package openai-web
 * @author  weijian.ye
 * @contact 891718689@qq.com
 * @link    https://github.com/vzina
 */
declare (strict_types=1);

namespace Vzina\Attributes\Attribute;

class LaravelValidator implements ValidatorContract
{

    public function __invoke(array $data, array $rules, array $messages): array
    {
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
    }
}