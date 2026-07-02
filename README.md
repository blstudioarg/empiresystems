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

## Credenciales de desarrollo (sembradas por `AuthSeeder`)

> ⚠️ Estas credenciales son solo para desarrollo local. **Cambiarlas antes de desplegar a
> producción.**

| Rol | Email | Contraseña |
|---|---|---|
| Super admin (global, sin tenant) | `admin@empiresystems.es` | `password` |
| Admin del tenant demo ("Empresa Demo SL") | `demo@empiresystems.es` | `password` |

## Tests

```powershell
php artisan test
```
