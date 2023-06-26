<?php
use ki100\Core;

require dirname(__DIR__) . '/ki100.php';

$core = new Core();
$core->addPlugins(__DIR__ . '/plugins');
$core->triggerEvent('init');
