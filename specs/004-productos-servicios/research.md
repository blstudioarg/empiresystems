# Research: Catálogo de Productos/Servicios

## 1. ¿Dónde vive el catálogo de tipos impositivos por régimen?

**Decision**: catálogo fijo en código (`App\Support\TiposImpositivos`), no una tabla de base de
datos.

**Rationale**: los tipos de IVA (21/10/4/0/exento) e IGIC (0/3/7/9,5/15/20) son valores normativos
estables, ya documentados como lista cerrada en `docs/02-facturacion-espana.md`. Crear una tabla
`tipos_impositivos` para dos listas de ≤6 valores fijos es complejidad no justificada por el
alcance del MVP (Principio V, YAGNI). IPSI no tiene una lista única nacional (cada ciudad autónoma
fija los suyos), así que para régimen `ipsi` no hay catálogo cerrado: se permite introducir el
porcentaje manualmente, validado solo por rango (0–100), tal como ya se resuelve
`tipo_impositivo_defecto` en `clientes` hoy.

**Alternatives considered**:
- Tabla `tipos_impositivos` (regimen, porcentaje, etiqueta) editable desde UI — rechazada por
  sobre-ingeniería: no hay caso de uso que requiera que un tenant cree tipos impositivos propios
  dentro de IVA/IGIC, y añadiría una pantalla de administración más para un catálogo que no
  cambia salvo por ley.
- Validar el tipo impositivo solo en frontend (selector) sin espejo en backend — rechazada porque
  viola el patrón de validación server-side ya establecido (`StoreClienteRequest`) y permitiría
  bypass vía API/curl.

## 2. ¿Cómo resolver el régimen del tenant si `regimen_impositivo` aún no existe en la tabla?

**Decision**: añadir la columna `regimen_impositivo` (string/enum, default `iva`) a `tenants` como
migración de esta feature, reutilizando el nombre y los valores ya documentados en
`docs/03-modelo-datos.md` (`iva`, `igic`, `ipsi`).

**Rationale**: la feature 001-user-auth implementó solo un subconjunto de columnas fiscales del
tenant (`nombre_comercial`, `razon_social`, `nif`, `email`, `activo`, más logos añadidos en 003).
`regimen_impositivo` es prerrequisito directo de esta feature (FR-008) y ya está especificado en
el modelo de datos central, así que se añade aquí en vez de bloquear la feature en un cambio
externo. Default `iva` es seguro porque la mayoría de tenants existentes están en
Península/Baleares (Assumptions de la spec).

**Alternatives considered**:
- Bloquear la feature hasta que otra feature añada la columna — rechazada, ralentiza sin
  necesidad; el dato es de bajo riesgo (una columna con default seguro) y ya está en el modelo de
  datos aprobado.
- Inferir el régimen de la provincia del tenant en vez de un campo explícito — rechazada, añade
  lógica de mapeo código-postal→régimen no solicitada ni documentada; el modelo de datos ya define
  un campo explícito para esto.

## 3. Patrón de resolución de binding tenant-scoped en rutas

**Decision**: igual que `ClienteController` (ver `[[project_tenant_route_binding]]` en memoria del
proyecto) — no usar binding implícito de ruta (`Route::resource` con type-hint del modelo); resolver
`Articulo::findOrFail($id)` manualmente dentro del método del controller.

**Rationale**: ya documentado como pitfall conocido del proyecto: `SubstituteBindings` puede
ejecutarse antes que el middleware `tenant.context`, permitiendo resolver artículos de otro tenant
antes de que el `TenantScope` esté activo. `ClienteController::update`/`destroy` ya resuelven esto
recibiendo `string $cliente` y llamando `Cliente::findOrFail($cliente)` dentro del método. Se
replica el mismo patrón en `ArticuloController`.

**Alternatives considered**: ninguna — es una decisión ya validada y en producción en esta misma
base de código; no hay motivo para reabrir la discusión.

## 4. Gestión de stock (producto vs. servicio)

**Decision**: `gestion_stock` (boolean) + `stock_actual`/`stock_minimo` (nullable) viven en la
misma tabla `articulos`, tal como especifica `docs/03-modelo-datos.md`. Validación: si
`tipo = servicio`, estos tres campos se ignoran/fuerzan a null en backend aunque lleguen en el
request; si `tipo = producto` y `gestion_stock = true`, `stock_actual` es obligatorio.

**Rationale**: coincide con el modelo de datos documentado y con el edge case de la spec
("un servicio nunca gestiona stock"). No se crea una tabla separada para stock en esta feature —
`movimientos_stock` (kardex) queda fuera de alcance (ver Assumptions de la spec).

**Alternatives considered**: tabla `productos` separada de `articulos`/`servicios` — rechazada,
contradice el modelo de datos ya documentado (catálogo unificado) y duplicaría columnas comunes
(nombre, precio, tipo_impositivo).
