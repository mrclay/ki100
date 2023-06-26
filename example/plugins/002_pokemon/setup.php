<?php
/**
 * Responds to URLs like http://localhost:8080/example/?PATH=/pokemon-color/brown
 */

use ki100\Core;
use ki100\Event;
use ki100\plugins\http\Response;
use ki100\plugins\http\Request;

function apiRequest($type, $id) {
  $context = stream_context_create([
    'http' => ['ignore_errors' => true],
  ]);

  $json = file_get_contents("https://pokeapi.co/api/v2/{$type}/{$id}", false, $context);

  $typeName = preg_replace('~^pokemon-~', '', $type);

  if (!is_string($json) || !str_starts_with($json, '{')) {
    return [false, $typeName, null];
  }

  $obj = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

  $pretty = json_encode($obj, JSON_PRETTY_PRINT);

  return [true, $typeName, $pretty];
}

return function (Core $core) {
  // Handle dispatch
  $core->addListener('call:dispatch', function (Event $event) use ($core) {
    /**
     * @var Request $request
     */
    $request = $event->data[0];

    if (!preg_match('~/([a-z-]+)/([0-9a-z-]+)~', $request->path, $m)) {
      return;
    }

    [, $type, $id] = $m;
    [$success, $typeName, $pretty] = apiRequest($type, $id);

    if (!$success) {
      $event->value = new Response(
        body: $core->call('template.html', [
          'content' => 'Not Found.',
          'title' => "Pokemon {$typeName} not found: {$id}",
        ]),
      );
      return;
    }

    $event->value = new Response(
      body: $core->call('template.html', [
        'content' => "<pre>" . $core->h($pretty) . "</pre>",
        'title' => "Pokemon {$typeName}: {$id}",
      ]),
    );
  });
};
