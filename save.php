<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'bad request']);
  exit;
}

// Чистим и ограничиваем данные.
$answers = [];
foreach ($data['answers'] as $a) {
  if (!is_array($a) || !isset($a['question'], $a['answer'])) {
    continue;
  }
  $answers[] = [
    'question' => mb_substr((string) $a['question'], 0, 300),
    'answer'   => mb_substr((string) $a['answer'], 0, 300),
  ];
}

if (count($answers) === 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no answers']);
  exit;
}

$attempt = [
  'id'     => (int) round(microtime(true) * 1000),
  'date'   => date('c'),
  'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
  'answers' => $answers,
];

$dir = dirname($DATA_FILE);
if (!is_dir($dir)) {
  mkdir($dir, 0775, true);
}

// Дозапись с блокировкой, чтобы попытки не накладывались друг на друга.
$fp = fopen($DATA_FILE, 'c+');
if ($fp === false) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'storage error']);
  exit;
}
flock($fp, LOCK_EX);
$contents = stream_get_contents($fp);
$all = json_decode($contents, true);
if (!is_array($all)) {
  $all = [];
}
$all[] = $attempt;
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['ok' => true]);
