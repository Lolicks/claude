<?php
// Конфиги, которыми делятся посетители.
//
//   GET  posts.php           -> { ok, posts: [...] }   (новые сверху)
//   POST posts.php  { author, title, description, config } -> { ok, post }

require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $posts = read_json($POSTS_FILE);
  json_out(['ok' => true, 'posts' => array_reverse($posts)]);
}

if ($method === 'POST') {
  $body = read_json_body();

  $author = clean_text($body['author'] ?? '', 40);
  $title  = clean_text($body['title'] ?? '', 120);
  $desc   = clean_text($body['description'] ?? '', 2000);
  $config = clean_text($body['config'] ?? '', 8000);

  if ($author === '') {
    $author = 'аноним';
  }
  if ($title === '' || $config === '') {
    json_out(['ok' => false, 'error' => 'нужны заголовок и конфиг'], 400);
  }

  $post = [
    'id'          => base_convert((string) (int) round(microtime(true) * 1000), 10, 36),
    'author'      => $author,
    'title'       => $title,
    'description' => $desc,
    'config'      => $config,
    'date'        => date('c'),
  ];

  with_json_lock($POSTS_FILE, function (array $posts) use ($post) {
    $posts[] = $post;
    return $posts;
  });

  json_out(['ok' => true, 'post' => $post]);
}

json_out(['ok' => false, 'error' => 'method not allowed'], 405);
