<?php
/**
 * Crude HTTP router
 */

namespace ki100\plugins\http;

use ki100\Core;
use ki100\Plugin;
use ki100\Event;

class Request {
  function __construct(
    public readonly string $path = '',
  ) {}
}

class Response {
  function __construct(
    public readonly string $body = '',
  ) {}
}

return function (Core $core, Plugin $plugin) {
  // to support nested plugins
  // $core->addPlugins(__DIR__ . '/plugins');


  // Example of using the Container
  $plugin->setFactory('originalRequest', fn() => new Request(
    path: $_GET['PATH'] ?? '/',
  ));

  $core->addListener('init', function (Event $event) use ($core, $plugin) {
    $response = $core->call('dispatch', [$plugin->originalRequest]);
    if (!($response instanceof Response)) {
      http_response_code(400);
      echo 'Request not handled';
      exit;
    }

    echo $response->body;
  }, 999);
};
