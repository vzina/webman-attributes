<?php

declare (strict_types=1);

namespace Vzina\Attributes\Reflection;

use ArrayAccess;
use support\Container;

class ServiceInjector
{

    public static function inject(string|array $key, $value = null): void
    {
        if ((empty($key) && empty($value)) || empty($container = Container::instance())) {
            return;
        }

        $definitions = is_array($key) ? $key : [$key => $value];
        if (method_exists($container, 'addDefinition')) {
            $container->addDefinition($definitions);
        } else {
            foreach ($definitions as $id => $definition) {
                if (method_exists($container, 'set')) {
                    $container->set($id, $definition);
                } elseif (method_exists($container, 'bind')) {
                    $container->bind($id, $definition);
                } elseif ($container instanceof ArrayAccess) {
                    $container->offsetSet($id, $definition);
                }
            }
        }
    }
}