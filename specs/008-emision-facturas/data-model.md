# Phase 1 — Data Model: Emisión de facturas

Fuente de verdad: `docs/03-modelo-datos.md`. Esta feature **crea una tabla** (`factura_eventos`) y
**usa columnas ya existentes** de `facturas`/`series` (creadas en 005). Convenciones del proyecto:
`id` bigint, `timestamps`, importes `DECIMAL(12,2)`, `tenant_id` indexado + `BelongsToTenant`.

## Tabla nueva

### `factura_eventos`
Log **append-only** de operaciones sobre la factura (base del futuro encadenamiento Verifactu).
No se edita ni se borra ninguna fila (sin `softDeletes`, sin update/delete expuestos).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk, index | `BelongsToTenant` |
| factura_id | fk → facturas, index | evento asociado a una factura (nullable para eventos de sistema futuros) |
| tipo_evento | varchar(30) | en esta feature: `emitida` |
| detalle | json, nullable | payload mínimo (`numero_completo`, `fecha_expedicion`) |
| huella | varchar(64), nullable | **null** en esta feature (reservado Verifactu) |
| ocurrido_at | datetime | momento del acto |
| timestamps | | |

Índices: `(tenant_id, factura_id)`, `(tenant_id, tipo_evento)`.

**Invariante append-only**: el modelo `FacturaEvento` no expone actualización ni borrado; los tests
verifican que emitir crea exactamente 1 evento `emitida` por factura.

## Columnas existentes que pasan a poblarse (tabla `facturas`, creada en 005)

| Campo | Antes (borrador) | Después (emitida) |
|-------|------------------|-------------------|
| estado | `borrador` | `emitida` |
| numero | NULL | entero correlativo (≥1) del año en curso |
| numero_completo | NULL (se muestra "Borrador") | string formateado según `series.formato` |
| fecha_expedicion | fecha del borrador | **hoy** (congelada) |
| fecha_vencimiento | derivada de la del borrador | recalculada sobre hoy + días por defecto |
| regimen_impositivo / cliente_* / importes | ya congelados al crear (005) | sin cambios (no se recalculan) |
| Verifactu (huella, qr_contenido, …) | NULL | NULL (siguen reservados) |

Sin cambios de esquema en `facturas` (todas las columnas ya existen desde 005).

## Tabla `series` (existente) — uso en esta feature

- `proximo_numero`: pasa a ser **caché de lectura** del siguiente número del año en curso
  (`= numero + 1` tras emitir). La **fuente de verdad** de la numeración es `MAX(facturas.numero)+1`
  para `(serie_id, año de fecha_expedicion)` (ver research.md › D1).
- `formato`: plantilla del `numero_completo` (`{serie}-{anio}-{numero:0000}`); `{anio}` se resuelve
  con el año de la fecha de expedición (hoy).
- Sin cambios de esquema.

## Reglas de estado / ciclo de vida (esta feature)

```
borrador ──(emitir: validar → numerar → congelar fecha → estado=emitida → evento)──▶ emitida
```

- **borrador**: editable y borrable (guardas de 005 intactos).
- **emitida**: INMUTABLE. No editar (403), no borrar (403), no re-emitir (rechazo de dominio). Solo
  ver/PDF.
- Estados posteriores (`pagada`, `anulada`, `rectificada`, `vencida`) siguen fuera de alcance.

## Validaciones previas a emitir (en `EmisorFacturas`)

1. `estado == borrador` — si no, se rechaza (FR-001, FR-010).
2. Al menos una línea con importe / `base_total > 0` (FR-011).
3. Datos fiscales mínimos del receptor congelados en la factura: `cliente_nif`, `cliente_nombre`
   (o razón social) y `cliente_direccion` presentes (FR-011).

Si alguna falla: no se numera, no se cambia estado, no se crea evento; la factura sigue en borrador.

## Numeración (servicio `NumeradorFacturas` — reescrito)

Entrada: la `Serie` y el año objetivo (año de la fecha de expedición = hoy).
Proceso (dentro de `DB::transaction`):
1. `lockForUpdate` sobre la fila de la serie (mutex por serie).
2. `numero = (MAX(numero) de facturas emitidas de esa serie en ese año) + 1`, o `1` si no hay.
3. `numero_completo = formatear(serie.formato, codigo, año, numero)`.
4. Actualizar `series.proximo_numero = numero + 1` (caché).

Salida: `{ numero, numeroCompleto }`. Garantiza correlativo sin huecos/duplicados, reinicio anual y
seguridad ante concurrencia (research.md › D1, D2).

## Entidades reutilizadas

- **facturas / factura_lineas / factura_impuestos** (005): la cabecera transita a emitida; líneas e
  impuestos no se tocan (importes inmutados).
- **series** (005): serie ordinaria por defecto del tenant.
- **clientes / tenants** (002/004): sus datos ya están congelados en la factura desde la creación.
