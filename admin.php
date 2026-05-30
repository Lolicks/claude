<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';
session_start();

// Выход.
if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: admin.php');
  exit;
}

// Вход по паролю.
$error = '';
if (isset($_POST['password'])) {
  if (hash_equals($ADMIN_PASSWORD, (string) $_POST['password'])) {
    $_SESSION['admin'] = true;
  } else {
    $error = 'Неверный пароль';
  }
}
$authed = !empty($_SESSION['admin']);

// Действия модерации.
if ($authed && isset($_POST['action'])) {
  $action = $_POST['action'];
  if ($action === 'clear_posts' && file_exists($POSTS_FILE)) {
    unlink($POSTS_FILE);
  } elseif ($action === 'clear_chat' && file_exists($CHAT_FILE)) {
    unlink($CHAT_FILE);
  } elseif ($action === 'del_post' && isset($_POST['id'])) {
    $delId = (string) $_POST['id'];
    with_json_lock($POSTS_FILE, function (array $posts) use ($delId) {
      return array_values(array_filter($posts, static fn($p) => (string) ($p['id'] ?? '') !== $delId));
    });
    // Заодно удалим чат-комнату этого поста.
    with_json_lock($CHAT_FILE, function (array $chat) use ($delId) {
      unset($chat['post-' . $delId]);
      return $chat;
    });
  }
  header('Location: admin.php');
  exit;
}

$posts = $authed ? read_json($POSTS_FILE) : [];
$chat  = $authed ? read_json($CHAT_FILE) : [];

function fmt_date(string $iso): string
{
  try {
    return (new DateTime($iso))->format('d.m.Y H:i');
  } catch (Exception $e) {
    return $iso;
  }
}
function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$chatTotal = 0;
foreach ($chat as $list) {
  if (is_array($list)) {
    $chatTotal += count($list);
  }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Модерация — <?= e($SITE_NAME) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="admin-body">
<?php if (!$authed): ?>
  <main class="admin-card">
    <h1>Вход в модерацию 🔒</h1>
    <?php if ($error): ?><p class="admin-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="login-form">
      <input class="login-input" type="password" name="password" placeholder="Пароль" autofocus>
      <button class="btn btn-primary" type="submit">Войти</button>
    </form>
  </main>
<?php else: ?>
  <main class="admin-wrap">
    <header class="admin-header">
      <h1>Модерация helpCisco</h1>
      <div class="admin-actions">
        <span class="count-badge">Постов: <?= count($posts) ?></span>
        <span class="count-badge">Сообщений: <?= $chatTotal ?></span>
        <a class="btn-ghost" href="admin.php?logout=1">Выйти</a>
      </div>
    </header>

    <!-- Посты -->
    <section class="admin-section">
      <h2>Конфиги сообщества
        <form method="post" style="display:inline;float:right" onsubmit="return confirm('Удалить все посты?');">
          <input type="hidden" name="action" value="clear_posts">
          <button class="btn btn-danger" type="submit">Очистить все</button>
        </form>
      </h2>
      <?php if (count($posts) === 0): ?>
        <p class="empty">Постов нет.</p>
      <?php else: ?>
        <?php foreach (array_reverse($posts) as $p): ?>
          <div class="admin-item">
            <div class="meta">
              <b><?= e((string) ($p['author'] ?? 'аноним')) ?></b>
              · <?= e(fmt_date((string) ($p['date'] ?? ''))) ?>
              · id: <?= e((string) ($p['id'] ?? '')) ?>
              <form method="post" style="display:inline;float:right" onsubmit="return confirm('Удалить пост?');">
                <input type="hidden" name="action" value="del_post">
                <input type="hidden" name="id" value="<?= e((string) ($p['id'] ?? '')) ?>">
                <button class="btn btn-danger" type="submit">Удалить</button>
              </form>
            </div>
            <strong><?= e((string) ($p['title'] ?? '')) ?></strong>
            <pre class="config"><code><?= e((string) ($p['config'] ?? '')) ?></code></pre>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- Чат -->
    <section class="admin-section">
      <h2>Чаты
        <form method="post" style="display:inline;float:right" onsubmit="return confirm('Очистить все чаты?');">
          <input type="hidden" name="action" value="clear_chat">
          <button class="btn btn-danger" type="submit">Очистить все</button>
        </form>
      </h2>
      <?php if ($chatTotal === 0): ?>
        <p class="empty">Сообщений нет.</p>
      <?php else: ?>
        <?php foreach ($chat as $room => $list): if (!is_array($list)) continue; ?>
          <div class="admin-item">
            <div class="meta">Комната: <b><?= e((string) $room) ?></b> · сообщений: <?= count($list) ?></div>
            <?php foreach ($list as $m): ?>
              <div style="font-size:14px;margin:4px 0;">
                <span style="color:var(--accent);font-weight:600;"><?= e((string) ($m['name'] ?? '')) ?>:</span>
                <?= e((string) ($m['text'] ?? '')) ?>
                <span style="color:var(--muted);font-size:11px;">· <?= e(fmt_date((string) ($m['date'] ?? ''))) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
<?php endif; ?>
</body>
</html>
