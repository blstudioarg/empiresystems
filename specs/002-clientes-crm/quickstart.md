# Quickstart / Validación: Gestión de Clientes (CRM)

**Feature**: 002-clientes-crm

Guía para validar end-to-end que la feature funciona. Detalles de datos en [data-model.md](./data-model.md)
y de rutas en [contracts/clientes-routes.md](./contracts/clientes-routes.md).

## Prerrequisitos

- Feature 001 (auth multi-tenant) operativa: existe el seeder con tenant demo + usuario admin.
- Dependencias instaladas (`composer install`), `.env` configurado, `php artisan key:generate`.
- Assets de DataTables ya trasplantados a `public/vendor/datatables/`.

## Setup

```bash
php artisan migrate:fresh --seed     # crea tablas (incl. clientes) y datos demo
php artisan serve                    # o el entorno habitual
```

## Ejecutar los tests (Principio IV primero)

```bash
php artisan test --filter=ClienteTenantIsolationTest   # aislamiento (crítico)
php artisan test --filter=ClienteCrudTest              # CRUD + validación
php artisan test --filter=NifEspanol                   # formato NIF
php artisan test                                        # suite completa
```

**Esperado**: todos verdes. Los tests de aislamiento deben haberse escrito y fallado ANTES de
implementar el modelo/controlador (Red-Green-Refactor).

## Validación manual

1. **Login** como admin del tenant demo (`demo@empiresystems.es`).
2. **Navegar** a "Clientes" desde el sidebar → carga `/clientes`.
   - ✅ Se ven 3 cartas (total, empresas, particulares) con cifras reales.
   - ✅ La tabla lista los clientes del tenant demo; en móvil (viewport estrecho) las columnas
     colapsan (icono responsive), sin scroll horizontal.
3. **Agregar**: clic en "Agregar cliente" → se abre `#clienteModal` vacío. Completar datos válidos
   de una empresa y guardar → **por AJAX, sin recargar la página**: el modal se cierra, aparece un
   alert de éxito, la tabla y las cartas se refrescan solas.
   - ✅ Intentar guardar empresa sin NIF/razón social → **la página no recarga**; el modal permanece
     abierto con los errores inyectados junto a cada campo; no se crea el registro.
   - ✅ NIF con formato inválido (p. ej. `12345678X` con letra errónea) → error de formato inline.
   - ✅ Repetir un NIF ya existente en el tenant → error de duplicado inline.
4. **Editar**: clic en "Editar" sobre una fila → el modal se abre ya precargado con los datos de
   ese cliente (vía atributos `data-*`, sin request adicional). Modificar y guardar → **por AJAX**;
   cambios persisten y se ven reflejados sin recargar la página.
5. **Eliminar** un cliente → confirmación previa (`confirm()`); **por AJAX** desaparece del listado
   y bajan las cartas, sin recargar la página.
   - ✅ En DB el registro sigue con `deleted_at` no nulo (borrado lógico).
   - ✅ Abrir las herramientas de desarrollo (Network) durante alta/edición/borrado y confirmar que
     no hay ninguna navegación de documento completo, solo peticiones `fetch`/`XHR`.
6. **Aislamiento**: iniciar sesión con un usuario de otro tenant (o crear uno) → NO ve los clientes
   del tenant demo. Enviar por herramientas de desarrollo un `PUT`/`DELETE` directo a
   `/clientes/{id}` de un cliente ajeno → 404 (no hay ruta `edit` por URL ya que el formulario vive
   en un modal sobre `index`).

## Criterios de aceptación cubiertos

- FR-001..FR-014, SC-001..SC-006 (ver [spec.md](./spec.md)).
- Aislamiento multi-tenant verificado por test automatizado y manualmente.
