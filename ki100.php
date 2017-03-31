<?php
namespace ki100; // forgive me
class Container {
    private $_factories = [];
    function setFactory($name, $factory) {
        $this->_factories[$name] = $factory;
    }
    function __get($name) {
        if (isset($this->_factories[$name])) {
            $this->{$name} = call_user_func($this->_factories[$name]);
            return $this->{$name};
        }
        return null;
    }
}
class Core extends Container {
    private $listenerWrappers = [];
    private $functionFiles = [];
    /** @var Plugin[] */
    private $plugins = [];
    function addPlugins($dir) {
        foreach (scandir($dir) as $entry) {
            if ($entry[0] !== '.' && is_file("$dir/$entry/setup.php")) {
                if (is_file("$dir/$entry/vendor/autoload.php")) {
                    require "$dir/$entry/vendor/autoload.php";
                }
                $id = preg_replace('~^\\d+_~', '', $entry);
                $plugin = new Plugin($id, "$dir/$entry");
                $this->plugins[$plugin->id] = $plugin;
                $factory = $this->requireFile("$dir/$entry/setup.php");
                if (is_callable($factory)) {
                    $factory($this, $plugin);
                }
                if (is_dir("$dir/$entry/functions")) {
                    $this->findFunctionFiles("$dir/$entry/functions", "");
                }
            }
        }
    }
    function call($name, array $args = [], $default = null) {
        $args = $this->triggerEvent("call_args:$name", $args);
        if (array_key_exists('__cancel', $args)) {
            return $args['__cancel'];
        }
        if (isset($this->functionFiles[$name])) {
            if (substr($this->functionFiles[$name], -4) === '.php') {
                ob_start();
                $value = $this->requireFile($this->functionFiles[$name], array_merge($args, ['core' => $this]));
                $output = ob_get_clean();
                if ($output !== '') {
                    $value = $output;
                }
            } else {
                $value = file_get_contents($this->functionFiles[$name]);
            }
        } else {
            $value = $default;
        }
        return $this->triggerEvent("call:$name", $value, $args);
    }
    function addListener($eventName, callable $listener, $timing = 0) {
        $this->listenerWrappers[] = [$eventName, $listener, (int)$timing];
    }
    function triggerEvent($eventName, $value = null, array $data = []) {
        $event = new Event($value);
        $event->name = $eventName;
        $event->data = $data;
        return $this->callListeners($event)->value;
    }
    function callListeners(Event $event) {
        $event->core = $this;
        foreach ($this->getListeners($event->name) as $listener) {
            $listener($event);
            if ($event->stopped) {
                break;
            }
        }
        return $event;
    }
    function getListeners($eventName) {
        $listenerSets = [];
        foreach ($this->listenerWrappers as $wrapper) {
            list ($registeredName, $listener, $timing) = $wrapper;
            if ($registeredName === $eventName
                    || ($registeredName[0] === '~' && preg_match($registeredName, $eventName))) {
                $listenerSets[$timing][] = $listener;
            }
        }
        ksort($listenerSets);
        $allListeners = [];
        foreach ($listenerSets as $listeners) {
            $allListeners = array_merge($allListeners, $listeners);
        }
        return $allListeners;
    }
    function getPlugin($id) {
        return isset($this->plugins[$id]) ? $this->plugins[$id] : null;
    }
    function getPlugins() {
        return $this->plugins;
    }
    function requireFile($_file, array $scope = []) {
        foreach ($scope as $key => $value) {
            if (is_int($key)) {
                unset($scope[$key]);
                $scope["arg{$key}"] = $value;
            }
        }
        unset($key, $value);
        extract($scope);
        return (require $_file);
    }
    function h($text) {
        return htmlspecialchars($text, ENT_QUOTES);
    }
    private function findFunctionFiles($dir, $prefix) {
        foreach (scandir($dir) as $entry) {
            if ($entry[0] === '.') {
                continue;
            }
            if (is_dir("$dir/$entry")) {
                $this->findFunctionFiles("$dir/$entry", "{$prefix}$entry/");
            } else {
                $this->functionFiles[$prefix . basename($entry, '.php')] = "$dir/$entry";
            }
        }
    }
}
class Plugin extends Container {
    public $id, $dir;
    public function __construct($id, $dir) {
        $this->id = $id;
        $this->dir = $dir;
    }
}
class Event {
    public $name, $value, $originalValue, $data = [], $stopped = false;
    /* @var Core */
    public $core;
    public function __construct($value = null) {
        $this->value = $value;
        $this->originalValue = $value;
    }
}
