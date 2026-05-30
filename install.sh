#!/usr/bin/env bash
#
# Установка сайта-теста с входом по IP (без домена).
# Поднимает сайт как systemd-сервис на отдельном порту через встроенный PHP-сервер.
# Не трогает занятые порты 80/443 — спокойно работает рядом с nginx/Apache/FastPanel.
#
# Использование:
#   sudo ./install.sh            # порт 8080 (или ближайший свободный)
#   sudo ./install.sh 8090       # свой порт
#   sudo PORT=8090 ./install.sh
#
set -euo pipefail

log() { printf "\n\033[1;35m==> %s\033[0m\n" "$*"; }
ok()  { printf "\033[1;32m    %s\033[0m\n" "$*"; }
err() { printf "\033[1;31m!!  %s\033[0m\n" "$*" >&2; }

if [[ "${EUID}" -ne 0 ]]; then
  err "Запусти от root: sudo ./install.sh"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEBROOT="/var/www/love-test"
SERVICE_NAME="love-test"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
RUN_USER="www-data"

# ---------- 1. Порт ----------
PORT="${PORT:-${1:-8080}}"

port_taken() {
  ss -tlnH 2>/dev/null | awk '{n=split($4,a,":"); print a[n]}' | grep -qx "$1"
}

REQUESTED="${PORT}"
while port_taken "${PORT}"; do
  PORT=$((PORT + 1))
done
if [[ "${PORT}" != "${REQUESTED}" ]]; then
  err "Порт ${REQUESTED} занят — беру свободный ${PORT}."
fi
log "Сайт будет на порту ${PORT}"

# ---------- 2. PHP ----------
log "Проверяю PHP..."
export DEBIAN_FRONTEND=noninteractive
if ! command -v php >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y php-cli php-mbstring
else
  # mbstring нужен для save.php; доустановим, если его нет.
  if ! php -m | grep -qi '^mbstring$'; then
    apt-get update -y && apt-get install -y php-mbstring || true
  fi
fi
command -v rsync >/dev/null 2>&1 || apt-get install -y rsync
PHP_BIN="$(command -v php)"
ok "PHP: ${PHP_BIN} ($(php -r 'echo PHP_VERSION;'))"

# ---------- 3. Останавливаем старый сервис (если был) ----------
if systemctl list-unit-files | grep -q "^${SERVICE_NAME}.service"; then
  systemctl stop "${SERVICE_NAME}" 2>/dev/null || true
fi

# ---------- 4. Копируем файлы ----------
log "Копирую сайт в ${WEBROOT}..."
mkdir -p "${WEBROOT}"
rsync -a --delete \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude '.claude' \
  --exclude 'install.sh' \
  --exclude 'README.md' \
  --exclude 'data/attempts.json' \
  "${SCRIPT_DIR}/" "${WEBROOT}/"
mkdir -p "${WEBROOT}/data"
ok "Файлы на месте."

# ---------- 5. Права ----------
log "Ставлю права..."
chown -R "${RUN_USER}:${RUN_USER}" "${WEBROOT}"
find "${WEBROOT}" -type d -exec chmod 755 {} \;
find "${WEBROOT}" -type f -exec chmod 644 {} \;
chmod 775 "${WEBROOT}/data"
ok "Папка data доступна для записи."

# ---------- 6. systemd-сервис ----------
log "Создаю сервис ${SERVICE_NAME}..."
cat > "${SERVICE_FILE}" <<EOF
[Unit]
Description=Love Test (PHP built-in server)
After=network.target

[Service]
Type=simple
User=${RUN_USER}
Group=${RUN_USER}
WorkingDirectory=${WEBROOT}
Environment=PHP_CLI_SERVER_WORKERS=4
ExecStart=${PHP_BIN} -S 0.0.0.0:${PORT} -t ${WEBROOT} ${WEBROOT}/router.php
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}" >/dev/null 2>&1
systemctl restart "${SERVICE_NAME}"
sleep 1
if systemctl is-active --quiet "${SERVICE_NAME}"; then
  ok "Сервис запущен и добавлен в автозапуск."
else
  err "Сервис не запустился. Логи: journalctl -u ${SERVICE_NAME} -n 30 --no-pager"
fi

# ---------- 7. Фаервол ----------
if command -v ufw >/dev/null 2>&1; then
  ufw allow "${PORT}/tcp" >/dev/null 2>&1 || true
  ok "Порт ${PORT} открыт в ufw."
fi

# ---------- 8. Определяем IP ----------
PUBLIC_IP="$(curl -s -m 5 https://api.ipify.org 2>/dev/null || true)"
[[ -z "${PUBLIC_IP}" ]] && PUBLIC_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
[[ -z "${PUBLIC_IP}" ]] && PUBLIC_IP="IP_СЕРВЕРА"

# ---------- Готово ----------
log "Готово! 🎉"
echo "  Тест (для неё):     http://${PUBLIC_IP}:${PORT}/"
echo "  Админка (для тебя): http://${PUBLIC_IP}:${PORT}/admin.php"
echo
err "Смени пароль админки: nano ${WEBROOT}/config.php  (по умолчанию 'changeme')"
echo
echo "Управление сервисом:"
echo "  systemctl status ${SERVICE_NAME}     # статус"
echo "  systemctl restart ${SERVICE_NAME}    # перезапуск"
echo "  journalctl -u ${SERVICE_NAME} -f     # логи"
echo
echo "Если по http://${PUBLIC_IP}:${PORT}/ не открывается снаружи —"
echo "открой порт ${PORT} ещё и в фаерволе/панели хостинга (внешний firewall)."
