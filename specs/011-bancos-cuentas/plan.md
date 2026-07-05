# Implementation Plan: Bancos y cuentas bancarias del tenant

**Branch**: `011-bancos-cuentas` | **Date**: 2026-07-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/011-bancos-cuentas/spec.md`

## Summary

Se implementa (1) un catálogo global de solo lectura `bancos` sembrado por seeder con las
principales entidades bancarias de España, mismo patrón que `provincias`/`localidades`; (2) un CRUD
de `cuentas_bancarias` propias del tenant (alias, banco, IBAN validado ISO 13616, titular,
activa/inactiva con baja lógica) accesible desde una nueva tab en la pantalla de configuración; y
(3) la integración con facturas: al crear/editar una factura con `forma_pago = transferencia` el
usuario puede elegir una de sus cuentas activas, cuyos datos (banco, IBAN, titular) se copian como
**snapshot congelado** en la factura y se muestran en el PDF. La cuenta es referencia informativa
opcional; su edición/baja posterior no altera facturas ya creadas.

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, trait `BelongsToTenant`), Blade +
template NexaDash, toastr para notificaciones, DataTables para listados; validación IBAN con la
regla nativa de Laravel `iban` si está disponible en la versión, o regla custom `IbanValido`.

**Storage**: MySQL/MariaDB. Tablas nuevas: `bancos` (central, sin `tenant_id`), `cuentas_bancarias`
(negocio, con `tenant_id`). Columnas nuevas en `facturas`: `cuenta_bancaria_id` +
`cuenta_bancaria_banco` / `cuenta_bancaria_iban` / `cuenta_bancaria_titular` (snapshot).

**Testing**: PHPUnit (Feature tests). Test-first obligatorio para el aislamiento multi-tenant de
`cuentas_bancarias` (Principio IV): tests de fuga entre ≥2 tenants antes de implementar.

**Target Platform**: Hosting compartido cPanel/Hostinger (Principio V).

**Project Type**: Aplicación web Laravel monolítica (backend + Blade).

**Performance Goals**: N/A (volúmenes pequeños: pocas cuentas por tenant, catálogo de bancos de
decenas de filas). Listados con DataTables client-side como en el resto del panel.

**Constraints**: IBAN validado solo por formato (no contra banco real). Snapshot inmutable en
facturas coherente con la regla de inmutabilidad de facturas emitidas (Principio II).

**Scale/Scope**: 2 tablas nuevas + 4 columnas en `facturas`, 1 modelo catálogo + 1 modelo negocio,
1 controlador CRUD, 1 tab de configuración, integración en `FacturaController` create/edit/store/
update + PDF. Sin unknowns pendientes.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ `cuentas_bancarias` lleva `tenant_id`
  indexado y usa el trait `BelongsToTenant` (global scope de stancl), igual que `clientes`/
  `articulos`. `bancos` es tabla **central** (catálogo global compartido) y por diseño NO lleva
  `tenant_id` — mismo criterio que `provincias`/`localidades`, no es dato de negocio del tenant.
  El controlador CRUD NO type-hinta el modelo en la firma (route-model binding bypassa TenantScope,
  [[project-tenant-route-binding]]): resuelve `CuentaBancaria::findOrFail($id)` en el cuerpo. Se
  incluyen tests de fuga entre ≥2 tenants.
- **II. Cumplimiento Normativo España-First**: ✅ La cuenta bancaria es informativa (dato de cobro
  mostrado en el PDF); no afecta a numeración, cálculo de impuestos ni Verifactu. El snapshot en la
  factura respeta la inmutabilidad: una factura `emitida` no cambia sus datos bancarios aunque la
  cuenta origen se edite/borre.
- **III. Integridad Financiera Server-Side**: ✅ No introduce cálculo de importes. El snapshot se
  compone en backend al guardar la factura, nunca desde el cliente.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ El aislamiento multi-tenant de
  `cuentas_bancarias` es área crítica → tests de aislamiento escritos antes de implementar. Resto
  (validación IBAN, CRUD, UI) con flujo de test flexible.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: ✅ Sin dependencias nuevas de
  infraestructura; reutiliza patrones existentes (catálogo tipo provincias, CRUD tipo clientes, tab
  de configuración, snapshot tipo `cliente_*` en facturas). YAGNI: sin cuenta por defecto, sin
  multidivisa, sin SEPA de cliente, sin conciliación (fuera de alcance en spec y docs).

**Resultado**: PASS. Sin violaciones → sección Complexity Tracking vacía.

## Project Structure

### Documentation (this feature)

```text
specs/011-bancos-cuentas/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1 (rutas/acciones HTTP)
│   └── cuentas-bancarias.md
├── checklists/
│   └── requirements.md  # (de /speckit-specify)
└── tasks.md             # Fase 2 (/speckit-tasks, no lo crea este comando)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Banco.php                        # NUEVO — catálogo central (sin tenant_id)
│   └── CuentaBancaria.php               # NUEVO — negocio (BelongsToTenant, SoftDeletes)
├── Http/
│   ├── Controllers/
│   │   ├── CuentaBancariaController.php  # NUEVO — CRUD (index/store/update/destroy/restore)
│   │   └── FacturaController.php         # MOD — pasar cuentas activas a create/edit; snapshot en store/update
│   └── Requests/
│       ├── StoreCuentaBancariaRequest.php   # NUEVO
│       └── UpdateCuentaBancariaRequest.php  # NUEVO
├── Rules/
│   └── IbanValido.php                   # NUEVO (si no se usa regla nativa 'iban')

database/
├── migrations/
│   ├── xxxx_create_bancos_table.php             # NUEVO (central)
│   ├── xxxx_create_cuentas_bancarias_table.php  # NUEVO (negocio)
│   └── xxxx_add_cuenta_bancaria_to_facturas_table.php  # NUEVO (4 columnas snapshot)
├── seeders/
│   ├── BancoSeeder.php                  # NUEVO — principales bancos ES
│   └── DatabaseSeeder.php               # MOD — registrar BancoSeeder
└── factories/
    └── CuentaBancariaFactory.php        # NUEVO

resources/views/
├── configuracion/
│   ├── index.blade.php                  # MOD — añadir tab "Cuentas bancarias"
│   └── _tab_cuentas.blade.php           # NUEVO — listado + modales alta/edición
└── facturas/
    ├── create.blade.php                 # MOD — selector de cuenta (solo si transferencia)
    └── pdf.blade.php                    # MOD — bloque datos bancarios de cobro

routes/web.php                           # MOD — resource cuentas-bancarias + restore

tests/Feature/
├── CuentaBancariaTenantIsolationTest.php  # NUEVO (test-first, Principio IV)
├── CuentaBancariaCrudTest.php             # NUEVO
├── CuentaBancariaIbanValidationTest.php   # NUEVO
└── FacturaCuentaBancariaSnapshotTest.php  # NUEVO (US3)
```

**Structure Decision**: Monolito Laravel existente. `bancos` se coloca en la capa central (como
`provincias`); `cuentas_bancarias` en la capa de negocio con `tenant_id` (como `clientes`). La UI de
gestión vive como nueva tab en `configuracion/` siguiendo el patrón de `_tab_apariencia` /
`_tab_facturacion`, pero el CRUD se sirve por su propio `CuentaBancariaController` con rutas
`resource` (no por el `ConfiguracionController`, que solo maneja apariencia). La integración con
facturas reutiliza el patrón snapshot ya establecido para los campos `cliente_*`.

## Complexity Tracking

> Sin violaciones de la constitución. No aplica.
