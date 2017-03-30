<?php

namespace ki100;

return function (Core $core, Plugin $plugin) {

    // support nested plugins
    $core->addPlugins(__DIR__ . '/plugins');
};
