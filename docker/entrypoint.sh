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

# --- Migraciones (con reintentos) ------------------------------------------
# En Railway los servicios arrancan en paralelo: la red privada del MySQL
# (mysql.railway.internal) puede no estar lista cuando corre el entrypoint. Sin
# reintento, `migrate` falla, el arranque sigue y las tablas nunca se crean
# (el error típico es "Table 'railway.cache' doesn't exist" en el worker). Por
# eso reintentamos hasta que la DB responde y las migraciones aplican.
echo "[entrypoint] Ejecutando migraciones (esperando a la base de datos)..."
MIGRATED=false
i=1
while [ "$i" -le 30 ]; do
    if php /app/artisan migrate --force 2>&1; then
        MIGRATED=true
        break
    fi
    echo "[entrypoint] La base no está lista todavía (intento $i/30); reintento en 3s..."
    sleep 3
    i=$((i + 1))
done

if [ "$MIGRATED" != "true" ]; then
    echo "[entrypoint] AVISO: las migraciones no se aplicaron tras 30 intentos; revisá la conexión a la base de datos."
fi

# --- Seed inicial (opt-in) -------------------------------------------------
# Con SEED_ON_DEPLOY=true crea el super admin + catálogo de permisos (idempotente).
# Solo si las migraciones aplicaron, para no fallar contra tablas inexistentes.
if [ "$MIGRATED" = "true" ] && [ "${SEED_ON_DEPLOY}" = "true" ]; then
    echo "[entrypoint] Sembrando datos iniciales (DeploySeeder)..."
    php /app/artisan db:seed --class=DeploySeeder --force \
        || echo "[entrypoint] AVISO: el seed inicial falló."
fi

echo "[entrypoint] Arrancando servicios."
exec "$@"
