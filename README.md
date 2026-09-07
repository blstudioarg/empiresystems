# Empire Systems CRM

SaaS de facturación para España (multi-tenant). Ver `docs/00-vision.md` y siguientes para la
visión del producto y las decisiones técnicas, y `CLAUDE.md` para el flujo de trabajo del
proyecto (spec-kit).

## Requisitos

- PHP 8.2+, Composer
- Node.js + npm
- MySQL/MariaDB

## Instalación

```powershell
composer install
copy .env.example .env   # ajustar DB_* si hace falta
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Certificados TLS en Windows (obligatorio para el asistente IA)

PHP en Windows no trae bundle de CAs configurado, así que cualquier llamada saliente por HTTPS
(OpenAI, VIES, etc.) falla con `cURL error 60: SSL certificate ... unable to get local issuer
certificate`. No se arregla desactivando la verificación: hay que apuntar PHP al bundle de CAs.

1. Descargar [cacert.pem](https://curl.se/ca/cacert.pem) y guardarlo, por ejemplo, en
   `<carpeta-de-PHP>\extras\ssl\cacert.pem`.
2. En el `php.ini` cargado (`php -i | findstr "Loaded Configuration File"`), descomentar y
   apuntar **ambas** claves a esa ruta absoluta:

   ```ini
   curl.cainfo = "C:\ruta\a\PHP\extras\ssl\cacert.pem"
   openssl.cafile = "C:\ruta\a\PHP\extras\ssl\cacert.pem"
   ```

3. Reiniciar `php artisan serve` (el `php.ini` se lee al arrancar el proceso) y comprobar con
   `php -i | findstr cainfo`.

## Credenciales de desarrollo (sembradas por `AuthSeeder`)

> ⚠️ Estas credenciales son solo para desarrollo local. **Cambiarlas antes de desplegar a
> producción.**

| Rol | Email | Contraseña |
|---|---|---|
| Super admin (global, sin tenant) | `admin@empiresystems.es` | `password` |
| Admin del tenant demo ("Empresa Demo SL") | `demo@empiresystems.es` | `password` |

### Acceso personal (no genérico)

Además del demo de arriba, `database/seeders/AccesoPersonalSeeder.php` garantiza un superadmin y
un tenant fijos con credenciales propias (no hardcodeadas en el código — salen de `ACCESO_PERSONAL_*`
en `.env`). Los valores reales están en `ACCESOS.local.md` en la raíz del repo (no versionado).
Es idempotente: correrlo de nuevo no pisa la contraseña de un usuario que ya existe.

```powershell
php artisan db:seed --class=AccesoPersonalSeeder
```

## Tests

```powershell
php artisan test
```
