<?php
// Чат между посетителями сайта.
// Комнаты: 'global' — общий лайв-чат; 'post-<id>' — комментарии-чат под конкретным конфигом.
//
//   GET  chat.php?room=global&since=<id>   -> { ok, messages: [...] }
//   POST chat.php   { room, name, text }    -> { ok, message }

require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

const MAX_PER_ROOM = 500;

function valid_room($room): bool
{
  return is_string($room) && (bool) preg_match('/^(global|post-[A-Za-z0-9]{1,32})$/', $room);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $room = $_GET['room'] ?? 'global';
  if (!valid_room($room)) {
    json_out(['ok' => false, 'error' => 'bad room'], 400);
  }
  $since = isset($_GET['since']) ? (int) $_GET['since'] : 0;

  $all = read_json($CHAT_FILE);
  $msgs = isset($all[$room]) && is_array($all[$room]) ? $all[$room] : [];

  if ($since > 0) {
    $msgs = array_values(array_filter($msgs, static fn($m) => (int) ($m['id'] ?? 0) > $since));
  }
  json_out(['ok' => true, 'messages' => $msgs]);
}

if ($method === 'POST') {
  $body = read_json_body();
  $room = $body['room'] ?? 'global';
  if (!valid_room($room)) {
    json_out(['ok' => false, 'error' => 'bad room'], 400);
  }

  $name = clean_text($body['name'] ?? '', 40);
  $text = clean_text($body['text'] ?? '', 1000);
  if ($name === '') {
    $name = 'аноним';
  }
  if ($text === '') {
    json_out(['ok' => false, 'error' => 'empty message'], 400);
  }

  $message = [
    'id'   => (int) round(microtime(true) * 1000),
    'name' => $name,
    'text' => $text,
    'date' => date('c'),
  ];

  with_json_lock($CHAT_FILE, function (array $all) use ($room, $message) {
    $list = isset($all[$room]) && is_array($all[$room]) ? $all[$room] : [];
    $list[] = $message;
    if (count($list) > MAX_PER_ROOM) {
      $list = array_slice($list, -MAX_PER_ROOM);
    }
    $all[$room] = $list;
    return $all;
  });

  json_out(['ok' => true, 'message' => $message]);
}

json_out(['ok' => false, 'error' => 'method not allowed'], 405);
