<?php

use support\Container;

if (! function_exists('vendor_path')) {
    function vendor_path(string $path = ''): string
    {
        return base_path(path_combine('vendor', $path));
    }
}

if (! function_exists('container_definitions')) {
    function container_definitions($key, $value = null): void
    {
        if (empty($key) && empty($value)) {
            return;
        }

        $container = Container::instance();
        if (is_callable($key)) {
            $key($container);
            return;
        }

        $definitions = is_array($key) ? $key : [$key => $value];
        if (method_exists($container, 'addDefinitions')) {
            $container->addDefinitions($definitions);
        } elseif ($container instanceof \DI\Container) {
            foreach ($definitions as $serviceClass => $definition) {
                $container->set($serviceClass, $definition);
            }
        }
    }
}

(function () {
    Vzina\Attributes\AttributeLoader::init();
})();