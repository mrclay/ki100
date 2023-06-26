<?php
/**
 * Responds to http://localhost:8080/example/?PATH=/hello
 */

use ki100\Core;
use ki100\Event;
use ki100\plugins\http\Response;
use ki100\plugins\http\Request;

return function (Core $core) {
  // Handle dispatch
  $core->addListener('call:dispatch', function (Event $event) use ($core) {
    /**
     * @var Request $request
     */
    $request = $event->data[0];
    
    if ($request->path === '/hello') {
      $event->value = new Response(
        body: $core->call('template.html', [
          'content' => $core->call('hello-world.html'),
          'title' => 'Hello, World!',
        ]),
      );
    }
  });
};
