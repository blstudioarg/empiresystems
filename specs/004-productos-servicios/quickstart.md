# Quickstart: Catálogo de Productos/Servicios

## Prerrequisitos

- Migraciones aplicadas (incluye `regimen_impositivo` en `tenants` y la nueva tabla `articulos`):
  ```powershell
  php artisan migrate
  ```
- Al menos dos tenants de prueba con `regimen_impositivo` distinto (uno `iva`, uno `igic`) para
  validar el aislamiento y la resolución de tipos impositivos. Puede lograrse con el seeder de
  desarrollo (`ArticuloSeeder`, análogo a `ClienteSeeder`) o creando tenants manualmente vía tinker:
  ```powershell
  php artisan tinker
  >>> App\Models\Tenant::factory()->create(['regimen_impositivo' => 'igic']);
  ```

## Validar el flujo end-to-end

1. **Login** como usuario de un tenant con `regimen_impositivo = iva`.
2. Ir a **Productos/Servicios** desde el sidebar → debe cargar vacío la primera vez (cards en 0,
   tabla con estado vacío) — cubre US1 / SC-005.
3. **Crear un artículo** tipo `producto`, `tipo_impositivo = 21` → debe guardarse (toast de éxito)
   y aparecer en la tabla; las cards deben reflejar `total=1, productos=1, servicios=0`.
4. **Intentar crear un artículo con `tipo_impositivo = 7`** (válido solo para IGIC) en este mismo
   tenant IVA → debe rechazarse con error de validación por campo (cubre FR-008/SC-002).
5. **Repetir el punto 3 y 4 en modo inverso** con un tenant `regimen_impositivo = igic`:
   `tipo_impositivo = 7` se acepta, `tipo_impositivo = 21` se rechaza.
6. **Editar** el artículo creado (cambiar precio/nombre) → cambios persisten.
7. **Eliminar** el artículo → desaparece de la tabla, las cards bajan a 0; verificar en BD
   (`php artisan tinker` → `Articulo::withTrashed()->find($id)`) que el registro sigue existiendo
   con `deleted_at` no nulo.
8. **Aislamiento**: repetir el punto 3 con un segundo tenant y confirmar que, al volver al primer
   tenant, no aparece el artículo del segundo (cubre Principio I / SC-001).

## Tests automatizados de referencia

- `tests/Feature/ArticuloCrudTest.php` — CRUD feliz + validaciones (precio negativo, campos
  obligatorios, stock solo en producto).
- `tests/Feature/ArticuloTenantIsolationTest.php` — aislamiento entre ≥2 tenants (igual que
  `ClienteTenantIsolationTest`).
- Caso específico de régimen fiscal: incluir en `ArticuloCrudTest` (o un test dedicado) escenarios
  con tenant `iva` rechazando un tipo de `igic` y viceversa, y un tenant `ipsi` aceptando cualquier
  porcentaje 0–100.

Ejecutar:
```powershell
php artisan test --filter=Articulo
```
