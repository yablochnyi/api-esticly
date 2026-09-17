#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[entrypoint] waiting for mysql and redis..."

MYSQL_HOST="${DB_HOST:-mysql}"
MYSQL_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

for i in {1..60}; do
  nc -z "$MYSQL_HOST" "$MYSQL_PORT" >/dev/null 2>&1 && break
  sleep 1
done

for i in {1..60}; do
  nc -z "$REDIS_HOST" "$REDIS_PORT" >/dev/null 2>&1 && break
  sleep 1
done

echo "[entrypoint] preparing app..."

mkdir -p /tmp/nginx/client_body /tmp/nginx/proxy /tmp/nginx/fastcgi /tmp/nginx/uwsgi /tmp/nginx/scgi
mkdir -p /backups/mysql
chown -R www-data:www-data /tmp/nginx /backups/mysql storage bootstrap/cache

if [ ! -f ".env" ]; then
  echo "[entrypoint] .env missing, copying from .env.example"
  cp .env.example .env
fi

php artisan config:clear || true
# Keep OTP limits and consumed App Check tokens across deployments.
php artisan view:clear || true

if [ -z "${APP_KEY:-}" ]; then
  echo "[entrypoint] ERROR: APP_KEY is empty. Refusing to start."
  echo "[entrypoint] Set a fixed APP_KEY in environment (same key across restarts/deploys)."
  exit 1
fi

echo "[entrypoint] running migrations..."
php artisan migrate --force

echo "[entrypoint] starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
