**ki100** is a plugin framework under ~~100~~ 150 lines and includes nested plugins, events/pointcuts, autoloading, lazy-loading DI containers, and basic templating.

This is something old sitting on my local drive and I had some fun making it. Don't use it for your next project!

# Usage

```php
<?php
use ki100\Core;

$core = new Core();
$core->addPlugins('path/to/plugins');
$core->triggerEvent('init');
```

By itself core provides almost nothing. It's up to plugins to add all business logic by working together like a big happy team. Plugins can listen for and trigger events, add lazy-loading dependencies to the core, or just have fun and chill.

# Plugins

A plugin is a directory containing a file "setup.php" which core includes. This file should return a callable that is used to set up the plugin. The callable is passed the `ki100\Core` and a unique `ki100\Plugin` object, which it can use as a value container. ([example](https://github.com/mrclay/ki100/blob/master/plugins/000_core/setup.php#L5))

Plugins are loaded in alpha order and the directory name becomes the plugin's "id". Since you may want to use numeric prefixes to re-order loading, `/^\d+_/` is automatically stripped from the id. Hence a directory `005_game` would hold the plugin `game` and its Plugin object could be accessed via `$core->getPlugin('game')`.

PSR-0 autoloading is set up for `$pluginDir/lib`.

During the "init" event, all plugins should prepare themselves for usage.

# Containers

The core and plugin objects are lazy-loading containers. E.g. you can use setFactory() to specify a callable that will be used to generate the value if it's missing on the container.

```php
<?php

// in the "html" plugin setup function
$plugin->setFactory('htmlFilter', function() {
    return new Html\Filter();
});

// later
$plugin->htmlFilter; // new Html\Filter
$plugin->htmlFilter; // same object
```

# Events

Events operate by passing a `ki100\Event` object to its listeners. Listeners are callables, and can be given an integer "timing" (default = 0) to influence the order in which they're called, with negative timings called earliest.

A listener can be registered for many events by using an event name beginning with `~`. This name will be interpreted as a PCRE pattern and be matched against the triggered event name.

```php
<?php

$core->addListener('~^foo/~', function (Event $event) {
    // match multiple event names
});

$core->addListener('foo/bar', function (Event $event) {
    // do stuff early
}, -100);

$core->triggerEvent('foo/bar'); // both listeners called
$core->triggerEvent('foo/bing'); // only 1st listener called
```

## Passing values through events

An event has an inherent "value" that can be altered by listeners, and this value is returned by triggerEvent(). Let's filter some HTML:

```php
<?php

// in the "html" plugin setup function
$core->addListener('sanitize-html', function (Event $event) use ($plugin) {
    // we set up htmlFilter in an above example
    $event->value = $plugin->htmlFilter->filter($event->value);
});

// later
$html = $core->triggerEvent('sanitize-html', $html);
```

Note in the above example, a typo of the event name could result in no filtering! Use constant names or wrap important processes in real PHP methods to avoid these kinds of risks.

## Event metadata and stopping propagation

An event also carries an array of "data", and a "stopped" property that can prevent the passage to other listeners. Let's ban a user from login in:

```php
<?php

// in the plugin setup function
$core->addListener('allow_login', function (Event $event) {
    if ($event->data['user']->name === 'Steve') {
        $event->value = false;
        $event->stopped = true;
    }
});

// in the app's login process
if (!$core->triggerEvent('allow_login', true, ['user' => $user])) {
    // abandon login
}
```

# Event-alterable function calls

call() provides a more formal way for plugins to collaborate on a "function call", a little like a [pointcut](https://en.wikipedia.org/wiki/Pointcut). First it passes the function arguments through the event `call_args:<function name>`. Then, if a script has been put in place to handle the function (see below), it's executed and the return value is used. The return value is then passed through another event `call:<function name>`. In effect, plugins can modify arguments, perform tasks before/after, or cancel propagation to set the return value.

Let's set up a basic subtract operation.

```php
<?php

$core->addListener('call:subtract', function (Event $event) {
    // in the "call:" event, the arguments will be in the event data
    $event->value = $event->data[0] - $event->data[1];
});

$diff = $core->call('subtract', [3, 2]); // 1

// an evil plugin wants to reverse the arguments...

$core->addListener('call_args:subtract', function (Event $event) {
    // in the "call_args:" event, the arguments are in the value
    $tmp = $event->value[0];
    $event->value[0] = $event->value[1];
    $event->value[1] = $tmp;
});

$diff = $core->call('subtract', [3, 2]); // -1
```

Now another plugin wants to cache this operation:

```php
<?php

$cache = [];

// after computing a value, cache it
$core->addListener('call:subtract', function (Event $event) use (&$cache) {
    $key = serialize($event->data);
    $cache[$key] = $event->value;
}, 999);

// bypass future computations
$core->addListener('call:subtract', function (Event $event) use (&$cache) {
    $key = serialize($event->data);
    if (array_key_exists($key, $cache)) {
        $event->value = $cache[$key];
        $event->stopped = true;
    }
}, -999);
```

## "Function" scripts

Another way to write a "function" for call() is to create a PHP script in your plugin's `functions` directory and have it return a value. E.g. the plugin `core` (in directory `/plugins/000_core`) has a script [`functions/example/subtract.php`](https://github.com/mrclay/ki100/blob/master/plugins/000_core/functions/example/subtract.php). This would get executed to handle `$core->call('example/subtract')`.

This script is executed before the `call:` event is triggered.

In the script's context, `$core` references the `ki100\Core`, and `extract()` is used to turn the arguments into local vars, with integer key names prefixed with `arg`. So `$core->call('example/foo', [3, 'bing' => 2])` will result in variables `$arg0` and `$bing`.

### No-frills templating

If a function script sends output, this becomes its return value. E.g. `$core->call('example/template.html')` will execute the script `/plugins/000_core/functions/example/template.html.php` and return its HTML output.

If the script isn't a PHP file, its content is returned directly. E.g. `$core->call('example/static-view.js')`.

Either way, the arguments and output of templates are passed through events.

# Atrocities here that I don't condone

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
