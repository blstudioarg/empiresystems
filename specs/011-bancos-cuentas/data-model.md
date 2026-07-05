# Data Model — Bancos y cuentas bancarias del tenant

Basado en `docs/03-modelo-datos.md` (secciones `bancos` y `cuentas_bancarias`, hasta ahora
"planeadas") y en las clarificaciones de la spec. Convenciones: `id` BIGINT autoincrement,
`timestamps`, `softDeletes` donde aplique; importes `DECIMAL(12,2)`, porcentajes `DECIMAL(5,2)`.

## Entidad: `bancos` (capa CENTRAL — sin `tenant_id`)

Catálogo global de solo lectura, sembrado por el sistema. Modelo `App\Models\Banco`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| nombre | varchar(255) | ej. `BBVA`, `CaixaBank`, `Banco Santander`; único |
| timestamps | | |

- **Índices**: único en `nombre` (evita duplicados en re-seed).
- **Relaciones**: `Banco hasMany CuentaBancaria`.
- **Reglas**: sin CRUD por parte del tenant; solo lectura. Se puebla con `BancoSeeder`
  (`firstOrCreate` por `nombre`).

## Entidad: `cuentas_bancarias` (capa TENANT — con `tenant_id`)

Cuenta propia del tenant para cobrar por transferencia. Modelo `App\Models\CuentaBancaria`
(`BelongsToTenant`, `SoftDeletes`).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice; global scope de tenant |
| banco_id | fk → bancos | requerido |
| alias | varchar(255) | nombre identificativo, ej. "Cuenta principal"; requerido |
| iban | varchar(34) | ISO 13616; normalizado (mayúsculas, sin espacios); requerido |
| titular | varchar(255) | nombre/razón social del titular; requerido |
| activa | boolean | default `true`; controla si aparece en el selector de facturas |
| softDeletes (deleted_at) | timestamp nullable | baja lógica |
| timestamps | | |

- **Índices**: `(tenant_id, activa)`.
- **Relaciones**: `belongsTo Tenant`, `belongsTo Banco`.
- **Validación**:
  - `banco_id`: requerido, existe en `bancos`.
  - `alias`: requerido, string ≤ 255.
  - `iban`: requerido, regla `IbanValido` (estructura + mod-97). Normalizar antes de persistir.
  - `titular`: requerido, string ≤ 255.
- **Estados / ciclo de vida**:
  - **Alta** → `activa = true`, no soft-deleted.
  - **Desactivar** → `activa = false` + `delete()` (soft). Deja de ofrecerse en selectores; sigue
    listada (con `withTrashed`) en configuración.
  - **Reactivar** → `restore()` + `activa = true`.
  - **Editar** → cambia alias/banco/iban/titular; no afecta a facturas ya creadas (snapshot).
- **Sin restricción de unicidad de IBAN** entre cuentas del mismo tenant (edge case aceptado).

## Cambios en entidad existente: `facturas`

Columnas nuevas (nullable), pobladas como snapshot solo si `forma_pago = transferencia`. Migración
`add_cuenta_bancaria_to_facturas_table`.

| Campo | Tipo | Notas |
|-------|------|-------|
| cuenta_bancaria_id | fk → cuentas_bancarias | nullable; referencia informativa de la cuenta elegida |
| cuenta_bancaria_banco | varchar(255) | nullable; **snapshot** del nombre del banco |
| cuenta_bancaria_iban | varchar(34) | nullable; **snapshot** del IBAN |
| cuenta_bancaria_titular | varchar(255) | nullable; **snapshot** del titular |

- **Regla de snapshot**: al guardar una factura con `forma_pago = transferencia` y una cuenta
  elegida, copiar `banco.nombre`, `iban`, `titular` en las columnas snapshot. Si la forma de pago no
  es transferencia, los cuatro campos quedan `null`. Congelado: editar/dar de baja la cuenta origen
  no cambia estos valores (coherente con `cliente_*`).
- **Inmutabilidad**: una factura `emitida` no modifica su snapshot bancario (Principio II).

## Relaciones (resumen)

```
tenants ──< cuentas_bancarias
bancos  ──< cuentas_bancarias
cuentas_bancarias ──(opcional, solo forma_pago=transferencia)── facturas
```
