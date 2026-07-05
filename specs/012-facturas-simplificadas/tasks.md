---

description: "Task list for POS â€” Facturas simplificadas (tickets)"
---

# Tasks: POS â€” Facturas simplificadas (tickets)

**Input**: Design documents from `/specs/012-facturas-simplificadas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/pos.md, quickstart.md

**Tests**: Se incluyen tareas de test SOLO para las Ã¡reas crÃ­ticas que la constituciÃ³n marca como
test-first NON-NEGOTIABLE (Principio IV): numeraciÃ³n de serie "S", validaciÃ³n de tope y aislamiento
multi-tenant. El resto de UI/endpoints no lleva test obligatorio.

**Organization**: Tareas agrupadas por user story (US1â€“US5 de `spec.md`) para implementaciÃ³n y prueba
independientes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: User story asociada (US1â€“US5); Setup/Foundational/Polish sin etiqueta

## Path Conventions

Monolito Laravel existente: `app/`, `resources/views/`, `public/js/`, `routes/`, `database/`,
`tests/` en la raÃ­z del repositorio (ver `plan.md` â†’ Project Structure).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparar semillas, configuraciÃ³n y ruteo que el resto de fases necesita.

- [X] T001 Sembrar la serie simplificada "S" (`tipo = simplificada`, `codigo = 'S'`, `formato = '{serie}-{anio}-{numero:0000}'`, `activa = true`) en `database/seeders/SerieSeeder.php`, con `firstOrCreate` por `(tenant_id, codigo, ejercicio)` igual que "F"/"R".
- [X] T002 [P] AÃ±adir la clave de configuraciÃ³n `factura.simplificada_tope_ampliado` (grupo `facturacion`, tipo `boolean`, default `false`) en `database/seeders/ConfiguracionSeeder.php` con `firstOrCreate` por `(tenant_id, clave)`.
- [X] T003 [P] Registrar el grupo de rutas `pos.*` (index, create, store, pdf) dentro del grupo `['tenant.context','auth']` en `routes/web.php`, apuntando a `PosController` (aÃºn por crear).

**Checkpoint**: Serie "S", clave de tope y rutas disponibles para el resto de fases.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Piezas de dominio compartidas por todas las user stories (tope, validaciÃ³n por tipo,
controlador base). âš ï¸ CRÃTICO: ninguna user story puede completarse sin esto.

- [X] T004 Crear `App\Support\TopeSimplificada` en `app/Support/TopeSimplificada.php` con constantes `CLAVE = 'factura.simplificada_tope_ampliado'`, `TOPE_BASE = 400.00`, `TOPE_AMPLIADO = 3000.00`, y mÃ©todo `topePara(): float` que resuelve la config del tenant (tenant activo â†’ fallback global) devolviendo 400.00 o 3000.00.
- [X] T005 Crear la excepciÃ³n `App\Exceptions\TicketFueraDeTopeException` en `app/Exceptions/TicketFueraDeTopeException.php` (mensaje por defecto: el importe requiere una factura ordinaria).
- [X] T006 Condicionar al `tipo` la validaciÃ³n de receptor en `App\Services\EmisorFacturas::validar()` (`app/Services/EmisorFacturas.php`): NO exigir `cliente_nif`/nombre/`cliente_direccion` cuando `tipo === TipoFactura::Simplificada`; mantener intacto el comportamiento para `ordinaria` (los tests de 008 deben seguir en verde).
- [X] T007 Crear el esqueleto de `App\Http\Controllers\PosController` en `app/Http/Controllers/PosController.php` con inyecciÃ³n de `CalculadoraFactura`, `NumeradorFacturas`/`EmisorFacturas` y `RegistroTicket` (por crear), y las firmas `index`, `create`, `store`, `pdf`.

**Checkpoint**: Tope, excepciÃ³n, emisor tipo-consciente y controlador base listos â†’ user stories pueden empezar.

---

## Phase 3: User Story 1 - Emitir un ticket rÃ¡pido desde la vista POS (Priority: P1) ðŸŽ¯ MVP

**Goal**: Crear y emitir un ticket simplificado (sin receptor) con nÃºmero de serie "S", importes
server-side, evento append-only e inmutabilidad.

**Independent Test**: AÃ±adir lÃ­neas en "Crear ticket", emitir sin receptor y verificar factura
`tipo = simplificada`, nÃºmero `S-<aÃ±o>-0001`, estado `emitida`, evento `emitida`, e inmutable.

### Tests for User Story 1 (test-first, Ã¡rea crÃ­tica) âš ï¸

> Escribir estos tests PRIMERO y verlos FALLAR antes de implementar.

- [X] T008 [P] [US1] Test de emisiÃ³n y numeraciÃ³n en `tests/Feature/PosTicketEmisionTest.php`: un ticket simple (sin receptor) se emite con `numero_completo = S-<aÃ±o>-0001`, `tipo = simplificada`, `estado = emitida`; segundo ticket â†’ `...-0002` sin huecos; reinicio anual; evento `emitida` creado; editar/eliminar/re-emitir un ticket emitido se rechaza (inmutabilidad).

### Implementation for User Story 1

- [X] T009 [US1] Crear `App\Services\RegistroTicket` en `app/Services/RegistroTicket.php`: resuelve la serie "S" (`Serie::activaPorTipo(TipoFactura::Simplificada)`), calcula importes con `CalculadoraFactura` (rÃ©gimen y recargo del tenant/cliente), construye la `Factura` en borrador con `tipo = simplificada`, valida el tope con `TopeSimplificada` (lanza `TicketFueraDeTopeException` si se supera), persiste lÃ­neas e impuestos, y emite vÃ­a `EmisorFacturas::emitir()` â€” todo en una transacciÃ³n atÃ³mica.
- [X] T010 [P] [US1] Crear `App\Http\Requests\StoreTicketRequest` en `app/Http/Requests/StoreTicketRequest.php`: valida `lineas` (â‰¥1, `cantidad > 0`, `precio_unitario â‰¥ 0`, `tipo_impositivo` vÃ¡lido), `receptor` opcional, `notas` opcional.
- [X] T011 [US1] Implementar `PosController::store()` en `app/Http/Controllers/PosController.php`: usa `StoreTicketRequest` + `RegistroTicket`, maneja `TicketFueraDeTopeException` (422/flash `error`) y responde JSON `{id, numero_completo}` (201) o redirect a `pos.index` con flash `success`.
- [X] T012 [US1] Implementar `PosController::create()` (vista de captura) y la vista base `resources/views/pos/create.blade.php` con el formulario mÃ­nimo (lÃ­neas + total en vivo), suficiente para emitir un ticket simple; el pulido visual tablet-first va en US aparte/Polish.
- [X] T013 [P] [US1] Crear `public/js/pos-form.js`: captura de lÃ­neas, cÃ¡lculo de total como *preview* en cliente y envÃ­o AJAX a `pos.store` con manejo de errores vÃ­a `window.showToast` (no fuente de verdad de importes).

**Checkpoint**: US1 funcional â€” se puede emitir un ticket simple end-to-end.

---

## Phase 4: User Story 2 - Respetar el tope de importe de la simplificada (Priority: P1)

**Goal**: Bloqueo duro de emisiÃ³n cuando el total (impuestos incl.) supera el tope aplicable
(400 â‚¬ / 3.000 â‚¬ segÃºn config del tenant).

**Independent Test**: Con tope base, 400 â‚¬ pasa y 401 â‚¬ se bloquea; con flag ampliado, 3.000 â‚¬ pasa y
3.001 â‚¬ se bloquea (lÃ­mite inclusivo).

### Tests for User Story 2 (test-first, Ã¡rea crÃ­tica) âš ï¸

- [X] T014 [P] [US2] Test de tope en `tests/Feature/PosTopeSimplificadaTest.php`: con `factura.simplificada_tope_ampliado = false`, un ticket cuyo bruto = 400.00 se emite y 400.01 se rechaza con `TicketFueraDeTopeException`/422; con `true`, 3000.00 se emite y 3000.01 se rechaza; el bruto se mide como `base_total + cuota_impuesto_total + cuota_recargo_total`.

### Implementation for User Story 2

- [X] T015 [US2] Asegurar en `RegistroTicket` (`app/Services/RegistroTicket.php`) que la validaciÃ³n de tope se ejecuta en backend sobre el total calculado por el servidor ANTES de asignar nÃºmero/emitir, y que al superarse no se crea ni emite nada (rollback de la transacciÃ³n).
- [X] T016 [P] [US2] AÃ±adir el switch "Sector con tope ampliado (3.000 â‚¬)" en `resources/views/configuracion/_tab_facturacion.blade.php` y persistirlo vÃ­a el mecanismo de `ConfiguracionController` (clave `factura.simplificada_tope_ampliado`), siguiendo el patrÃ³n de config existente.
- [X] T017 [P] [US2] Exponer el tope aplicable a la vista "Crear ticket" y avisar en cliente (preview) cuando el total se acerque/supere el tope, dejando la verdad del bloqueo en backend, en `public/js/pos-form.js` y `resources/views/pos/create.blade.php`.

**Checkpoint**: US1 + US2 â€” tickets vÃ¡lidos se emiten, los que exceden el tope se bloquean.

---

## Phase 5: User Story 3 - Listar y consultar los tickets en su propia vista POS (Priority: P2)

**Goal**: DataTable POS exclusivo de simplificadas + exclusiÃ³n de simplificadas en el listado de
Facturas, con aislamiento por tenant.

**Independent Test**: Con tickets y facturas ordinarias, `GET /pos` (JSON) devuelve sÃ³lo simplificadas
y `GET /facturas` (JSON) las excluye; en dos tenants, listados y numeraciÃ³n no se cruzan.

### Tests for User Story 3 (test-first, Ã¡rea crÃ­tica: separaciÃ³n + aislamiento) âš ï¸

- [X] T018 [P] [US3] Test de separaciÃ³n de listados en `tests/Feature/PosListadoSeparadoTest.php`: `pos.index` JSON contiene sÃ³lo `tipo = simplificada`; `facturas.index` JSON excluye simplificadas y sus totales no las cuentan.
- [X] T019 [P] [US3] Test de aislamiento multi-tenant en `tests/Feature/PosTenantIsolationTest.php`: con â‰¥2 tenants, la serie "S", su numeraciÃ³n y el listado POS de cada tenant son independientes (0 fugas).

### Implementation for User Story 3

- [X] T020 [US3] Implementar `PosController::index()` en `app/Http/Controllers/PosController.php`: rama JSON con la estructura de `contracts/pos.md` (identificador, estado, cualificada, receptor, total, urls PDF) filtrando `tipo = simplificada`; rama HTML devuelve `pos/index.blade.php`.
- [X] T021 [US3] Excluir `tipo = simplificada` en `App\Http\Controllers\FacturaController::index()` (`app/Http/Controllers/FacturaController.php`), tanto en la colecciÃ³n JSON como en los totales, sin alterar el resto del mÃ©todo.
- [X] T022 [P] [US3] Crear la vista `resources/views/pos/index.blade.php` (DataTable de simplificadas) y `public/js/plugins-init/pos-datatable.init.js` siguiendo el patrÃ³n de `facturas-datatable.init.js`.
- [X] T023 [P] [US3] AÃ±adir el dropdown "POS" al sidebar en `resources/views/partials/sidebar.blade.php` con enlaces a `pos.index` ("Facturas simplificadas") y `pos.create` ("Crear ticket").

**Checkpoint**: US1â€“US3 â€” tickets emitibles, con tope, listados y separados del mÃ³dulo Facturas.

---

## Phase 6: User Story 4 - Ticket cualificado con datos del receptor (Priority: P2)

**Goal**: Capturar opcionalmente NIF+domicilio del receptor manteniendo `tipo = simplificada`.

**Independent Test**: Emitir un ticket con receptor â†’ snapshot `cliente_*` relleno, `tipo` sigue
`simplificada`; detalle/PDF muestran receptor y desglose de cuota.

### Tests for User Story 4 (Ã¡rea crÃ­tica: persistencia correcta del snapshot) âš ï¸

- [X] T024 [P] [US4] Ampliar `tests/Feature/PosTicketEmisionTest.php` (o test propio): un ticket con receptor informado persiste el snapshot `cliente_*`, mantiene `tipo = simplificada` (no cambia a ordinaria); un ticket sin receptor deja `cliente_id`/snapshot en null.

### Implementation for User Story 4

- [X] T025 [US4] Extender `StoreTicketRequest` (`app/Http/Requests/StoreTicketRequest.php`) y `RegistroTicket` (`app/Services/RegistroTicket.php`) para aceptar el receptor opcional; si viene informado (cualificada), exigir coherencia mÃ­nima (NIF + `cliente_direccion`) y persistir el snapshot `cliente_*`; si se elige un cliente existente, precargar sus datos como snapshot editable.
- [X] T026 [P] [US4] AÃ±adir en `resources/views/pos/create.blade.php` + `public/js/pos-form.js` la secciÃ³n opcional/plegable de datos del receptor (selecciÃ³n de cliente existente o alta manual de NIF+domicilio).

**Checkpoint**: US1â€“US4 â€” tickets simples y cualificados.

---

## Phase 7: User Story 5 - Descargar/imprimir el ticket en 80 mm o A4 (Priority: P3)

**Goal**: PDF del ticket en formato 80 mm (rollo) o A4, elegible al ver/descargar.

**Independent Test**: Desde un ticket emitido, `?formato=ticket` da rollo 80 mm y `?formato=a4` da A4;
ambos con contenido mÃ­nimo de simplificada (y receptor+cuota si cualificada).

### Implementation for User Story 5

- [X] T027 [US5] Implementar `PosController::pdf()` en `app/Http/Controllers/PosController.php`: parÃ¡metro `formato âˆˆ {ticket, a4}` (default `ticket`), resolviendo el modelo por el cuerpo del controlador bajo scope de tenant (no binding implÃ­cito, ver `docs`/memoria de route binding).
- [X] T028 [P] [US5] Crear la plantilla `resources/views/facturas/ticket-80mm.blade.php` (papel 80 mm, alto variable) con el contenido mÃ­nimo de la simplificada y, si cualificada, receptor + desglose de cuota.
- [X] T029 [P] [US5] Preparar la salida A4 para simplificada reutilizando/parametrizando `resources/views/facturas/pdf.blade.php` (ocultar bloques no aplicables cuando no hay receptor).
- [X] T030 [US5] Configurar el tamaÃ±o de papel de DomPDF en `PosController::pdf()` para el formato ticket (`setPaper([0,0,226.77,<alto>])`, 80 mm â‰ˆ 226.77 pt) y A4 por defecto para el otro.

**Checkpoint**: US1â€“US5 â€” flujo POS completo con impresiÃ³n en ambos formatos.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Pulido visual tablet-first, documentaciÃ³n y validaciÃ³n final.

- [X] T031 Pulir la vista "Crear ticket" (`resources/views/pos/create.blade.php` + estilos) para uso tÃ¡ctil en tablet/TPV (botones grandes, bÃºsqueda Ã¡gil de artÃ­culos, feedback inmediato) usando las skills de diseÃ±o (`frontend-design`/`emil-design-eng`) y `docs/04-front-guidelines.md`.
- [X] T032 [P] Actualizar `docs/03-modelo-datos.md`: registrar la clave `factura.simplificada_tope_ampliado` en la tabla "Ejemplos de claves" y marcar la nota de simplificadas (lÃ­nea ~234) como implementada por la feature 012.
- [X] T033 [P] Revisar si `docs/02-facturacion-espana.md` Â§3.1 necesita ajuste tras la implementaciÃ³n (topes/serie "S") y actualizar si procede.
- [X] T034 Ejecutar la validaciÃ³n de `quickstart.md` (suite `php artisan test --filter=Pos` en verde + recorrido manual de los 8 pasos) y corregir desviaciones.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias; T001â€“T003 pueden ir en paralelo.
- **Foundational (Phase 2)**: depende de Setup. T004/T005 en paralelo; T006 independiente; T007 tras tener firmas. BLOQUEA todas las user stories.
- **User Stories (Phase 3â€“7)**: dependen de Foundational. Orden de prioridad P1â†’P3; US2 depende de US1 (comparten `RegistroTicket`); US3/US4/US5 pueden solaparse tras US1.
- **Polish (Phase 8)**: tras completar las user stories deseadas.

### User Story Dependencies

- **US1 (P1)**: base del mÃ³dulo; requiere Foundational.
- **US2 (P1)**: extiende `RegistroTicket` de US1 (tope); tras US1.
- **US3 (P2)**: listados; independiente de US2, requiere que existan tickets (US1).
- **US4 (P2)**: receptor opcional; extiende US1; independiente de US2/US3.
- **US5 (P3)**: PDF; requiere tickets emitidos (US1); independiente de US2â€“US4.

### Within Each User Story

- Los tests de Ã¡rea crÃ­tica (numeraciÃ³n, tope, aislamiento, snapshot) se escriben ANTES y deben FALLAR.
- `RegistroTicket` (servicio) antes que `PosController` (endpoint); endpoint antes que vistas/JS.

### Parallel Opportunities

- Setup: T001, T002, T003 en paralelo.
- Foundational: T004 y T005 en paralelo.
- US1: T010 y T013 en paralelo con T009 una vez definida su interfaz; T008 primero.
- US3: T018 y T019 en paralelo; T022 y T023 en paralelo tras T020/T021.
- Polish: T032 y T033 en paralelo.

---

## Parallel Example: User Story 1

```text
# Test primero (debe fallar):
Task: "T008 Test de emisiÃ³n y numeraciÃ³n en tests/Feature/PosTicketEmisionTest.php"

# Tras definir RegistroTicket (T009), en paralelo:
Task: "T010 StoreTicketRequest en app/Http/Requests/StoreTicketRequest.php"
Task: "T013 public/js/pos-form.js"
```

---

## Implementation Strategy

### MVP First (User Story 1 + 2)

1. Phase 1 Setup â†’ Phase 2 Foundational.
2. Phase 3 (US1) â†’ emitir ticket simple.
3. Phase 4 (US2) â†’ bloqueo de tope (imprescindible por normativa).
4. **STOP y VALIDAR**: tickets vÃ¡lidos se emiten, los que exceden se bloquean. MVP fiscalmente correcto.

### Incremental Delivery

1. Foundation lista.
2. US1 â†’ demo emitir ticket.
3. US2 â†’ tope duro.
4. US3 â†’ listado POS + separaciÃ³n de Facturas.
5. US4 â†’ cualificada.
6. US5 â†’ PDF 80 mm/A4.
7. Polish â†’ tablet-first + docs.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- Ãreas test-first (Principio IV): numeraciÃ³n serie "S" (T008), tope (T014), aislamiento (T019),
  snapshot cualificada (T024). No marcar completas sin sus tests en verde.
- Sin cambios de esquema en `facturas`: cualquier tarea que proponga una migraciÃ³n de `facturas` es un error de alcance.
- Commit tras cada tarea o grupo lÃ³gico; parar en cada checkpoint para validar la story de forma aislada.

