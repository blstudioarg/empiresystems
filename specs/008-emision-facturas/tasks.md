# Tasks: Emisión de facturas (borrador → emitida)

**Input**: Design documents from `/specs/008-emision-facturas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/emision.md

**Tests**: Incluidos y OBLIGATORIOS (Principio IV de la constitución: numeración, inmutabilidad y
aislamiento se cubren con tests escritos primero, deben fallar antes de implementar).

**Organization**: Tareas agrupadas por user story (US1 emitir/numerar, US2 inmutabilidad, US3
visibilidad/evento) para poder implementar y validar cada una de forma independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: US1 / US2 / US3
- Rutas de archivo exactas en cada descripción

## Path Conventions

Monolito Laravel existente: `app/`, `database/`, `resources/views/`, `routes/`, `tests/` en la raíz
del repo (ver plan.md § Project Structure).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Crear la tabla append-only que necesitan todas las historias (US1 la puebla al
emitir, US3 la expone).

- [X] T001 Crear migración `database/migrations/2026_07_03_180000_create_factura_eventos_table.php`
      con columnas `tenant_id` (index), `factura_id` (fk → facturas, index), `tipo_evento`
      varchar(30), `detalle` json nullable, `huella` varchar(64) nullable, `ocurrido_at` datetime,
      timestamps; índices `(tenant_id, factura_id)` y `(tenant_id, tipo_evento)` (ver data-model.md
      § Tabla nueva).
- [X] T002 [P] Crear modelo `app/Models/FacturaEvento.php` con `BelongsToTenant`, `HasFactory`,
      `$fillable = [tenant_id, factura_id, tipo_evento, detalle, huella, ocurrido_at]`, casts
      (`detalle` => array, `ocurrido_at` => datetime), relación `factura(): BelongsTo` — **sin**
      exponer update/delete (append-only, sin `SoftDeletes`).
- [X] T003 [P] Crear factory `database/factories/FacturaEventoFactory.php` (para tests) con
      `tenant_id`, `factura_id` (Factura::factory()), `tipo_evento = 'emitida'`, `detalle` json
      mínimo, `ocurrido_at = now()`.
- [X] T004 [P] Añadir relación `eventos(): HasMany` a `app/Models/Factura.php` (→ `FacturaEvento`,
      `orderBy('ocurrido_at')`).

**Checkpoint**: `php artisan migrate` crea `factura_eventos`; modelo y factory listos para tests.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Reescribir el numerador (fuente de verdad D1/D2) y crear el servicio orquestador antes
de que ninguna historia pueda emitir nada. Bloquea todas las user stories.

⚠️ **CRÍTICO**: sin esta fase no hay `emitir()` posible.

- [X] T005 Reescribir `app/Services/NumeradorFacturas.php`: método
      `siguienteNumero(Serie $serie, \DateTimeInterface $fecha): array` que dentro de
      `DB::transaction` hace `Serie::whereKey($serie->id)->lockForUpdate()->first()`, calcula
      `numero = (MAX(numero) de Factura::where('serie_id', $serie->id)->whereYear('fecha_expedicion', $fecha->year)) + 1`
      (o `1` si no hay ninguna), formatea `numero_completo` con `{serie}`, `{anio}` = año de
      `$fecha`, `{numero:0000}`, actualiza `series.proximo_numero = numero + 1` como caché, y
      devuelve `['numero' => ..., 'numeroCompleto' => ...]` (research.md § D1, D2).
- [X] T006 Crear enum/excepción de dominio `app/Exceptions/FacturaNoEmitibleException.php`
      (extiende `\DomainException`, mensaje libre) para que `EmisorFacturas` señale rechazos de
      validación previa sin usar `abort()` directamente (permite capturarla igual en web/JSON).
- [X] T007 Crear `app/Services/EmisorFacturas.php` con método `emitir(Factura $factura): Factura`
      que, dentro de `DB::transaction`:
      1. valida `estado == borrador` (si no, lanza `FacturaNoEmitibleException` — FR-001/FR-010),
      2. valida al menos una línea con importe / `base_total > 0` (FR-011),
      3. valida `cliente_nif`, `cliente_nombre` (o `cliente_razon_social`) y `cliente_direccion`
         presentes en la factura (FR-011),
      4. obtiene la serie de la factura, llama a `NumeradorFacturas::siguienteNumero($serie, hoy)`,
      5. fija `fecha_expedicion = hoy`, recalcula `fecha_vencimiento` (mismos días por defecto que
         al crear — reutilizar lógica de `FacturaController::calcularVencimiento` o extraerla a
         config/servicio compartido),
      6. actualiza `estado = Emitida`, `numero`, `numero_completo`, guarda,
      7. crea `FacturaEvento` (`tipo_evento = 'emitida'`, `detalle = ['numero_completo' => ..., 'fecha_expedicion' => ...]`, `ocurrido_at = now()`),
      8. devuelve la factura recargada (depende de T005, T002, T006).

**Checkpoint**: `EmisorFacturas::emitir()` es invocable y testeable de forma aislada; ninguna ruta
la expone todavía.

---

## Phase 3: User Story 1 - Emitir una factura en borrador (Priority: P1) 🎯 MVP

**Goal**: Un borrador válido, al emitir, recibe número correlativo (reinicio anual, sin huecos ni
duplicados bajo concurrencia), fecha de expedición congelada a hoy, y pasa a `emitida` sin alterar
importes.

**Independent Test**: crear un borrador válido, invocar la acción de emitir, verificar numero/
numero_completo/estado/fecha/importes según acceptance scenarios de US1.

### Tests for User Story 1 ⚠️

> Escribir primero, deben FALLAR antes de implementar T012-T015.

- [X] T008 [P] [US1] Test `tests/Feature/FacturaNumeracionTest.php`: correlativo consecutivo sin
      huecos al emitir 2 borradores de la misma serie (0001, 0002); reinicio a 1 con una factura
      emitida el año anterior en la serie; concurrencia (dos emisiones simultáneas de la misma
      serie mediante transacciones anidadas/paralelas no producen número repetido ni hueco).
- [X] T009 [P] [US1] Test `tests/Feature/FacturaEmisionTest.php`: `POST /facturas/{factura}/emitir`
      sobre borrador válido → `estado = emitida`, `numero`/`numero_completo` asignados,
      `fecha_expedicion = hoy`, totales idénticos a los del borrador (acceptance scenarios 1, 3, 4);
      rechazo (422 y sin cambios) si faltan líneas/importe o datos fiscales del receptor (FR-011,
      edge case "Borrador incompleto").
- [X] T010 [P] [US1] Test `tests/Feature/FacturaEmisionTenantIsolationTest.php`: emitir en tenant A
      no altera `proximo_numero`/numeración del tenant B; una factura de otro tenant en
      `POST /facturas/{id}/emitir` responde 404 (SC-005, edge case aislamiento).

### Implementation for User Story 1

- [X] T011 [US1] Añadir ruta `POST /facturas/{factura}/emitir` → `facturas.emitir` en `routes/web.php`
      (grupo `tenant.context`+`auth`, junto a las demás rutas de `facturas.*`) — depende de T012.
- [X] T012 [US1] Añadir acción `emitir(Request $request, string $factura)` en
      `app/Http/Controllers/FacturaController.php`: resuelve la factura por tenant (`findOrFail`),
      inyecta `EmisorFacturas` (constructor), captura `FacturaNoEmitibleException` →
      302 back + flash `error` (web) / 422 `{message}` (JSON); éxito → 302 `facturas.index` + flash
      `success` (web) / 200 `{message, numero_completo}` (JSON) (contracts/emision.md).
- [X] T013 [US1] Extraer `diasVencimientoDefecto()`/`calcularVencimiento()` de
      `FacturaController` a un lugar reutilizable por `EmisorFacturas` (p. ej. método público en
      `Configuracion` o pequeño helper compartido) para no duplicar la lectura de
      `factura.dias_vencimiento` — depende de T007.
- [X] T014 [US1] Reforzar índice único existente `(tenant_id, serie_id, numero)` en `facturas` como
      salvaguarda: confirmar que ya existe en la migración 005 (`2026_07_03_130001_...`) — sin
      migración nueva, solo verificar en el test de concurrencia (T008) que una colisión real
      lanzaría error de integridad si el lock fallara.

**Checkpoint**: US1 funcional de punta a punta — emitir un borrador válido asigna número correcto,
congela fecha e importes, bajo tenant y concurrencia seguros.

---

## Phase 4: User Story 2 - Una factura emitida es inmutable (Priority: P2)

**Goal**: ninguna factura `emitida` puede editarse, borrarse ni re-emitirse; la UI no ofrece esas
acciones sobre ella.

**Independent Test**: sobre una factura ya emitida, intentar editar/actualizar/borrar/re-emitir por
petición directa y por UI, y verificar rechazo total sin cambios.

### Tests for User Story 2 ⚠️

- [X] T015 [P] [US2] Test `tests/Feature/FacturaInmutabilidadTest.php`: sobre factura `emitida`,
      `GET /facturas/{id}/editar` → 403, `PUT/PATCH /facturas/{id}` → 403 (factura sin cambios),
      `DELETE /facturas/{id}` → 403 (factura persiste), `POST /facturas/{id}/emitir` → 422 sin
      consumir número nuevo (acceptance scenarios 1-3 de US2).

### Implementation for User Story 2

- [X] T016 [US2] Confirmar/ajustar los guardas ya existentes en `edit`/`update`/`destroy` de
      `app/Http/Controllers/FacturaController.php` (`abort(403, ...)` si `estado != Borrador`) —
      ya están en el código; solo tocar si T015 detecta un hueco (p. ej. mensaje o condición).
- [X] T017 [US2] Confirmar en `EmisorFacturas::emitir()` (T007) que re-emitir una factura no
      `borrador` lanza `FacturaNoEmitibleException` y no llama a `NumeradorFacturas` (sin
      consumir número) — cubierto por diseño de T007, validar con T015.

**Checkpoint**: las tres vías de mutación (editar, borrar, re-emitir) están bloqueadas para
facturas emitidas; US1 sigue funcionando para borradores.

---

## Phase 5: User Story 3 - El número fiscal es visible y la emisión queda registrada (Priority: P3)

**Goal**: listado/detalle/PDF muestran `numero_completo` (o "Borrador"); UI ofrece Emitir solo en
borradores y oculta editar/eliminar en emitidas; existe evento `emitida` append-only.

**Independent Test**: emitir una factura, verificar número visible en listado/PDF y exactamente 1
evento `emitida` registrado con su fecha.

### Tests for User Story 3 ⚠️

- [X] T018 [P] [US3] Test en `tests/Feature/FacturaEmisionTest.php` (ampliar): tras emitir, el JSON
      de `facturas.index` devuelve `identificador = numero_completo`, `estado = emitida`,
      `es_borrador = false`, `emitir_url = null`, `edit_url = null`, `delete_url = null`,
      `pdf_url` presente; para un borrador, `es_borrador = true` y los 3 URLs (emitir/edit/delete)
      presentes (contracts/emision.md § Cambios en `facturas.index`).
- [X] T019 [P] [US3] Test en `tests/Feature/FacturaEmisionTest.php` (ampliar) o nuevo caso: tras
      emitir, existe exactamente 1 `FacturaEvento` con `tipo_evento = 'emitida'` para la factura,
      con `ocurrido_at` no nulo (FR-013, SC-006); el PDF (`facturas.pdf`) usa `numero_completo` en
      el nombre de archivo para emitidas (ya soportado, verificar que no regresiona).

### Implementation for User Story 3

- [X] T020 [US3] Actualizar el bloque JSON de `FacturaController::index()` en
      `app/Http/Controllers/FacturaController.php` para añadir `es_borrador`, `emitir_url`
      (condicional a borrador, `route('facturas.emitir', $factura)`), y anular `edit_url`/
      `delete_url` cuando `!es_borrador` (contracts/emision.md).
- [X] T021 [US3] Actualizar `public/js/plugins-init/facturas-datatable.init.js`
      (`renderAcciones`): mostrar **Emitir** solo si `row.es_borrador`, ocultar **Editar**/
      **Eliminar** si `!row.es_borrador`, mantener siempre **Ver/PDF`; añadir manejador
      `.btn-emitir-factura` que haga `POST` a `emitir_url` (con confirmación vía
      `window.confirmDelete`-like o `window.showToast` tras éxito) y recargue la tabla
      (`table.ajax.reload()`), igual patrón que `.btn-delete-factura`.
- [X] T022 [US3] Revisar `resources/views/facturas/pdf.blade.php` y cualquier vista de detalle:
      confirmar que ya muestran `numero_completo ?? 'Borrador'` (según plan.md, ya lo hacen) — solo
      tocar si T018/T019 detectan un caso sin cubrir.

**Checkpoint**: todas las user stories completas — numeración, inmutabilidad y visibilidad/trazabilidad
funcionan de punta a punta.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: validación final end-to-end y housekeeping.

- [X] T023 [P] Ejecutar `php artisan test --filter=Factura` completo y confirmar que los 4 archivos
      de test (Emision, Numeracion, Inmutabilidad, EmisionTenantIsolation) están en verde.
- [X] T024 Recorrer manualmente (o vía test) los 9 escenarios de `quickstart.md` y tachar los que
      falten.
- [X] T025 [P] Revisar que ninguna notificación nueva (éxito/error al emitir) use markup de alerta
      ad-hoc — debe pasar por flash + `partials/flash-toastr` o `window.showToast` (CLAUDE.md).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — puede arrancar de inmediato.
- **Foundational (Phase 2)**: depende de Setup (T002/T006 antes que T007) — BLOQUEA todas las
  historias.
- **User Story 1 (Phase 3)**: depende de Foundational completa.
- **User Story 2 (Phase 4)**: depende de Foundational; reutiliza `EmisorFacturas` de US1 (T007) —
  en la práctica conviene implementarla después de US1 aunque los guardas de editar/borrar ya
  existan desde 005.
- **User Story 3 (Phase 5)**: depende de US1 (necesita que existan facturas emitidas y el evento).
- **Polish (Phase 6)**: depende de que las historias deseadas estén completas.

### User Story Dependencies

- **US1 (P1)**: la base — sin ella no hay número que mostrar (US3) ni estado que congelar (US2).
- **US2 (P2)**: mayormente ya cubierta por guardas de 005 + el propio diseño de `EmisorFacturas`;
  solo necesita sus tests (T015) para confirmar el candado, en particular la re-emisión.
- **US3 (P3)**: depende de US1 para tener datos que mostrar/registrar.

### Parallel Opportunities

- T002, T003, T004 (Phase 1) en paralelo tras T001.
- T008, T009, T010 (tests de US1) en paralelo entre sí (archivos distintos).
- T015 (test US2) puede escribirse en paralelo a los tests de US1 (distinto archivo), aunque su
  implementación depende de T007.
- T018, T019 (tests US3, mismo archivo `FacturaEmisionTest.php` en parte) — coordinar si se tocan
  las mismas líneas; T019 puede ir en archivo/método separado para paralelizar.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup (`factura_eventos` + modelo/factory).
2. Phase 2: Foundational (`NumeradorFacturas` reescrito + `EmisorFacturas`).
3. Phase 3: User Story 1 completa con sus tests en verde.
4. **STOP y VALIDAR**: numeración correcta, reinicio anual, concurrencia, importes intactos.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → emitir funcional y numerado → validar independientemente.
3. US2 → candado de inmutabilidad confirmado por tests.
4. US3 → visibilidad en listado/PDF + evento append-only.
5. Polish → correr quickstart.md completo.
