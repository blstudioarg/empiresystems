# Quickstart: Sistema de roles y permisos por tenant

## Prerrequisitos
- `composer install` (añade `spatie/laravel-permission:^6`).
- `php artisan migrate` (tablas spatie + migración de datos de tenants existentes).
- `php artisan db:seed --class=PermisosSeeder` (idempotente; también corre dentro de la migración de datos).

## Validación rápida

### 1. Tests automatizados (fuente principal de verdad)
```powershell
php artisan test --filter=Roles
php artisan test tests/Feature/RolesTest.php tests/Feature/RolesAislamientoTest.php tests/Feature/SidebarPermisosTest.php
php artisan test   # suite completa: nada existente se rompe (SC-005)
```
Cobertura esperada:
- Aislamiento con 2 tenants: roles del tenant A invisibles/ inasignables desde B (SC-002).
- 403 por ruta protegida sin permiso, HTML y JSON (SC-001).
- Alta de tenant → rol Administrador completo asignado al admin inicial (SC-004); rollback
  atómico si falla un paso.
- Anti-lockout: eliminar/editar/reasignar que dejaría al tenant sin administración → 422/409.
- Seeder re-ejecutado: no duplica y sincroniza el rol Administrador con permisos nuevos.

### 2. Validación manual (e2e)
1. Login como super admin → crear tenant nuevo → login como su admin: menú completo visible,
   entrar a **Roles** (SC-004).
2. Crear rol "Ventas" con Clientes + Facturas desde el modal → aparece en la datatable y en
   las cards (SC-003).
3. En **Usuarios**, asignar "Ventas" a un usuario → login con ese usuario: el sidebar solo
   muestra Inicio*, Clientes, Facturas y sus secciones personales; URL directa a `/stock` →
   403 (SC-001). (*según permisos marcados)
4. Editar "Ventas" quitando Facturas → recargar como el usuario: Facturas desaparece del
   menú y `/facturas` da 403.
5. Intentar eliminar el rol Administrador o quitarle `ver-roles` → bloqueado con mensaje.

## Referencias
- Contratos: [contracts/http.md](contracts/http.md)
- Modelo de datos: [data-model.md](data-model.md)
- Decisiones: [research.md](research.md)
