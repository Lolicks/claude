#!/usr/bin/env bash
#
# helpCisco — перезагрузка сайта + проверка работы всех функций.
#
# Что делает:
#   1) перезапускает сайт (systemd-сервис helpcisco ИЛИ локальный php -S);
#   2) ждёт, пока он поднимется;
#   3) проверяет все функции: главная, статика, чат, лента конфигов,
#      скрытый мануал, защита служебных файлов, админка и — отдельно —
#      реально ли подключён и отвечает ИИ (через ai.php → поле "source").
#
# Использование:
#   ./restart.sh                 # авто: systemd-сервис, иначе локальный php -S
#   ./restart.sh --no-restart    # только проверки, без перезапуска
#   ./restart.sh --url http://1.2.3.4:8080   # проверять конкретный адрес
#   ./restart.sh --port 8090     # задать порт (локальный режим / systemd)
#   ./restart.sh --webroot /var/www/helpcisco   # где лежит сайт (для чтения .env)
#
# Коды выхода: 0 — все проверки прошли; 1 — есть проваленные проверки.

set -uo pipefail

# ---------- параметры ----------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="helpcisco"
WEBROOT_DEFAULT="/var/www/helpcisco"
LOCAL_PORT_DEFAULT="8000"
ENV_FILE="/etc/helpcisco.env"

NO_RESTART=0
BASE_URL=""
PORT=""
WEBROOT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-restart) NO_RESTART=1; shift ;;
    --url)        BASE_URL="${2:-}"; shift 2 ;;
    --port)       PORT="${2:-}"; shift 2 ;;
    --webroot)    WEBROOT="${2:-}"; shift 2 ;;
    -h|--help)    grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Неизвестный аргумент: $1" >&2; exit 2 ;;
  esac
done

# ---------- цвета ----------
if [[ -t 1 ]]; then
  C_OK=$'\033[1;32m'; C_BAD=$'\033[1;31m'; C_WARN=$'\033[1;33m'
  C_H=$'\033[1;35m'; C_DIM=$'\033[2m'; C_R=$'\033[0m'
else
  C_OK=""; C_BAD=""; C_WARN=""; C_H=""; C_DIM=""; C_R=""
fi

PASS=0; FAIL=0; WARN=0
BODY="$(mktemp /tmp/helpcisco-hc.XXXXXX)"
trap 'rm -f "$BODY"' EXIT

head()  { printf "\n${C_H}==> %s${C_R}\n" "$*"; }
pass()  { PASS=$((PASS+1)); printf "  ${C_OK}✔${C_R} %s\n" "$*"; }
fail()  { FAIL=$((FAIL+1)); printf "  ${C_BAD}x${C_R} %s\n" "$*"; }
warn()  { WARN=$((WARN+1)); printf "  ${C_WARN}!${C_R} %s\n" "$*"; }
info()  { printf "  ${C_DIM}%s${C_R}\n" "$*"; }

# ---------- определяем режим работы ----------
MODE="local"
if command -v systemctl >/dev/null 2>&1 \
   && systemctl list-unit-files 2>/dev/null | grep -q "^${SERVICE_NAME}\.service"; then
  MODE="systemd"
fi

# Порт: из аргумента, иначе из systemd-юнита, иначе дефолт.
if [[ -z "$PORT" ]]; then
  if [[ "$MODE" == "systemd" ]]; then
    PORT="$(systemctl cat "$SERVICE_NAME" 2>/dev/null \
      | sed -n 's/.*-S[[:space:]]\{1,\}[0-9.]*:\([0-9]\{1,\}\).*/\1/p' | head -n1)"
  fi
  [[ -z "$PORT" ]] && PORT="$LOCAL_PORT_DEFAULT"
fi

# Каталог сайта (для чтения эффективного конфига через php).
if [[ -z "$WEBROOT" ]]; then
  if [[ "$MODE" == "systemd" ]]; then WEBROOT="$WEBROOT_DEFAULT"; else WEBROOT="$SCRIPT_DIR"; fi
fi

# Базовый URL.
[[ -z "$BASE_URL" ]] && BASE_URL="http://localhost:${PORT}"
BASE_URL="${BASE_URL%/}"

info "Режим: ${MODE} | URL: ${BASE_URL} | сайт: ${WEBROOT}"

# ---------- HTTP-помощники ----------
req() { # req METHOD URL [JSON] -> печатает http-код, тело пишет в $BODY
  local method="$1" url="$2" data="${3:-}"
  if [[ -n "$data" ]]; then
    curl -s -m 30 -o "$BODY" -w '%{http_code}' -X "$method" \
      -H 'Content-Type: application/json' --data "$data" "$url" 2>/dev/null
  else
    curl -s -m 30 -o "$BODY" -w '%{http_code}' -X "$method" "$url" 2>/dev/null
  fi
}

expect_code() { # desc METHOD URL EXPECTED [JSON]
  local desc="$1" method="$2" url="$3" exp="$4" data="${5:-}" code
  code="$(req "$method" "$url" "$data")"
  if [[ "$code" == "$exp" ]]; then pass "$desc (HTTP $code)"
  else fail "$desc — ожидался HTTP $exp, получен ${code:-нет ответа}"; fi
}

expect_ok_json() { # desc URL
  local desc="$1" url="$2" code
  code="$(req GET "$url")"
  if [[ "$code" == "200" ]] && grep -q '"ok":true' "$BODY"; then pass "$desc"
  else fail "$desc — HTTP ${code:-?}, тело: $(head -c140 "$BODY")"; fi
}

# ---------- эффективный конфиг сайта (через php, учитывая .env и окружение) ----------
PHP_BIN="$(command -v php || true)"
php_cfg() { # php_cfg 'echo $VAR;'  -> значение или пусто
  [[ -z "$PHP_BIN" || ! -f "$WEBROOT/config.php" ]] && return 0
  ( [[ -f "$ENV_FILE" ]] && set -a && . "$ENV_FILE" 2>/dev/null || true
    cd "$WEBROOT" && "$PHP_BIN" -r 'error_reporting(0); require "config.php"; '"$1" ) 2>/dev/null
}

AI_PROVIDER="$(php_cfg 'echo $AI_PROVIDER;')"
AI_HAS_KEY="$(php_cfg 'echo ($AI_API_KEY !== "" ? "1" : "0");')"
AI_MODEL="$(php_cfg 'echo $AI_MODEL;')"
MANUAL_KEY="$(php_cfg 'echo $MANUAL_KEY;')"

# ---------- 1. Перезапуск ----------
if [[ "$NO_RESTART" == "1" ]]; then
  head "Перезапуск пропущен (--no-restart)"
elif [[ "$MODE" == "systemd" ]]; then
  head "Перезапускаю systemd-сервис ${SERVICE_NAME}"
  if systemctl restart "$SERVICE_NAME" 2>/dev/null; then info "systemctl restart выполнен"
  else warn "Не удалось перезапустить сервис (нужен root?). Продолжаю проверки."; fi
else
  head "Перезапускаю локальный сервер (php -S :${PORT})"
  if [[ -z "$PHP_BIN" ]]; then
    fail "php не найден — локальный сервер не запустить. Поставь php-cli."
  else
    pkill -f "php -S [^ ]*:${PORT} .*router.php" 2>/dev/null || true
    pkill -f "php -S [^ ]*:${PORT} router.php"  2>/dev/null || true
    sleep 1
    ( cd "$WEBROOT" && nohup "$PHP_BIN" -S 0.0.0.0:"$PORT" router.php \
        >/tmp/helpcisco-php.log 2>&1 & )
    info "Запущен php -S 0.0.0.0:${PORT} (лог: /tmp/helpcisco-php.log)"
  fi
fi

# ---------- 2. Ждём, пока поднимется ----------
head "Жду готовности сайта"
up=0
for i in $(seq 1 30); do
  code="$(curl -s -m 5 -o /dev/null -w '%{http_code}' "${BASE_URL}/" 2>/dev/null || true)"
  if [[ "$code" == "200" ]]; then up=1; break; fi
  sleep 0.5
done
if [[ "$up" == "1" ]]; then pass "Сайт отвечает на ${BASE_URL}/"
else
  fail "Сайт не поднялся за 15 c (${BASE_URL}/)"
  printf "\n${C_BAD}Дальнейшие проверки бессмысленны — сайт недоступен.${C_R}\n"
  exit 1
fi

# ---------- 3. Страницы и статика ----------
head "Страницы и статические файлы"
code="$(req GET "${BASE_URL}/")"
if [[ "$code" == "200" ]] && grep -qi 'helpCisco' "$BODY"; then pass "Главная (index.php) отдаётся и содержит контент"
else fail "Главная — HTTP ${code:-?} или нет ожидаемого контента"; fi
expect_code "style.css доступен"  GET "${BASE_URL}/style.css"  200
expect_code "script.js доступен"  GET "${BASE_URL}/script.js"  200
expect_code "theme.js доступен (рандомная тема)" GET "${BASE_URL}/theme.js" 200
expect_code "Админка (admin.php) открывает форму входа" GET "${BASE_URL}/admin.php" 200

# ---------- 4. API: лента конфигов и чат ----------
head "API: лента конфигов и чат"
expect_ok_json "posts.php (GET) — лента конфигов отвечает ok"        "${BASE_URL}/posts.php"
expect_ok_json "chat.php?room=global (GET) — общий чат отвечает ok"  "${BASE_URL}/chat.php?room=global"
expect_code   "chat.php с битой комнатой отклоняется (400)" GET "${BASE_URL}/chat.php?room=..%2Fbad" 400

# ---------- 5. Скрытый мануал ----------
head "Скрытый раздел (manual.php)"
expect_code "Без ключа manual.php прикидывается 404 (раздел скрыт)" GET "${BASE_URL}/manual.php" 404
if [[ -n "$MANUAL_KEY" ]]; then
  code="$(req GET "${BASE_URL}/manual.php?key=$(printf '%s' "$MANUAL_KEY" | sed 's/ /%20/g')")"
  if [[ "$code" == "302" || "$code" == "200" ]]; then pass "С верным ключом доступ открывается (HTTP $code)"
  else fail "С верным ключом ожидался 302/200, получен ${code:-?}"; fi
else
  warn "MANUAL_KEY не прочитан (нет php/.env рядом) — позитивную проверку мануала пропускаю"
fi

# ---------- 6. Защита служебных файлов ----------
head "Защита служебных файлов (не должны отдаваться)"
expect_code "config.php закрыт (403)"      GET "${BASE_URL}/config.php"      403
expect_code "lib.php закрыт (403)"         GET "${BASE_URL}/lib.php"         403
expect_code ".env закрыт (403)"            GET "${BASE_URL}/.env"            403
expect_code "data/posts.json закрыт (403)" GET "${BASE_URL}/data/posts.json" 403

# ---------- 7. ИИ-поддержка ----------
head "ИИ-поддержка (ai.php)"
code="$(req POST "${BASE_URL}/ai.php" '{"message":"healthcheck: ответь одним словом"}')"
source="$(sed -n 's/.*"source"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$BODY" | head -n1)"
if [[ "$code" != "200" ]] || ! grep -q '"ok":true' "$BODY"; then
  fail "ai.php не ответил корректно — HTTP ${code:-?}, тело: $(head -c140 "$BODY")"
else
  pass "ai.php отвечает (HTTP 200, ok), source=${source:-?}"
  # Разбираем, реально ли подключён внешний ИИ.
  prov="$(printf '%s' "${AI_PROVIDER:-}" | tr '[:upper:]' '[:lower:]')"
  if [[ -z "$PHP_BIN" || ! -f "$WEBROOT/config.php" ]]; then
    info "Конфиг недоступен скрипту — сужу только по ответу."
    [[ "$source" == "offline" ]] \
      && warn "ИИ работает в ОФФЛАЙН-режиме (встроенный ассистент). Если ждёшь внешнюю модель — проверь AI_API_KEY/AI_PROVIDER в .env." \
      || pass "Внешний ИИ подключён и отвечает (source=${source})."
  elif [[ "$prov" == "openrouter" || "$prov" == "openai" || "$prov" == "anthropic" ]]; then
    if [[ "$AI_HAS_KEY" != "1" ]]; then
      warn "AI_PROVIDER=${prov}, но AI_API_KEY пуст → отвечает оффлайн-ассистент. Впиши ключ в .env."
    elif [[ "$source" == "$prov" ]]; then
      pass "Внешний ИИ ПОДКЛЮЧЁН и РАБОТАЕТ: провайдер=${prov}, модель=${AI_MODEL:-?}."
    elif [[ "$source" == "offline" ]]; then
      fail "ИИ НАСТРОЕН (provider=${prov}, ключ есть), но ответил ОФФЛАЙН-ассистент → запрос к ${prov} не прошёл. Проверь: верный ли ключ, существует ли модель '${AI_MODEL:-?}', есть ли php-curl и доступ в интернет (см. /etc/helpcisco.env, Settings→Privacy в OpenRouter, лимиты)."
    else
      warn "Неожиданный source='${source}' при провайдере ${prov}."
    fi
  else
    info "AI_PROVIDER='${AI_PROVIDER:-none}' (внешняя модель не настроена) — оффлайн-ассистент это норма."
    [[ "$source" == "offline" ]] && pass "Оффлайн-ассистент по NAT работает." \
      || pass "ИИ отвечает (source=${source})."
  fi
fi

# ---------- Итог ----------
head "Итог"
printf "  ${C_OK}Пройдено: %d${C_R}   ${C_WARN}Предупреждений: %d${C_R}   ${C_BAD}Провалено: %d${C_R}\n" \
  "$PASS" "$WARN" "$FAIL"
if [[ "$FAIL" -gt 0 ]]; then
  printf "${C_BAD}Есть проблемы — смотри строки с ✗ выше.${C_R}\n"
  exit 1
fi
printf "${C_OK}Все функции работают.${C_R}\n"
exit 0
