# Despliegue en Railway

Guía para servir Empire Systems CRM (Laravel 12, multi-tenant single-database) en
[Railway](https://railway.com). El proyecto se despliega como **un contenedor Docker**
(Nginx + PHP-FPM + worker de cola vía supervisord) más un servicio **MySQL**.

## Arquitectura del deploy

```
┌─────────────────────────── Railway Project ───────────────────────────┐
│                                                                        │
│   Servicio "web" (este repo)            Servicio "MySQL" (plugin)      │
│   ┌───────────────────────────┐         ┌──────────────────────────┐  │
│   │ Dockerfile                │         │ MySQL 8                  │  │
│   │  ├─ Nginx (:$PORT)        │◀────────│ MYSQLHOST/PORT/USER/...  │  │
│   │  ├─ PHP-FPM (:9000)       │  red    │                          │  │
│   │  └─ queue:work            │ interna │                          │  │
│   │ Volumen -> /app/storage   │         └──────────────────────────┘  │
│   └───────────────────────────┘                                       │
└────────────────────────────────────────────────────────────────────────┘
```

## Archivos relevantes (ya en el repo)

| Archivo | Rol |
|---|---|
| `Dockerfile` | Build multi-stage: assets (Vite) → vendor (Composer) → runtime (PHP-FPM + Nginx). |
| `docker/nginx.conf.template` | Config de Nginx; `${PORT}` se resuelve en el arranque. |
| `docker/php.ini` | Ajustes de producción (OPcache, límites de subida, zona horaria). |
| `docker/supervisord.conf` | Levanta PHP-FPM, Nginx y el worker de cola. |
| `docker/entrypoint.sh` | Prepara storage, cachea config/rutas/vistas y corre migraciones. |
| `railway.json` | Le dice a Railway que use el Dockerfile. |
| `.env.railway.example` | Plantilla de variables de entorno para Railway. |

## Paso a paso

### 1. Crear el proyecto y la base de datos
1. En Railway: **New Project → Deploy from GitHub repo** y elegí este repositorio.
   Railway detecta el `railway.json`/`Dockerfile` y construye la imagen.
2. En el mismo proyecto: **New → Database → Add MySQL**. Railway crea el servicio
   con las variables `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`.

### 2. Variables de entorno
En el servicio web → **Variables**, cargá las claves de `.env.railway.example`.
Puntos importantes:

- **`APP_KEY`**: generala una vez en local con `php artisan key:generate --show`
  y pegá el valor (`base64:...`).
- **`DB_*`**: referenciá el plugin MySQL con la sintaxis de Railway, p. ej.
  `DB_HOST=${{MySQL.MYSQLHOST}}` (ajustá `MySQL` al nombre real del servicio de DB).
- **`CENTRAL_DOMAINS`**: incluí el dominio público del servicio (ver paso 4). Es lo
  que permite que la app **central** (login, `super_admin`) resuelva por `Host`.
- **`APP_ENV=production`**, **`APP_DEBUG=false`**.

### 3. Volumen para archivos subidos
El filesystem del contenedor es efímero: sin volumen, cada redeploy borra los PDFs
de facturas, logos de tenant e imágenes de artículos.

- Servicio web → **Settings → Volumes → Add Volume**, con **mount path** `/app/storage`.
- El `entrypoint.sh` recrea la estructura (`framework/`, `logs/`, `app/public`) y el
  symlink `public/storage` en cada arranque, así que el volumen vacío inicial no rompe nada.

### 4. Dominio y `CENTRAL_DOMAINS`
- Servicio web → **Settings → Networking → Generate Domain**. Obtenés algo como
  `empiresystems-production.up.railway.app`.
- Poné ese host en `APP_URL` (con `https://`) y en `CENTRAL_DOMAINS` (sin esquema).
- **Tenants**: la app resuelve el tenant por el `Host` de la request contra la tabla
  `domains`. Para que un tenant tenga su propio dominio/subdominio:
  1. Añadí ese dominio como **Custom Domain** del servicio web en Railway (y apuntá el
     DNS con el `CNAME` que indica Railway).
  2. Registralo en la tabla `domains` asociado al tenant (desde el panel `super_admin`).
  Los dominios de tenant **no** van en `CENTRAL_DOMAINS`.

### 5. Migraciones y datos iniciales
- Las migraciones corren solas en cada arranque (`entrypoint.sh`, `migrate --force`).
- Para seeders o crear el primer super admin, usá la consola del servicio en Railway:
  `php artisan db:seed` / `php artisan tinker`, o el comando que tenga el proyecto.

## Cola de trabajos
El worker `queue:work` va incluido en el contenedor (supervisord). Si preferís aislarlo,
poné `autostart=false` en `docker/supervisord.conf` y creá un segundo servicio Railway
sobre el mismo repo con start command `php artisan queue:work`.

## Notas y límites

- **Sesión/cache/cola** usan driver `database`: funciona sin Redis. Si más adelante hay
  carga, agregá el plugin Redis de Railway y cambiá `SESSION_DRIVER`/`CACHE_STORE`/
  `QUEUE_CONNECTION` a `redis`.
- **HTTPS/proxy**: Railway termina TLS por delante. Si aparecen problemas de URLs http/https
  o cookies, configurá `TrustProxies` (`App\Http\Middleware`) para confiar en el proxy de Railway.
- **OPcache** cachea el código sin revalidar (imagen inmutable): un cambio se refleja al
  redeployar, no en caliente. Es el comportamiento deseado en producción.
- **Coste**: el volumen y el servicio MySQL consumen del plan de Railway; revisá el uso
  en el dashboard.
