# Contract: Rutas HTTP de Clientes

**Feature**: 002-clientes-crm

Todas las rutas viven dentro del grupo `middleware(['auth','tenant.context'])` de `routes/web.php`,
por lo que exigen usuario autenticado con contexto de tenant inicializado. El aislamiento por
`tenant_id` es automático (scope global de `BelongsToTenant`). Definidas con
`Route::resource('clientes', ClienteController::class)->only(['index','store','update','destroy'])`.

**No hay rutas `create`/`edit`**: alta y edición usan un modal Bootstrap único sobre `index`
(ver research.md D10). Los datos del cliente a editar viajan embebidos en atributos `data-*` de su
fila en la tabla (ya renderizada completa). `store`/`update`/`destroy` se invocan **por AJAX**
desde el modal/la tabla (ver research.md D11): ninguna de las tres acciones navega ni recarga la
página; el listado se refresca reemplazando `#clientes-cards`/`#clientes-table-body` tras un `GET`
a la propia `index`.

| Método | URI | Nombre | Acción | Descripción |
|--------|-----|--------|--------|-------------|
| GET | `/clientes` | `clientes.index` | index | Pantalla principal: 3 cartas de métricas + tabla DataTables responsive + modal de alta/edición (oculto). |
| POST | `/clientes` | `clientes.store` | store | Crea el cliente (validado). Con `Accept: application/json` responde JSON 201 `{message}`; sin ese header, redirige a `index` con flash (fallback sin JS). |
| PUT/PATCH | `/clientes/{cliente}` | `clientes.update` | update | Actualiza (validado). 404 cross-tenant. Con `Accept: application/json` responde JSON 200 `{message}`; sin ese header, redirige a `index` con flash. |
| DELETE | `/clientes/{cliente}` | `clientes.destroy` | destroy | Borrado lógico (soft delete). 404 cross-tenant. Con `Accept: application/json` responde JSON 200 `{message}`; sin ese header, redirige a `index` con flash. |

## Comportamiento de aislamiento (Principio I)

- El binding de ruta `{cliente}` resuelve vía `findOrFail` bajo el `TenantScope`: un id que
  pertenece a otro tenant NO se encuentra → **HTTP 404** (nunca 403, para no revelar existencia).
- `store` NO acepta `tenant_id` del request; lo asigna `BelongsToTenant` desde el tenant activo.

## Respuestas de validación (SC-005)

- Vía AJAX (`Accept: application/json`, el camino real usado por la UI): Laravel devuelve
  automáticamente **422** con `{message, errors: {campo: [mensajes]}}` para cualquier
  `ValidationException` — no requiere código propio en el controller.
- Sin ese header (fallback sin JS): redirect back con `errors` por campo y `old()` input (patrón
  Blade estándar). En ambos casos no se crea/modifica registro.
- Casos: `nombre` faltante, `email` inválido, empresa sin `razon_social`/`nif`, NIF con formato
  inválido, NIF duplicado en el tenant, porcentajes fuera de 0–100.

## Contrato de la vista `index` (UI)

- Contenedor `#clientes-cards` con 3 cartas (`col-xl-4 col-sm-6`) mostrando `total`, `empresas`,
  `particulares` — adaptadas del bloque `depostit-card`/`same-card` de `crm.blade.php`.
- Tabla `<table id="clientes-table" class="display responsive nowrap">` con `<tbody id="clientes-table-body">`
  (filas via parcial `clientes/_row.blade.php`), columnas: Nombre / Razón social · Tipo · NIF ·
  Email · Teléfono · Ciudad · Acciones (editar, eliminar).
- Botón "Agregar cliente" → abre `#clienteModal` vacío (JS), formulario apunta a `clientes.store`.
- Botón "Editar" por fila → lleva los datos del cliente en atributos `data-*`; el JS de
  `clientes-modal.init.js` los lee, rellena `#clienteModal` y cambia el `action` del formulario a
  `clientes.update` (con `@method('PUT')`), luego abre el modal.
- `#clienteModal` incluye el parcial `_form.blade.php` (mismo markup para alta y edición), con
  contenedores `[data-error-for="<campo>"]` vacíos donde el JS inyecta errores 422 sin cerrar el modal.
- El `submit` del formulario y el `click` de "Eliminar" van por `$.ajax` (ver research.md D11); en
  éxito, cierran/mantienen la UI, muestran un alert y llaman `refreshListado()`, que reemplaza
  `#clientes-cards` y `#clientes-table-body` con el HTML fresco de un `GET` a `index` (sin navegar).
- Init DataTables en `public/js/plugins-init/clientes-datatable.init.js` (expone
  `window.initClientesDataTable()`, reutilizada tras cada refresh) con `responsive: true`,
  paginación/búsqueda/orden y `language` en español; cargado vía `@push('scripts')`.
- Estado vacío: si no hay clientes, la tabla muestra el mensaje vacío de DataTables (en español) y
  las cartas muestran `0`.
