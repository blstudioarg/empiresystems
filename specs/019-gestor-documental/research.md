# Research — Gestor documental por tenant

Todas las decisiones abiertas del plan quedan resueltas aquí. No queda ningún NEEDS CLARIFICATION.

## D1 — Disco de almacenamiento: privado, no público

- **Decisión**: nuevo disco `documentos` (driver `local`, root `storage_path('app/tenants')`,
  `visibility` privado). Los ficheros se guardan en `tenants/{tenant_id}/documentos/{uuid}.{ext}`.
- **Rationale**: FR-019 exige que los ficheros NUNCA sean accesibles por URL pública directa. Los
  uploads actuales (logos, avatars) usan el disco `public` (symlink `storage/app/public`, servido
  por el webserver) — inaceptable aquí porque cualquiera con la URL vería el documento de un tenant.
  El disco privado vive fuera de `public/`, así que solo se sirve a través del controlador, que
  reafirma el scope de tenant antes de devolver el binario (`Storage::disk('documentos')->download`
  / `response()->file`).
- **Alternativas descartadas**: (a) disco `public` con nombres UUID "impredecibles" — sigue siendo
  URL pública, un enlace filtrado expone el fichero, no cumple FR-019. (b) S3/cloud — viola
  Principio V (hosting compartido, sin infra extra).

## D2 — Nombre físico UUID vs nombre visible

- **Decisión**: el fichero se almacena con un nombre generado (`Str::uuid()` + extensión original
  saneada); el nombre visible y el original viven en columnas de `archivos`.
- **Rationale**: FR-020 — renombrar es solo un `UPDATE` de BD, sin mover/reescribir el fichero;
  evita colisiones cuando dos archivos comparten nombre visible en la misma carpeta (permitido), y
  neutraliza caracteres problemáticos del nombre subido en el sistema de ficheros.
- **Alternativas descartadas**: guardar con el nombre original — colisiones, path traversal, y
  renombrar obligaría a mover el fichero.

## D3 — Aislamiento de tenant en descarga/preview (barrera de fichero)

- **Decisión**: las rutas de descarga y preview reciben el `id` del archivo y **resuelven el modelo
  manualmente en el cuerpo del controlador** acotado al tenant activo (`Archivo::findOrFail($id)`
  bajo el global scope de `BelongsToTenant`), **nunca** por implicit route-model binding.
- **Rationale**: memoria `project_tenant_route_binding` — el binding implícito de Laravel puede
  puentear el `TenantScope` y resolver un modelo de otro tenant. Al ser este el punto donde se
  entrega un binario, un fallo aquí es una fuga de datos directa (Principio I). El path físico
  incluye `tenant_id`, que se toma del tenant activo, no de un parámetro del request.
- **Test-first (Principio IV)**: con 2 tenants, el tenant B pide por id un archivo de A → 404 (no
  403 que confirmaría existencia); nunca recibe el binario.

## D4 — Árbol de carpetas: autorreferencia + borrado en cascada

- **Decisión**: `carpetas.parent_id` nullable (null = raíz) apuntando a `carpetas.id` del mismo
  tenant. Unicidad de `nombre` por `(tenant_id, parent_id)`. Borrar una carpeta borra en cascada
  (subcarpetas + archivos) tras confirmación reforzada (FR-018), en una transacción; cada archivo
  borrado también borra su fichero físico.
- **Rationale**: modelo mínimo para un Drive. La cascada se implementa en el servicio/controlador
  recorriendo el subárbol (no solo FK ON DELETE CASCADE, porque hay que borrar también los ficheros
  físicos y respetar soft delete). Se usa soft delete en BD; el fichero físico sí se elimina (no hay
  papelera de restauración en el MVP, así que conservar bytes no aporta y ocupa disco).
- **Mover carpetas** (revisado tras el cierre inicial del MVP): sí soportado, por drag&drop en el
  explorador (soltar sobre otra carpeta o sobre una miga de pan) o vía `PUT` con `parent_id`. Se
  valida en `UpdateCarpetaRequest` que el destino pertenezca al tenant, que no sea la propia carpeta
  y que no sea uno de sus descendientes (`Carpeta::descendientesIds()`, recorrido recursivo del
  subárbol — volumen esperado bajo, sin necesidad de nested-set/materialized-path para esto). La
  unicidad de `nombre` en el nivel destino se revalida con los valores efectivos (nombre/parent_id
  actuales si no se envían) porque un movimiento por drag&drop no siempre incluye `nombre`.
- **Alternativas descartadas**: nested-set / materialized-path — complejidad innecesaria para el
  volumen y las operaciones del MVP (YAGNI, Principio V).

## D5 — Lista blanca de tipos y límite de tamaño (configurables)

- **Decisión**: validación en servidor (Form Request) contra:
  - **Tamaño**: `configuraciones` clave `archivos.limite_mb` (default **10**, tipo integer, grupo
    `archivos`). Debe quedar ≤ `upload_max_filesize`/`post_max_size` de PHP del hosting.
  - **Tipos**: lista blanca de extensiones + MIME (FR-016b): `pdf`; `jpg/jpeg/png/webp/gif`;
    `docx/xlsx/pptx`, `odt/ods/odp`; `txt/csv`. Centralizada en `App\Support\ArchivosTenant`
    (constantes `EXTENSIONES_PERMITIDAS` / `MIMES_PERMITIDOS`), opcionalmente ajustable por tenant
    vía config `archivos.tipos_permitidos` (fuera de MVP editar por UI; el default cubre el caso).
- **Rationale**: FR-010/011/016b. Validar tipo por extensión **y** MIME real evita subir un `.exe`
  renombrado a `.pdf`. Reutiliza el patrón de `configuraciones` (3 pasos: seeder + catálogo/Support
  + input en tab) ya documentado en `docs/03-modelo-datos.md`.
- **Alternativas descartadas**: validar solo por extensión (spoofeable); permitir todo salvo
  ejecutables (mayor superficie de riesgo, la spec eligió lista blanca).

## D6 — Subida: sin colas, feedback inmediato

- **Decisión**: subida vía AJAX (multipart) desde el explorador, con drag & drop y barra de
  progreso por archivo usando `XMLHttpRequest`/`fetch` + evento `progress`. El backend guarda de
  forma síncrona dentro del request (documentos ligeros, muy por debajo del timeout). Respuesta
  JSON con el registro creado → se inserta en el listado sin recargar (SC-006).
- **Rationale**: Principio V (sin `queue:work`). Coherente con el envío síncrono ya adoptado en la
  feature 017/018. UX ágil (FR-017b) sin infraestructura extra.
- **Atomicidad**: si la validación falla o la escritura del fichero no se completa, no se crea el
  registro `archivos` (o se hace rollback + borrado del fichero a medias) → sin huérfanos (FR-010,
  edge case de subida interrumpida).

## D7 — UX / diseño de la vista (requisito de primer nivel)

- **Decisión**: antes de implementar la vista se invocan las skills `frontend-design` (dirección
  estética), `ui-ux-pro-max` (componentes/paleta concretos) y `emil-design-eng` (pulido,
  micro-interacciones). Base visual: la demo **file-manager** del banco NexaDash (`template/`),
  trasplantada al layout `default`. Interacciones cuidadas: drag&drop de subida, hover states,
  transición grid↔lista, breadcrumbs, estados vacío/carga, modal de preview.
- **Rationale**: FR-017b/SC-006 y directriz de `CLAUDE.md`. La UX es explícitamente crítica en este
  módulo.
- **Nota de implementación**: seguir `docs/04-front-guidelines.md` (previews de imagen,
  notificaciones toastr vía `window.showToast`, orden de CSS en `layouts/app.blade.php`).

## D8 — Iconos por tipo de archivo

- **Decisión**: icono por extensión (pdf, imagen, doc, hoja de cálculo, presentación, texto)
  derivado en el front a partir de `extension`; para imágenes, miniatura servida por el endpoint de
  preview. Reutilizar los assets de icono ya presentes en el template/lordicon donde encajen.
- **Rationale**: legibilidad del explorador sin dependencias nuevas.
