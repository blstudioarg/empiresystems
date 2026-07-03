# Phase 0 — Research: Emisión de facturas

Decisiones técnicas que resuelven los puntos abiertos del plan. Contexto: Laravel 12 + MySQL,
single-database multi-tenant, reutilizando el módulo de facturas de la feature 005.

## D1 — Fuente de verdad de la numeración y reinicio anual

**Decisión**: al emitir, el siguiente número se calcula como
`MAX(numero) + 1` sobre las facturas **emitidas** de esa `serie_id` cuyo año de `fecha_expedicion`
sea el año en curso; si no hay ninguna, el número es `1`. El cálculo se hace dentro de una
transacción tras bloquear la fila de la serie (ver D2). El contador `series.proximo_numero` se
mantiene como caché de lectura (`= numero + 1`), pero NO es la fuente de verdad.

**Rationale**:
- El reinicio por año natural (Clarifications) sale gratis filtrando por año; no hace falta un
  contador por (serie, año) ni crear filas de serie por ejercicio.
- Derivar de las facturas reales hace **imposible** el drift entre contador y realidad: como una
  factura emitida es inmutable y nunca se borra (Principio II), `MAX+1` nunca deja huecos ni
  reutiliza números.
- Menos piezas móviles que reasignar `serie_id` a una fila-año (Principio V, YAGNI).

**Alternativas consideradas**:
- *Contador `proximo_numero` por fila de serie por ejercicio* (usando el índice único
  `(tenant_id, codigo, ejercicio)` existente): obliga a crear/gestionar una fila de serie por año y
  a reasignar `serie_id` al emitir. Más complejo y con riesgo de drift si el contador se desincroniza.
- *Contador global sin reinicio*: descartado por la decisión de Clarifications (reinicio anual).

## D2 — Concurrencia: sin huecos ni duplicados

**Decisión**: la asignación de número y la emisión ocurren dentro de `DB::transaction`, y lo primero
que se hace es `Serie::whereKey($serie->id)->lockForUpdate()->first()` para serializar las emisiones
concurrentes de esa serie. Con la fila bloqueada se calcula `MAX+1` (D1), se persiste la factura
(numero, numero_completo, estado, fecha) y se actualiza la caché `proximo_numero`. La transacción
garantiza atomicidad (FR-003): o todo o nada.

**Rationale**: es el patrón que exige la constitución (Principio III: "transacción con bloqueo").
`lockForUpdate` sobre la fila de la serie es un mutex natural por serie/tenant; dos emisiones
simultáneas de la misma serie se serializan y obtienen números consecutivos. InnoDB (MySQL/MariaDB)
lo soporta en hosting compartido sin privilegios especiales (Principio V).

**Alternativas consideradas**:
- *Índice único `(tenant_id, serie_id, numero)` + reintento ante colisión*: el índice ya existe y
  actúa como red de seguridad, pero como estrategia primaria implica lógica de reintento; el lock es
  más simple y determinista. Se conserva el índice único como salvaguarda.
- *Locks aplicativos (cache/atomic)*: innecesario y menos robusto que el lock de BD transaccional.

## D3 — Congelado de datos al emitir

**Decisión**: en la emisión se fija `fecha_expedicion = hoy` (sobrescribe la del borrador) y se
recalcula `fecha_vencimiento` sobre esa fecha con los días por defecto. El resto de datos que la
normativa exige congelar (datos fiscales del cliente `cliente_nombre/nif/direccion/...`,
`regimen_impositivo`, `aplica_recargo`, importes) **ya se congelan en la creación** (feature 005:
`FacturaController::guardar` copia los datos del cliente y el régimen del tenant a la factura). La
emisión no vuelve a tocarlos.

**Rationale**: evita duplicar lógica; el modelo de 005 ya guarda copia propia de los datos del
cliente. Solo falta sellar la fecha y el estado. Fijar la fecha a hoy garantiza que el orden de
números correlativos coincide con el orden cronológico (Clarifications, FR-006).

**Alternativas consideradas**:
- *Conservar la fecha del borrador*: descartado en Clarifications (riesgo de nº mayor con fecha
  anterior).

## D4 — Validación previa a emitir

**Decisión**: antes de numerar, `EmisorFacturas` valida (y aborta con error de dominio si falla,
dejando la factura intacta en borrador):
1. `estado == borrador` (si no, rechazo / no re-emisión — FR-001, FR-010).
2. Al menos una línea con importe (`base_total > 0` o ≥1 línea válida).
3. Datos fiscales mínimos del receptor presentes en la factura congelada: `cliente_nif`,
   `cliente_nombre` (o razón social) y domicilio (`cliente_direccion`) — requisito de factura
   ordinaria completa (FR-011).

**Rationale**: la normativa (docs/02, §4) exige NIF, nombre/razón social y domicilio del receptor en
factura completa. Validar sobre los datos ya congelados en la factura (no sobre el cliente vivo)
respeta el principio de inmutabilidad de la copia.

**Alternativas consideradas**:
- *Validar contra el cliente actual*: el cliente pudo cambiar tras crear el borrador; se valida la
  copia congelada, que es lo que irá en la factura.

## D5 — Registro de eventos (append-only) y Verifactu

**Decisión**: crear tabla/modelo `factura_eventos` (según `docs/03-modelo-datos.md`): `tenant_id`,
`factura_id`, `tipo_evento`, `detalle` (json), `huella` (varchar 64, **null** en esta feature),
`ocurrido_at`. Al emitir se inserta un evento `tipo_evento = 'emitida'` con `detalle` mínimo
(numero_completo, fecha). El modelo es append-only por convención: sin `update()`/`delete()`
expuestos, sin softDeletes, sin ruta de edición.

**Rationale**: FR-013 y Principio II piden un log inalterable como base del futuro encadenamiento
Verifactu. Se crea la tabla ahora (el docs ya la define) pero **sin** calcular huella/hash ni
encadenar — eso queda para la feature de Verifactu real. `huella` nullable evita bloquear ese diseño.

**Nota de alcance**: `tipo_evento` usa el valor `'emitida'` (coherente con `EstadoFactura`). El docs
lista ejemplos (`alta`, `anulacion`, …); no es un enum cerrado (columna `varchar`), así que añadir
`emitida` no requiere migración de enum.

**Alternativas consideradas**:
- *Diferir la tabla a la feature Verifactu*: descartado; FR-013 exige registrar la emisión ahora y
  el modelo append-only debe existir desde ya para no reescribir la emisión después.

## D6 — Inmutabilidad en backend y UI

**Decisión**: el backend ya bloquea `edit/update/destroy` cuando `estado != borrador` (403). Se
añade:
- `emitir()` valida `estado == borrador` (rechazo de re-emisión, FR-010).
- Índice: el listado (`index` JSON) expone flags/URLs condicionales para que el JS muestre **Emitir**
  solo en borradores y **oculte editar/borrar** en emitidas, ofreciendo solo ver/PDF (FR-009).

**Rationale**: reutiliza los guardas existentes de 005; la inmutabilidad se refuerza en el único
punto nuevo (emitir) y en la UI. Tests cubren los tres intentos (editar/borrar/re-emitir).

**Alternativas consideradas**:
- *Policy de Laravel centralizada*: válida, pero los guardas inline de 005 ya cubren el patrón; se
  mantiene la consistencia con el código existente (YAGNI). Se puede refactor a policy más adelante.
