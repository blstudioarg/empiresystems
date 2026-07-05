---
description: "Task list — Bancos y cuentas bancarias del tenant"
---

# Tasks: Bancos y cuentas bancarias del tenant

**Input**: Design documents from `specs/011-bancos-cuentas/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/cuentas-bancarias.md, quickstart.md

**Tests**: Se incluyen tests. El aislamiento multi-tenant de `cuentas_bancarias` es área crítica
(Constitución, Principio IV) → **test-first obligatorio** (escribir el test, verlo fallar, luego
implementar). El resto de tests (IBAN, CRUD, snapshot) acompañan pero admiten flujo flexible.

**Organization**: agrupadas por user story (US1 P1, US2 P2, US3 P3) para entrega incremental.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: US1/US2/US3; Setup, Foundational y Polish sin etiqueta

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: catálogo global y andamiaje de datos compartido por todas las historias.

- [X] T001 Crear migración `database/migrations/xxxx_create_bancos_table.php` (tabla central: `id`, `nombre` varchar(255) único, timestamps) según [data-model.md](data-model.md)
- [X] T002 [P] Crear modelo `app/Models/Banco.php` (sin `BelongsToTenant`; `$fillable = ['nombre']`; relación `hasMany(CuentaBancaria::class)`), patrón `app/Models/Provincia.php`
- [X] T003 [P] Crear `database/seeders/BancoSeeder.php` con las principales entidades ES (Santander, BBVA, CaixaBank, Sabadell, Bankinter, ING, Unicaja, Abanca, Kutxabank, Ibercaja, Cajamar, Openbank, EVO, Deutsche Bank España) usando `firstOrCreate(['nombre' => ...])` (idempotente)
- [X] T004 Registrar `BancoSeeder::class` en `database/seeders/DatabaseSeeder.php`

**Checkpoint**: `php artisan migrate --seed` deja la tabla `bancos` poblada y de solo lectura.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: tabla/modelo de negocio y validación de IBAN — bloquean todas las historias.

- [X] T005 Crear migración `database/migrations/xxxx_create_cuentas_bancarias_table.php` (`id`, `tenant_id` índice, `banco_id` fk, `alias`, `iban` varchar(34), `titular`, `activa` boolean default true, softDeletes, timestamps; índice `(tenant_id, activa)`) según [data-model.md](data-model.md)
- [X] T006 [P] Crear modelo `app/Models/CuentaBancaria.php` (traits `BelongsToTenant, HasFactory, SoftDeletes`; `$fillable`; cast `activa => boolean`; relaciones `belongsTo(Tenant)`, `belongsTo(Banco)`), patrón `app/Models/Cliente.php`
- [X] T007 [P] Crear regla `app/Rules/IbanValido.php` (validación ISO 13616: estructura + dígito de control mod-97; normaliza mayúsculas/sin espacios) según [research.md](research.md) D2
- [X] T008 [P] Crear `database/factories/CuentaBancariaFactory.php` (banco aleatorio del catálogo, IBAN válido de ejemplo, `activa = true`)

**Checkpoint**: modelo `CuentaBancaria` y regla `IbanValido` disponibles para las historias.

---

## Phase 3: User Story 1 — Dar de alta cuentas bancarias propias (Priority: P1) 🎯 MVP

**Goal**: crear cuentas bancarias del tenant desde configuración, con validación de IBAN y
aislamiento entre tenants.

**Independent Test**: crear una cuenta desde la tab de configuración y verla listada; IBAN inválido
rechazado; un tenant no ve cuentas de otro.

### Tests (test-first para aislamiento)

- [X] T009 [P] [US1] Escribir `tests/Feature/CuentaBancariaTenantIsolationTest.php` (≥2 tenants; asserts de no-fuga en index/store/update/destroy). **Debe fallar antes de implementar** (Principio IV)
- [X] T010 [P] [US1] Escribir `tests/Feature/CuentaBancariaIbanValidationTest.php` (IBAN válido pasa, inválido devuelve 422 en el campo `iban`)

### Implementation

- [X] T011 [US1] Crear `app/Http/Requests/StoreCuentaBancariaRequest.php` (`banco_id` required/exists:bancos,id; `alias` required max:255; `iban` required + `IbanValido`; `titular` required max:255) según [contracts/cuentas-bancarias.md](contracts/cuentas-bancarias.md)
- [X] T012 [US1] Crear `app/Http/Controllers/CuentaBancariaController.php` con `index` (cuentas del tenant, incl. inactivas con `withTrashed`) y `store` (normaliza IBAN, `activa=true`, flash `success`); **sin type-hint del modelo** ([[project-tenant-route-binding]])
- [X] T013 [US1] Añadir rutas en `routes/web.php` dentro del grupo autenticado + `tenant.context`: `Route::resource('cuentas-bancarias', CuentaBancariaController::class)->only(['index','store','update','destroy'])` + `POST cuentas-bancarias/{id}/restaurar` → `restore`
- [X] T014 [US1] Crear `resources/views/configuracion/_tab_cuentas.blade.php` (listado DataTable de cuentas + modal de alta con select de banco, alias, IBAN, titular; notificaciones vía toastr, sin alerts ad-hoc)
- [X] T015 [US1] Registrar la nueva tab "Cuentas bancarias" en `resources/views/configuracion/index.blade.php` (incluir `_tab_cuentas`, pasar `$bancos` y `$cuentas` desde `ConfiguracionController@show`)
- [X] T016 [US1] Ampliar `app/Http/Controllers/ConfiguracionController.php@show` para pasar `bancos` (catálogo) y `cuentas` (del tenant, incl. inactivas) a la vista

**Checkpoint**: US1 entregable — alta y listado de cuentas funcionando con validación y aislamiento verde.

---

## Phase 4: User Story 2 — Editar y desactivar cuentas bancarias (Priority: P2)

**Goal**: editar datos, desactivar (baja lógica) y reactivar cuentas sin borrado físico.

**Independent Test**: editar una cuenta refleja cambios; desactivarla la saca de selectores pero
sigue listada como inactiva; reactivarla la vuelve a ofrecer.

### Tests

- [X] T017 [P] [US2] Escribir `tests/Feature/CuentaBancariaCrudTest.php` (update cambia datos; destroy → `activa=false` + soft delete; restore → `activa=true` + restaurada; aislamiento en update/destroy/restore)

### Implementation

- [X] T018 [US2] Crear `app/Http/Requests/UpdateCuentaBancariaRequest.php` (mismas reglas que store; `iban` con `IbanValido`)
- [X] T019 [US2] Añadir `update` a `CuentaBancariaController` (resuelve `CuentaBancaria::findOrFail($id)` en el cuerpo, normaliza IBAN, flash `success`)
- [X] T020 [US2] Añadir `destroy` a `CuentaBancariaController` (set `activa=false` + `delete()` soft; flash "Cuenta bancaria desactivada")
- [X] T021 [US2] Añadir `restore` a `CuentaBancariaController` (`CuentaBancaria::withTrashed()->findOrFail($id)` → `restore()` + `activa=true`; flash "Cuenta bancaria reactivada")
- [X] T022 [US2] Ampliar `resources/views/configuracion/_tab_cuentas.blade.php` con modal de edición, botón desactivar y botón reactivar (indicador visual de estado activa/inactiva)

**Checkpoint**: US1 + US2 — ciclo de vida completo de cuentas en configuración.

---

## Phase 5: User Story 3 — Cuenta bancaria en factura por transferencia (Priority: P3)

**Goal**: elegir una cuenta activa al crear/editar una factura con `forma_pago = transferencia`,
copiar snapshot congelado y mostrarlo en el PDF.

**Independent Test**: factura con transferencia + cuenta elegida muestra banco/IBAN/titular en el
PDF; editar la cuenta después no cambia el PDF de la factura ya creada; otra forma de pago no ofrece
selector.

### Tests

- [X] T023 [P] [US3] Escribir `tests/Feature/FacturaCuentaBancariaSnapshotTest.php` (store con transferencia + cuenta compone snapshot; editar cuenta origen no altera la factura; forma de pago ≠ transferencia deja los 4 campos `null`)

### Implementation

- [X] T024 [US3] Crear migración `database/migrations/xxxx_add_cuenta_bancaria_to_facturas_table.php` (`cuenta_bancaria_id` fk nullable + `cuenta_bancaria_banco`/`cuenta_bancaria_iban`/`cuenta_bancaria_titular` varchar nullable) según [data-model.md](data-model.md)
- [X] T025 [US3] Añadir los 4 campos a `$fillable` de `app/Models/Factura.php` y relación `belongsTo(CuentaBancaria::class)`
- [X] T026 [US3] Añadir regla `cuenta_bancaria_id` (nullable, `exists` en cuentas del tenant) a `app/Http/Requests/StoreFacturaRequest.php` y `app/Http/Requests/UpdateFacturaRequest.php`
- [X] T027 [US3] En `app/Http/Controllers/FacturaController.php@create` y `@edit`, pasar `$cuentasBancarias` = cuentas del tenant `where('activa', true)` (no soft-deleted)
- [X] T028 [US3] En `FacturaController@store` y `@update`, componer el snapshot bancario server-side SOLO si `forma_pago = transferencia` y hay `cuenta_bancaria_id` (copiar `banco.nombre`, `iban`, `titular`); en caso contrario dejar los 4 campos `null`
- [X] T029 [US3] En `resources/views/facturas/create.blade.php`, añadir el selector de cuenta bancaria bajo `forma_pago`, visible solo cuando la forma de pago sea "transferencia" (toggle JS)
- [X] T030 [US3] En `resources/views/facturas/pdf.blade.php`, renderizar el bloque de datos bancarios de cobro (banco, IBAN, titular) cuando el snapshot esté presente

**Checkpoint**: las 3 historias entregadas; PDF muestra cuenta de cobro congelada.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T031 [P] Actualizar `docs/03-modelo-datos.md`: marcar `bancos` y `cuentas_bancarias` como **implementadas** (quitar "planeada, no implementada") y confirmar las columnas snapshot en `facturas` como implementadas
- [X] T032 [P] Ejecutar `php artisan test --filter=CuentaBancaria` y `--filter=FacturaCuentaBancariaSnapshot`; dejar en verde
- [X] T033 Validar manualmente los 4 escenarios de [quickstart.md](quickstart.md) (alta, edición/baja, snapshot en PDF, aislamiento)

---

## Dependencies & Execution Order

- **Setup (T001–T004)** → antes de todo.
- **Foundational (T005–T008)** → bloquea todas las historias.
- **US1 (T009–T016)** → MVP; depende de Foundational.
- **US2 (T017–T022)** → depende de US1 (comparte controlador/vista).
- **US3 (T023–T030)** → depende de Foundational (modelo `CuentaBancaria`); independiente de US2, pero requiere que existan cuentas activas (US1) para probarse de punta a punta.
- **Polish (T031–T033)** → tras completar las historias entregadas.

## Parallel Opportunities

- Setup: T002, T003 en paralelo (tras T001).
- Foundational: T006, T007, T008 en paralelo (tras T005).
- Tests iniciales de cada historia marcados [P] (archivos distintos): T009/T010, T017, T023.

## Implementation Strategy

- **MVP = US1** (alta + listado + validación IBAN + aislamiento). Entregable y demostrable por sí solo.
- Incremento 2 = US2 (ciclo de vida). Incremento 3 = US3 (integración con facturas y PDF).
- Respetar test-first en el aislamiento multi-tenant (T009) antes de implementar el controlador.
