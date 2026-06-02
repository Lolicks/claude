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
    $name = 'аноним'; // все сообщения анонимны
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
  <script>
    // Случайная палитра на каждую загрузку страницы: если сайт открывают
    // рядом несколько человек — у каждого свой цвет. Меняются только цвета
    // (CSS-переменные), вёрстка и расположение элементов не трогаются.
    // Выполняется до отрисовки, чтобы не было мигания темы.
    (function () {
      function rgb(h, s, l) {
        s /= 100; l /= 100;
        var a = s * Math.min(l, 1 - l);
        var f = function (n) {
          var k = (n + h / 30) % 12;
          var c = l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
          return Math.round(255 * c);
        };
        return f(0) + ', ' + f(8) + ', ' + f(4);
      }
      var hue = Math.floor(Math.random() * 360);
      var offsets = [40, 120, 150, 180, 210];
      var hue2 = (hue + offsets[Math.floor(Math.random() * offsets.length)]) % 360;
      var s = document.documentElement.style;
      s.setProperty('--accent-rgb', rgb(hue, 85, 60));
      s.setProperty('--accent2-rgb', rgb(hue2, 80, 58));
      s.setProperty('--bg', 'hsl(' + hue + ', 30%, 6%)');
      s.setProperty('--bg-2', 'hsl(' + hue + ', 28%, 9%)');
      s.setProperty('--panel', 'hsl(' + hue + ', 26%, 12%)');
      s.setProperty('--panel-2', 'hsl(' + hue + ', 24%, 16%)');
      s.setProperty('--border', 'hsl(' + hue + ', 22%, 24%)');
      s.setProperty('--code-bg', 'hsl(' + hue + ', 35%, 5%)');
      s.setProperty('--text', 'hsl(' + hue + ', 25%, 92%)');
      s.setProperty('--muted', 'hsl(' + hue + ', 16%, 65%)');
    })();
  </script>
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
      <a href="#guide">Статья</a>
      <a href="#comments">Комментарии</a>
      <a href="#feed">Конфиги сообщества</a>
      <a href="#share">Поделиться</a>
    </nav>
  </header>

  <!-- ===== Статья: что такое NAT и как его настроить ===== -->
  <section class="hero" id="guide">
    <div class="hero-inner">
      <span class="eyebrow">Сообщество сетевиков · разбор</span>
      <h1>Что такое NAT</h1>
      <p class="lead">
        NAT (Network Address Translation) — это преобразование сетевых адресов.
        Технология позволяет множеству устройств локальной сети с «серыми» (приватными)
        адресами выходить в интернет через один «белый» (публичный) IP.
      </p>
    </div>

    <article class="article">
      <p>
        Представьте офис: у провайдера вы покупаете один белый IP-адрес, а компьютеров
        в офисе — десятки. Все они выходят в интернет через этот единственный адрес.
        Роутер на границе сети подменяет внутренний (серый) адрес отправителя на свой
        внешний (белый) и запоминает, кому вернуть ответ. Снаружи кажется, что в сеть
        ходит один хост, хотя за ним скрывается вся локальная сеть.
      </p>
      <p>
        Серые адреса (<code>10.0.0.0/8</code>, <code>172.16.0.0/12</code>,
        <code>192.168.0.0/16</code>) в интернете не маршрутизируются — они живут только
        внутри локальных сетей. NAT работает «переводчиком» между ними и белыми адресами.
      </p>

      <h2 id="types">Виды NAT</h2>
      <div class="nat-types">
        <div class="nat-card">
          <h3>Статический NAT</h3>
          <p>Жёсткое соответствие один-к-одному: один серый адрес ↔ один белый.
             Используют для проброса портов — например, чтобы снаружи зайти по RDP
             на внутренний сервер.</p>
        </div>
        <div class="nat-card">
          <h3>Динамический NAT</h3>
          <p>Серый адрес получает любой свободный белый из заранее заданного пула.
             Соответствие назначается на время сессии.</p>
        </div>
        <div class="nat-card">
          <h3>Перегруженный (PAT)</h3>
          <p>Port Address Translation, он же overload. Много серых адресов выходят
             через один белый, а сессии различаются по номерам портов. Самый
             распространённый вариант для офиса.</p>
        </div>
      </div>

      <h2 id="lab">Настройка NAT на Cisco</h2>
      <p>Соберём небольшой офис и поднимем PAT. Схема сети выглядит примерно так:</p>
      <div class="schema-box">
        <ul>
          <li>3 ПК — во VLAN 2 (пользователи), сеть <code>192.168.2.0/24</code></li>
          <li>Сервер — во VLAN 3, сеть <code>192.168.3.0/24</code></li>
          <li>Коммутатор <b>Cisco Catalyst 2960</b> — раздаёт VLAN'ы по портам</li>
          <li>Роутер <b>Cisco 1841</b> — маршрутизация между VLAN и выход наружу</li>
        </ul>
      </div>

      <h3 class="cfg-title">Настройка коммутатора Cisco 2960</h3>
      <p>Создаём два VLAN'а, раздаём порты по доступу и поднимаем транк до роутера.</p>
      <pre class="config"><code>enable
configure terminal
!
vlan 2
 name users
vlan 3
 name servers
exit
!
interface range fa0/1 - 3
 switchport mode access
 switchport access vlan 2
exit
!
interface fa0/4
 switchport mode access
 switchport access vlan 3
exit
!
interface fa0/5
 switchport mode trunk
 switchport trunk allowed vlan 2,3
exit</code></pre>

      <h3 class="cfg-title">Настройка роутера Cisco 1841</h3>
      <p>Router-on-a-stick: один физический интерфейс <code>fa0/0</code> делим на
         подинтерфейсы — по одному на VLAN. Каждый становится шлюзом для своей сети.</p>
      <pre class="config"><code>enable
configure terminal
!
ip routing
!
interface fa0/0
 no shutdown
!
interface fa0/0.2
 encapsulation dot1Q 2
 ip address 192.168.2.251 255.255.255.0
!
interface fa0/0.3
 encapsulation dot1Q 3
 ip address 192.168.3.251 255.255.255.0
exit</code></pre>
      <p>Теперь ПК из VLAN 2 указывают шлюзом <code>192.168.2.251</code>, сервер из
         VLAN 3 — <code>192.168.3.251</code>, и сети уже видят друг друга.</p>

      <h2 id="pat">Настройка PAT</h2>
      <p>Чтобы офис вышел в «интернет», эмулируем провайдера отдельным роутером.
         Между нами и провайдером — стык на белых адресах с маской <code>/30</code>.</p>
      <div class="schema-box">
        <ul>
          <li>Роутер провайдера: <code>fa0/0 — 213.235.1.1/30</code>,
              <code>fa0/1 — 213.235.1.25/30</code></li>
          <li>«Интернет»-сервер: <code>213.235.1.26</code>, шлюз <code>213.235.1.25</code></li>
          <li>Наш Router0: <code>fa0/1 — 213.235.1.2/30</code>,
              маршрут по умолчанию на <code>213.235.1.1</code></li>
        </ul>
      </div>
      <p>На нашем роутере размечаем интерфейсы (<code>inside</code> / <code>outside</code>),
         задаём дефолтный маршрут, описываем внутренние сети в ACL и включаем overload.</p>
      <pre class="config"><code>enable
configure terminal
!
interface fa0/1
 ip address 213.235.1.2 255.255.255.252
 ip nat outside
 no shutdown
!
interface fa0/0.2
 ip nat inside
interface fa0/0.3
 ip nat inside
exit
!
ip route 0.0.0.0 0.0.0.0 213.235.1.1
!
access-list 1 permit 192.168.2.0 0.0.0.255
access-list 1 permit 192.168.3.0 0.0.0.255
!
ip nat inside source list 1 interface fa0/1 overload</code></pre>

      <h3 class="cfg-title">Проверка</h3>
      <p>Пингуем «интернет»-сервер с компьютера из локальной сети — трафик уходит
         через PAT и возвращается:</p>
      <pre class="config"><code>PC&gt; ping 213.235.1.26

Reply from 213.235.1.26: bytes=32 time=1ms  TTL=126
Reply from 213.235.1.26: bytes=32 time&lt;1ms TTL=126
Reply from 213.235.1.26: bytes=32 time&lt;1ms TTL=126
Reply from 213.235.1.26: bytes=32 time&lt;1ms TTL=126

Success rate is 100 percent (4/4)</code></pre>
      <p>А на роутере видны сами трансляции — все внутренние адреса спрятаны за одним
         белым <code>213.235.1.2</code>, сессии различаются по портам:</p>
      <pre class="config"><code>Router0# show ip nat translations

Pro Inside global      Inside local       Outside local      Outside global
icmp 213.235.1.2:1     192.168.2.1:1      213.235.1.26:1     213.235.1.26:1
icmp 213.235.1.2:2     192.168.2.2:2      213.235.1.26:2     213.235.1.26:2
icmp 213.235.1.2:3     192.168.3.10:3     213.235.1.26:3     213.235.1.26:3</code></pre>
    </article>
  </section>

  <!-- ===== Комментарии (на самом деле живой чат) ===== -->
  <section class="live" id="comments">
    <div class="section-head">
      <h2>Комментарии</h2>
      <p>Пишите комментарии — их сразу видят все, кто на сайте. Всё анонимно, имя не нужно.</p>
    </div>
    <div class="live-box">
      <div class="chat-log live-log" data-room="global" data-last="<?= last_id($chat, 'global') ?>">
        <?= render_messages($chat, 'global') ?>
      </div>
      <form class="chat-form" data-room="global">
        <input class="chat-text" type="text" placeholder="написать комментарий…" maxlength="1000" autocomplete="off">
        <button class="btn btn-send" type="submit">→</button>
      </form>
    </div>
  </section>

  <!-- ===== Лента конфигов сообщества ===== -->
  <section class="feed" id="feed">
    <div class="section-head">
      <h2>Конфиги сообщества</h2>
      <p>Как настраивали NAT другие участники.</p>
    </div>

    <div class="posts" id="posts">
      <?php if (count($posts) === 0): ?>
        <p class="empty">Пока никто не поделился конфигом. Будь первым ниже 👇</p>
      <?php else: ?>
        <?php foreach (array_reverse($posts) as $post): ?>
          <article class="post">
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
