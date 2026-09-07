# Contratos HTTP: Historial de conversaciones del asistente IA

**Feature**: 045-historial-conversaciones-asistente

Todos bajo el middleware del área de tenant, autenticados, con CSRF, y devuelven JSON salvo el de
mensaje (SSE). Ninguno es accesible para super admin (el widget no se emite en su layout).

Regla transversal: **toda conversación referenciada por id se resuelve acotada al tenant activo y al
usuario autenticado**. Un id de otro tenant o de otro usuario responde `404`, nunca `403`, para no
revelar su existencia (FR-002, FR-003).

---

## `GET /asistente/conversaciones`

Lista las conversaciones del usuario autenticado (FR-005).

**Respuesta 200**

```json
{
  "conversaciones": [
    {
      "id": 128,
      "titulo": "Cuántas facturas emití este mes",
      "ultima_actividad_en": "2026-09-07T10:12:00+02:00",
      "activa": true
    }
  ]
}
```

- Orden: `ultima_actividad_en` descendente.
- Solo conversaciones con al menos un mensaje del usuario (FR-008).
- `activa` marca la que el panel tiene abierta.
- Tope de 50 elementos; el historial no pagina en esta feature (con retención de 90 días el volumen
  esperado por usuario está muy por debajo).

---

## `POST /asistente/conversaciones`

Crea una conversación vacía y la deja activa (FR-007). Sustituye a `POST /asistente/reiniciar`.

**Respuesta 200**

```json
{ "ok": true, "conversacion_id": null }
```

`conversacion_id` es `null` a propósito: la fila no se crea hasta el primer mensaje del usuario
(FR-008). El panel queda en un hilo vacío y la sesión olvida la conversación activa.

**Efecto secundario obligatorio**: descarta cualquier acción pendiente (FR-023).

---

## `GET /asistente/conversaciones/{id}`

Abre una conversación anterior y la deja activa (FR-006).

**Respuesta 200**

```json
{
  "id": 128,
  "titulo": "Cuántas facturas emití este mes",
  "resumida": true,
  "mensajes": [
    { "rol": "user", "contenido": "¿Cuántas facturas emití este mes?" },
    { "rol": "actividad", "contenido": "buscar_facturas" },
    { "rol": "assistant", "contenido": "Este mes emitiste 14 facturas." }
  ]
}
```

- `resumida` a `true` indica que hay una parte anterior compactada; el panel muestra el separador
  que exige FR-012.
- Los mensajes de rol `tool` **no** se devuelven crudos: se traducen al pseudo-rol `actividad` con
  el nombre de la herramienta, que es lo que el panel ya sabe pintar
  (`.asistente-msg--actividad`). El contenido del resultado de la herramienta no viaja al cliente.
- El `resumen` en sí **no** se envía: es contexto para el modelo, no contenido para leer.

**Respuesta 404**: la conversación no existe, es de otro usuario o de otro tenant.

**Efecto secundario obligatorio**: descarta cualquier acción pendiente que perteneciera a otra
conversación (FR-023).

---

## `DELETE /asistente/conversaciones/{id}`

Borra una conversación propia y sus mensajes, de forma definitiva (FR-020, FR-021).

**Respuesta 200**

```json
{ "ok": true, "mensaje": "Conversación eliminada." }
```

**Respuesta 404**: no existe, o no pertenece a quien lo pide.

Si la conversación borrada era la activa, la sesión queda sin conversación activa y el panel muestra
un hilo nuevo y vacío.

---

## `POST /asistente/mensaje` *(modificado)*

Sigue devolviendo SSE con los mismos eventos (`texto`, `actividad`, `accion_pendiente`, `error`,
`fin`). Cambios:

**Petición**

```json
{ "mensaje": "¿Cuántas facturas emití este mes?" }
```

La conversación destino es la activa en sesión. Si no hay ninguna, se crea al vuelo con este
mensaje como primero, y su título sale de él (research D7).

**Eventos nuevos**

| Evento | Cuándo | Payload |
|---|---|---|
| `compactando` | antes de empezar a responder, si el hilo cruzó el umbral | `{}` |
| `conversacion` | tras crear una conversación nueva al vuelo | `{ "id": 131, "titulo": "..." }` |

`compactando` es lo que alimenta el indicador de progreso existente para cumplir FR-011. El panel lo
traduce a un texto del tipo "Compactando la conversación…" en `.asistente-chat__estado`.

`conversacion` permite al panel conocer el id sin recargar el historial.

**Comportamiento ante fallo de compactación** (FR-013): no se emite `error`. Se recurre al recorte
simple, se sigue respondiendo con normalidad y el fallo se reporta por `report()` en servidor. El
usuario no ve una conversación rota por algo que el sistema puede resolver solo.

---

## Notas de implementación que el contrato impone

- El guardado explícito de sesión al cerrar el stream (`docs/01-arquitectura.md`, Decisión 9) sigue
  siendo obligatorio: la conversación activa vive en sesión y se escribe dentro de la closure.
- El widget solo se emite para usuarios no super admin y con el asistente visible; estos endpoints
  deben validar lo mismo en servidor, no confiar en que la UI no exista.
