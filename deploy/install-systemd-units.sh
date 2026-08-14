#!/usr/bin/env bash
# Instala/activa los systemd units de royal_back (queue worker + scheduler) en AlmaLinux.
# Los deja "enabled" -> persisten a reinicio del servidor sin necesidad de nohup/screen/tmux.
#
# Uso:
#   sudo deploy/install-systemd-units.sh [ruta_proyecto] [usuario_php]
#
# Por defecto:
#   ruta_proyecto = directorio actual (pwd)
#   usuario_php   = detectado automáticamente desde el proceso php-fpm (apache/nginx en AlmaLinux)

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Este script necesita sudo/root (systemctl, /etc/systemd/system, restorecon)." >&2
  exit 1
fi

PROJECT_DIR="${1:-$(pwd)}"
PHP_USER="${2:-$(ps -eo user,comm | awk '$2 ~ /php-fpm/ {print $1; exit}')}"
PHP_BIN="$(command -v php)"

if [[ -z "$PHP_USER" ]]; then
  echo "No pude detectar el usuario de php-fpm automáticamente." >&2
  echo "Pasalo a mano: sudo $0 $PROJECT_DIR <usuario>" >&2
  exit 1
fi

if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
  echo "No encuentro artisan en $PROJECT_DIR. ¿Es la ruta correcta del proyecto?" >&2
  exit 1
fi

echo "Proyecto: $PROJECT_DIR"
echo "Usuario:  $PHP_USER"
echo "PHP:      $PHP_BIN"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

for unit in royal-back-queue.service royal-back-scheduler.service royal-back-scheduler.timer; do
  sed \
    -e "s#/var/www/royal_back#${PROJECT_DIR}#g" \
    -e "s#www-data#${PHP_USER}#g" \
    -e "s#/usr/bin/php#${PHP_BIN}#g" \
    "$(dirname "$0")/$unit" > "$TMP_DIR/$unit"
  cp "$TMP_DIR/$unit" "/etc/systemd/system/$unit"
  echo "Instalado /etc/systemd/system/$unit"
done

systemctl daemon-reload
systemctl enable --now royal-back-queue.service
systemctl enable --now royal-back-scheduler.timer

if command -v getenforce >/dev/null && [[ "$(getenforce)" == "Enforcing" ]]; then
  echo "SELinux enforcing: aplicando restorecon sobre $PROJECT_DIR"
  restorecon -Rv "$PROJECT_DIR" || true
fi

echo
echo "--- Estado ---"
systemctl is-enabled royal-back-queue.service royal-back-scheduler.timer
systemctl list-timers royal-back-scheduler.timer --no-pager
echo
echo "Logs: journalctl -u royal-back-queue -f  /  journalctl -u royal-back-scheduler -f"
