**ki100** is a plugin framework under ~~100~~ 170 lines and includes nested plugins, events/pointcuts, autoloading, lazy-loading DI containers, and basic templating.

This is something old sitting on my local drive and I had some fun making it. Don't use it for your next project!

Usage
-----

```php
<?php
use ki100\Core;

$core = new Core();
$core->addPlugins('path/to/plugins');
$core->initialize();
```

By itself core does nothing but set up plugins and call the "init" event. It's up to plugins to add all business logic by working together like a big happy team. Plugins can listen for and trigger events, add lazy-loading dependencies to the core, or just have fun and chill.

Plugins
-------

A plugin is a directory containing a file "setup.php" which core includes. This file should return a callable that is used to set up the plugin. The callable is passed the `ki100\Core` and a unique `ki100\Plugin` object, which it can use as a value container.

Plugins are loaded in alpha order and the directory name becomes the plugin's "id". Since you may want to use numeric prefixes to re-order loading, `/^\d+_/` is automatically stripped from the id. Hence a directory `005_game` would hold the plugin `game` and its Plugin object could be accessed via `$core->getPlugin('game')`.

PSR-0 autoloading is set up for `$pluginDir/lib`.

Once the "init" event fires, all the plugins should be set up and available.

Events
------

Events operate by passing a `ki100\Event` object to its listeners. An event has an inherent "value" that can be altered by listeners, an array of "data" passed by the triggering party, and can be stopped from propagating. A method can return the original value passed in at trigger-time.

Event listeners are callables, and can be given an integer "timing" (default = 0) to influence the order in which they're called. Negative values are called earliest, large values are called last.

A listener can be registered for many events by using an event name beginning with `~`. This name will be interpreted as a PCRE pattern and be matched against the triggered event name.

```php
<?php

$core->addListener('foo/bar', function (Event $event) {
    $event->value = 1;
}, -100);

$core->addListener('~^foo/~', function (Event $event) {
    $event->value += 1;
    $event->stopPropagation();
});

$core->addListener('foo/bar', function (Event $event) {
    $event->value += 1;
}, 100);

$core->triggerEvent('foo/bar', new Event()); // 2
$core->triggerEvent('foo/bing', new Event()); // 1 and probably a NOTICE
```

Filters
-------

Filters are just sugar for using the event system to collaboratively produce or process a value:

```php
<?php

$core->addListener('filter:sanitize-html', function (Event $event) {
    $event->value = MyFavoriteHtmlFilter::filter($event->value);
});

$html = $core->filter('sanitize-html', $html); 
```

Containers
----------

The core and plugin objects are lazy-loading containers. E.g. you can use setFactory() to specify a callable that will be used to generate the value if it's missing on the container.

```php
<?php

$core->component; // null

$core->setFactory('component', function() {
    return new My\Component();
});

// later
$core->component; // new My\Component
$core->component; // same object
```

Event-alterable function calls
------------------------------

Core's call() allows plugins to collaborate on function calls, a little like a [pointcut](https://en.wikipedia.org/wiki/Pointcut). Both the arguments and the return value are filtered for each call(), so a plugin can replace functionality, alter arguments, perform tasks before/after, or, e.g., inject a caching layer.

```php
<?php

$core->addListener('call:subtract', function (Event $event) {
    $event->value = $event->data[0] - $event->data[1];
});

$diff = $core->call('subtract', [3, 2]); // 1

// another plugin can influence arguments...

$core->addListener('call_args:subtract', function (Event $event) {
    // swap args, evil
    $tmp = $event->value[0];
    $event->value[0] = $event->value[1];
    $event->value[1] = $tmp;
});

$diff = $core->call('subtract', [3, 2]); // -1

// or set up a cache

$cache = [];

$core->addListener('call:subtract', function (Event $event) use (&$cache) {
    $key = serialize($event->data);
    $cache[$key] = $event->value;
}, 999);

$core->addListener('call:subtract', function (Event $event) use (&$cache) {
    $key = serialize($event->data);
    if (array_key_exists($key, $cache)) {
        $event->value = $cache[$key];
        $event->stopPropagation();
    }
}, -999);
```

"Function" scripts
------------------

A simpler way to write a "function" for call() is to create a PHP script in your plugin's `functions` directory and have it return a value. E.g. the plugin `core` (in directory `/plugins/000_core`) has a script `functions/example/subtract.php`. This would get executed to handle `$core->call('example/subtract')`.

In the script's context, `$this` references the `ki100\Core`, and `extract()` is used to turn the arguments into local vars, with integer key names prefixed with `arg`. So `$core->call('example/foo', [3, 'bing' => 2])` will result in variables `$arg0` and `$bing`.

The arguments and return value are filtered through `call_args:*` and `call:*` events.

No-frills templating
--------------------

If a function script sends output, this becomes its return value. E.g. `$core->call('example/template.html')` will execute the script `/plugins/000_core/functions/example/template.html.php` and return its HTML output.

If the script isn't a PHP file, its content is returned directly. E.g. `$core->call('example/static-view.js')`.

Either way, templates are still like functions and are filtered by events similarly. 

Atrocities here that I don't condone
------------------------------------

 - no tests
 - public properties
 - no phpdocs/comments
 - multiple property declarations on a line
 - no whitespace
 - Container allows undeclared properties
 - [] and list() instead of value objects
 - multiple classes in a single file
 - no interfaces
 - insufficient value/error checking
 - no way to remove event listeners
 - and many more
