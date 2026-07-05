# Contract: Plantillas de Email

Rutas dentro del grupo `['tenant.context', 'auth']`. CRUD estilo recurso, patrón modal como en
otros recursos del CRM (clientes, artículos). Vista `plantillas-email/index` (banco de piezas:
`email-template.blade.php`).

## `GET /plantillas-email` — index

Listado con filtro (título/estado) y tabla: título, estado (activa/inactiva), modificado,
acciones (editar / eliminar). Botón "Nueva plantilla" abre modal.

## `POST /plantillas-email` — store

Crea una plantilla.

**Body**: `titulo` (req, máx 150), `asunto` (req, máx 255), `cuerpo` (req, HTML),
`activa` (bool, default true).

**Respuesta**: redirect a `plantillas-email.index` con flash `success`.

## `PUT/PATCH /plantillas-email/{plantilla}` — update

Edita título, asunto, cuerpo, estado activa/inactiva. Mismas reglas que store.

## `DELETE /plantillas-email/{plantilla}` — destroy

Soft delete. Las campañas ya creadas conservan su copia de asunto/cuerpo (no dependen de la
plantilla viva); `campanas.plantilla_email_id` es `nullOnDelete`.

## Consumo desde campañas

`GET /campanas/crear` ofrece solo plantillas `activa = true` del tenant; seleccionar una precarga
`asunto` y `cuerpo` en el formulario (editables antes de enviar; FR-010).
