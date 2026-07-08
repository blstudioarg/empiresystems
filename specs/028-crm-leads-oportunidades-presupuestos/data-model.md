# Data Model: Gestión de Clientes CRM — Leads, Oportunidades y Presupuestos

**Feature**: 028 | **Fecha**: 2026-07-08 | **Fase**: 1

Convenciones del proyecto (`docs/03-modelo-datos.md`): `id` BIGINT autoincrement; `tenant_id`
indexado + global scope (`BelongsToTenant`); `timestamps`; `softDeletes` donde aplique; importes
`DECIMAL(12,2)`, cantidades/precios `DECIMAL(12,4)`, porcentajes `DECIMAL(5,2)`.

## Diagrama de relaciones (resumen)

```
tenants ──< leads ──< lead_notas
users   ──(asignado_a)── leads
leads   ──(opcional)── clientes           (convertido_a_cliente_id)
tenants ──< oportunidades
oportunidades ──(lead_id XOR cliente_id)── leads / clientes
tenants ──< presupuestos ──< presupuesto_lineas
oportunidades ──(opcional)──< presupuestos
presupuestos ──(opcional)── facturas       (convertido_a_factura_id)
articulos ──(opcional)── presupuesto_lineas
```

---

## `leads` — contacto potencial (aún no cliente)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| nombre | varchar(150) | requerido |
| empresa | varchar(150), nullable | |
| email | varchar(255), nullable | dato de contacto; al menos email **o** teléfono requerido |
| telefono | varchar(30), nullable | ídem |
| estado | varchar(15) | enum `EstadoLead`: `nuevo`, `contactado`, `cualificado`, `descartado`, `convertido` |
| origen | varchar(15) | enum `OrigenLead`: `manual`, `importacion` |
| asignado_a | fk → users, nullable | responsable comercial; `null` = bandeja "sin asignar" (`nullOnDelete`) |
| convertido_a_cliente_id | fk → clientes, nullable | trazabilidad al ganar (`nullOnDelete`) |
| motivo_descarte | varchar(255), nullable | al pasar a `descartado` |
| notas | text, nullable | notas libres de cabecera |
| softDeletes, timestamps | | `created_at` = fecha de captación |

Índices: `(tenant_id, estado)`, `(tenant_id, asignado_a)`, `(tenant_id, email)`,
`(tenant_id, telefono)` (dedup, FR-004).

**Reglas**:
- Dedup por email/teléfono dentro del tenant: alta/importación con coincidencia se **rechaza** con
  motivo "duplicado" (FR-004, no fusiona).
- Datos personales → sujetos a retención/purga configurable (`leads.retencion_dias`, D5) para leads
  `descartado`/no convertidos.
- Transiciones de `estado` registran autor/fecha vía `lead_notas` de tipo sistema o `logs_actividad`.

**Transiciones de estado** (`EstadoLead`):

```
nuevo ──► contactado ──► cualificado ──► (abre oportunidad) ──► convertido
  │            │              │
  └────────────┴──────────────┴──► descartado (con motivo)
```

`convertido` es terminal (el lead ya es cliente). `descartado` puede reactivarse a `nuevo`
manualmente si el comercial retoma el contacto.

---

## `lead_notas` — actividad comercial sobre un lead

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| lead_id | fk → leads | `cascadeOnDelete` |
| user_id | fk → users, nullable | autor (`nullOnDelete`) |
| tipo | varchar(15) | `nota`, `llamada`, `email`, `reunion`, `sistema` (cambios de estado) |
| contenido | varchar(500) | |
| timestamps | | `created_at` = momento de la interacción |

Índice: `(tenant_id, lead_id)`.

---

## `oportunidades` — posible venta en curso

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| titulo | varchar(150) | descripción corta de la oportunidad |
| lead_id | fk → leads, nullable | vínculo **XOR** con cliente (`nullOnDelete`) |
| cliente_id | fk → clientes, nullable | vínculo **XOR** con lead (`nullOnDelete`) |
| etapa | varchar(15) | enum `EtapaOportunidad`: `nueva`, `en_negociacion`, `ganada`, `perdida` |
| importe_estimado | decimal(12,2), nullable | valor esperado (para el pipeline agregado) |
| asignado_a | fk → users, nullable | responsable; heredado del lead al abrir (`nullOnDelete`) |
| motivo_perdida | varchar(255), nullable | requerido al pasar a `perdida` |
| cerrada_at | datetime, nullable | fecha de cierre (ganada/perdida) |
| notas | text, nullable | |
| softDeletes, timestamps | | `created_at` = apertura |

Índices: `(tenant_id, etapa)`, `(tenant_id, lead_id)`, `(tenant_id, cliente_id)`,
`(tenant_id, asignado_a)`.

**Invariante**: exactamente uno de `lead_id` / `cliente_id` no nulo (CHECK a nivel de aplicación en
el Request/servicio; una oportunidad nace de un lead o de un cliente existente).

**Transiciones de etapa** (`EtapaOportunidad`):

```
nueva ──► en_negociacion ──► ganada   (si lead_id: convierte lead → cliente)
   │            │
   └────────────┴──► perdida (con motivo_perdida; no admite nuevos presupuestos)
```

`ganada` y `perdida` son terminales (fijan `cerrada_at`). Al **ganar**, si hay `lead_id`, se ejecuta
`ConversorLeadCliente` (FR-012). Aceptar un presupuesto de la oportunidad la marca `ganada` (FR-020).

---

## `presupuestos` — oferta comercial (documento NO fiscal)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| numero | varchar(20) | numeración propia de presupuesto (NO serie de factura, FR-017) |
| oportunidad_id | fk → oportunidades, nullable | `nullOnDelete` |
| cliente_id | fk → clientes, nullable | receptor si es cliente |
| lead_id | fk → leads, nullable | receptor si aún es lead |
| estado | varchar(12) | enum `EstadoPresupuesto`: `borrador`, `enviado`, `aceptado`, `rechazado`, `caducado`, `facturado` |
| **Snapshot receptor** (precargado, editable, propio del presupuesto) | | mismo patrón que `facturas.cliente_*` |
| receptor_nombre, receptor_nif, receptor_direccion, receptor_cp, receptor_ciudad, receptor_provincia, receptor_pais | varchar | congelados en el documento |
| fecha_emision | date | |
| fecha_validez | date, nullable | tras esta fecha, candidato a `caducado` |
| fecha_envio | datetime, nullable | al pasar a `enviado` |
| regimen_impositivo | varchar(5) | `iva`/`igic`/`ipsi` (del tenant/cliente; se congela al convertir a factura) |
| aplica_recargo | boolean | recargo de equivalencia (solo IVA) |
| **Totales (calculados en backend con `CalculadoraFactura`, D1):** | | |
| base_total | decimal(12,2) | |
| cuota_impuesto_total | decimal(12,2) | |
| cuota_recargo_total | decimal(12,2) | |
| irpf_porcentaje | decimal(5,2), nullable | |
| irpf_cuota | decimal(12,2) | se resta del total |
| total | decimal(12,2) | base + impuesto + recargo − IRPF |
| convertido_a_factura_id | fk → facturas, nullable | enlace a la factura borrador creada (`nullOnDelete`) |
| notas | text, nullable | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, estado)`, `(tenant_id, oportunidad_id)`, `(tenant_id, cliente_id)`,
`unique(tenant_id, numero)`.

**Regla de no-fiscalidad (FR-017)**: `numero` es una numeración propia de presupuestos (p. ej.
`P-2026-0001`), **independiente** de las series de factura; no participa del encadenamiento/huella
Verifactu ni consume `series.proximo_numero`. Un presupuesto es un borrador de negocio, no un
registro fiscal.

**Transiciones de estado** (`EstadoPresupuesto`):

```
borrador ──► enviado ──► aceptado ──► facturado (crea Factura borrador; terminal)
   │            │            │
   │            ├──► rechazado
   │            └──► caducado (pasada fecha_validez)
   └──► (editable libremente solo en borrador, FR-018)
```

`facturado` es terminal y de solo lectura (FR-022). Solo un presupuesto `aceptado` habilita la
conversión (FR-020). `rechazado`/`caducado` no ofrecen conversión.

---

## `presupuesto_lineas` — detalle de la oferta

Espejo de `factura_lineas` en las columnas de importe (D1), sin los campos fiscales de Verifactu
(`calificacion_operacion`, `causa_exencion`, `mencion_legal`) que solo aplican al documento fiscal.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; `BelongsToTenant` |
| presupuesto_id | fk → presupuestos | `cascadeOnDelete`; índice |
| articulo_id | fk → articulos, nullable | línea de catálogo o concepto libre (`nullOnDelete`) |
| concepto | varchar | descripción (siempre presente) |
| unidad | varchar(20), nullable | copiada del artículo o libre |
| cantidad | decimal(12,4) | |
| precio_unitario | decimal(12,4) | |
| descuento_porcentaje | decimal(5,2), nullable | |
| base | decimal(12,2) | calculada (cantidad × precio − descuento) |
| tipo_impositivo | decimal(5,2) | % IVA/IGIC/IPSI según régimen |
| cuota_impuesto | decimal(12,2) | calculada |
| tipo_recargo | decimal(5,2), nullable | recargo de equivalencia (solo IVA) |
| cuota_recargo | decimal(12,2) | calculada |
| orden | smallint | ordenación en el PDF |
| timestamps | | |

Al **convertir a factura** (D4), cada `presupuesto_linea` se copia a una `factura_linea` con sus
importes **congelados** (no se recalcula contra el catálogo actual). La factura resultante nace en
`borrador` y su `factura_impuestos` se genera en la conversión con los mismos importes.

---

## Enums nuevos

| Enum | Valores | Uso |
|------|---------|-----|
| `EstadoLead` | `nuevo`, `contactado`, `cualificado`, `descartado`, `convertido` | `leads.estado` |
| `OrigenLead` | `manual`, `importacion` | `leads.origen` |
| `EstrategiaAsignacion` | `manual`, `round_robin` | regla de asignación (D3) |
| `EtapaOportunidad` | `nueva`, `en_negociacion`, `ganada`, `perdida` | `oportunidades.etapa` |
| `EstadoPresupuesto` | `borrador`, `enviado`, `aceptado`, `rechazado`, `caducado`, `facturado` | `presupuestos.estado` |

Todos string-backed con método `label(): string` en español, siguiendo el patrón de los enums
existentes (`TipoEventoFichaje`, etc.).

---

## Configuración nueva (`configuraciones`)

| clave | grupo | default | descripción |
|-------|-------|---------|-------------|
| `leads.retencion_dias` | crm | `1095` (3 años) | plazo antes de purgar leads descartados/no convertidos (D5) |
| `leads.asignacion_estrategia` | crm | `manual` | estrategia de asignación vigente (D3) |
| `leads.asignacion_comerciales` | crm | `[]` (json) | ids de usuarios del reparto round-robin |
| `leads.asignacion_ultimo_indice` | crm | `0` | puntero round-robin (interno, no editable en UI) |
| `presupuesto.dias_validez` | crm | `30` | validez por defecto de un presupuesto nuevo |

(Se registran siguiendo los 3 pasos de `docs/03-modelo-datos.md`: seeder + catálogo + input en la
tab de configuración correspondiente.)

## Impacto en documentación (cierre de feature)

- Añadir las 5 tablas nuevas y sus claves de `configuraciones` a `docs/03-modelo-datos.md`.
- Actualizar `docs/06-kit-digital.md`: los ítems "Gestión de Leads" y "Oportunidades + presupuestos"
  pasan de ⚠️ *Gap* a ✅ implementado (spec 028).
