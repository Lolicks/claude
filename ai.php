<?php
// ИИ-поддержка в углу сайта.
// Вместо обычной техподдержки отвечает ИИ.
//
//   POST ai.php  { message, history?: [{role, content}, ...] } -> { ok, reply, source }
//
// Если в config.php задан провайдер и ключ — отвечает реальная LLM.
// Если ключа нет или запрос упал — отвечает встроенный оффлайн-ассистент по NAT.

require __DIR__ . '/config.php';
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_out(['ok' => false, 'error' => 'method not allowed'], 405);
}

$body    = read_json_body();
$message = clean_text($body['message'] ?? '', 4000);
if ($message === '') {
  json_out(['ok' => false, 'error' => 'пустой вопрос'], 400);
}

// История диалога (для контекста). Чистим и ограничиваем.
$history = [];
if (isset($body['history']) && is_array($body['history'])) {
  foreach (array_slice($body['history'], -10) as $turn) {
    $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = clean_text($turn['content'] ?? '', 4000);
    if ($content !== '') {
      $history[] = ['role' => $role, 'content' => $content];
    }
  }
}

$provider = strtolower((string) $AI_PROVIDER);
$reply = null;

if ($AI_API_KEY !== '' && $provider === 'anthropic') {
  $reply = call_anthropic($AI_API_KEY, $AI_MODEL, $AI_SYSTEM_PROMPT, $history, $message);
} elseif ($AI_API_KEY !== '' && $provider === 'openrouter') {
  $reply = call_openrouter($AI_API_KEY, $AI_MODEL, $AI_SYSTEM_PROMPT, $history, $message);
} elseif ($AI_API_KEY !== '' && $provider === 'openai') {
  $reply = call_openai($AI_API_KEY, $AI_MODEL, $AI_SYSTEM_PROMPT, $history, $message);
}

if (is_string($reply) && trim($reply) !== '') {
  json_out(['ok' => true, 'reply' => $reply, 'source' => $provider]);
}

// Запасной вариант — встроенный ассистент по NAT.
json_out(['ok' => true, 'reply' => offline_assistant($message), 'source' => 'offline']);


// ---------- Провайдеры ----------

function http_post_json(string $url, array $headers, array $payload): ?array
{
  if (!function_exists('curl_init')) {
    return null;
  }
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($resp === false || $code < 200 || $code >= 300) {
    return null;
  }
  $decoded = json_decode((string) $resp, true);
  return is_array($decoded) ? $decoded : null;
}

function call_anthropic(string $key, string $model, string $system, array $history, string $message): ?string
{
  $messages = $history;
  $messages[] = ['role' => 'user', 'content' => $message];
  $data = http_post_json('https://api.anthropic.com/v1/messages', [
    'content-type: application/json',
    'x-api-key: ' . $key,
    'anthropic-version: 2023-06-01',
  ], [
    'model'      => $model ?: 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'system'     => $system,
    'messages'   => $messages,
  ]);
  if ($data === null) {
    return null;
  }
  // Ответ: { content: [ { type:'text', text:'...' } ] }
  if (isset($data['content'][0]['text'])) {
    return (string) $data['content'][0]['text'];
  }
  return null;
}

// OpenRouter — единый шлюз ко множеству моделей. API совместим с OpenAI,
// отличается базовый URL и пара необязательных заголовков (для статистики OpenRouter).
function call_openrouter(string $key, string $model, string $system, array $history, string $message): ?string
{
  $messages = [['role' => 'system', 'content' => $system]];
  foreach ($history as $turn) {
    $messages[] = $turn;
  }
  $messages[] = ['role' => 'user', 'content' => $message];
  $data = http_post_json('https://openrouter.ai/api/v1/chat/completions', [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $key,
    'HTTP-Referer: https://helpcisco.local',
    'X-Title: helpCisco',
  ], [
    'model'    => $model ?: 'openai/gpt-4o-mini',
    'messages' => $messages,
  ]);
  if ($data === null) {
    return null;
  }
  if (isset($data['choices'][0]['message']['content'])) {
    return (string) $data['choices'][0]['message']['content'];
  }
  return null;
}

function call_openai(string $key, string $model, string $system, array $history, string $message): ?string
{
  $messages = [['role' => 'system', 'content' => $system]];
  foreach ($history as $turn) {
    $messages[] = $turn;
  }
  $messages[] = ['role' => 'user', 'content' => $message];
  $data = http_post_json('https://api.openai.com/v1/chat/completions', [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $key,
  ], [
    'model'    => $model ?: 'gpt-4o-mini',
    'messages' => $messages,
  ]);
  if ($data === null) {
    return null;
  }
  if (isset($data['choices'][0]['message']['content'])) {
    return (string) $data['choices'][0]['message']['content'];
  }
  return null;
}


// ---------- Оффлайн-ассистент по NAT (работает без ключа и интернета) ----------

function offline_assistant(string $q): string
{
  $t = mb_strtolower($q);

  $has = static function (array $words) use ($t): bool {
    foreach ($words as $w) {
      if (mb_strpos($t, $w) !== false) {
        return true;
      }
    }
    return false;
  };

  if ($has(['привет', 'здравств', 'хай', 'hello', 'hi '])) {
    return "Привет! Я ассистент поддержки helpCisco. Спрашивай про NAT в Cisco IOL: "
      . "PAT (overload), статический или динамический NAT, ACL, inside/outside — помогу с командами.";
  }

  if ($has(['overload', 'pat', 'много адресов', 'один адрес', 'шар', 'интернет для сети'])) {
    return "PAT (NAT overload) — много внутренних хостов через один внешний адрес:\n\n"
      . "interface e0/0\n ip address 10.0.0.1 255.255.255.0\n ip nat inside\n"
      . "interface e0/1\n ip address 198.51.100.1 255.255.255.0\n ip nat outside\n"
      . "access-list 1 permit 10.0.0.0 0.0.0.255\n"
      . "ip nat inside source list 1 interface e0/1 overload\n\n"
      . "Проверка: show ip nat translations, show ip nat statistics.";
  }

  if ($has(['static', 'статич', 'проброс', 'port forward', 'сервер наружу', 'опубликовать'])) {
    return "Статический NAT — постоянное соответствие адресов (например, сервер наружу):\n\n"
      . "ip nat inside source static 10.0.0.10 198.51.100.10\n\n"
      . "Только один порт (port forwarding):\n"
      . "ip nat inside source static tcp 10.0.0.10 80 198.51.100.10 80\n\n"
      . "Не забудь ip nat inside / ip nat outside на интерфейсах.";
  }

  if ($has(['dynamic', 'динамич', 'пул', 'pool'])) {
    return "Динамический NAT — трансляция из пула внешних адресов:\n\n"
      . "ip nat pool MYPOOL 198.51.100.10 198.51.100.20 netmask 255.255.255.0\n"
      . "access-list 1 permit 10.0.0.0 0.0.0.255\n"
      . "ip nat inside source list 1 pool MYPOOL\n\n"
      . "Добавь overload в конце, если адресов в пуле меньше, чем хостов.";
  }

  if ($has(['inside', 'outside', 'интерфейс', 'какой интерфейс'])) {
    return "NAT смотрит на роли интерфейсов:\n"
      . " - ip nat inside  — внутренняя (локальная) сторона;\n"
      . " - ip nat outside — внешняя (интернет) сторона.\n"
      . "Без правильно расставленных inside/outside трансляция работать не будет.";
  }

  if ($has(['acl', 'access-list', 'список доступа', 'permit'])) {
    return "ACL задаёт, какие адреса попадают под NAT:\n\n"
      . "access-list 1 permit 10.0.0.0 0.0.0.255\n\n"
      . "Затем привязываешь его: ip nat inside source list 1 interface e0/1 overload.\n"
      . "Маска в ACL — обратная (wildcard): 0.0.0.255 = /24.";
  }

  if ($has(['не работает', 'не пингу', 'нет интернета', 'проблем', 'debug', 'ошибк', 'не транслир'])) {
    return "Чек-лист, если NAT не работает:\n"
      . "1) ip nat inside / ip nat outside стоят на правильных интерфейсах;\n"
      . "2) ACL действительно matchит твою сеть (show access-lists);\n"
      . "3) есть маршрут по умолчанию (ip route 0.0.0.0 0.0.0.0 <next-hop>);\n"
      . "4) смотри трансляции: show ip nat translations;\n"
      . "5) живой дебаг: debug ip nat (осторожно на проде).";
  }

  if ($has(['iol', 'ios on linux', 'gns3', 'eve-ng', 'лаб', 'эмулят'])) {
    return "В Cisco IOL (IOS on Linux) NAT настраивается теми же командами, что и на железе. "
      . "Интерфейсы обычно вида e0/0, e0/1. Поднимаешь топологию в GNS3/EVE-NG, "
      . "ставишь ip nat inside/outside и правило ip nat inside source ... — всё как на реальном роутере.";
  }

  return "Я ассистент поддержки helpCisco и помогаю с NAT в Cisco IOL. "
    . "Уточни вопрос — например: «настроить PAT overload», «статический проброс порта», "
    . "«динамический NAT из пула», «не работает NAT, что проверить».\n\n"
    . "(Подсказка для владельца сайта: задай ключ ИИ в config.php — и тут будут отвечать "
    . "развёрнутые ответы реальной модели на любой вопрос.)";
}
