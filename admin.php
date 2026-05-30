<?php
require __DIR__ . '/config.php';
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

// Очистка всех попыток.
if ($authed && isset($_POST['action']) && $_POST['action'] === 'clear') {
  if (file_exists($DATA_FILE)) {
    unlink($DATA_FILE);
  }
  header('Location: admin.php');
  exit;
}

// Загрузка попыток.
$attempts = [];
if ($authed && file_exists($DATA_FILE)) {
  $decoded = json_decode((string) file_get_contents($DATA_FILE), true);
  if (is_array($decoded)) {
    $attempts = $decoded;
  }
}

function fmt_date(string $iso): string
{
  try {
    $d = new DateTime($iso);
    return $d->format('d.m.Y H:i');
  } catch (Exception $e) {
    return $iso;
  }
}

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Админка — результаты теста 💌</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="results-body">
<?php if (!$authed): ?>
  <main class="card">
    <h1 class="question">Вход в админку 🔒</h1>
    <?php if ($error): ?>
      <p class="result" style="margin-top:0;margin-bottom:20px;"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" class="login-form">
      <input class="login-input" type="password" name="password" placeholder="Пароль" autofocus />
      <button class="btn btn-yes" type="submit">Войти</button>
    </form>
  </main>
<?php else: ?>
  <main class="results-wrap">
    <header class="results-header">
      <h1>Все попытки 💌</h1>
      <div class="results-actions">
        <span class="count-badge">Всего: <?= count($attempts) ?></span>
        <form method="post" onsubmit="return confirm('Удалить все попытки? Это нельзя отменить.');" style="display:inline;">
          <input type="hidden" name="action" value="clear" />
          <button class="btn btn-clear" type="submit">Очистить всё</button>
        </form>
        <a class="btn btn-clear" href="admin.php?logout=1">Выйти</a>
      </div>
    </header>

    <?php if (count($attempts) === 0): ?>
      <p class="empty">Пока нет ни одной попытки.</p>
    <?php else: ?>
      <div class="attempts">
        <?php
        $total = count($attempts);
        // Новые сверху. Каждая попытка — отдельная карточка.
        foreach (array_reverse($attempts) as $idx => $attempt):
          $number = $total - $idx;
          $answers = is_array($attempt['answers'] ?? null) ? $attempt['answers'] : [];
        ?>
          <section class="attempt">
            <div class="attempt-head">
              <span class="attempt-num">Попытка №<?= $number ?></span>
              <span class="attempt-date"><?= e(fmt_date($attempt['date'] ?? '')) ?></span>
            </div>
            <ol class="answers">
              <?php foreach ($answers as $a): ?>
                <li>
                  <span class="q"><?= e((string) ($a['question'] ?? '')) ?></span>
                  <span class="a"><?= e((string) ($a['answer'] ?? '')) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
<?php endif; ?>
  <script src="hearts.js"></script>
</body>
</html>
