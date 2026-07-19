---

description: "Task list — Verifactu: registro y remisión de facturas a la AEAT (feature 032)"
---

# Tasks: Verifactu — registro y remisión de facturas a la AEAT

**Input**: Design documents from `/specs/032-verifactu-registro-aeat/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: **OBLIGATORIOS y test-first**. El Principio IV de la constitución (NON-NEGOTIABLE) nombra explícitamente el *encadenamiento Verifactu* y el *aislamiento multi-tenant*: los tests se escriben antes, deben fallar primero (Red), y luego se implementa hasta que pasen (Green). Ninguna tarea de esas áreas se marca completa sin sus tests en verde.

**Organization**: Agrupadas por user story para poder implementar y validar cada una de forma independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: A qué user story pertenece (US1, US2, US3)
- Rutas de archivo exactas en cada descripción

## Path Conventions

Monolito Laravel (ver plan.md → Structure Decision): `app/` para código, `resources/views/` para Blade, `tests/Unit` y `tests/Feature` para tests, `database/migrations/` para esquema.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Dependencias y verificación de la especificación oficial antes de escribir lógica normativa.

- [X] T001 Verificar contra la documentación técnica oficial de la AEAT y volcar a `docs/02-facturacion-espana.md` (§1): orden exacto de campos de la huella de alta y de anulación, formato de `FechaHoraHusoGenRegistro`, decimales de importes, y mayúsculas/minúsculas del hash. Citar fuente BOE/AEAT (Principio II exige doc antes que código; research R1)
- [X] T002 Verificar y documentar en `docs/02-facturacion-espana.md`: URL base del servicio de cotejo del QR por entorno, nombre/orden de parámetros y rango de tamaño impreso del QR (research R4)
- [X] T003 Verificar y documentar en `docs/02-facturacion-espana.md`: endpoints del web service AEAT (preproducción y producción), WSDL vigente y modo de autenticación por certificado (research R3)
- [X] T004 Añadir a `composer.json` la librería de generación de QR en PHP puro (sin exigir `ext-gd`/`imagick`, Principio V) y ejecutar `composer require`
- [X] T005 Decidir y documentar en `research.md` la vía de transporte al web service AEAT (`ext-soap` vs Guzzle + TLS mutuo) según disponibilidad en el hosting objetivo

**Checkpoint**: la especificación normativa está confirmada y documentada; ya se puede codificar sin inventar formatos.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Esquema, enums y configuración que TODAS las user stories necesitan. Bloquea Phase 3+.

- [X] T006 [P] Crear enum `App\Enums\VerifactuEstado` (`pendiente`, `registrada`, `enviada`, `error`) en `app/Enums/VerifactuEstado.php`
- [X] T007 [P] Crear enum `App\Enums\EntornoVerifactu` (`pruebas`, `produccion`) en `app/Enums/EntornoVerifactu.php`
- [X] T008 [P] Crear excepción `App\Exceptions\VerifactuNoRegistrableException` en `app/Exceptions/VerifactuNoRegistrableException.php`
- [X] T009 [P] Crear excepción `App\Exceptions\CadenaVerifactuRotaException` en `app/Exceptions/CadenaVerifactuRotaException.php`
- [X] T010 Crear migración `database/migrations/2026_07_XX_000000_add_entorno_to_facturas_verifactu.php`: columna `verifactu_entorno` (varchar(12) nullable) + índice `(tenant_id, registrada_at, id)` en `facturas` (data-model §1)
- [X] T011 Actualizar `app/Models/Factura.php`: añadir `verifactu_estado` y `verifactu_entorno` a `casts()` (enums de T006/T007) y a `$fillable`, más helpers de lectura (`tieneRegistroVerifactu()`, `verifactuReintentable()`)
- [X] T012 Crear `App\Support\VerifactuTenant` en `app/Support/VerifactuTenant.php` con las claves `verifactu.activo` (bool, default `false`) y `verifactu.entorno` (default `pruebas`), siguiendo el patrón de `app/Support/ConfigTenant.php` y `app/Support/CertificadoTenant.php` (research R5)
- [X] T013 [P] Test de aislamiento multi-tenant de la configuración en `tests/Feature/VerifactuTenantConfigTest.php`: el flag y el entorno de un tenant no se leen ni afectan a otro (Principio I)

**Checkpoint**: esquema, enums y configuración listos. Las user stories pueden arrancar.

---

## Phase 3: User Story 1 — Registro Verifactu inalterable y encadenado (Priority: P1) 🎯 MVP

**Goal**: Al emitir una factura con `verifactu.activo`, se sella su registro (huella SHA-256 encadenada por tenant + XML) de forma atómica con la emisión.

**Independent Test**: Emitir varias facturas en un tenant y verificar que cada una queda con huella no vacía, que cada `huella_anterior` coincide con la `huella` previa, que el primer registro no tiene anterior, y que el XML contiene los campos mínimos. Sin necesidad de conexión con la AEAT.

### Tests primero (Red) — Principio IV NON-NEGOTIABLE

- [X] T014 [P] [US1] Test unitario del algoritmo de huella en `tests/Unit/HuellaVerifactuTest.php`, usando el **vector de prueba oficial de la AEAT** confirmado en T001 (no un valor inventado); cubre alta y anulación
- [X] T015 [P] [US1] Test de sellado al emitir en `tests/Feature/RegistroVerifactuTest.php`: emitir deja `huella`, `registro_xml`, `verifactu_estado = registrada`, `registrada_at` y `verifactu_entorno` poblados, más un evento `verifactu_alta` con huella
- [X] T016 [P] [US1] Test de atomicidad en `tests/Feature/RegistroVerifactuTest.php`: si el sellado falla, la emisión se revierte por completo (no queda factura emitida ni eslabón parcial) — FR-001
- [X] T017 [P] [US1] Test de encadenamiento en `tests/Feature/CadenaVerifactuTest.php`: primer eslabón sin huella anterior; eslabón N enlaza con N−1; sin huecos (invariantes 1–3 de data-model §4)
- [X] T018 [P] [US1] Test de concurrencia en `tests/Feature/CadenaVerifactuTest.php`: dos emisiones simultáneas del mismo tenant no comparten `huella_anterior` ni bifurcan la cadena (FR-004)
- [X] T019 [P] [US1] Test de aislamiento en `tests/Feature/VerifactuAislamientoTenantTest.php`: con ≥2 tenants, ninguna huella de uno aparece como `huella_anterior` del otro (Principio I, FR-017)
- [X] T020 [P] [US1] Test del flag en `tests/Feature/VerifactuFlagTest.php`: con `verifactu.activo = false`, emitir no genera huella, ni QR, ni evento Verifactu, ni intento de envío (FR-000)
- [X] T021 [P] [US1] Test de los tres tipos de documento en `tests/Feature/RegistroVerifactuTest.php`: ordinaria, rectificativa y simplificada (POS) encadenan en la misma cadena del tenant (FR-007)
- [X] T022 [P] [US1] Test de bloqueo por NIF de emisor inválido en `tests/Feature/RegistroVerifactuTest.php` (FR-018), y de que el entorno incoherente con la cadena existente lanza `CadenaVerifactuRotaException` (FR-020)
- [X] T022b [P] [US1] Test de inmutabilidad en `tests/Feature/CadenaVerifactuTest.php` (FR-008): una factura ya registrada no se puede editar ni borrar, y ningún eslabón previo de la cadena se altera al emitir nuevas facturas. Exigido por el Development Workflow de la constitución (verificar inmutabilidad de emitidas en revisión)

### Implementación (Green)

- [X] T023 [US1] Implementar `App\Support\HuellaVerifactu` en `app/Support/HuellaVerifactu.php`: métodos `alta()` y `anulacion()`, cálculo SHA-256 puro sin dependencias de BD (contrato en `contracts/registro-verifactu.md`)
- [X] T024 [US1] Implementar `App\Services\GeneradorXmlVerifactu` en `app/Services/GeneradorXmlVerifactu.php`: mapea factura + líneas + desglose al XML de la Orden HAC/1177/2024, incluyendo calificación de operación (S1/S2/N1/N2) y causa de exención (E1–E6) por desglose, agnóstico al régimen (IVA/IGIC/IPSI) — FR-005
- [X] T025 [US1] Implementar `App\Services\RegistroVerifactu` en `app/Services/RegistroVerifactu.php`: método `registrar()` que lee el último eslabón del tenant con bloqueo pesimista (`lockForUpdate()`, research R2), calcula la huella, genera el XML, persiste los campos y crea el evento `verifactu_alta`
- [X] T026 [US1] Integrar en `app/Services/EmisorFacturas.php`: invocar `RegistroVerifactu::registrar()` dentro de la transacción existente de `emitir()`, solo si `VerifactuTenant::activo()`; mantener intacto el comportamiento actual cuando el flag está apagado
- [X] T027 [US1] Verificar que el flujo del POS (`app/Services/RegistroTicket.php`) hereda el sellado a través de `EmisorFacturas` sin duplicar lógica (Principio III: un único lugar de cálculo)

**Checkpoint**: US1 entregable de forma independiente — hay registro Verifactu válido y encadenado aunque todavía no haya QR ni envío. **Este es el MVP.**

---

## Phase 4: User Story 2 — QR de cotejo AEAT y "VERI*FACTU" en el PDF (Priority: P2)

**Goal**: Las facturas registradas muestran el QR oficial de cotejo y el texto "VERI*FACTU" en A4 y en ticket 80 mm.

**Independent Test**: Emitir una factura con el flag activo y generar su PDF en ambos formatos; comprobar que aparecen QR y texto, y que el contenido del QR es la URL oficial con los datos del registro.

**Dependency**: requiere US1 (sin registro no hay datos para el QR).

### Tests primero

- [X] T028 [P] [US2] Test de composición de la URL de cotejo en `tests/Unit/QrVerifactuTest.php`: parámetros y orden según lo confirmado en T002, y URL base distinta por entorno
- [X] T029 [P] [US2] Test de presencia en PDF en `tests/Feature/VerifactuQrPdfTest.php`: QR + texto "VERI*FACTU" en A4 y en ticket 80 mm (FR-009)
- [X] T030 [P] [US2] Test de casos negativos en `tests/Feature/VerifactuQrPdfTest.php`: factura en borrador y factura emitida con el flag apagado no muestran QR ni texto
- [X] T030b [P] [US2] Test del caso positivo de FR-010 en `tests/Feature/VerifactuQrPdfTest.php`: una factura **registrada pero con envío en `error`** muestra igualmente el QR y el texto (el QR no depende del acuse de la AEAT)

### Implementación

- [X] T031 [US2] Implementar `App\Support\QrVerifactu` en `app/Support/QrVerifactu.php`: `url()` (compone la URL oficial por entorno) e `imagen()` (renderiza el QR como data URI con la librería de T004) — contrato en `contracts/qr-cotejo.md`
- [X] T032 [US2] Persistir `qr_contenido` en el momento del sellado, dentro de `app/Services/RegistroVerifactu.php` (data-model §1: se guarda para ser auditable y no recomponerlo en cada render)
- [X] T033 [P] [US2] Crear el partial compartido `resources/views/partials/verifactu-qr.blade.php` (QR + texto "VERI*FACTU"), condicionado a que la factura tenga registro
- [X] T034 [US2] Incluir el partial en `resources/views/facturas/pdf.blade.php` (A4), respetando el tamaño mínimo del QR confirmado en T002
- [X] T035 [US2] Incluir el partial en `resources/views/facturas/ticket-80mm.blade.php`, adaptando el tamaño al ancho de 80 mm sin bajar del mínimo normativo

**Checkpoint**: US1 + US2 entregan una factura completa y cotejable, aún sin remisión.

---

## Phase 5: User Story 3 — Remisión en tiempo real a la AEAT (Priority: P3)

**Goal**: El registro se remite al web service de la AEAT; el estado refleja aceptación/rechazo; los fallos son reintentables sin tocar la cadena.

**Independent Test**: Con envío mockeado, comprobar que aceptación deja `enviada`, rechazo/timeout deja `error` con motivo, y que el reintento reenvía el mismo registro sin alterar huella ni añadir eslabón.

**Dependency**: requiere US1 (sin registro sellado no hay nada que remitir).

### Tests primero

- [X] T036 [P] [US3] Test de mapeo de respuestas en `tests/Feature/RemisionVerifactuTest.php`: aceptado → `enviada`; rechazo funcional → `error` (`tipoError = rechazo`); timeout/TLS → `error` (`tipoError = transporte`), con evento y detalle en cada caso (contrato `contracts/aeat-webservice.md`)
- [X] T037 [P] [US3] Test de invariante crítica en `tests/Feature/RemisionVerifactuTest.php`: ningún resultado de envío modifica `huella`, `huella_anterior`, `registro_xml` ni `registrada_at` (FR-014)
- [X] T038 [P] [US3] Test de que un fallo de la AEAT no bloquea la emisión en `tests/Feature/RemisionVerifactuTest.php`: la factura queda emitida y registrada igualmente (clarify: emitir nunca se bloquea)
- [X] T039 [P] [US3] Test de reintento en `tests/Feature/RemisionVerifactuTest.php`: reintento manual y automático reenvían el mismo registro sellado, sin crear eslabón nuevo ni recalcular huella (FR-013)
- [X] T040 [P] [US3] Test de certificado ausente en `tests/Feature/RemisionVerifactuTest.php`: con el flag activo y sin certificado, la factura se emite y registra, y el envío queda en `error` (FR-018)
- [X] T041 [P] [US3] Test del registro de anulación en `tests/Feature/RemisionVerifactuTest.php`: anular genera un registro de anulación encadenado y lo remite (FR-015)

### Implementación

- [X] T042 [US3] Implementar `App\Services\RemisorVerifactu` en `app/Services/RemisorVerifactu.php`: construye el envío al endpoint del entorno configurado, autentica con TLS mutuo usando `CertificadoTenant::paraFirmar()`, parsea la respuesta y devuelve `ResultadoRemision` (contrato `contracts/aeat-webservice.md`); transporte inyectable para poder mockearlo en tests
- [X] T043 [US3] Crear `App\Jobs\RemitirRegistroVerifactu` en `app/Jobs/RemitirRegistroVerifactu.php`: job en cola que invoca el remisor, actualiza `verifactu_estado` y escribe el evento (`verifactu_enviado` / `verifactu_error`)
- [X] T044 [US3] Despachar el job con `DB::afterCommit()` desde el flujo de emisión en `app/Services/EmisorFacturas.php`, para enviar solo lo confirmado en base de datos (research R3)
- [X] T045 [US3] Implementar `RegistroVerifactu::registrarAnulacion()` en `app/Services/RegistroVerifactu.php`: encadenamiento con el conjunto de campos del registro de anulación y evento `verifactu_anulacion` (research R1/R7)
- [X] T045b [US3] Implementar el **disparador de anulación** de una factura emitida (hoy inexistente: `EstadoFactura::Anulada` existe pero ninguna ruta lleva a él): acción en `app/Http/Controllers/FacturaController.php` + ruta en `routes/web.php`, restringida a los supuestos que la normativa permite y que invoca `registrarAnulacion()`. Ver nota de alcance de FR-015
- [X] T046 [US3] Crear el comando `App\Console\Commands\VerifactuReintentar` en `app/Console/Commands/VerifactuReintentar.php` (`verifactu:reintentar`): reencola las facturas en `verifactu_estado = error`, con tope de intentos (research R6)
- [X] T047 [US3] Registrar el comando en `bootstrap/app.php` → `withSchedule`, junto a los ya existentes (`logs:purgar`, `facturae:purgar`, `importaciones:purgar`)
- [X] T048 [US3] Crear `App\Http\Controllers\VerifactuController` en `app/Http/Controllers/VerifactuController.php` con la acción de reintento manual, y su ruta en `routes/web.php` (autorizada y con scope de tenant)
- [X] T049 [US3] Mostrar el estado Verifactu de la factura y la acción de reintento en la UI de facturas (FR-019), usando toastr para las notificaciones (convención del proyecto, nunca alerts ad-hoc)

**Checkpoint**: feature funcionalmente completa (registro + QR + remisión + reintento + anulación).

---

## Phase 6: Configuración de usuario e integración

- [X] T050 Añadir la sección Verifactu (flag `activo` + selector de `entorno`) a la vista de configuración del tenant en `resources/views/configuracion/`, reutilizando el patrón de las secciones existentes
- [X] T051 Impedir en backend el cambio de entorno cuando el tenant ya tiene cadena iniciada (FR-020), con mensaje claro al usuario
- [X] T052 [P] Test de la configuración en `tests/Feature/VerifactuTenantConfigTest.php`: activar/desactivar el flag y bloqueo del cambio de entorno con cadena iniciada

---

## Phase 7: Polish & Documentación (las 4 capas — obligatorio para cerrar)

- [X] T053 [P] Actualizar `docs/03-modelo-datos.md`: nueva columna `verifactu_entorno` en la tabla `facturas` y nuevos `tipo_evento` de `factura_eventos` (`verifactu_alta`, `verifactu_anulacion`, `verifactu_enviado`, `verifactu_error`)
- [X] T054 [P] Revisar `docs/02-facturacion-espana.md` §1: confirmar que refleja lo implementado (huella, QR, endpoints, entornos) tras T001–T003
- [X] T055 [P] Crear la guía in-app `resources/views/ayuda/verifactu.blade.php` (capa 3): qué es Verifactu, cómo activarlo, qué significa cada estado, cómo reintentar un envío
- [X] T056 [P] Crear `resources/ia/conocimiento/verifactu.md` (capa 4, FR-013 de la feature 030): un archivo nuevo por módulo, sin tocar el resto (invariante SC-007 de la 030)
- [X] T057 [P] Anotar en `docs/04-front-guidelines.md` cualquier convención de UI reutilizable que surja (p. ej. presentación del bloque QR en PDF, badge de estado Verifactu)
- [X] T058 Ejecutar la suite completa (`php artisan test`) y validar los escenarios manuales de [quickstart.md](./quickstart.md) §2

---

## Dependencies

```
Phase 1 (Setup: verificación normativa)
        │  T001 bloquea T023 (no se codifica el hash sin el formato oficial)
        │  T002 bloquea T031 · T003 bloquea T042 · T004 bloquea T031
        ▼
Phase 2 (Foundational: enums, migración, config)  ← BLOQUEA todas las stories
        ▼
        ├────────────▶ Phase 3 · US1 (P1) 🎯 MVP — registro + cadena
        │                    │
        │                    ├──▶ Phase 4 · US2 (P2) — QR en PDF   (depende de US1)
        │                    │
        │                    └──▶ Phase 5 · US3 (P3) — remisión AEAT (depende de US1)
        ▼
Phase 6 (configuración de usuario)  ← depende de Phase 2 (T012)
        ▼
Phase 7 (documentación 4 capas + validación final)
```

**Notas de dependencia**:
- **US2 y US3 son independientes entre sí**: ambas cuelgan de US1 y pueden desarrollarse en paralelo por personas distintas.
- Dentro de cada story, los tests preceden a la implementación (Principio IV).
- T032 toca `RegistroVerifactu` (creado en T025), por eso no está marcada `[P]` pese a pertenecer a US2.
- T044 y T026 tocan ambas `EmisorFacturas.php`: no paralelizables entre sí.
- T045b depende de T045 (el disparador necesita el método de registro de anulación) y toca `FacturaController.php` + `routes/web.php`, igual que T048: coordinar para evitar conflictos.

## Parallel Execution Examples

**Phase 2** — enums y excepciones, archivos distintos:
```
T006, T007, T008, T009  (en paralelo)
```

**Phase 3 (US1)** — toda la batería de tests antes de implementar:
```
T014, T015, T016, T017, T018, T019, T020, T021, T022, T022b  (en paralelo)
```

**Phase 5 (US3)** — tests de remisión:
```
T036, T037, T038, T039, T040, T041  (en paralelo)
```

**Phase 7** — documentación, archivos distintos:
```
T053, T054, T055, T056, T057  (en paralelo)
```

**Entre stories** (tras completar US1): un desarrollador toma Phase 4 (US2) y otro Phase 5 (US3).

## Implementation Strategy

### MVP (mínimo entregable con valor)

**Phase 1 + Phase 2 + Phase 3 (US1)** = registro Verifactu válido, encadenado, aislado por tenant y atómico con la emisión. Es lo que cumple el núcleo normativo y lo que exige la constitución; QR y remisión se pueden añadir después sin rehacer nada.

### Entrega incremental

1. **Incremento 1 (MVP)**: US1 → hay cadena de registros verificable.
2. **Incremento 2**: US2 → la factura impresa ya es cotejable por el receptor.
3. **Incremento 3**: US3 → remisión efectiva a la AEAT con reintentos.
4. **Cierre**: Phase 6 (configuración) + Phase 7 (4 capas de documentación).

### Riesgo principal

La exactitud del **algoritmo de huella** y del **formato del XML/QR**: si no coinciden con la especificación oficial, la AEAT rechaza y la cadena queda inservible. Por eso T001–T003 (verificación contra fuente oficial) están **antes** de cualquier código, y T014 exige un **vector de prueba oficial** en vez de un valor generado por nosotros.

## Resumen

| Fase | Tareas | Story |
|------|--------|-------|
| 1 · Setup | T001–T005 (5) | — |
| 2 · Foundational | T006–T013 (8) | — |
| 3 · Registro y cadena | T014–T027 + T022b (15) | US1 (P1) 🎯 |
| 4 · QR en PDF | T028–T035 + T030b (9) | US2 (P2) |
| 5 · Remisión AEAT | T036–T049 + T045b (15) | US3 (P3) |
| 6 · Configuración | T050–T052 (3) | — |
| 7 · Documentación | T053–T058 (6) | — |
| **Total** | **61** | |
