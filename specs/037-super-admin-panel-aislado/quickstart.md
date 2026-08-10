# Quickstart — validación de la feature 037

Guía de verificación end-to-end. **No** contiene implementación (eso vive en `tasks.md` y en el
código); son los pasos para comprobar que la feature hace lo que promete.

## Prerrequisitos

- Entorno local en marcha (`php artisan serve` o el vhost habitual) con el dominio central
  configurado en `config/tenancy.php` (`localhost` por defecto).
- Credenciales de Super Admin y del tenant de demo: **`ACCESOS.local.md`** en la raíz del repo
  (gitignored). Reproducibles con `php artisan db:seed --class=AccesoPersonalSeeder` (idempotente).
- **No** ejecutar `migrate:fresh`/`migrate:refresh`: esta feature no añade migraciones y hay datos de
  demo con valor de presentación (regla de `CLAUDE.md`).

## 1. Pruebas automatizadas

```bash
php artisan test --filter=SuperAdmin
```

Esperado: verde, incluidos los tests existentes `SuperAdminBypassTest` y `TenantDomainResolutionTest`
(no deben requerir cambios; si alguno se pone rojo, es una regresión de FR-005, no un test a
"ajustar").

Batería completa antes de dar por cerrada la feature:

```bash
php artisan test
```

## 2. Aislamiento (US1) — manual

1. Iniciar sesión como Super Admin en el dominio central.
2. Escribir en la barra de direcciones `/(clientes|facturas|articulos|configuracion|logs)` uno a uno.
   → **Esperado**: cada uno redirige a `/super_admin` y aparece un toast de aviso. Ninguno pinta la
   pantalla.
3. Abrir las herramientas de desarrollo y lanzar una petición con `Accept: application/json` a
   `/clientes`. → **Esperado**: `403` con `message` legible, no un `302`.
4. Abrir `/perfil` → **Esperado**: 200, se puede cambiar la contraseña.
5. Pulsar "Cerrar sesión" → **Esperado**: vuelve a login.
6. Revisar `storage/logs/laravel.log` → **Esperado**: una entrada `warning` con
   `super_admin.acceso_area_tenant_bloqueado` por cada intento del paso 2, con IP y user-agent.
7. Iniciar sesión como administrador del tenant de demo en **su** dominio y recorrer clientes,
   facturas, artículos y configuración. → **Esperado**: todo funciona igual que antes (FR-005).
8. Con esa misma sesión de tenant, abrir `/super_admin/tenants` → **Esperado**: 403 (sin cambios).

## 3. Home del panel (US2) — manual

1. Cerrar sesión y volver a entrar como Super Admin. → **Esperado**: aterriza en `/super_admin`, no
   en el listado de tenants (SC-004).
2. Contrastar las 5 tarjetas contra la realidad de la base (`select count(*) from tenants`, etc.).
   → **Esperado**: coincidencia exacta (SC-005).
3. Comprobar el gráfico de altas: 12 columnas, meses sin altas visibles en cero.
4. Comprobar "Últimos tenants creados": nombre, dominio, estado y fecha; pulsar uno lleva a su
   gestión en ≤2 clics (SC-007).
5. Comprobar el ranking por tamaño contra los recuentos reales de usuarios y documentos emitidos.
6. Entrar en "Gestión de tenants" desde el menú → **Esperado**: el listado DataTable de siempre,
   intacto (FR-016).

## 4. Atención a tenants (US3) — manual

Preparar (desde el propio panel, sin tocar la base a mano cuando sea posible):

1. Desactivar un tenant → aparece en "requieren atención" con motivo "Desactivado".
2. Un tenant sin usuarios aprobados y activos → motivo "Sin usuarios que puedan entrar".
3. Un tenant sin actividad en 30 días → motivo "Sin actividad en 30 días".
4. Dejar todos los tenants en orden → el bloque muestra el estado vacío positivo, no una tabla vacía.

Al terminar, **revertir** los cambios de prueba (reactivar el tenant desactivado).

## 5. Estado vacío (FR-015)

En un entorno sin tenants (base limpia de un compañero o test), abrir `/super_admin`.
→ **Esperado**: indicadores en cero, sin gráficos rotos, con una llamada a la acción para crear el
primer tenant.

## 6. Documentación (regla transversal de CLAUDE.md)

Antes de cerrar, verificar que se actualizó:

- `docs/04-front-guidelines.md` — excepción de los widgets de resumen del panel (research D6.1).
- `docs/01-arquitectura.md` — Decisión sobre el aislamiento del panel central (amplía la Decisión 4).
- `resources/views/ayuda/super-admin-panel.blade.php` — ayuda in-app de la pantalla nueva.
- `resources/ia/conocimiento/` — verificado: la feature no cambia ninguna pantalla de tenant, así que
  no hay archivo que añadir (decisión explícita, no omisión — research D8).
