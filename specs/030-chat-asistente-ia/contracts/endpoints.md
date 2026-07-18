# Contracts — Endpoints del asistente (030)

Todas las rutas: middleware `web` + `auth` + contexto de tenant (`SetTenantContext`). Nunca disponibles en dominio central/superadmin.

## POST `/asistente/mensaje`

Envía un mensaje del usuario y devuelve la respuesta por streaming.

**Request** (JSON): `{ "mensaje": "string, requerido, max 4000 chars" }`

**Precondiciones**: tenant con `ia.api_key` configurada → si no, `409` `{ "error": "no_configurado" }`.

**Response**: `200`, `Content-Type: text/event-stream`. Eventos (formato SSE `event:`/`data:` JSON):

| event | data | Significado |
|---|---|---|
| `texto` | `{ "delta": "..." }` | Fragmento incremental de la respuesta |
| `actividad` | `{ "tool": "buscar_clientes" }` | El asistente está ejecutando una tool de lectura |
| `accion_pendiente` | `{ "id": "uuid", "resumen": "...", "tool": "crear_cliente" }` | Propuesta de escritura: el widget muestra Confirmar/Cancelar |
| `error` | `{ "codigo": "clave_invalida\|servicio_no_disponible\|limite_excedido\|interno", "mensaje": "amigable", "detalle": "solo si can(ver-configuracion)" }` | Error traducido (FR-011) |
| `fin` | `{}` | Cierre normal del stream |

Efectos: agrega el turno a la sesión; enviar un mensaje nuevo descarta cualquier `accion_pendiente` previa.

## POST `/asistente/accion/{id}/confirmar`

Ejecuta la acción pendiente. **Única vía de escritura del asistente.**

**Response** `200`: `{ "ok": true, "mensaje": "Cliente creado", "url": "/clientes/123" }` (url opcional al recurso creado/editado).

**Errores**: `404` id no coincide con la pendiente en sesión (o expirada); `403` el usuario ya no tiene el permiso requerido; `422` la validación de negocio falla al ejecutar (p. ej. la factura ya no está en borrador, NIF duplicado) → `{ "ok": false, "mensaje": "..." }`.

Efectos: ejecuta vía servicios existentes, registra en `logs_actividad` (patrón 021), limpia la pendiente y añade a la conversación un turno informando el resultado (para que el modelo tenga el contexto).

## POST `/asistente/accion/{id}/cancelar`

**Response** `200`: `{ "ok": true }`. Limpia la pendiente sin ejecutar nada; se informa al modelo en el siguiente turno.

## POST `/asistente/reiniciar`

Conversación nueva. **Response** `200`: `{ "ok": true }`. Limpia mensajes y pendiente de la sesión.

## Configuración (rutas existentes de `ConfiguracionController`, permiso `ver-configuracion`)

- `PUT /configuracion/ia` — `{ "api_key": "sk-ant-..." }` → guarda cifrada; flash `success`. `{ "api_key": null }` o vacío → quita la clave.
- `POST /configuracion/ia/probar` — llamada mínima a OpenAI con la clave guardada → `{ "ok": bool, "mensaje": "..." }` (espejo del email de prueba de la feature 017).
- La vista muestra la clave solo enmascarada (`IaTenant::apiKeyEnmascarada()`); el valor completo jamás vuelve al front.

## Contrato de seguridad (invariantes verificables por test)

1. Toda tool ejecuta bajo el `TenantScope` del tenant del dominio; ninguna acepta `tenant_id` como parámetro.
2. El servidor solo ejecuta tools presentes en `CatalogoTools::paraUsuario($user)` (permiso re-verificado al ejecutar y al confirmar).
3. No existe ninguna ruta ni tool que emita facturas, registre pagos, elimine registros o modifique configuración/usuarios/roles en nombre del asistente.
4. Ninguna escritura ocurre fuera de `POST /asistente/accion/{id}/confirmar`.
5. Los importes de facturas/presupuestos creados provienen de los servicios de cálculo del servidor, nunca de parámetros del modelo.
