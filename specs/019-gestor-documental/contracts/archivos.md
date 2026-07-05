# Contrato — Archivos

Rutas dentro del grupo `['tenant.context', 'auth']`. Operan sobre el tenant activo. Los binarios
salen SIEMPRE por estos endpoints (disco privado), nunca por URL pública (FR-019).

## GET `/archivos` — explorador

**Nombre de ruta**: `archivos.index`

**Query params**:
| Campo | Regla |
|-------|-------|
| carpeta | nullable, integer; id de la carpeta actual (ausente = raíz) del tenant activo |
| vista | nullable, `grid`\|`lista` (default `grid`) |
| q | nullable, string; si viene (y la petición es AJAX/JSON), activa **búsqueda global** por nombre — ver más abajo. Ignora `carpeta` cuando está presente. |

**Respuesta**: vista Blade `archivos/index` con: breadcrumbs (ruta desde raíz hasta la carpeta
actual), subcarpetas y archivos del nivel actual, y el modo de vista. Carpeta de otro tenant → `404`.

### Búsqueda global (`q`)

Cuando `q` viene no vacío en una petición JSON, el endpoint busca por `nombre LIKE %q%` (sin
distinguir mayúsculas) en **archivos y carpetas de todo el tenant**, sin acotar a un nivel — no
paginado, límite de 50 resultados por tipo (Principio V, volumen esperado bajo). La respuesta trae
`buscando: true`, `termino`, `carpeta_actual: null`, `breadcrumbs: []`, y cada ítem de `carpetas`/
`data` incluye además `ruta` (breadcrumb en texto, ej. `"Proveedores / Contratos 2026"`, o `"Raíz"`)
para orientar al usuario ya que los resultados pueden vivir en cualquier nivel. Aislamiento: nunca
devuelve archivos/carpetas de otro tenant (mismo `TenantScope` que el resto del módulo).

## POST `/archivos` — subir archivo(s)

**Nombre de ruta**: `archivos.store`

**Body (multipart)**:
| Campo | Regla |
|-------|-------|
| archivo | required, file, mimes/mimetypes ∈ lista blanca (FR-016b), max ≤ `archivos.limite_mb` (config) |
| carpeta_id | nullable, integer, carpeta del tenant activo (ausente = raíz) |

**Comportamiento**: valida tipo (extensión **y** MIME real) y tamaño en servidor; guarda el fichero
en `tenants/{tenant_id}/documentos/{uuid}.{ext}` (disco privado) vía `AlmacenArchivos`; crea el
registro `archivos` con `nombre = nombre_original`, `subido_por = auth id`. Atómico: si algo falla,
sin registro ni fichero huérfano (FR-010).

**Respuestas**:
- `201`/JSON con el registro creado (para insertar en el listado sin recargar).
- `422` → tipo no permitido / supera el límite / datos inválidos (mensaje claro).

## PUT/PATCH `/archivos/{id}` — renombrar o mover

**Nombre de ruta**: `archivos.update`

**Body**:
| Campo | Regla |
|-------|-------|
| nombre | sometimes, required, string, max:255 (renombrar; contenido intacto) |
| carpeta_id | sometimes, nullable, integer, carpeta del tenant activo (mover; null = raíz) |

**Resolución**: `{id}` resuelto acotado al tenant activo. Otro tenant → `404`. Renombrar solo toca
`nombre` (no mueve el fichero, FR-013). Mover solo toca `carpeta_id` (FR-014).

**Respuestas**: `200`/JSON actualizado; `422` inválido; `404` fuera de tenant.

## DELETE `/archivos/{id}` — borrar archivo

**Nombre de ruta**: `archivos.destroy`

**Comportamiento**: soft delete del registro + borrado del fichero físico. `{id}` acotado al tenant.

**Respuestas**: `200`/redirect `success`; `404` fuera de tenant.

## GET `/archivos/{id}/descargar` — descargar

**Nombre de ruta**: `archivos.descargar`

**Comportamiento**: resuelve el modelo **manualmente acotado al tenant activo** (nunca binding
implícito, memoria `project_tenant_route_binding`); devuelve el binario del disco privado con
`Content-Disposition: attachment` y el **nombre visible** (`nombre`) + extensión. Otro tenant →
`404` (no confirmar existencia).

## GET `/archivos/{id}/preview` — previsualizar inline

**Nombre de ruta**: `archivos.preview`

**Comportamiento**: igual resolución acotada al tenant. Solo tipos con preview (pdf, imágenes):
sirve el binario con `Content-Disposition: inline` y su `mime` para embeber en `<iframe>`/`<img>`.
Tipos sin preview → `404`/redirect a descarga. Otro tenant → `404`.

## Aislamiento (obligatorio en tests, test-first — Principio IV)

- Tenant B pide `descargar`/`preview`/`update`/`destroy` de un archivo de A por id → `404`, y
  **nunca** recibe el binario ni altera datos de A.
- Subir con `carpeta_id` de otro tenant → `422`/`404`.
- El path físico servido siempre pertenece al `tenant_id` activo.
