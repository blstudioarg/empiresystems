# ============================================================================
# Empire Systems CRM — imagen de producción para Railway
# Un solo contenedor: Nginx + PHP-FPM gestionados por supervisord.
# ============================================================================

# --- Stage 1: build de assets front (Vite + Tailwind) ---------------------
FROM node:20-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# npm bug con dependencias opcionales nativas de rollup (npm/cli#4828): un lockfile
# generado en otra plataforma (Windows) puede hacer que `npm ci` no baje el binario
# nativo de linux (@rollup/rollup-linux-x64-gnu en node:20-slim) y Vite falle con
# "Cannot find module @rollup/rollup-linux-*". Si pasa, reinstalación limpia que
# resuelve la dependencia opcional para la plataforma del contenedor.
RUN npm ci --no-audit --no-fund \
    || (rm -rf node_modules package-lock.json && npm install --no-audit --no-fund)
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- Stage 2: dependencias PHP (Composer, sin dev) ------------------------
FROM composer:2 AS vendor
WORKDIR /app
# Instala solo con los manifiestos primero para aprovechar la cache de capas.
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --ignore-platform-reqs
# Copia el código y genera el autoload optimizado ya con todo presente.
COPY . .
# --no-scripts: package:discover (y su boot de Laravel) no aplica en build sin .env/DB;
# el manifiesto de paquetes se regenera solo en el primer arranque (bootstrap/cache escribible).
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# --- Stage 3: runtime ------------------------------------------------------
FROM php:8.2-fpm-alpine AS runtime

# gettext -> envsubst (plantilla de Nginx con $PORT); nginx + supervisor;
# curl + ca-certificates para bajar el instalador de extensiones.
RUN apk add --no-cache nginx supervisor gettext curl ca-certificates

# Extensiones PHP necesarias por el proyecto:
#   pdo_mysql  -> MySQL/MariaDB en Railway
#   gd, exif   -> imágenes de artículos / dompdf
#   zip, intl  -> facturae-php / localización
#   bcmath     -> cálculos de importes exactos
#   opcache    -> rendimiento en producción
#   pcntl      -> workers de cola (queue:work)
RUN curl -fsSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql gd exif zip intl bcmath opcache pcntl

WORKDIR /app

# Código + vendor (stage 2) y assets compilados (stage 1).
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

# Configuración de servicios.
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permisos: php-fpm corre como www-data en la imagen oficial.
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && mkdir -p /var/lib/nginx /var/log/nginx /run/nginx

# Railway inyecta $PORT; documentamos el default.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
