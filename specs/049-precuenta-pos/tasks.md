# Tasks: Precuenta en el POS de hostelería

**Input**: Design documents from `/specs/049-precuenta-pos/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/precuenta.md](contracts/precuenta.md),
[quickstart.md](quickstart.md)

**Tests**: obligatorios donde la constitución lo exige (Principio IV: aislamiento multi-tenant,
cálculo de importes, "no toca numeración/Verifactu"). Marcados **[TEST-FIRST]**: se escriben antes que
la implementación y deben fallar primero (Red-Green-Refactor).

**Notas de testing del proyecto** (memorias): las factories fijan `tenant_id => Tenant::factory()`,
hay que forzar el tenant activo al crear datos; con `SESSION_DRIVER=array` no mezclar dos usuarios en
un mismo método de test; el texto de las guías in-app se renderiza siempre, así que los asserts de
vista van sobre marcadores HTML (`id`/`data-*`), no sobre texto que también esté en la ayuda.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

**Purpose**: dejar la normativa y el modelo de datos documentados antes del código (Principio II:
los cambios normativos van primero a `docs/`).

- [X] T001 [P] Añadir en `docs/02-facturacion-espana.md` una subsección "§3.2 Precuenta (documento no fiscal)" con lo de research D1: no es factura, no genera registro Verifactu ni consume serie, debe distinguirse sin ambigüedad (título, leyenda "Documento no válido como factura", sin número/serie/QR), registro append-only de emisiones; citar la fuente en "Fuentes" y dejar la nota de reconfirmar contra las FAQ oficiales de la AEAT antes de producción
- [X] T002 [P] Añadir la tabla `pos_precuentas` a `docs/03-modelo-datos.md` (sección del módulo de hostelería) con las columnas de [data-model.md](data-model.md), la regla append-only, el estado derivado por huella y la nota de datos personales de research D10

---

## Phase 2: Foundational (bloquea todas las historias)

**Purpose**: tabla, modelo y el servicio que calcula huellas, estado y total. Todo lo demás lo consume.

### Tests (TEST-FIRST)

- [X] T003 [P] [TEST-FIRST] `tests/Feature/Pos/PrecuentaTotalIgualTicketTest.php`: para una cuenta con líneas de dos tipos impositivos, opciones con suplemento y zona con suplemento, el total que calcula `PrecuentaCuenta` es **idéntico al céntimo** al `total` de la factura que emite después `CobradorCuenta::cobrar()` sobre esa misma cuenta; repetir con cliente receptor con recargo de equivalencia y con el tenant en régimen IGIC (SC-002, research D2, Principio II: nada asume IVA)
- [X] T004 [P] [TEST-FIRST] `tests/Feature/Pos/PrecuentaVigenciaTest.php` (a nivel de servicio): estado `ninguna` sin precuentas; `vigente` tras emitir; pasa a `desactualizada` al cambiar cantidad, precio u opciones de una línea, al añadir o quitar una, al unir otra cuenta (`TransferidorCuenta::unir`) y al transferir a una zona con otro suplemento; **sigue** `vigente` tras un cobro parcial (`CobradorCuenta` con selección), tras cambiar notas/comensales/receptor y tras un guardado que recrea las líneas con ids nuevos pero mismo contenido (research D4, FR-016, FR-017)

### Implementación

- [X] T005 Migración `database/migrations/2026_10_05_100000_create_pos_precuentas_table.php` según [data-model.md](data-model.md): FKs `cuenta_id` restrict, `mesa_id` y `usuario_id` nullOnDelete, índices `tenant_id` y `(tenant_id, cuenta_id, id)`. Aplicar con `php artisan migrate` (nunca `migrate:fresh`, ver CLAUDE.md)
- [X] T006 [P] Modelo `app/Models/PosPrecuenta.php` (`BelongsToTenant`, `HasFactory`, casts de data-model, relaciones `cuenta`/`mesa`/`usuario`) que lance excepción en `updating` y `deleting` (append-only, FR-013); factory `database/factories/PosPrecuentaFactory.php`
- [X] T007 [P] `app/Models/PosCuenta.php`: relación `precuentas()` (orden por `id`) y docblock que recuerde que el estado de precuenta se deriva, no se guarda
- [X] T008 Refactor sin cambio de comportamiento en `app/Services/CobradorCuenta.php`: extraer a un método público `lineasACobrar(PosCuenta $cuenta, array $unidades, float $suplemento): array` la construcción de `$lineasPayload` (incluido `conceptoConOpciones`) y a otro público `suplementoEfectivo(PosCuenta $cuenta): float` la lectura del suplemento de zona; `cobrar()` los usa. Correr `php artisan test tests/Feature/Pos` y confirmar que todo sigue en verde
- [X] T009 Servicio `app/Services/PrecuentaCuenta.php`: `huellaConsumo(PosCuenta)`, `huellaPendiente(PosCuenta)` (representación canónica de research D4), `estado(PosCuenta, ?PosPrecuenta $ultima): string`, `calcular(PosCuenta): array{lineas, total, suplemento}` usando `CobradorCuenta::lineasACobrar` + `CalculadoraFactura` con `tenant()->regimen_impositivo` y el recargo del cliente receptor de la cuenta (mismo criterio que `RegistroTicket::registrar`), y `emitir(PosCuenta, int $version, ?int $usuarioId): PosPrecuenta` en transacción con `lockForUpdate` sobre la cuenta, que rechaza cuenta no abierta, versión distinta y pendiente 0, marca `reimpresion` si la última tiene la misma `huella_pendiente`, y guarda la foto de líneas. Hasta que T003 y T004 pasen

**Checkpoint**: T003 y T004 en verde; la suite del POS existente sigue en verde.

---

## Phase 3: User Story 1 — Dar la precuenta a una mesa (Priority: P1) 🎯 MVP

**Goal**: desde el TPV con la cuenta cargada, generar y ver/imprimir la precuenta, sin efecto fiscal.

**Independent Test**: cuenta abierta con varias líneas → Precuenta → vista previa correcta; ningún
documento nuevo en tickets, numeración intacta, cuenta sigue abierta.

### Tests (TEST-FIRST)

- [X] T010 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/PrecuentaSinEfectoFiscalTest.php`: emitir varias precuentas no crea `facturas`, no cambia el contador de la serie, no crea `factura_eventos`/registros Verifactu, ni `pos_cobros`, ni movimientos de stock, ni cambia `cantidad_saldada` ni `pos_cuentas.version`; el siguiente cobro recibe el número que le tocaba (FR-009, SC-003)
- [X] T011 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/PrecuentaEmisionTest.php` contra `POST /pos/cuentas/{id}/precuentas` ([contracts/precuenta.md](contracts/precuenta.md)): 201 con `precuenta` y `cuenta.precuenta.estado = vigente`; 409 con versión vieja (no inserta nada); 422 con cuenta anulada/cerrada y con pendiente 0; solo incluye unidades **pendientes** tras un cobro parcial (FR-004); cuenta sin mesa → `mesa_nombre` null; el modelo impide `update`/`delete` de una precuenta; módulo de hostelería apagado → la ruta no es accesible (mismo comportamiento que el resto de rutas del grupo); anular una cuenta con precuenta deja intactas sus filas en `pos_precuentas` (SC-007)
- [X] T012 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/PrecuentaDocumentoTest.php` contra `GET /pos/precuentas/{id}/pdf`: responde `application/pdf`; renderizando la vista `pos.precuenta-80mm` con la precuenta, el HTML contiene "PRECUENTA", la leyenda "Documento no válido como factura" dos veces, la mención del impuesto del régimen ("IGIC incluido" con tenant IGIC), y **no** contiene "VERI*FACTU", "QR tributario" ni ningún número de serie; si la cuenta cambia después, el documento sigue mostrando la foto original (research D3)
- [X] T013 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/AislamientoPrecuentaTest.php` con 2 tenants: emitir sobre la cuenta de otro tenant → 404 y sin filas; PDF de una precuenta de otro tenant → 404; `PosPrecuenta::all()` en el tenant A no ve las de B (Principio I)

### Implementación

- [X] T014 [US1] Controlador `app/Http/Controllers/Pos/PrecuentaController.php`: `store` (valida `version` entero, resuelve la cuenta manualmente bajo scope con `findOrFail`, delega en `PrecuentaCuenta::emitir`, responde 201 / 409 con el mismo formato que `CuentaController::conflictoDeVersion` / 422) y `pdf` (resuelve la precuenta bajo scope, dompdf con `pos.precuenta-80mm` y papel `[0, 0, 226.77, alto]` como `PosController::pdf`, `stream`)
- [X] T015 [US1] Rutas en `routes/web.php`, dentro del grupo `['can:ver-pos-sala', 'modulo.hosteleria']`: `POST /pos/cuentas/{cuenta}/precuentas` → `pos.cuentas.precuentas.store` y `GET /pos/precuentas/{precuenta}/pdf` → `pos.precuentas.pdf`; actualizar `RutasPermisosTest::mapaRutas()` si enumera las rutas del grupo
- [X] T016 [US1] `app/Http/Controllers/Pos/CuentaController.php`: añadir al `payload()` el bloque `precuenta: { estado, ultima: { id, total, emitida_en, reimpresion, pdf_url } | null, total_actual }` usando `PrecuentaCuenta` (cargar la última precuenta sin N+1; `total_actual` solo cuando `estado = desactualizada`, ver data-model); el payload lo devuelven `show`, `update`, `transferir` y `unir`
- [X] T017 [P] [US1] Plantilla `resources/views/pos/precuenta-80mm.blade.php` (research D6): misma base tipográfica que `facturas/ticket-80mm.blade.php`; logo + nombre comercial (sin NIF ni dirección); título "PRECUENTA"; leyenda "Documento no válido como factura" arriba y abajo; mesa/zona o "Sin mesa", fecha y hora, comensales, emitida por, "Reimpresión" si aplica; líneas desde `lineas` con las opciones bajo el concepto; línea de suplemento de zona si `suplemento_zona > 0`; total y "<IVA|IGIC|IPSI> incluido" según `regimen_impositivo`. **Sin** `@include('partials.verifactu-qr')`
- [X] T018 [US1] `resources/views/pos/create.blade.php`: botón "Precuenta" (`#pos-precuenta-btn`, sin `data-loading-text` porque lleva icono — § "Botones con markup interno") dentro de `#pos-mesa-chip`, junto a ⇄ y ×; modal `#posPrecuentaModal` (`modal-dialog-centered modal-lg`, iframe `#pos-precuenta-frame`, footer con "Imprimir" y "Listo"); cargar `pos-precuenta.js` con `@assetv` después del orquestador `pos-form.js`; todo dentro del bloque que ya condiciona el módulo de hostelería, para que con el módulo apagado no exista nada (FR-028)
- [X] T019 [US1] Módulo `public/js/pos-precuenta.js` (`PosApp.registrar('precuenta', …)`, § "Partición de un archivo JS grande"): al pulsar el botón, `withButtonLoading` → `PosApp.modulos.cuenta.guardar()` → si ok, `fetch` POST a `/pos/cuentas/{id}/precuentas` con `{ version }` y header `X-CSRF-TOKEN` → abre el modal con `pdf_url` en el iframe; 409 → `showToast('error', …)` y recargar la cuenta como hace el guardado; 422 → `showToast` con el mensaje del servidor. "Imprimir" hace `contentWindow.print()` del iframe. Al `hidden.bs.modal`: vaciar el `src` y llamar a `vaciarPantalla()` del módulo cuenta (FR-011). Nunca abrir este modal mientras otro está visible (esperar `hidden.bs.modal`)
- [X] T020 [US1] `public/js/pos-cuenta.js`: exponer `vaciarPantalla` en la API pública del módulo y mostrar/ocultar `#pos-precuenta-btn` en `renderChip()`: visible siempre que haya mesa (también con una mesa recién tocada, cuya cuenta se crea al guardar), deshabilitado mientras el ticket en pantalla esté vacío (`PosApp.lineas.length === 0`); el caso "todo cobrado" lo rechaza el servidor con 422 (edge case)

**Checkpoint**: US1 completa y demostrable sola (quickstart pasos 1, 2 y 6).

---

## Phase 4: User Story 2 — Ver en la Sala qué mesas esperan para pagar (Priority: P1)

**Goal**: cuarto estado de mesa "precuenta pedida", decidido en servidor, igual en tarjetas, plano y
métricas.

**Independent Test**: emitir precuenta → la mesa aparece como "precuenta" en las dos vistas y en la
métrica; cobrarla entera → libre.

### Tests (TEST-FIRST)

- [X] T021 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/PrecuentaSalaTest.php` contra `GET /pos/sala` (JSON): mesa con precuenta vigente → `precuenta_pedida: true`, `precuenta_hace_min` entero y `olvidada: false` aunque supere el umbral (FR-018); precuenta desactualizada → `precuenta_pedida: false`; tras cobro parcial sigue `true` con el pendiente actualizado; tras cobrar entera la mesa es `libre`; tras transferir, el estado viaja a la mesa destino; mesas libres llevan `precuenta_pedida: false`; el número de consultas no crece con el número de mesas (sin N+1). Ampliar `tests/Feature/Pos/SalaPayloadPlanoTest.php` para fijar los dos campos nuevos (§ "Dos vistas…", corolario de contrato)

### Implementación

- [X] T022 [US2] `app/Http/Controllers/Pos/SalaController.php`: cargar `lineas.opciones` y `mesa.zona` de las cuentas abiertas y, en una sola consulta, sus precuentas (`whereIn('cuenta_id', …)`, `orderByDesc('id')`, agrupadas en PHP); por mesa ocupada calcular `precuenta_pedida` con `PrecuentaCuenta::estado()` y `precuenta_hace_min` desde `emitida_en`; forzar `olvidada = false` cuando `precuenta_pedida`; añadir ambos campos también a las mesas libres
- [X] T023 [P] [US2] `public/js/plugins-init/pos-plano-dibujo.js`: `claseEstado()` devuelve `'precuenta'` si `mesa.precuenta_pedida`, antes de mirar `olvidada`; en modo servicio, el tiempo mostrado es `precuenta_hace_min` cuando aplica
- [X] T024 [US2] `public/js/plugins-init/pos-sala.init.js`: la tarjeta usa `claseEstado()` (no su propia condición), muestra la etiqueta "Precuenta" y el tiempo desde la precuenta; el conteo de métricas añade `precuenta` y pinta `[data-metric="precuentas"]`; tocar una mesa en precuenta sigue pasando por `window.posSalaDestinoMesa(mesa)` sin cambios (FR-021)
- [X] T025 [US2] `resources/views/pos/sala.blade.php`: variables `--pos-precuenta: #7c3aed` y fondo `#f6f2ff`; reglas `.pos-mesa.precuenta` y `.plano-mesa.precuenta` (borde, importe, meta, badge, sillas) siguiendo las de `.olvidada`; card de métrica "Precuenta" en la tira plegable de resumen con `data-metric="precuentas"`; actualizar el comentario de cabecera "Gris = libre, verde = ocupada, ámbar = olvidada" con el violeta

**Checkpoint**: US1 + US2 = flujo completo de precuenta en un turno real (quickstart paso 3).

---

## Phase 5: User Story 3 — Aviso cuando la cuenta cambia después de la precuenta (Priority: P2)

**Goal**: que nunca se cobre una cifra distinta de la precuenta sin aviso.

**Independent Test**: precuenta → añadir línea → guardar: toast, chip "desactualizada", Sala en
ocupada, franja en el modal de cobro.

- [X] T026 [P] [US3] [TEST-FIRST] Ampliar `tests/Feature/Pos/PrecuentaEmisionTest.php`: tras `PUT /pos/cuentas/{id}` que añade una línea, la respuesta trae `precuenta.estado = desactualizada`, `precuenta.ultima.total` con el total impreso y `precuenta.total_actual` igual al total que emitiría el cobro; tras un `PUT` que solo cambia notas, sigue `vigente`
- [X] T027 [US3] `public/js/pos-cuenta.js`: guardar el `precuenta.estado` previo antes de cada guardado y, si la respuesta pasa de `vigente` a `desactualizada`, `window.showToast('warning', 'La precuenta impresa ya no coincide con la cuenta. Reimprímela antes de cobrar.')` (FR-022); en `renderChip()` pintar el indicador `#pos-precuenta-estado` ("Precuenta" vigente / "Precuenta desactualizada" con estilo de aviso) según el estado (FR-023)
- [X] T028 [US3] `resources/views/pos/create.blade.php`: marcado del indicador `#pos-precuenta-estado` dentro del chip de mesa y de la franja `#pos-cobro-aviso-precuenta` (oculta por defecto) en la cabecera del modal de cobro, junto al contexto de mesa
- [X] T029 [US3] `public/js/pos-cobro.js`: al abrir el modal de cobro con cuenta cuya `precuenta.estado === 'desactualizada'`, mostrar en `#pos-cobro-aviso-precuenta` "Precuenta entregada: X € · Total actual: Y €" con importes que vienen del servidor (`precuenta.ultima.total` y `precuenta.total_actual`; nunca un total calculado en el navegador, Principio III); oculta en cualquier otro caso; nunca bloquea "Emitir" (FR-024)

**Checkpoint**: quickstart paso 4.

---

## Phase 6: User Story 4 — Reimprimir la precuenta (Priority: P2)

**Goal**: volver a sacar la precuenta, marcada como reimpresión cuando nada cambió.

**Independent Test**: dos precuentas seguidas sin cambios → la segunda "Reimpresión"; ambas
registradas.

- [X] T030 [P] [US4] [TEST-FIRST] Ampliar `tests/Feature/Pos/PrecuentaEmisionTest.php`: dos emisiones seguidas → la segunda con `reimpresion: true` y dos filas en `pos_precuentas`; emisión tras añadir una línea → `reimpresion: false`; emisión tras un cobro parcial → `reimpresion: false` (FR-025); el PDF de la reimpresión contiene "Reimpresión" (en `PrecuentaDocumentoTest.php`)
- [X] T031 [US4] Verificar en `public/js/pos-precuenta.js` que el botón sigue disponible con precuenta vigente (no se deshabilita tras la primera) y que el toast de éxito distingue "Precuenta generada" de "Precuenta reimpresa" según `precuenta.reimpresion`

**Checkpoint**: quickstart paso 5.

---

## Phase 7: Polish & documentación (las 4 capas, CLAUDE.md)

- [X] T032 [P] `resources/views/ayuda/pos-crear.blade.php`: paso nuevo "Precuenta" (qué es, que no es un ticket ni cobra nada, que al cerrar la vista previa la pantalla queda en cero y la cuenta sigue en su mesa) y nota de error común "si añades algo después, reimprime la precuenta antes de cobrar". **Corregir** además el ítem "Cobro por partes", que describe una selección de líneas que hoy la interfaz no ofrece (research D9): dejarlo como "disponible próximamente" o quitarlo, para que la guía no mienta
- [X] T033 [P] `resources/views/ayuda/pos-sala.blade.php`: el cuarto estado (borde violeta "Precuenta": la mesa ya tiene la cuenta y espera para pagar; prevalece sobre "olvidada"; vuelve a ocupada si se añade algo)
- [X] T034 [P] `resources/ia/conocimiento/pos-hosteleria.md`: sección "Precuenta" (documento no fiscal, cómo se genera, vigente/desactualizada, reimpresión, estado en la Sala, que no consume numeración ni Verifactu) y corregir la sección "Cobro por partes" con el mismo criterio que T032
- [X] T035 [P] `docs/04-front-guidelines.md`: ampliar "Tarjeta de mesa y sus tres estados" a cuatro (violeta = precuenta, prevalece sobre olvidada, decidido en servidor); nota en "«Ver» un documento…" de que los documentos de rollo de 80 mm del TPV (ticket y precuenta) usan `modal-lg` en vez de `modal-xl`; y una sección corta "Estado derivado por huella, no por flag" (research D4) como patrón reutilizable
- [X] T036 Correr `php artisan test` completo y la validación manual de [quickstart.md](quickstart.md) (pedir confirmación antes de usar cualquier herramienta de navegador, CLAUDE.md)
  - **Automático: hecho** — 1431 en verde y 1 fallo en `MovimientoStockConcurrenciaTest`, que pasa solo y no toca código de esta feature (intermitente). `--filter=Precuenta` y `tests/Feature/Pos` en verde.
  - **Validación manual: hecha** con Chrome DevTools (2026-10-05, tenant Empire Demo, 1280×800): pasos 1–5 del quickstart OK (precuenta con guardado previo, PDF sin número/QR con leyenda ×2, TPV a cero al cerrar, mesa violeta en tarjetas y plano + métrica, aviso de desactualizada + chip + franja en el cobro, reimpresión). 0 facturas creadas. Paso 6 (cobro) no se ejecutó en la demo para no emitir un ticket fiscal real con la caja cerrada; lo cubre `PrecuentaTotalIgualTicketTest`. Paso 7 (módulo apagado) cubierto por tests.
  - **Defectos encontrados y corregidos**: (1) el chip de mesa se desbordaba de la cabecera del ticket → ahora baja a su propia línea (`flex-wrap` + `white-space: nowrap`) y el estado vigente es un ✓ compacto; (2) la hora del documento salía en UTC → `enZonaTenant()` + test.
- [X] T037 Revisar si la constitución necesita enmienda (`/speckit-constitution`): esperado **no** (no se crea principio ni se amplía alcance; la precuenta se encaja en el Principio II existente). Dejar constancia en el cierre
  - **Resultado**: no requiere enmienda. La precuenta encaja en los Principios I–V tal como están (registro con `tenant_id`, documento no fiscal documentado en `docs/02` §3.2, cálculo en servidor, tests primero, sin dependencias nuevas).

## Notas de implementación

- **T016 ampliada**: el bloque `precuenta` también viaja en el payload inicial que `PosController::create` inyecta en el TPV al retomar una cuenta desde la Sala; sin él, el chip y el aviso del cobro no sabrían el estado hasta el primer guardado.
- El payload de la cuenta, `resolverCuenta()` y `conflictoDeVersion()` se movieron a un trait compartido (`App\Http\Controllers\Pos\Concerns\RespondeConCuenta`) para que `CuentaController` y `PrecuentaController` devuelvan exactamente la misma cuenta.
- La revalidación de versión bajo bloqueo en `PrecuentaCuenta::emitir` lanza `CuentaVersionDesfasadaException`, que el controlador traduce al mismo 409.
- `SalaController` evita N+1: una consulta de precuentas para todas las cuentas abiertas y, solo para las que tienen precuenta, una de opciones; mesa y zona se enganchan desde memoria. `SalaSinNMasUnoTest` sube su límite fijo de 10 a 11 consultas (la de precuentas).
- Las líneas de la precuenta se imprimen sin el suplemento de zona y el suplemento va como concepto propio, con importe = total real − suma de líneas: líneas + suplemento suman el total al céntimo.
- Orden TEST-FIRST: T003/T004 se escribieron y fallaron antes de T005–T009. T010–T013 se escribieron después de que el endpoint existiera (lo necesitaban ya los tests de la fase 2), así que no se vieron en rojo por sí mismos.

---

## Dependencies & Execution Order

- **Phase 1** (docs) no bloquea código pero va primero por Principio II.
- **Phase 2** bloquea todo: T005 → T006/T007 → T008 → T009 (T003/T004 escritos antes de T009).
- **US1 (Phase 3)** depende de Phase 2. Es el MVP.
- **US2 (Phase 4)** depende de Phase 2 (servicio de estado), **no** de la UI de US1: se puede hacer
  en paralelo con US1 creando precuentas por test/endpoint.
- **US3 (Phase 5)** depende de US1 (botón, payload `precuenta` en la cuenta).
- **US4 (Phase 6)** depende de US1.
- **Polish (Phase 7)** al final; T032–T035 en paralelo entre sí.

### Parallel Opportunities

- T001 ∥ T002; T003 ∥ T004; T006 ∥ T007.
- US1: T010 ∥ T011 ∥ T012 ∥ T013 (tests); T017 ∥ T014.
- US2: T023 en paralelo con T022 (archivos distintos; el payload ya está fijado en el contrato).
- Polish: T032 ∥ T033 ∥ T034 ∥ T035.

## Implementation Strategy

1. **MVP**: Phase 1 + Phase 2 + US1 → el local ya puede dar precuentas y cobrar como hoy.
2. **+ US2**: la Sala muestra qué mesas esperan para pagar (completa el valor operativo).
3. **+ US3 y US4**: protección contra cobrar una cifra distinta de la revisada, y reimpresión.
4. Polish y documentación en el mismo cambio, antes de dar la feature por cerrada.
