<?php
// ==================== НАСТРОЙКИ helpCisco ====================
//
// Все секреты и настройки хранятся в файле .env (рядом с этим файлом).
// .env НЕ коммитится в репозиторий (см. .gitignore) — там лежат боевые ключи и пароли.
// Шаблон со списком всех переменных — в .env.example: скопируй его в .env и заполни:
//   cp .env.example .env
//
// Приоритет источников (от высшего к низшему):
//   1) переменные окружения процесса (напр. systemd EnvironmentFile=/etc/helpcisco.env);
//   2) файл .env;
//   3) значения по умолчанию ниже.

// --- Мини-загрузчик .env (без сторонних библиотек) ---
function load_dotenv(string $file): void
{
  if (!is_file($file) || !is_readable($file)) {
    return;
  }
  foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
      continue;
    }
    $eq = strpos($line, '=');
    if ($eq === false) {
      continue;
    }
    $key = trim(substr($line, 0, $eq));
    $val = trim(substr($line, $eq + 1));
    // Снимаем парные кавычки вокруг значения, если есть.
    $len = strlen($val);
    if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
      $val = substr($val, 1, -1);
    }
    if ($key === '') {
      continue;
    }
    // Реальное окружение процесса важнее .env — не перетираем его.
    if (getenv($key) === false) {
      putenv("$key=$val");
      $_ENV[$key] = $val;
    }
  }
}

// Достаёт настройку из окружения / .env, иначе — значение по умолчанию.
function env_str(string $key, string $default = ''): string
{
  $v = getenv($key);
  return ($v === false || $v === '') ? $default : $v;
}

load_dotenv(__DIR__ . '/.env');

// --- Название сайта ---
$SITE_NAME = env_str('SITE_NAME', 'helpCisco');

// --- Админка / модерация ---
// Обязательно задай свой пароль в .env (ADMIN_PASSWORD) перед загрузкой на сервер!
$ADMIN_PASSWORD = env_str('ADMIN_PASSWORD', 'changeme');

// --- Хранилище (база данных не нужна, всё в JSON-файлах) ---
$POSTS_FILE = __DIR__ . '/data/posts.json'; // опубликованные конфиги сообщества
$CHAT_FILE  = __DIR__ . '/data/chat.json';  // сообщения чатов (по комнатам)

// --- Скрытый раздел с мануалом ---
// Секретный ключ, который открывает доступ к manual.php (задаётся в .env → MANUAL_KEY).
$MANUAL_KEY = env_str('MANUAL_KEY', 'ios-nat-2024');

// --- ИИ-поддержка (кнопка поддержки в углу) ---
// Провайдер: 'openrouter', 'anthropic', 'openai' или 'none'.
//   openrouter/anthropic/openai — реальные ответы LLM (нужен ключ AI_API_KEY в .env);
//   none или пустой ключ — встроенный оффлайн-ассистент по NAT (работает без интернета и ключа).
$AI_PROVIDER = env_str('AI_PROVIDER', 'openrouter');

// API-ключ — только из .env / окружения, в коде его нет.
$AI_API_KEY = env_str('AI_API_KEY', '');

// Модель.
//   openrouter — id вида 'openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet', и т.д.;
//   anthropic  — например 'claude-sonnet-4-6';
//   openai     — например 'gpt-4o-mini'.
$AI_MODEL = env_str('AI_MODEL', 'openai/gpt-4o-mini');

// Системная подсказка для ассистента поддержки (можно переопределить в .env → AI_SYSTEM_PROMPT).
$AI_SYSTEM_PROMPT = env_str('AI_SYSTEM_PROMPT',
  'Ты — ассистент поддержки сайта helpCisco. '
  . 'Помогаешь пользователям с настройкой NAT (PAT/overload, static NAT, dynamic NAT) '
  . 'и сетями на оборудовании Cisco, в том числе в Cisco IOL (IOS on Linux). '
  . 'Отвечай по делу, на русском, давай готовые команды IOS, когда это уместно.');

// Совместимость со старым именем переменной: HELPCISCO_AI_KEY имеет приоритет, если задан
// (например, через systemd EnvironmentFile=/etc/helpcisco.env).
$legacyKey = getenv('HELPCISCO_AI_KEY');
if (is_string($legacyKey) && $legacyKey !== '') {
  $AI_API_KEY = $legacyKey;
}
