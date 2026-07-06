---
description: "Task list for feature 022 — Emisión y recepción de facturas en formato Facturae"
---

# Tasks: Emisión y recepción de facturas en formato Facturae

**Input**: Design documents from `specs/022-facturae-emision-recepcion/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/http.md](./contracts/http.md), [quickstart.md](./quickstart.md)

**Tests**: INCLUIDOS. La constitución (Principio IV, NON-NEGOTIABLE) exige test-first en aislamiento
multi-tenant, cálculo/mapeo de impuestos, y parseo/importación. Esas tareas se escriben antes que su
implementación y deben fallar primero (Red-Green-Refactor).

**Organización**: por historia de usuario (US1..US4) para entrega incremental e independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1..US4 (o sin etiqueta en Setup/Foundational/Polish)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependencias y fixtures compartidos.

- [X] T001 Instalar la librería de Facturae: `composer require josemmo/facturae-php` y verificar que
  `openssl`/`dom`/`libxml` están disponibles (ver [research.md](./research.md) R1).
- [X] T002 [P] Crear fixtures de test en `tests/Fixtures/facturae/`: un certificado `.p12` de prueba
  (autofirmado) con su contraseña, un XML Facturae 3.2.2 de proveedor **válido**, uno **inválido**
  (esquema roto), y uno **válido con firma no verificable** (ver [quickstart.md](./quickstart.md)).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: cambios de esquema y tipos que sostienen todas las historias. Hallazgo R7: estas
columnas están en `docs/03-modelo-datos.md` pero NO en el esquema — se crean aquí.

**⚠️ CRITICAL**: ninguna historia puede completarse hasta terminar esta fase.

- [X] T003 [P] Enum `App\Enums\CalificacionOperacion` (`S1`,`S2`,`N1`,`N2`) en `app/Enums/CalificacionOperacion.php`.
- [X] T004 [P] Enum `App\Enums\CausaExencion` (`E1`..`E6`, con helper al artículo LIVA) en `app/Enums/CausaExencion.php`.
- [X] T005 [P] Enum `App\Enums\OrigenCompra` (`manual`,`facturae`,`otro`) en `app/Enums/OrigenCompra.php`.
- [X] T006 [P] Enum `App\Enums\EstadoB2b` (`recibida`,`aceptada`,`rechazada`,`pagada`) en `app/Enums/EstadoB2b.php`.
- [X] T007 Migración `ALTER TABLE factura_lineas`: `calificacion_operacion` (default `S1`),
  `causa_exencion` (nullable), `mencion_legal` (nullable) en
  `database/migrations/****_add_menciones_to_factura_lineas_table.php` (ver [data-model.md](./data-model.md) §1).
- [X] T008 Migración `ALTER TABLE compras`: `origen` (default `manual`), `formato_recepcion`
  (nullable), `archivo_recibido_path` (nullable), `estado_b2b` (nullable), `estado_b2b_fecha`
  (nullable) + índice `(tenant_id, estado_b2b)` en
  `database/migrations/****_add_recepcion_facturae_to_compras_table.php` (ver data-model §2).
- [X] T009 [P] Añadir casts/fillable de las columnas nuevas al modelo `app/Models/FacturaLinea.php`
  (enums de calificación/causa).
- [X] T010 [P] Añadir casts/fillable de las columnas nuevas al modelo `app/Models/Compra.php`
  (enums de origen/estado_b2b, fecha).
- [X] T011 [P] Actualizar factories `database/factories/FacturaLineaFactory.php` y
  `database/factories/CompraFactory.php` con valores por defecto de las columnas nuevas (default
  `S1`/`manual`) para no romper tests existentes.

**Checkpoint**: esquema y tipos listos — las historias pueden empezar.

---

## Phase 3: User Story 1 - Exportar factura emitida como Facturae firmado + envío (Priority: P1) 🎯 MVP

**Goal**: desde una factura emitida, generar el XML Facturae 3.2.2 firmado (XAdES-EPES) con el
certificado del tenant, conservarlo asociado a la factura, permitir su descarga y **enviarlo
automáticamente por email** al cliente.

**Independent Test**: con un tenant con certificado válido y una factura emitida, generar el
Facturae y verificar validez de esquema 3.2.2, firma verificable, mapeo fiel de cabecera/líneas/
desglose/menciones, y envío por email — sin depender de recepción.

### Tests for User Story 1 (test-first ⚠️) — escribir y ver fallar antes de implementar

- [X] T012 [P] [US1] `tests/Feature/CertificadoTenantTest.php`: subida válida guarda archivo +
  password cifrada y expone titular/caducidad; password incorrecta y `.p12` caducado se rechazan;
  aislamiento (tenant A no ve/usa el certificado de B).
- [X] T013 [P] [US1] `tests/Feature/FacturaeGeneracionTest.php`: (a) el XML generado **valida contra
  el esquema Facturae 3.2.2**; (b) la firma XAdES-EPES es verificable con el certificado; (c) los
  importes/desglose del XML coinciden con la factura; (d) sin certificado → 422 (FR-007); (e)
  factura no emitida → 422 (FR-008); (f) **aislamiento**: A no puede generar/descargar el Facturae
  de B (Principio I).
- [X] T014 [P] [US1] En `FacturaeGeneracionTest`: casos de **menciones** — línea ISP (`S2`, cuota 0)
  y línea exenta `E5` se reflejan con calificación/mención correctas; régimen IGIC se refleja como
  IGIC, no IVA (SC-003, FR-004/005).
- [X] T015 [P] [US1] En `FacturaeGeneracionTest`: `POST` genera+envía email con XML+PDF adjuntos y
  registra `FacturaEvento` `envio_facturae`; cliente sin email o SMTP fallido → XML conservado y
  aviso, sin romper (FR-006a) — con `Mail::fake()`.

### Implementation for User Story 1

- [X] T016 [US1] `App\Support\CertificadoTenant` en `app/Support/CertificadoTenant.php`: guardar/leer
  `.p12` (disco `documentos` vía `AlmacenArchivos`) + password cifrada en `configuraciones` (grupo
  `certificado`, patrón `EmailTenant`/`Crypt`); validar (password abre el `.p12`, hay clave privada,
  no caducado); exponer titular/caducidad/estado (data-model §3).
- [X] T017 [US1] `ConfiguracionController@updateCertificado` en
  `app/Http/Controllers/ConfiguracionController.php` + ruta `PATCH /configuracion/certificado`
  (`configuracion.certificado.update`) en `routes/web.php` (contracts/http.md).
- [X] T018 [US1] Formulario del certificado en la vista de configuración
  `resources/views/configuracion/*` (subida `.p12` + password, muestra titular/caducidad; toastr
  para éxito/error; nunca devuelve la password al front).
- [X] T019 [US1] `App\Services\GeneradorFacturae` en `app/Services/GeneradorFacturae.php`: mapear
  `Factura`+`FacturaLinea`+`FacturaImpuesto` (incl. régimen congelado, recargo, IRPF y menciones
  S1/S2/N1/N2 + E1–E6 + mención legal) al modelo de `josemmo/facturae-php`, firmar XAdES-EPES con el
  certificado del tenant y devolver el XML firmado. Todo server-side (Principio III).
- [X] T020 [US1] Conservar el XML: guardar el Facturae firmado en disco privado `documentos` (por
  tenant), asociado a la factura, y registrar `FacturaEvento` `facturae_generado`. Reutilizar el
  archivo existente en descargas posteriores (FR-006).
- [X] T021 [US1] `App\Services\EnvioFacturae` en `app/Services/EnvioFacturae.php`: orquesta
  generar→conservar→enviar; adjunta el XML al email reutilizando `TenantMailer` + `App\Mail\FacturaMail`
  (extender `FacturaMail` para adjuntar también el XML). Degradación con aviso si no hay email/SMTP.
- [X] T022 [US1] `App\Http\Controllers\FacturaeController` en `app/Http/Controllers/FacturaeController.php`:
  `descargar` (GET), `generarYEnviar` (POST), `reenviar` (POST) + rutas en `routes/web.php`
  (`facturas.facturae.*`), resolviendo la factura **acotada por tenant** (memoria: no binding
  implícito). Precondiciones/errores 422 según contracts/http.md.
- [X] T023 [US1] Botones "Descargar Facturae" / "Generar y enviar Facturae" en el detalle de factura
  `resources/views/facturas/*` (solo facturas emitidas; toastr; aviso si falta certificado).

**Checkpoint**: US1 funcional e independiente — MVP entregable.

---

## Phase 4: User Story 2 - Importar Facturae de proveedor como compra (Priority: P2)

**Goal**: subir un XML Facturae recibido y crear una `compra` con `origen=facturae`, volcando
cabecera/líneas, asociando proveedor por NIF, guardando el XML y marcando aviso si la firma no
verifica.

**Independent Test**: subir un XML de proveedor válido y verificar la compra creada con datos/
importes correctos, `origen=facturae` y archivo conservado; rechazar inválidos; avisar firma/duplicado.

### Tests for User Story 2 (test-first ⚠️)

- [X] T024 [P] [US2] `tests/Feature/FacturaeRecepcionTest.php`: XML válido → crea `compra`
  `origen=facturae` con líneas/importes volcados, proveedor asociado por NIF, `archivo_recibido_path`
  guardado, `estado_b2b=recibida`; **aislamiento** (A no importa hacia/lee XML de B).
- [X] T025 [P] [US2] En `FacturaeRecepcionTest`: archivo inválido (esquema roto o importes
  incoherentes) → **rechazo** sin crear compra (FR-016); XML válido con **firma no verificable** →
  compra creada con **aviso** (FR-016a); reimportar mismo emisor+número+fecha → aviso duplicado, no
  crea otra (FR-018); proveedor inexistente → flujo de crear proveedor (FR-015).

### Implementation for User Story 2

- [X] T026 [US2] `App\Services\ImportadorFacturae` en `app/Services/ImportadorFacturae.php`: parsear
  el XML con `josemmo/facturae-php`, validar esquema/importes, comprobar firma (marcar aviso si no
  verifica), mapear a datos de compra + líneas, detectar duplicado (tenant+proveedor NIF+número+
  fecha), resolver/crear proveedor. No crea importes desde el cliente (Principio III).
- [X] T027 [US2] `App\Http\Controllers\CompraFacturaeController@importar` (POST
  `/compras/importar-facturae`) y `@descargar` (GET `/compras/{compra}/facturae`) en
  `app/Http/Controllers/CompraFacturaeController.php` + rutas en `routes/web.php`
  (`compras.facturae.*`), acotadas por tenant. Usa `AlmacenArchivos` para el XML recibido.
- [X] T028 [US2] UI en `resources/views/compras/*`: formulario de importar XML, enlace de descarga
  del XML original, y aviso visible de firma no verificable (toastr/badge). Flujo de crear proveedor
  cuando no existe.

**Checkpoint**: US1 y US2 funcionan de forma independiente.

---

## Phase 5: User Story 3 - Estado de ciclo B2B de la compra recibida (Priority: P3)

**Goal**: marcar `estado_b2b` (recibida/aceptada/rechazada/pagada) con fecha y filtrar el listado.

**Independent Test**: cambiar el estado de una compra `origen=facturae` y ver estado+fecha guardados;
filtrar el listado por estado B2B.

### Tests for User Story 3 (test-first ⚠️)

- [X] T029 [P] [US3] `tests/Feature/CompraEstadoB2bTest.php`: transición de estado actualiza
  `estado_b2b` y `estado_b2b_fecha=now()` (FR-019); valor inválido o compra no-facturae → 422;
  listado filtra por estado (FR-020); aislamiento por tenant.

### Implementation for User Story 3

- [X] T030 [US3] `CompraFacturaeController@cambiarEstadoB2b` (PATCH `/compras/{compra}/estado-b2b`,
  `compras.estado-b2b.update`) en `app/Http/Controllers/CompraFacturaeController.php` + ruta.
- [X] T031 [US3] En `app/Http/Controllers/CompraController.php` (index): soportar filtro por
  `estado_b2b`; y en `resources/views/compras/*`: selector de estado + columna/filtro en el listado.

**Checkpoint**: US1, US2 y US3 independientes.

---

## Phase 6: User Story 4 - Validación de identificación fiscal + VIES (Priority: P3)

**Goal**: validar NIF/CIF/NIE (dígito de control) de emisor/receptor antes de generar Facturae y
verificar NIF-IVA contra VIES para intracomunitarias.

**Independent Test**: NIF inválido bloquea la exportación; VIES devuelve válido/inválido y degrada
sin bloquear si está indisponible.

### Tests for User Story 4 (test-first ⚠️)

- [X] T032 [P] [US4] `tests/Unit/ValidadorIdentificacionFiscalTest.php`: NIF persona (módulo 23),
  NIE (`X/Y/Z`), CIF/entidad válidos e inválidos; casos límite.
- [X] T033 [P] [US4] `tests/Unit/VerificadorViesTest.php` con `Http::fake()`: NIF-IVA válido,
  inválido, y VIES indisponible (timeout/fallo → `verificado=false` sin excepción); cache por NIF-IVA.

### Implementation for User Story 4

- [X] T034 [P] [US4] `App\Support\ValidadorIdentificacionFiscal` en
  `app/Support/ValidadorIdentificacionFiscal.php` (lógica pura, sin dependencias) — NIF/NIE/CIF.
- [X] T035 [P] [US4] `App\Support\VerificadorVies` en `app/Support/VerificadorVies.php`: consulta
  VIES por HTTP (`Http`, timeout corto, cache por NIF-IVA), degrada con aviso (FR-022).
- [X] T036 [US4] Enganchar `ValidadorIdentificacionFiscal` como **gate** en `GeneradorFacturae`/
  `FacturaeController` (bloquear generación con NIF de emisor/receptor inválido, FR-021) y exponer la
  verificación VIES (endpoint/acción según contracts/http.md) para operaciones E5.

**Checkpoint**: las 4 historias independientes y funcionales.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T037 Retención/purga (Principio II, FR-024): incluir los XML Facturae emitidos y recibidos en
  el patrón de retención por tenant (reutilizar el mecanismo de `RetencionLogsTenant`/comando
  programado; NO inventar uno nuevo) para no conservar datos personales indefinidamente.
- [X] T038 [P] Actualizar `specs/022-facturae-emision-recepcion/data-model.md`/`contracts/http.md` si
  algo cambió al implementar, y marcar en `docs/03-modelo-datos.md` que las columnas de
  `factura_lineas`/`compras` ya están materializadas (dejaron de ser solo documentación).
- [X] T039 Ejecutar la validación de [quickstart.md](./quickstart.md) end-to-end (escenarios 1–7 +
  aislamiento) y confirmar SC-001..SC-007 de [spec.md](./spec.md).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup; **BLOQUEA** todas las historias (esquema + enums).
- **US1 (Phase 3)**: depende de Foundational. MVP.
- **US2 (Phase 4)**: depende de Foundational; independiente de US1 (usa columnas de `compras`).
- **US3 (Phase 5)**: depende de Foundational; usa la compra creada por US2 para tener sentido pleno,
  pero es testeable con una compra `origen=facturae` de factory.
- **US4 (Phase 6)**: depende de Foundational; refuerza US1 (gate NIF) pero se testea aislada.
- **Polish (Phase 7)**: depende de las historias deseadas completas.

### User Story Dependencies

- **US1 (P1)**: solo Foundational. Sin dependencia de otras historias.
- **US2 (P2)**: solo Foundational. Independiente de US1.
- **US3 (P3)**: solo Foundational; integra con la salida de US2 (compras recibidas).
- **US4 (P3)**: solo Foundational; el gate NIF toca `GeneradorFacturae` (US1) — si US1 no está,
  el validador y VIES se entregan igual y el gate se enchufa cuando US1 exista.

### Within Each User Story

- Tests (test-first en áreas críticas) escritos y en **rojo** antes de implementar.
- Modelos/enums/migraciones (Foundational) antes de servicios; servicios antes de controladores;
  controladores antes de vistas.

### Parallel Opportunities

- Setup: T002 en paralelo.
- Foundational: enums T003–T006 en paralelo; migraciones T007/T008 en paralelo; casts T009/T010 y
  factories T011 en paralelo tras las migraciones.
- Tests de cada historia marcados [P] en paralelo antes de su implementación.
- Con equipo: tras Foundational, US1/US2/US4 pueden avanzar en paralelo (US3 tras US2).

---

## Parallel Example: Foundational

```bash
# Enums en paralelo:
Task: "Enum CalificacionOperacion en app/Enums/CalificacionOperacion.php"
Task: "Enum CausaExencion en app/Enums/CausaExencion.php"
Task: "Enum OrigenCompra en app/Enums/OrigenCompra.php"
Task: "Enum EstadoB2b en app/Enums/EstadoB2b.php"

# Migraciones en paralelo (tablas distintas):
Task: "ALTER factura_lineas (menciones)"
Task: "ALTER compras (recepción)"
```

---

## Implementation Strategy

### MVP First (US1)

1. Phase 1 Setup → 2. Phase 2 Foundational (CRÍTICO) → 3. Phase 3 US1 → **PARAR y VALIDAR** el
   Facturae firmado + envío de forma independiente → demo/deploy (MVP).

### Incremental Delivery

Foundational → US1 (MVP: emitir/firmar/enviar) → US2 (recibir) → US3 (estado B2B) → US4 (validación
fiscal/VIES) → Polish (retención + docs + quickstart). Cada historia añade valor sin romper las
anteriores.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- Áreas test-first obligatorias (Principio IV): aislamiento multi-tenant (T012/T013/T024/T029),
  mapeo de importes/menciones factura→Facturae (T013/T014), parseo Facturae→compra (T024/T025), y
  validación NIF (T032). No marcar completas sin sus tests en verde.
- Ningún importe proviene del cliente: se leen de la factura emitida (Principio III).
- Certificado `.p12` + password y los XML son datos sensibles/personales → cifrado en reposo y
  retención/purga por tenant (Principios I y II).
- Commit tras cada tarea o grupo lógico; parar en cada checkpoint para validar la historia.
