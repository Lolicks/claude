<?php
// Скрытый раздел с мануалом.
// Доступ только по секретному ключу ($MANUAL_KEY из config.php).
// Скрытые кнопки на сайте подставляют ключ автоматически (manual.php?key=...).
// После верного ключа доступ запоминается в сессии — можно ходить по страницам без ?key.
// Кто пришёл без ключа и не имеет доступа в сессии — видит обычную «страница не найдена».

require __DIR__ . '/config.php';
session_start();

// Выход из скрытого раздела.
if (isset($_GET['logout'])) {
  unset($_SESSION['manual_access']);
  header('Location: index.php');
  exit;
}

// Проверка ключа.
if (isset($_GET['key']) && hash_equals($MANUAL_KEY, (string) $_GET['key'])) {
  $_SESSION['manual_access'] = true;
  // Чистим ключ из URL, чтобы он не светился в адресной строке/истории.
  $p = isset($_GET['p']) ? (string) $_GET['p'] : 'index';
  header('Location: manual.php?p=' . rawurlencode($p));
  exit;
}

$authed = !empty($_SESSION['manual_access']);

if (!$authed) {
  // Притворяемся обычной несуществующей страницей — раздел остаётся скрытым.
  http_response_code(404);
  ?>
  <!DOCTYPE html>
  <html lang="ru"><head><meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — страница не найдена</title>
  <link rel="stylesheet" href="style.css"></head>
  <body class="manual-404">
    <main class="notfound">
      <h1>404</h1>
      <p>Страница не найдена.</p>
      <p><a href="index.php">На главную</a></p>
    </main>
  </body></html>
  <?php
  exit;
}

// ---- Доступ есть. Показываем мануал. ----
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>2–3 модуль — <?= htmlspecialchars($SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <!-- Случайная тема на каждую загрузку (как на главной). -->
  <script src="theme.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="manual-body">
  <header class="manual-top">
    <a class="manual-logo" href="index.php">help<span>Cisco</span></a>
    <span class="manual-badge">скрытый раздел</span>
    <a class="manual-exit" href="manual.php?logout=1">выйти из раздела</a>
  </header>

  <?php
    // Контент мануала (текст + картинки из «2-3 модуль.docx»).
    // Генерируется в отдельный файл, чтобы manual.php оставался компактным.
    $contentFile = __DIR__ . '/manual_mod23.html';
    if (is_file($contentFile)) {
      readfile($contentFile);
    } else {
      echo '<div class="manual-layout"><main class="manual-main">'
        . '<p>Контент мануала не найден.</p></main></div>';
    }
  ?>
</body>
</html>
