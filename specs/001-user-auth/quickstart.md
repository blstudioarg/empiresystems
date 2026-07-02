# Quickstart: validar la autenticación end-to-end

**Feature**: 001-user-auth · Referencias: [contrato de rutas](contracts/routes.md) ·
[modelo de datos](data-model.md)

## Prerrequisitos

- XAMPP MySQL corriendo, base `empire_crm` existente (`.env` ya apunta ahí).
- Dependencias instaladas (`composer install`).

## Preparar

```powershell
php artisan migrate:fresh --seed   # migraciones + super_admin + tenant demo + admin demo
php artisan test                   # suite completa en verde (SQLite in-memory)
php artisan serve                  # http://127.0.0.1:8000
```

## Validación manual (mapa a criterios de éxito)

1. **Rutas protegidas (SC-002)**: abrir `http://127.0.0.1:8000/` sin sesión → redirige a
   `/login`. La pantalla está en español, con branding Empire Systems, sin registro, sin social,
   sin "olvidé mi contraseña".
2. **Login admin demo (SC-001, SC-004)**: entrar con `demo@empiresystems.es` + contraseña del
   README → aterriza en el dashboard.
3. **Contexto de tenant (SC-003, parcial)**: con el admin demo autenticado, `php artisan tinker`
   no aplica a la sesión web; la verificación de contexto queda cubierta por
   `tests/Feature/Auth/TenantContextTest.php` (contexto inicializado con el tenant demo;
   super_admin sin contexto). El aislamiento sobre datos reales se ejercita cuando exista el
   primer modelo de negocio con `BelongsToTenant`.
4. **Logout**: menú de usuario → "Cerrar sesión" → vuelve a `/login`; botón atrás del navegador
   no muestra contenido interno.
5. **Credenciales inválidas**: probar email inexistente y contraseña errónea → mismo mensaje
   genérico en ambos casos.
6. **Throttling (SC-005)**: 5 intentos fallidos seguidos con el mismo email → el 6º muestra
   "Demasiados intentos…" con segundos de espera.
7. **Remember me (P3)**: login con "Recordarme" marcado → cerrar el navegador → reabrir `/` →
   sigue autenticado.
8. **Super admin (US4)**: login con `admin@empiresystems.es` → entra al dashboard (sin panel
   propio todavía, según Assumptions de la spec).
9. **Tenant inactivo**: en DB poner `activo = 0` al tenant demo → el admin demo no puede volver a
   entrar y, si tenía sesión abierta, al navegar es deslogueado.

## Resultado esperado

- `php artisan test` → 100% verde, incluyendo los tests de tenant context y seeder.
- Los 9 pasos manuales pasan sin tocar la base de datos a mano (salvo el paso 9, que la toca a
  propósito).
