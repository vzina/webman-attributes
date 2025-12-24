<?php

use support\App;
use Webman\Config;

(function() {
    if (empty(Config::get())) {
        if (! method_exists(App::class, 'loadAllConfig')) {
            return;
        }
        App::loadAllConfig(['route']);
    }

    \Vzina\Attributes\AttributeLoader::init();
})();

