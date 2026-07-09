#!/bin/sh
set -e

echo "[entrypoint] Preparando Empire Systems CRM..."

# Railway inyecta $PORT en runtime; default local 8080.
export PORT="${PORT:-8080}"

# --- Estructura de storage -------------------------------------------------
# Un volumen de Railway montado en /app/storage llega vacío la primera vez y
# tapa la estructura de la imagen: la recreamos siempre para que exista.
mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Enlace público -> storage/app/public (idempotente).
php /app/artisan storage:link --force 2>/dev/null || true

# --- Nginx: resolver ${PORT} en la plantilla -------------------------------
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
echo "[entrypoint] Nginx escuchará en el puerto ${PORT}."

# --- Cachés de Laravel (imagen inmutable => cachear una vez por arranque) ---
php /app/artisan config:cache
php /app/artisan route:cache
php /app/artisan view:cache
php /app/artisan event:cache 2>/dev/null || true

# --- Migraciones -----------------------------------------------------------
# No abortamos el arranque si fallan: dejamos el server arriba para inspeccionar
# logs en Railway en vez de entrar en un bucle de reinicios.
echo "[entrypoint] Ejecutando migraciones..."
php /app/artisan migrate --force || echo "[entrypoint] AVISO: las migraciones fallaron; revisá la conexión a la base de datos."

echo "[entrypoint] Arrancando servicios."
exec "$@"
