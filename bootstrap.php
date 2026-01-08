<?php

if (! function_exists('vendor_path')) {
    function vendor_path(string $path = ''): string
    {
        return base_path(path_combine('vendor', $path));
    }
}

(function () {
    Vzina\Attributes\AttributeLoader::init();
})();