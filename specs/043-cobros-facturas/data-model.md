# Phase 1 — Data Model: Módulo de Cobros de facturas

## Cambios de esquema

**Ninguno.** Esta feature no crea ni altera tablas, columnas ni índices, y no añade migraciones.
Es una vista de lectura sobre datos existentes más dos acciones que ya están implementadas.

Consecuencia sobre la constitución: al no introducirse ninguna tabla con datos personales nuevos,
**no aplica** el requisito de plazo de retención/purga del Principio II (`RetencionLogsTenant`).
Los datos personales que se muestran (nombre del cliente) ya están sujetos a la retención de su
propia tabla.

## Tablas leídas

### `facturas` (existente)

Campos que consume el módulo:

| Campo | Uso |
|---|---|
| `id`, `tenant_id` | Identidad y aislamiento (Principio I) |
| `numero_completo` | Identificador mostrado y campo de búsqueda |
| `cliente_id` | Join para nombre del cliente y filtro por cliente |
| `serie_id` | Filtro por serie |
| `fecha_expedicion` | Columna, orden y filtro de rango del listado |
| `fecha_vencimiento` | Vencimiento, días de retraso, filtro "solo vencidas". **Anulable**: si es `NULL`, la factura nunca es vencida |
| `total` | Base del importe a cobrar |
| `estado` | Elegibilidad (`emitida` / `rectificada`) |
| `tipo` | Excluye `simplificada` (tickets POS) |
| `es_rectificativa`, `factura_rectificada_id`, `tipo_rectificacion` | Resolución del importe efectivo a cobrar |

**Inmutabilidad**: el módulo **no escribe** en esta tabla. Ni un `update`.

### `pagos` (existente)

| Campo | Uso |
|---|---|
| `id`, `tenant_id`, `factura_id` | Identidad, aislamiento y relación |
| `fecha` | Criterio del indicador "cobrado en el periodo" (D4) y orden del historial |
| `importe` | Suma de cobrado; validado contra el saldo por `RegistroPagos` |
| `metodo` (`FormaPago`) | Columna del historial y campo del formulario |
| `referencia` | Campo opcional del formulario |
| `anulado_at` | Un cobro anulado no suma a ningún importe pero sigue visible (FR-025) |

**Escrituras**: solo a través de `RegistroPagos::registrar()` (INSERT) y `::anular()` (marca
`anulado_at`), vía los endpoints existentes. El módulo no escribe directo.

### `clientes`, `series` (existentes)

Solo lectura, para mostrar el nombre y poblar los selects de filtro.

## Valores derivados (no persistidos)

Calculados en el servidor; la fuente de verdad conceptual son los métodos del modelo, y
`App\Support\ConsultaCobros` es su **único** espejo en SQL (ver research D2).

| Valor derivado | Definición | Método del modelo equivalente |
|---|---|---|
| `cobrado` | Σ `importe` de los pagos con `anulado_at IS NULL` | `Factura::montoCobrado()` |
| `total_cobrable` | `total` de la factura; si está rectificada por sustitución, el total de la rectificativa; si por diferencias, la suma de ambos | `Factura::totalCobrable()` |
| `saldo_pendiente` | `total_cobrable − cobrado` | `Factura::saldoPendiente()` |
| `estado_cobro` | `cobrado ≤ 0` ⇒ pendiente; `cobrado ≥ total_cobrable` ⇒ cobrada; resto ⇒ parcial. Comparado **en céntimos enteros**, nunca en flotantes | `Factura::estadoCobro()` |
| `admite_cobros` | No simplificada, no rectificativa, y (`emitida` o `rectificada` con rectificativa emitida) | `Factura::admiteCobros()` |
| `vencida` | `fecha_vencimiento IS NOT NULL` **y** `fecha_vencimiento < hoy` **y** `saldo_pendiente > 0` | — (nueva expresión, sin equivalente previo) |
| `dias_retraso` | Días entre `fecha_vencimiento` y hoy, **solo** si `vencida`; en otro caso `NULL` | — |

### Métricas del resumen

| Métrica | Definición | Rango |
|---|---|---|
| `pendiente_total` | Σ `saldo_pendiente` de las filas elegibles con saldo > 0 | Instantánea (ignora el rango) |
| `cobrado_periodo` | Σ `importe` de pagos vigentes con `fecha` dentro del rango, sobre facturas elegibles | Sujeta al rango |
| `vencido_total` | Σ `saldo_pendiente` de las filas `vencida` | Instantánea |
| `facturas_pendientes` | Nº de filas elegibles con `saldo_pendiente > 0` | Instantánea |

## Estados y transiciones

El módulo **no define** ninguna máquina de estados nueva. `EstadoCobro` es derivado, nunca
almacenado, y sus transiciones son consecuencia de insertar o anular un pago:

```text
                 registrar cobro parcial          registrar el resto
   Pendiente ──────────────────────────► Parcial ────────────────────► Cobrada
       ▲                                    │  ▲                          │
       │            anular el único cobro   │  │   anular parte del cobro │
       └────────────────────────────────────┘  └──────────────────────────┘
```

Un cambio de estado nunca se escribe: se recalcula al leer.

## Reglas de validación

Ninguna nueva. Las que aplican ya viven en `StorePagoRequest` + `RegistroPagos`:
importe > 0, importe ≤ saldo pendiente, fecha válida, método de pago dentro de `FormaPago`,
referencia opcional, y no se puede anular un pago ya anulado. FR-019 y SC-007 se cumplen por
reutilización, no por código nuevo.
