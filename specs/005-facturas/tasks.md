---
description: "Task list — Facturas (núcleo mínimo)"
---

# Tasks: Facturas — emisión de facturas ordinarias (núcleo mínimo)

**Input**: Design documents from `/specs/005-facturas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: INCLUIDOS. La constitución (Principio IV, NON-NEGOTIABLE) exige test-first para cálculo
de impuestos, numeración de series y aislamiento multi-tenant. El resto (UI, PDF) lleva tests
Feature estándar pero no estrictamente test-first.

**Organization**: por historia de usuario (US1 P1, US2 P2, US3 P2), cada una entregable e
independientemente testeable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede correr en paralelo (archivo distinto, sin dependencias pendientes)
- Rutas exactas incluidas en cada tarea

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependencia de PDF y andamiaje.

- [X] T001 Instalar dependencia PDF: `composer require barryvdh/laravel-dompdf` y verificar auto-discovery (sin publicar config salvo necesidad)
- [X] T002 [P] Crear enums en `app/Enums/`: `EstadoFactura.php`, `TipoFactura.php`, `FormaPago.php`, `TipoImpuesto.php` (valores según data-model.md)
- [X] T003 [P] Extender `app/Support/TiposImpositivos.php` con: (a) helper del recargo de equivalencia por tipo de IVA (21→5.2, 10→1.4, 4→0.5, 0→0); (b) helper de validación de tipo que, ante `validosPara()==null` (IPSI), acepte cualquier valor en 0–100 en lugar de rechazar

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: esquema de datos, modelos, serie por defecto y servicios de dominio. Bloquea todas las historias.

**⚠️ CRITICAL**: ninguna historia puede empezar hasta completar esta fase.

### Migraciones (data-model.md)

- [X] T004 [P] Migración `create_series_table` en `database/migrations/` (tenant_id index, codigo, tipo, ejercicio, proximo_numero, formato, activa; único `(tenant_id, codigo, ejercicio)`)
- [X] T005 [P] Migración `create_facturas_table` (cabecera + totales + `regimen_impositivo`/`aplica_recargo` congelados + columnas Verifactu y ciclo B2B **nullable sin usar** + softDeletes; índices de data-model.md; `numero`/`numero_completo` nullable)
- [X] T006 [P] Migración `create_factura_lineas_table` (fk factura cascade, articulo_id nullable, copia de datos, base/cuotas, orden)
- [X] T007 [P] Migración `create_factura_impuestos_table` (fk factura cascade, tipo_impuesto, porcentaje, base_imponible, cuota)

### Modelos (BelongsToTenant)

- [X] T008 [P] Modelo `app/Models/Serie.php` (BelongsToTenant, casts, relación `facturas`)
- [X] T009 [P] Modelo `app/Models/FacturaLinea.php` (BelongsToTenant, casts decimales, relación `factura`, `articulo`)
- [X] T010 [P] Modelo `app/Models/FacturaImpuesto.php` (BelongsToTenant, casts, relación `factura`)
- [X] T011 Modelo `app/Models/Factura.php` (BelongsToTenant + SoftDeletes; casts de enums/decimales/fechas; relaciones `hasMany` lineas/impuestos, `belongsTo` cliente/serie/tenant) — depende de T008–T010

### Serie por defecto (seed)

- [X] T012 Seeder `database/seeders/SerieSeeder.php` que crea la serie ordinaria por defecto (`codigo=F`, tipo ordinaria) por tenant, y engancharlo en `database/seeders/DatabaseSeeder.php` + creación de serie al dar de alta un tenant (factory/observer según patrón existente)

### Factories

- [X] T013 [P] Factories en `database/factories/`: `SerieFactory`, `FacturaFactory`, `FacturaLineaFactory`, `FacturaImpuestoFactory`

### Servicios de dominio (TEST-FIRST — Principio IV)

- [X] T014 [P] **Test-first** `tests/Unit/CalculadoraFacturaTest.php` cubriendo todos los casos de `contracts/calculadora-factura.md` (una línea 21%, varios tipos, recargo solo IVA, IRPF 15% y null, descuento 100%, redondeo cuadra, IGIC 7%) — deben FALLAR primero
- [X] T015 Implementar `app/Services/CalculadoraFactura.php` hasta que T014 pase (única fuente de verdad de importes; fórmulas de research.md D4)
- [X] T016 [P] **Test-first** `tests/Unit/NumeradorFacturasTest.php` (secuencia sin huecos, formato `F-2026-0001`, concurrencia con lock — el caso de concurrencia se ejecuta/asserta solo bajo MySQL, `skip` en SQLite; ver contracts) — deben FALLAR primero
- [X] T017 Implementar `app/Services/NumeradorFacturas.php` con `lockForUpdate` hasta que T016 pase (diseñado; no se invoca desde rutas en esta feature)

**Checkpoint**: esquema, modelos, serie por defecto y servicios listos y en verde.

---

## Phase 3: User Story 1 - Crear una factura ordinaria en borrador (Priority: P1) 🎯 MVP

**Goal**: crear/editar una factura ordinaria en borrador con líneas (catálogo o libres), cálculo
server-side de totales/desglose y persistencia, desde una vista full-page con preview en vivo.

**Independent Test**: crear una factura con cliente y varias líneas de distintos tipos y verificar
que se persiste en borrador (sin nº) con base/cuotas/IRPF/total correctos y desglose por tipo.

### Tests (test-first en aislamiento; Feature de CRUD)

- [X] T018 [P] [US1] **Test-first** `tests/Feature/FacturaTenantIsolationTest.php`: con ≥2 tenants, no se puede crear/editar/borrar/leer factura de otro tenant (store/update/destroy/edit) — FALLA primero
- [X] T019 [P] [US1] `tests/Feature/FacturaCrudTest.php`: store crea borrador con totales calculados en servidor (ignora importes del cliente); **asserta que `regimen_impositivo` (del tenant) y `aplica_recargo` (del cliente) quedan congelados**; validación (sin cliente / sin líneas → 422; tipo fuera de régimen IVA/IGIC → 422); update recalcula; destroy solo borrador; edit precarga

### Implementación backend

- [X] T020 [P] [US1] `app/Http/Requests/StoreFacturaRequest.php` (reglas de data-model.md: cliente del tenant, ≥1 línea válida, tipo_impositivo ∈ lista del régimen para IVA/IGIC o 0–100 libre para IPSI, descuento/irpf rango, forma_pago, fechas)
- [X] T021 [P] [US1] `app/Http/Requests/UpdateFacturaRequest.php` (idem + solo borrador)
- [X] T022 [US1] `app/Http/Controllers/FacturaController.php` — `create`, `store`, `edit`, `update`, `destroy`: resolución manual con `findOrFail` bajo TenantScope; congelar `regimen_impositivo` (tenant) y `aplica_recargo` (cliente); usar `CalculadoraFactura`; persistir cabecera + lineas + `factura_impuestos` en transacción; guardar en `borrador` sin número; autorizar edición/borrado solo en borrador — depende de T015, T020, T021
- [X] T023 [US1] Registrar rutas en `routes/web.php` (`facturas.create/store/edit/update/destroy`) bajo `['auth','tenant.context']` según `contracts/http-routes.md`
- [X] T024 [US1] Autocompletar vencimiento en backend con `factura.dias_vencimiento` (config, default 30) al no venir del form

### Implementación front (vista full-page + preview — prioridad de la feature)

- [X] T025 [US1] Vista `resources/views/facturas/create.blade.php` (full-page, NO modal): secciones emisor/tenant, selector cliente, fechas+forma de pago, tabla de líneas editables, IRPF/recargo, totales — aplicar skill `frontend-design` dentro de NexaDash; leer `docs/04-front-guidelines.md`
- [X] T026 [US1] Parcial `resources/views/facturas/_form.blade.php` si conviene dividir las secciones del formulario (depende de T025, misma área de vista)
- [X] T027 [US1] JS `public/js/facturas-form.js`: alta/baja de líneas dinámicas, autocompletado desde `articulos.index` (JSON) y `clientes.index` (JSON), recálculo de **preview** client-side (solo UX; el importe válido es el del backend)
- [X] T028 [US1] Enlace "Facturas" en `resources/views/partials/sidebar.blade.php`

**Checkpoint**: US1 funcional — se puede crear/editar/borrar borradores con cálculo correcto desde la vista full-page.

---

## Phase 4: User Story 2 - Listar y gestionar facturas en borrador (Priority: P2)

**Goal**: listado DataTable de facturas del tenant con acciones básicas (ver, editar, borrar, PDF).

**Independent Test**: con varias facturas, el listado muestra solo las del tenant con
identificador/cliente/fecha/total/estado, con búsqueda/orden y acciones por fila correctas.

- [X] T029 [P] [US2] Añadir método `index` (HTML + JSON `wantsJson`) a `FacturaController` con el payload de `contracts/http-routes.md` (identificador "Borrador"/número, cliente, fecha, total, estado, urls) + bloque `totales`
- [X] T030 [US2] Ruta `facturas.index` en `routes/web.php`
- [X] T031 [US2] Vista `resources/views/facturas/index.blade.php` (DataTable + cards, patrón `clientes/index.blade.php` y `articulos/index.blade.php`), botón "Nueva factura" → `facturas.create`; acción "ver" por fila abre `facturas.edit` (no hay vista `show`); columna identificador ("Borrador"/número)
- [X] T032 [US2] Ampliar `tests/Feature/FacturaCrudTest.php` (o Isolation) para el listado JSON: solo facturas del tenant, campos correctos, acciones

**Checkpoint**: US1 + US2 operativas de forma independiente.

---

## Phase 5: User Story 3 - Visualizar el PDF de la factura (Priority: P2)

**Goal**: generar/ver el PDF de una factura con emisor+logo, cliente, líneas, desglose y total.

**Independent Test**: abrir el PDF de una factura y verificar que refleja los datos persistidos;
un usuario de otro tenant recibe 404.

- [X] T033 [P] [US3] `tests/Feature/FacturaPdfTest.php`: el PDF responde 200 para el tenant propietario y refleja datos; cross-tenant → 404
- [X] T034 [US3] Plantilla `resources/views/facturas/pdf.blade.php` (formato factura española: emisor con `logo_path`, cliente, líneas, desglose por tipo, IRPF/recargo, total; opcional `factura.pie_legal`)
- [X] T035 [US3] Método `pdf` en `FacturaController` (dompdf, resolución manual bajo scope, inline) + ruta `facturas.pdf` en `routes/web.php`
- [X] T036 [US3] Botón/acción "PDF" por fila en `resources/views/facturas/index.blade.php`

**Checkpoint**: las tres historias funcionan de forma independiente.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T037 [P] Actualizar `docs/04-front-guidelines.md` con patrones reutilizables surgidos (líneas editables, preview de documento, totales sticky)
- [X] T038 [P] Revisar `docs/03-modelo-datos.md` por si el detalle implementado difiere de lo documentado (nota "Facturae-ready" opcional)
- [X] T039 Pasar `./vendor/bin/pint` (formato) y revisar consultas bajo TenantScope
- [X] T040 Ejecutar validación de `quickstart.md` (suite completa + recorrido manual)

---

## Dependencies & Execution Order

- **Setup (Ph1)**: sin dependencias.
- **Foundational (Ph2)**: tras Setup. BLOQUEA todas las historias. T011 depende de T008–T010; T015 de T014; T017 de T016.
- **US1 (Ph3)**: tras Foundational. T022 depende de T015/T020/T021. Front (T025–T028) tras T022/T023.
- **US2 (Ph4)**: tras Foundational; reutiliza el controller de US1 (idealmente tras US1).
- **US3 (Ph5)**: tras Foundational; el botón PDF (T036) toca la vista de US2 (T031).
- **Polish (Ph6)**: al final.

### Parallel Opportunities

- Setup: T002, T003 en paralelo.
- Migraciones T004–T007 y modelos T008–T010 en paralelo; factories T013 en paralelo.
- Tests test-first T014/T016 en paralelo; T018/T019 en paralelo.
- FormRequests T020/T021 en paralelo.

---

## Implementation Strategy

### MVP First (US1)

1. Ph1 Setup → 2. Ph2 Foundational (crítico) → 3. Ph3 US1 → **validar** crear/editar borrador con cálculo correcto → demo.

### Incremental

US1 (crear) → US2 (listado) → US3 (PDF). Cada historia añade valor sin romper la anterior.

---

## Notes

- [P] = archivos distintos, sin dependencias pendientes.
- Test-first obligatorio en T014/T016/T018 (cálculo, numeración, aislamiento) — deben fallar antes de implementar.
- Resolver modelos con `findOrFail` bajo TenantScope, nunca binding implícito (memoria `project_tenant_route_binding`).
- Notificaciones vía toastr/flash (CLAUDE.md), nunca alerts ad-hoc.
- Commit tras cada tarea o grupo lógico.
