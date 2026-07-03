# Data Model: Gestión de Clientes (CRM)

**Feature**: 002-clientes-crm · **Date**: 2026-07-02

Fuente de verdad: `docs/03-modelo-datos.md` (tabla `clientes`). Este documento concreta la
implementación para esta feature. Toda tabla de negocio lleva `tenant_id` (Principio I).

## Entidad: `Cliente` (tabla `clientes`)

Receptor de las facturas del tenant. Pertenece a un tenant. Puede ser empresa o particular.

| Campo | Tipo (migración) | Reglas / Notas |
|-------|------------------|----------------|
| `id` | `bigIncrements` | PK |
| `tenant_id` | `unsignedBigInteger`, index | fk lógica a `tenants`; autocompletada por `BelongsToTenant` |
| `tipo` | `string` (enum `TipoCliente`) | `empresa` \| `particular`; required |
| `nombre` | `string` | required (nombre de contacto o comercial) |
| `razon_social` | `string`, nullable | required si `tipo=empresa` (validación) |
| `nif` | `string(15)`, nullable | NIF/CIF/NIE; required si `tipo=empresa`; formato validado; único por tenant (no vacío) |
| `direccion` | `string`, nullable | |
| `cp` | `string(10)`, nullable | código postal |
| `ciudad` | `string`, nullable | |
| `provincia` | `string`, nullable | |
| `pais` | `string(2)`, default `ES` | código país |
| `email` | `string`, nullable | formato email |
| `telefono` | `string(30)`, nullable | |
| `aplica_recargo_equivalencia` | `boolean`, default `false` | minorista en recargo |
| `irpf_defecto` | `decimal(5,2)`, nullable | 0–100 |
| `tipo_impositivo_defecto` | `decimal(5,2)`, nullable | 0–100 (% IVA/IGIC/IPSI habitual) |
| `notas` | `text`, nullable | |
| `deleted_at` | `softDeletes` | borrado lógico |
| `created_at` / `updated_at` | `timestamps` | |

**Índices**:
- `index(tenant_id, nif)` — búsqueda por NIF dentro del tenant (NO único; ver research D2).
- `index(tenant_id, nombre)` — búsqueda/orden por nombre.

**Casts (modelo `Cliente`)**:
- `tipo` → `TipoCliente::class`
- `aplica_recargo_equivalencia` → `boolean`
- `irpf_defecto`, `tipo_impositivo_defecto` → `decimal:2`

**Traits del modelo**: `BelongsToTenant` (scope + autofill tenant_id), `SoftDeletes`, `HasFactory`.

**Relaciones**:
- `tenant()` → `belongsTo(Tenant::class)`.
- (Futuro, fuera de esta feature) `facturas()` → `hasMany(Factura::class)`.

## Enum: `TipoCliente`

```
Empresa    = 'empresa'
Particular = 'particular'
```

## Reglas de validación (Form Requests)

| Campo | Regla base | Condicional |
|-------|-----------|-------------|
| `tipo` | required, enum TipoCliente | |
| `nombre` | required, string, max:255 | |
| `razon_social` | nullable, string, max:255 | **required si `tipo=empresa`** |
| `nif` | nullable, string, max:15, `NifEspanol`, único por tenant | **required si `tipo=empresa`** |
| `email` | nullable, email, max:255 | |
| `pais` | required, string, size:2 (default `ES`) | |
| `cp` | nullable, string, max:10 | |
| `direccion`,`ciudad`,`provincia` | nullable, string | |
| `telefono` | nullable, string, max:30 | |
| `aplica_recargo_equivalencia` | boolean | |
| `irpf_defecto` | nullable, numeric, between:0,100 | |
| `tipo_impositivo_defecto` | nullable, numeric, between:0,100 | |
| `notas` | nullable, string | |

**Unicidad NIF** (FR-007b): `Rule::unique('clientes','nif')->where('tenant_id', $tenantId)` (con
`whereNull('deleted_at')`), y en update `->ignore($cliente->id)`. Solo si `nif` presente.

**Formato NIF** (FR-007a): regla `NifEspanol` — acepta DNI/NIF, NIE (X/Y/Z) y CIF con dígito/letra
de control válidos.

## Ciclo de vida

```
[no existe] --store--> activo (deleted_at null)
activo --update--> activo
activo --destroy--> borrado lógico (deleted_at set) → no aparece en listado ni métricas
```

No hay restauración ni borrado físico en esta feature.

## Impacto en métricas (cartas)

Todas dentro del scope de tenant y excluyendo soft-deleted (automático):
- **Total clientes** = `Cliente::count()`
- **Empresas** = `Cliente::where('tipo', TipoCliente::Empresa)->count()`
- **Particulares** = `Cliente::where('tipo', TipoCliente::Particular)->count()`
