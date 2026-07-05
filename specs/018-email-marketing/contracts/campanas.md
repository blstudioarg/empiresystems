# Contract: Campañas

Todas las rutas dentro del grupo `['tenant.context', 'auth']` de `routes/web.php`. Resuelven
modelos por el scope del tenant activo (ver pitfall de binding: resolver en el cuerpo del
controlador, no por implicit binding que salta `TenantScope`).

## `GET /campanas` — index

Listado de campañas del tenant (asunto, estado, total/enviados/fallidos, fecha). Vista
`campanas/index`.

## `GET /campanas/crear` — create

Formulario de nueva campaña. Vista `campanas/create`: selector de plantilla activa (precarga
asunto/cuerpo), editor de texto enriquecido, selección múltiple de clientes, barra de progreso.

## `POST /campanas` — store

Crea la campaña en estado `borrador` y materializa sus `campana_destinatarios`.

**Body**: `asunto` (req), `cuerpo` (req, HTML), `plantilla_email_id` (opcional),
`cliente_ids[]` (req, ≥1).

**Reglas**: dedup de `cliente_ids`; cada cliente → fila destinatario (`pendiente`, o `fallido`
"sin email" si no tiene email); valida que el tenant tenga SMTP configurado.

**Respuesta**: redirect a `campanas.show` (o JSON `{ campana_id, destinatarios: [{id, cliente_id, email, estado}] }`
si la creación y el arranque de envío se hacen en la misma pantalla vía AJAX).

## `POST /campanas/{campana}/enviar-tanda` — enviarTanda (AJAX)

Envía una tanda de destinatarios. Llamado repetidamente por el JS hasta agotar pendientes.

**Body**: `destinatario_ids[]` (IDs de `campana_destinatarios` de esta campaña, máx tamaño de
tanda = 8).

**Comportamiento**:
- Solo procesa filas de la campaña en estado `pendiente` (ignora las ya `enviado`; evita
  duplicados). Cada envío en try/catch individual: ok → `enviado` + `enviado_at`; excepción →
  `fallido` + `error`. Cliente sin email → `fallido` "sin email" sin intentar SMTP.
- Recalcula contadores cache y estado de la campaña (`en_curso`/`finalizada`).
- Si el tenant no tiene SMTP → HTTP 422 con mensaje (no procesa la tanda).

**Respuesta JSON**:
```json
{
  "resultados": [
    { "id": 12, "cliente_id": 3, "estado": "enviado", "error": null },
    { "id": 13, "cliente_id": 4, "estado": "fallido", "error": "Mailbox unavailable" }
  ],
  "campana": { "estado": "en_curso", "enviados": 6, "fallidos": 2, "total": 20 }
}
```

## `GET /campanas/{campana}` — show

Detalle: cabecera + tabla de destinatarios con su estado/motivo. Botón "reintentar fallidos" si
hay ≥1 `fallido`. Vista `campanas/show`.

## `POST /campanas/{campana}/reintentar` — reintentar (AJAX)

Repone a `pendiente` las filas `fallido` (que tengan email) de la campaña y devuelve sus IDs para
que el JS los reenvíe con el mismo mecanismo de tandas. No toca las `enviado` (FR-012/SC-004).

**Respuesta JSON**: `{ "destinatario_ids": [13, 27, 41] }`
