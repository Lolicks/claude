<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

$posts = read_json($POSTS_FILE);     // конфиги сообщества
$chat  = read_json($CHAT_FILE);      // сообщения по комнатам

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function fmt_date(string $iso): string
{
  try {
    return (new DateTime($iso))->format('d.m.Y H:i');
  } catch (Exception $e) {
    return $iso;
  }
}

// Рендер существующих сообщений комнаты (быстрый первый показ; дальше JS опрашивает).
function render_messages(array $chat, string $room): string
{
  $list = isset($chat[$room]) && is_array($chat[$room]) ? $chat[$room] : [];
  $html = '';
  foreach ($list as $m) {
    $name = e((string) ($m['name'] ?? 'аноним'));
    $text = nl2br(e((string) ($m['text'] ?? '')));
    $time = e(fmt_date((string) ($m['date'] ?? '')));
    $id   = (int) ($m['id'] ?? 0);
    $html .= "<div class=\"msg\" data-id=\"{$id}\">"
      . "<span class=\"msg-name\">{$name}</span>"
      . "<span class=\"msg-text\">{$text}</span>"
      . "<span class=\"msg-time\">{$time}</span>"
      . "</div>";
  }
  return $html;
}

function last_id(array $chat, string $room): int
{
  $list = isset($chat[$room]) && is_array($chat[$room]) ? $chat[$room] : [];
  $last = end($list);
  return is_array($last) ? (int) ($last['id'] ?? 0) : 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($SITE_NAME) ?> — настройка NAT в Cisco IOL</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="site">

  <header class="topbar">
    <a class="logo" href="index.php">help<span class="logo-accent">Cisco</span><!--
      --><sup class="logo-reg" id="secretLogo" title="">&reg;</sup></a>
    <nav class="topnav">
      <a href="#guide">Гайд</a>
      <a href="#feed">Конфиги сообщества</a>
      <a href="#live">Чат</a>
      <a href="#share">Поделиться</a>
    </nav>
  </header>

  <!-- ===== Гайд (фасад: инструкция по настройке NAT) ===== -->
  <section class="hero" id="guide">
    <div class="hero-inner">
      <span class="eyebrow">Сообщество сетевиков</span>
      <h1>Настройка NAT в Cisco IOL</h1>
      <p class="lead">
        Пошаговый гайд и живая база конфигов от участников. Здесь люди выкладывают,
        как они поднимали NAT в Cisco IOS / IOL, и обсуждают это прямо в комментариях.
      </p>
    </div>

    <div class="guide-grid">
      <article class="guide-step">
        <span class="num">1</span>
        <h3>Расставь роли интерфейсов</h3>
        <p>Внутренняя сторона — <code>ip nat inside</code>, внешняя — <code>ip nat outside</code>.</p>
        <pre class="config"><code>interface e0/0
 ip address 10.0.0.1 255.255.255.0
 ip nat inside
!
interface e0/1
 ip address 198.51.100.1 255.255.255.0
 ip nat outside</code></pre>
      </article>

      <article class="guide-step">
        <span class="num">2</span>
        <h3>Опиши, что транслировать (ACL)</h3>
        <p>Какие внутренние адреса выпускаем наружу.</p>
        <pre class="config"><code>access-list 1 permit 10.0.0.0 0.0.0.255</code></pre>
      </article>

      <article class="guide-step">
        <span class="num">3</span>
        <h3>Включи PAT (overload)</h3>
        <p>Вся сеть выходит через один внешний адрес интерфейса.</p>
        <pre class="config"><code>ip nat inside source list 1 interface e0/1 overload</code></pre>
      </article>

      <article class="guide-step">
        <span class="num">4</span>
        <h3>Проверь трансляции</h3>
        <p>Убедись, что записи появляются.</p>
        <pre class="config"><code>show ip nat translations
show ip nat statistics</code></pre>
      </article>
    </div>
  </section>

  <!-- ===== Лента конфигов сообщества ===== -->
  <section class="feed" id="feed">
    <div class="section-head">
      <h2>Конфиги сообщества</h2>
      <p>Как настраивали NAT другие участники. Обсуждай в комментариях — это живой чат под каждым постом.</p>
    </div>

    <div class="posts" id="posts">
      <?php if (count($posts) === 0): ?>
        <p class="empty">Пока никто не поделился конфигом. Будь первым ниже 👇</p>
      <?php else: ?>
        <?php foreach (array_reverse($posts) as $post):
          $id = (string) ($post['id'] ?? '');
          $room = 'post-' . $id;
        ?>
          <article class="post" data-room="<?= e($room) ?>">
            <header class="post-head">
              <div>
                <h3 class="post-title"><?= e((string) ($post['title'] ?? '')) ?></h3>
                <span class="post-meta">
                  <span class="post-author"><?= e((string) ($post['author'] ?? 'аноним')) ?></span>
                  · <?= e(fmt_date((string) ($post['date'] ?? ''))) ?>
                </span>
              </div>
            </header>

            <?php if (trim((string) ($post['description'] ?? '')) !== ''): ?>
              <p class="post-desc"><?= nl2br(e((string) $post['description'])) ?></p>
            <?php endif; ?>

            <pre class="config"><code><?= e((string) ($post['config'] ?? '')) ?></code></pre>

            <div class="comments">
              <h4 class="comments-title">Обсуждение</h4>
              <div class="chat-log" data-room="<?= e($room) ?>" data-last="<?= last_id($chat, $room) ?>">
                <?= render_messages($chat, $room) ?>
              </div>
              <form class="chat-form" data-room="<?= e($room) ?>">
                <input class="chat-name" type="text" placeholder="имя" maxlength="40" autocomplete="off">
                <input class="chat-text" type="text" placeholder="написать в обсуждение…" maxlength="1000" autocomplete="off">
                <button class="btn btn-send" type="submit">→</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== Поделиться своим конфигом ===== -->
  <section class="share" id="share">
    <div class="section-head">
      <h2>Поделись своим конфигом</h2>
      <p>Покажи, как ты поднимал NAT — пост появится в ленте сообщества.</p>
    </div>
    <form id="shareForm" class="share-form">
      <div class="row">
        <input id="shareAuthor" type="text" placeholder="Имя / ник" maxlength="40" autocomplete="off">
        <input id="shareTitle" type="text" placeholder="Заголовок (напр. «PAT для офиса /24»)" maxlength="120" required>
      </div>
      <textarea id="shareDesc" placeholder="Описание: что за задача, что получилось…" maxlength="2000" rows="3"></textarea>
      <textarea id="shareConfig" class="mono" placeholder="Вставь свой конфиг IOS сюда…" maxlength="8000" rows="8" required></textarea>
      <div class="row row-end">
        <span class="share-status" id="shareStatus"></span>
        <button class="btn btn-primary" type="submit">Опубликовать</button>
      </div>
    </form>
  </section>

  <!-- ===== Общий живой чат ===== -->
  <section class="live" id="live">
    <div class="section-head">
      <h2>Живой чат сообщества</h2>
      <p>Общий чат для всех, кто сейчас на сайте.</p>
    </div>
    <div class="live-box">
      <div class="chat-log live-log" data-room="global" data-last="<?= last_id($chat, 'global') ?>">
        <?= render_messages($chat, 'global') ?>
      </div>
      <form class="chat-form" data-room="global">
        <input class="chat-name" type="text" placeholder="имя" maxlength="40" autocomplete="off">
        <input class="chat-text" type="text" placeholder="сообщение в общий чат…" maxlength="1000" autocomplete="off">
        <button class="btn btn-send" type="submit">→</button>
      </form>
    </div>
  </section>

  <footer class="footer">
    <span>© <?= date('Y') ?> <?= e($SITE_NAME) ?> — community NAT configs</span>
    <span class="footer-ver" id="secretVersion">v2.4.1</span>
  </footer>

  <!-- ===== ИИ-поддержка в углу ===== -->
  <button class="support-fab" id="supportFab" aria-label="Поддержка" title="Поддержка">
    <span class="support-fab-icon">?</span>
  </button>

  <div class="support-panel" id="supportPanel" hidden>
    <div class="support-head">
      <div>
        <strong>Поддержка helpCisco</strong>
        <span class="support-sub">отвечает ИИ-ассистент</span>
      </div>
      <button class="support-close" id="supportClose" aria-label="Закрыть">×</button>
    </div>
    <div class="support-log" id="supportLog">
      <div class="msg bot">
        <span class="msg-text">Привет! Я ассистент поддержки. Спрашивай что угодно про NAT в Cisco IOL — помогу с командами и разбором конфигов.</span>
      </div>
    </div>
    <form class="support-form" id="supportForm">
      <input id="supportInput" type="text" placeholder="Ваш вопрос…" maxlength="4000" autocomplete="off">
      <button class="btn btn-send" type="submit">→</button>
    </form>
  </div>

  <script>
    // Конфиг для фронтенда. manualKey подставляется сервером и используется скрытыми кнопками.
    window.HELPCISCO = {
      manualKey: <?= json_encode($MANUAL_KEY, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="script.js"></script>
</body>
</html>
