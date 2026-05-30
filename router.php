<?php
// Роутер для встроенного PHP-сервера (php -S).
// Закрывает прямой доступ к данным, конфигу и служебным файлам,
// всё остальное отдаёт как есть (статика + исполнение .php).

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . ltrim($uri, '/');

if (
  preg_match('#^/data(/|$)#i', $path) ||
  preg_match('#/(config\.php|router\.php|lib\.php)$#i', $path) ||
  preg_match('#\.(sh|md|log|docx?|xlsx?|pptx?)$#i', $path)
) {
  http_response_code(403);
  echo 'Forbidden';
  return true;
}

return false;
