<?php
// Общие помощники для чтения/записи JSON-хранилищ с блокировкой.

function read_json(string $file): array
{
  if (!file_exists($file)) {
    return [];
  }
  $decoded = json_decode((string) file_get_contents($file), true);
  return is_array($decoded) ? $decoded : [];
}

// Безопасно дозаписывает данные: читает, отдаёт колбэку, пишет результат.
// Колбэк получает текущий массив и должен вернуть новый массив для записи.
function with_json_lock(string $file, callable $mutator)
{
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  $fp = fopen($file, 'c+');
  if ($fp === false) {
    return false;
  }
  flock($fp, LOCK_EX);
  $contents = stream_get_contents($fp);
  $data = json_decode($contents, true);
  if (!is_array($data)) {
    $data = [];
  }
  $result = $mutator($data);
  ftruncate($fp, 0);
  rewind($fp);
  fwrite($fp, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return true;
}

function clean_text($value, int $max): string
{
  $s = trim((string) $value);
  // Убираем управляющие символы, кроме переноса строки и таба.
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
  return mb_substr($s, 0, $max);
}

function json_out($data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array
{
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
