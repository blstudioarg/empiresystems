# Tasks: Gestión de Clientes CRM — Leads, Oportunidades y Presupuestos

**Feature**: 028 | **Branch**: `028-crm-leads-oportunidades-presupuestos`
**Input**: [plan.md](./plan.md), [spec.md](./spec.md), [data-model.md](./data-model.md),
[research.md](./research.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

> **Test-first (Principio IV, NON-NEGOTIABLE)**: los tests de aislamiento multi-tenant y de cálculo
> de importes se escriben antes de la implementación, deben fallar primero y luego pasar. El resto
> del código (UI, endpoints no críticos) sigue un flujo más flexible.
>
> **Convención de rutas**: raíz del repo. Modelos en `app/Models/`, servicios en `app/Services/`,
> controladores en `app/Http/Controllers/`, requests en `app/Http/Requests/`, enums en `app/Enums/`,
> soporte en `app/Support/`, migraciones en `database/migrations/`, vistas en `resources/views/`,
> tests en `tests/Feature/` y `tests/Unit/`.

---

## Phase 1: Setup

- [x] T001 Confirmar/instalar la dependencia de importación de ficheros (research D2): comprobar si `maatwebsite/excel` está en `composer.json`; si no, decidir entre `maatwebsite/excel` o `league/csv` + PhpSpreadsheet, instalar vía composer y registrar la elección en este archivo.
  **Decisión**: `maatwebsite/excel ^3.1` (resuelto a 3.1.69, con `phpoffice/phpspreadsheet ^1.30`).
  Cubre CSV y XLSX con una sola API (`Excel::toArray`). Requiere la extensión PHP `ext-zip`
  (PhpSpreadsheet la necesita para leer `.xlsx`, que es un contenedor zip); en el entorno local
  XAMPP no estaba habilitada en `php.ini` y se activó (`extension=zip`). cPanel/Hostinger la traen
  habilitada por defecto, así que no hay riesgo para Principio V (hosting compartido).
- [x] T002 [P] Crear los enums `app/Enums/EstadoLead.php`, `app/Enums/OrigenLead.php`, `app/Enums/EstrategiaAsignacion.php`, `app/Enums/EtapaOportunidad.php`, `app/Enums/EstadoPresupuesto.php` (string-backed, con `label(): string` en español), según [data-model.md](./data-model.md).
- [x] T003 Añadir los 3 permisos nuevos (`ver-leads`, `ver-oportunidades`, `ver-presupuestos`, módulo "CRM") a `app/Support/CatalogoPermisos.php` y re-ejecutar el seeder de permisos para asignarlos al rol Administrador (research D6).

---

## Phase 2: Foundational (prerequisitos bloqueantes)

**Bloquea todas las user stories: migraciones, modelos base y configuración compartida.**

- [x] T004 [P] Crear migración `database/migrations/*_create_leads_table.php` con columnas e índices de [data-model.md](./data-model.md) (`tenant_id`, dedup por email/teléfono, `asignado_a`, `convertido_a_cliente_id`, softDeletes).
- [x] T005 [P] Crear migración `database/migrations/*_create_lead_notas_table.php` (`tenant_id`, `lead_id` cascadeOnDelete, `user_id` nullOnDelete, `tipo`, `contenido`).
- [x] T006 [P] Crear migración `database/migrations/*_create_oportunidades_table.php` (`tenant_id`, `lead_id`/`cliente_id` nullables XOR, `etapa`, `importe_estimado`, `asignado_a`, `motivo_perdida`, `cerrada_at`, softDeletes).
- [x] T007 [P] Crear migración `database/migrations/*_create_presupuestos_table.php` (`tenant_id`, `numero` único por tenant, `oportunidad_id`/`cliente_id`/`lead_id`, snapshot receptor, fechas, régimen, totales, `convertido_a_factura_id`, softDeletes).
- [x] T008 [P] Crear migración `database/migrations/*_create_presupuesto_lineas_table.php` (espejo de `factura_lineas` en columnas de importe, sin campos Verifactu; `cascadeOnDelete`).
- [x] T009 [P] Crear modelo `app/Models/Lead.php` con `BelongsToTenant`, casts de enum, relaciones (`notas`, `oportunidades`, `clienteConvertido`, `asignadoA`).
- [x] T010 [P] Crear modelo `app/Models/LeadNota.php` con `BelongsToTenant` y relaciones.
- [x] T011 [P] Crear modelo `app/Models/Oportunidad.php` con `BelongsToTenant`, casts de enum, relaciones (`lead`, `cliente`, `presupuestos`, `asignadoA`).
- [x] T012 [P] Crear modelo `app/Models/Presupuesto.php` con `BelongsToTenant`, casts de enum, relaciones (`lineas`, `oportunidad`, `cliente`, `lead`, `facturaConvertida`).
- [x] T013 [P] Crear modelo `app/Models/PresupuestoLinea.php` con `BelongsToTenant` y relación a `articulo`.
- [x] T014 Registrar las 5 claves de `configuraciones` del módulo CRM (`leads.retencion_dias`, `leads.asignacion_estrategia`, `leads.asignacion_comerciales`, `leads.asignacion_ultimo_indice`, `presupuesto.dias_validez`) en `database/seeders/ConfiguracionSeeder.php` y documentarlas en `docs/03-modelo-datos.md` (3 pasos de config).
- [x] T015 Añadir las entradas del menú CRM (Leads, Oportunidades, Presupuestos) en `resources/views/partials/sidebar.blade.php`, protegidas por sus permisos.

**Checkpoint**: migraciones aplican limpio (`php artisan migrate:fresh`), modelos resuelven bajo el tenant activo.

---

## Phase 3: User Story 1 — Captación y gestión de leads (P1) 🎯 MVP

**Goal**: alta manual + importación CSV/Excel con reporte de rechazos, dedup, asignación
(manual + round-robin), estados/notas, conversión a cliente.
**Independent test**: alta manual + subir CSV con filas válidas/inválidas/duplicadas; verificar
creación con tenant correcto, asignación por regla, rechazos reportados, no-fuga entre tenants.

### Tests (escribir primero — Principio IV)

- [x] T016 [P] [US1] Test de aislamiento multi-tenant en `tests/Feature/Leads/LeadTenantScopeTest.php` (≥2 tenants, un tenant no ve leads del otro) — debe fallar antes de implementar.
- [x] T017 [P] [US1] Test de importación en `tests/Feature/Leads/ImportadorLeadsTest.php`: filas válidas creadas, inválidas rechazadas con motivo, duplicadas rechazadas, sin importación parcial silenciosa (FR-003/FR-004).
- [x] T018 [P] [US1] Test de asignación round-robin en `tests/Feature/Leads/AsignadorLeadsTest.php`: reparto ~equitativo entre comerciales; bandeja "sin asignar" cuando el conjunto está vacío (FR-005/FR-006).

### Implementación

- [x] T019 [P] [US1] Crear `app/Support/RetencionLeadsTenant.php` (patrón `RetencionLogsTenant`, clave `leads.retencion_dias`) y el comando `leads:purgar` programado en `bootstrap/app.php → withSchedule` (research D5).
- [x] T020 [US1] Crear `app/Services/AsignadorLeads.php` (único punto): estrategias `manual`/`round_robin` con puntero por tenant y bloqueo transaccional (research D3).
- [x] T021 [US1] Crear `app/Services/ImportadorLeads.php` (único punto): parseo CSV/Excel → `{importados, rechazadas:[{fila, motivo}]}`, invocando dedup y `AsignadorLeads` (research D2).
- [x] T022 [US1] Crear `app/Services/ConversorLeadCliente.php`: lead → cliente conservando trazabilidad (`convertido_a_cliente_id`, `estado=convertido`), advirtiendo si ya existe cliente con el mismo NIF (FR-009, edge case).
- [x] T023 [P] [US1] Crear `app/Http/Requests/StoreLeadRequest.php` y `UpdateLeadRequest.php` (nombre requerido; email o teléfono requerido; dedup por tenant).
- [x] T024 [P] [US1] Crear `app/Http/Requests/ImportarLeadsRequest.php` (fichero requerido, mimes csv/txt/xlsx, tamaño máx).
- [x] T025 [US1] Crear `app/Http/Controllers/LeadController.php` (index con "mis leads"/todos y bandeja sin asignar, create, store, show, edit, update, destroy, notas.store, convertir) por [contracts/leads.md](./contracts/leads.md).
- [x] T026 [US1] Crear `app/Http/Controllers/LeadImportacionController.php` (form + importar) devolviendo el resumen estructurado a la vista.
- [x] T027 [US1] Registrar las rutas de leads en `routes/web.php` protegidas por `can:ver-leads`.
- [x] T028 [P] [US1] Crear vistas `resources/views/leads/` (index con datatable, _form, importar con resumen de rechazos, show con notas y oportunidades) siguiendo `docs/04-front-guidelines.md` (incluye override CSS de paginación de datatable).
- [x] T029 [US1] Añadir input de la regla de asignación (estrategia + comerciales) en la tab de configuración correspondiente (`resources/views/configuracion/`), grupo `crm`.

**Checkpoint**: US1 entregable de forma independiente — leads gestionables de punta a punta.

---

## Phase 4: User Story 3 — Presupuestos y conversión a factura (P1) 🎯 MVP

**Goal**: presupuesto con líneas que reutiliza `CalculadoraFactura`, ciclo de vida, y conversión a
factura borrador sin reintroducir importes ni permitir doble facturación.
**Independent test**: crear presupuesto con varios tipos impositivos; comparar totales con una
factura equivalente (iguales al céntimo, 3 regímenes); aceptar, convertir a factura borrador,
verificar líneas idénticas y guarda de no doble facturación.

> Nota de prioridad: US3 es P1 junto con US1 (forman el MVP demostrable). Puede construirse en
> paralelo con US1 salvo por la dependencia de `presupuestos.oportunidad_id` (opcional) con US2.

### Tests (escribir primero — Principio IV)

- [x] T030 [P] [US3] Test de aislamiento en `tests/Feature/Presupuestos/PresupuestoTenantScopeTest.php` (≥2 tenants).
- [x] T031 [P] [US3] Test crítico de cálculo en `tests/Feature/Presupuestos/PresupuestoCalculoIgualFacturaTest.php`: totales de presupuesto == factura con las mismas líneas para IVA, IGIC e IPSI (SC-003).
- [x] T032 [P] [US3] Test de conversión en `tests/Feature/Presupuestos/PresupuestoConversionFacturaTest.php`: crea factura borrador con líneas congeladas, marca `facturado`, y bloquea segunda conversión (SC-005/FR-022).

### Implementación

- [x] T033 [US3] Crear `app/Services/RegistroPresupuesto.php` (único punto de escritura): crear/editar presupuesto invocando `App\Services\CalculadoraFactura` y persistiendo líneas + totales; numeración propia `P-{anio}-{n}` no fiscal (research D1, FR-016/FR-017).
- [x] T034 [US3] Crear `app/Services/ConversorPresupuestoFactura.php`: presupuesto aceptado → `Factura` borrador con importes congelados, enlace bidireccional, `estado=facturado`, guarda transaccional contra doble facturación (research D4, FR-021/FR-022).
- [x] T035 [P] [US3] Crear `app/Http/Requests/StorePresupuestoRequest.php` y `UpdatePresupuestoRequest.php` (receptor cliente o lead; ≥1 línea; update solo si borrador; el cliente no envía importes).
- [x] T036 [US3] Crear `app/Http/Controllers/PresupuestoController.php` (index, create, store, show, edit, update, destroy, estado, convertir, pdf, enviar) por [contracts/presupuestos.md](./contracts/presupuestos.md).
- [x] T037 [US3] Registrar las rutas de presupuestos en `routes/web.php` protegidas por `can:ver-presupuestos`.
- [x] T038 [P] [US3] Crear vistas `resources/views/presupuestos/` (index, _form con líneas dinámicas y cálculo en backend, show, pdf) reutilizando el patrón de líneas de facturas.
- [x] T039 [P] [US3] Integrar el envío por email del presupuesto reutilizando `App\Services\TenantMailer` (opcional, `presupuestos.enviar`).

**Checkpoint**: US3 entregable — presupuestos calculados y convertibles a factura borrador.

---

## Phase 5: User Story 2 — Oportunidades y pipeline (P2)

**Goal**: pipeline (nueva → negociación → ganada/perdida) vinculado a lead o cliente; ganar
convierte el lead en cliente; vista agregada por etapa.
**Independent test**: partiendo de un cliente, abrir oportunidad, mover por etapas, ganarla (desde
lead → convierte a cliente); verificar transiciones registradas y aislamiento por tenant.

### Tests (escribir primero — Principio IV)

- [x] T040 [P] [US2] Test de aislamiento en `tests/Feature/Oportunidades/OportunidadTenantScopeTest.php` (≥2 tenants).
- [x] T041 [P] [US2] Test de transición en `tests/Feature/Oportunidades/OportunidadPipelineTest.php`: ganar desde lead ejecuta `ConversorLeadCliente`; perder exige motivo y bloquea nuevos presupuestos (FR-012/FR-013).

### Implementación

- [x] T042 [P] [US2] Crear `app/Http/Requests/StoreOportunidadRequest.php` y `UpdateOportunidadRequest.php` (lead_id XOR cliente_id; motivo_perdida requerido si etapa=perdida).
- [x] T043 [US2] Crear `app/Http/Controllers/OportunidadController.php` (index pipeline agregado, create, store, show con presupuestos, edit, update, etapa, destroy) por [contracts/oportunidades.md](./contracts/oportunidades.md); la transición a `ganada` invoca `ConversorLeadCliente`.
- [x] T044 [US2] Registrar las rutas de oportunidades en `routes/web.php` protegidas por `can:ver-oportunidades`.
- [x] T045 [P] [US2] Crear vistas `resources/views/oportunidades/` (index vista pipeline por etapa con recuento e importe agregado, _form, show).
- [x] T046 [US2] Enlazar la creación de oportunidad desde la ficha de lead cualificado (US1) y desde la ficha de cliente.

**Checkpoint**: US2 entregable — pipeline completo con conversión lead→cliente al ganar.

---

## Phase 6: Polish & Cross-Cutting

- [x] T047 [P] Registrar acciones de negocio en `logs_actividad` vía `RegistradorActividad` (alta/baja/modificación de leads, oportunidades, presupuestos y conversiones), siguiendo el patrón existente.
- [x] T048 [P] Actualizar `docs/06-kit-digital.md`: marcar "Gestión de Leads" y "Oportunidades + presupuestos" como ✅ implementado (spec 028), y revisar si la vista pipeline cubre la "alerta gráfica" pedida.
- [x] T049 [P] Añadir un `LeadFactory`, `OportunidadFactory`, `PresupuestoFactory` (forzando `tenant_id` del tenant activo — ver pitfall de factories con `tenant_id`) para los tests y el seeding de demo.
- [x] T050 Ejecutar el quickstart ([quickstart.md](./quickstart.md)) de punta a punta (3 escenarios) y la suite completa (`php artisan test`); confirmar SC-001..SC-007 en verde.

---

## Dependencies & Execution Order

- **Setup (Phase 1)** → **Foundational (Phase 2)** bloquean todo.
- **US1 (P1)** y **US3 (P1)** forman el MVP; pueden ir en paralelo tras Foundational (US3 solo usa
  `oportunidad_id` de forma opcional, así que no bloquea).
- **US2 (P2)** depende de que existan leads (US1) para el flujo ganar→cliente, y enlaza presupuestos
  (US3) en su ficha; construir tras US1/US3.
- **Polish (Phase 6)** al final.

## Parallel Opportunities

- Phase 2: T004–T013 (migraciones + modelos) son `[P]` entre sí (archivos distintos).
- Dentro de cada US: los tests `[P]` se escriben juntos; Requests y vistas `[P]` son paralelizables.
- US1 y US3 pueden desarrollarse por dos personas en paralelo tras el checkpoint de Foundational.

## MVP Scope

**MVP mínimo demostrable**: Phase 1 + Phase 2 + **US1 (leads)** + **US3 (presupuestos + conversión)**.
Cubre el corazón del gap de homologación (captación + oferta → factura). US2 (pipeline) añade la
visión comercial y se entrega en la siguiente iteración.

## Format validation

Todas las tareas siguen `- [ ] TID [P?] [Story?] descripción con ruta`. Las de Setup/Foundational/
Polish no llevan etiqueta de historia; las de fase de historia llevan `[US1]`/`[US2]`/`[US3]`.
