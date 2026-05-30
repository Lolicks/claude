#!/usr/bin/env bash
#
# Автоустановка сайта-теста на сервер Debian/Ubuntu.
# Ставит Apache + PHP + Certbot, разворачивает сайт под твой домен и выпускает SSL.
#
# Использование:
#   sudo ./install.sh                      # спросит домен и email
#   sudo ./install.sh example.com you@mail.ru
#   sudo DOMAIN=example.com EMAIL=you@mail.ru ./install.sh
#
set -euo pipefail

# ---------- helpers ----------
log()  { printf "\n\033[1;35m==> %s\033[0m\n" "$*"; }
ok()   { printf "\033[1;32m    %s\033[0m\n" "$*"; }
err()  { printf "\033[1;31m!!  %s\033[0m\n" "$*" >&2; }

if [[ "${EUID}" -ne 0 ]]; then
  err "Запусти скрипт от root: sudo ./install.sh"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ---------- 1. Домен и email ----------
DOMAIN="${DOMAIN:-${1:-}}"
EMAIL="${EMAIL:-${2:-}}"

if [[ -z "${DOMAIN}" ]]; then
  read -rp "Введи домен (например example.com): " DOMAIN
fi
if [[ -z "${EMAIL}" ]]; then
  read -rp "Введи email (для Let's Encrypt): " EMAIL
fi

DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN%/}"

if [[ -z "${DOMAIN}" || -z "${EMAIL}" ]]; then
  err "Домен и email обязательны."
  exit 1
fi

WEBROOT="/var/www/${DOMAIN}"
VHOST="/etc/apache2/sites-available/${DOMAIN}.conf"

log "Домен: ${DOMAIN}"
log "Email: ${EMAIL}"
log "Папка сайта: ${WEBROOT}"

# ---------- 2. Установка пакетов ----------
log "Устанавливаю Apache, PHP и Certbot..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 php libapache2-mod-php certbot python3-certbot-apache rsync
ok "Пакеты установлены."

# ---------- 3. Деплой файлов ----------
log "Копирую файлы сайта в ${WEBROOT}..."
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
ok "Файлы скопированы."

# ---------- 4. Права ----------
log "Ставлю права..."
chown -R www-data:www-data "${WEBROOT}"
find "${WEBROOT}" -type d -exec chmod 755 {} \;
find "${WEBROOT}" -type f -exec chmod 644 {} \;
chmod 775 "${WEBROOT}/data"
ok "Права выставлены (папка data доступна для записи)."

# ---------- 5. Виртуальный хост Apache ----------
log "Создаю виртуальный хост..."
cat > "${VHOST}" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    DocumentRoot ${WEBROOT}

    <Directory ${WEBROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Доступ к ответам закрыт наглухо.
    <Directory ${WEBROOT}/data>
        Require all denied
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${DOMAIN}-access.log combined
</VirtualHost>
EOF

a2enmod rewrite headers >/dev/null
a2ensite "${DOMAIN}.conf" >/dev/null
a2dissite 000-default.conf >/dev/null 2>&1 || true
apache2ctl configtest
systemctl reload apache2
ok "Виртуальный хост подключён."

# ---------- 6. Фаервол ----------
if command -v ufw >/dev/null 2>&1; then
  log "Открываю порты 80 и 443 в ufw..."
  ufw allow 80/tcp  >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  ok "Порты открыты."
fi

# ---------- 7. SSL-сертификат ----------
log "Выпускаю SSL-сертификат через Let's Encrypt..."
CERT_DOMAINS=(-d "${DOMAIN}")
if getent hosts "www.${DOMAIN}" >/dev/null 2>&1; then
  CERT_DOMAINS+=(-d "www.${DOMAIN}")
  ok "www.${DOMAIN} резолвится — включаю в сертификат."
else
  err "www.${DOMAIN} не резолвится — выпускаю сертификат только для ${DOMAIN}."
fi

if certbot --apache "${CERT_DOMAINS[@]}" \
     --non-interactive --agree-tos -m "${EMAIL}" --redirect; then
  ok "SSL выпущен, HTTP перенаправлен на HTTPS."
  ok "Автопродление настроено (systemd timer certbot)."
else
  err "Не удалось выпустить сертификат. Проверь, что домен указывает A-записью на этот сервер,"
  err "и запусти повторно: certbot --apache -d ${DOMAIN} --redirect"
fi

# ---------- Готово ----------
log "Готово! 🎉"
echo "  Тест (для неё): https://${DOMAIN}/"
echo "  Админка (для тебя): https://${DOMAIN}/admin.php"
echo
err "Не забудь поменять пароль админки в ${WEBROOT}/config.php (по умолчанию 'changeme')!"
