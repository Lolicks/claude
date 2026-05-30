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

// Структура мануала. Контент добавим позже — пока заглушки.
$pages = [
  'index' => [
    'title' => 'Мануал — оглавление',
    'body'  => '', // оглавление рисуется ниже автоматически
  ],
  '1' => [
    'title' => 'Раздел 1',
    'body'  => '', // TODO: добавить контент позже
  ],
  '2' => [
    'title' => 'Раздел 2',
    'body'  => '', // TODO: добавить контент позже
  ],
  '3' => [
    'title' => 'Раздел 3',
    'body'  => '', // TODO: добавить контент позже
  ],
];

$p = isset($_GET['p']) ? (string) $_GET['p'] : 'index';
if (!isset($pages[$p])) {
  $p = 'index';
}
$page = $pages[$p];

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($page['title']) ?> — <?= e($SITE_NAME) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="manual-body">
  <header class="manual-top">
    <a class="manual-logo" href="index.php">help<span>Cisco</span></a>
    <span class="manual-badge">скрытый раздел</span>
    <a class="manual-exit" href="manual.php?logout=1">выйти из раздела</a>
  </header>

  <div class="manual-layout">
    <nav class="manual-nav">
      <a class="<?= $p === 'index' ? 'active' : '' ?>" href="manual.php?p=index">Оглавление</a>
      <a class="<?= $p === '1' ? 'active' : '' ?>" href="manual.php?p=1">Раздел 1</a>
      <a class="<?= $p === '2' ? 'active' : '' ?>" href="manual.php?p=2">Раздел 2</a>
      <a class="<?= $p === '3' ? 'active' : '' ?>" href="manual.php?p=3">Раздел 3</a>
    </nav>

    <main class="manual-main">
      <h1><?= e($page['title']) ?></h1>

      <?php if ($p === 'index'): ?>
        <p>Это скрытый раздел с мануалом. Контент будет добавлен позже.</p>
        <ul class="manual-toc">
          <li><a href="manual.php?p=1">Раздел 1</a> — заглушка</li>
          <li><a href="manual.php?p=2">Раздел 2</a> — заглушка</li>
          <li><a href="manual.php?p=3">Раздел 3</a> — заглушка</li>
        </ul>
      <?php elseif (trim($page['body']) !== ''): ?>
        <?= $page['body'] ?>
      <?php else: ?>
        <div class="manual-placeholder">
          <p>Здесь пока пусто. Контент мануала добавим сюда позже.</p>
          <p class="hint">Редактируется в <code>manual.php</code> → массив <code>$pages</code>.</p>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
