# Data Model: Precuenta en el POS de hostelería

## Tabla nueva: `pos_precuentas`

Registro **append-only** de cada precuenta emitida (research D3). No es un documento fiscal: no
tiene número, serie, huella Verifactu ni QR.

| Columna | Tipo | Nulos | Notas |
|---|---|---|---|
| `id` | BIGINT autoincrement | no | |
| `tenant_id` | BIGINT, índice | no | `BelongsToTenant` (Principio I) |
| `cuenta_id` | FK → `pos_cuentas.id` | no | `restrictOnDelete` (las cuentas no se borran) |
| `mesa_id` | FK → `pos_mesas.id` | sí | mesa **en el momento** de emitir; `nullOnDelete`; `null` = cuenta sin mesa |
| `mesa_nombre` | VARCHAR(100) | sí | foto para el documento (la mesa puede renombrarse) |
| `zona_nombre` | VARCHAR(100) | sí | foto para el documento |
| `usuario_id` | FK → `users.id` | sí | quién la emitió; `nullOnDelete` (research D10) |
| `emitida_en` | TIMESTAMP | no | |
| `cuenta_version` | INT UNSIGNED | no | `pos_cuentas.version` al emitir (trazabilidad, FR-012) |
| `huella_consumo` | CHAR(64) | no | SHA-256 del consumo canónico (research D4); decide vigente/desactualizada |
| `huella_pendiente` | CHAR(64) | no | SHA-256 del consumo + `cantidad_saldada`; decide "reimpresión" |
| `reimpresion` | BOOLEAN | no | `true` si la anterior tenía la misma `huella_pendiente` |
| `regimen_impositivo` | VARCHAR(10) | no | régimen del tenant al emitir (IVA/IGIC/IPSI), para la mención "… incluido" |
| `suplemento_zona` | DECIMAL(5,2) | no | % efectivo aplicado (0 si no aplica) |
| `comensales` | SMALLINT UNSIGNED | sí | foto |
| `lineas` | JSON | no | foto impresa: `[{concepto, opciones: [nombre…], cantidad, precio_unitario, importe}]` |
| `total` | DECIMAL(12,2) | no | importe impuestos incluidos (= lo que costaría cobrar entera, D2) |
| `created_at` / `updated_at` | TIMESTAMP | | convención del proyecto |

Índices: `tenant_id`; `(tenant_id, cuenta_id, id)` para "última precuenta de cada cuenta".

**Reglas**:

- Solo se crea con la cuenta `abierta` y pendiente > 0 (FR-001, edge cases).
- `update` y `delete` prohibidos a nivel de modelo (excepción), igual que los ledgers append-only
  (FR-013). Ninguna cascada borra filas: `cuenta_id` es `restrict`.
- `lineas` y `total` se calculan en servidor (FR-002, Principio III) con el mismo camino que el cobro
  (research D2).

## Modelo nuevo: `App\Models\PosPrecuenta`

`BelongsToTenant`, `HasFactory`. Casts: `emitida_en` datetime, `lineas` array, `total` y
`suplemento_zona` decimal:2, `reimpresion` bool. Relaciones: `cuenta()`, `mesa()`, `usuario()`.

## Cambios en modelos existentes

- **`PosCuenta`**: `precuentas(): HasMany` (orden por `id`), `ultimaPrecuenta()`. Sin columnas nuevas:
  el estado de precuenta se **deriva** (research D4), nunca se almacena.
- Ninguna tabla existente cambia. `facturas`, series, `factura_eventos` y Verifactu no se tocan.

## Estado derivado de precuenta de una cuenta

Lo calcula un único servicio (`App\Services\PrecuentaCuenta`), consumido por el payload de la cuenta
y por la Sala:

| Estado | Condición |
|---|---|
| `ninguna` | la cuenta no tiene precuentas |
| `vigente` | la última precuenta tiene `huella_consumo` == huella actual de la cuenta |
| `desactualizada` | la última precuenta tiene otra `huella_consumo` |

Transiciones (todas derivadas, sin escritura de estado):

- `ninguna → vigente`: se emite una precuenta.
- `vigente → desactualizada`: cambia una línea en cantidad/precio/opciones, se añade o se quita una,
  se une otra cuenta, o el suplemento efectivo cambia (transferencia a otra zona o cambio de
  configuración de la zona).
- `vigente → vigente`: cobro parcial, cambio de notas/comensales/receptor, transferencia sin cambio
  de suplemento, reimpresión.
- `desactualizada → vigente`: se emite una precuenta nueva.
- La cuenta al pasar a `cerrada`/`anulada` deja de mostrarse en la Sala; sus precuentas quedan.

**Huella canónica del consumo**: lista de líneas, cada una `[articulo_id, concepto, cantidad,
precio_unitario, suplemento_opciones, tipo_impositivo, opcion_ids ordenados]` con importes
normalizados a 2 decimales, ordenada por su propio contenido (no por id, porque `sincronizarLineas`
puede recrear filas), más el `%` de suplemento efectivo; serializada en JSON y hasheada con SHA-256.
`huella_pendiente` añade `cantidad_saldada` a cada línea.

## Payloads afectados

- **Cuenta** (`CuentaController::payload`): nuevo bloque
  `precuenta: { estado, ultima: { id, total, emitida_en, reimpresion, pdf_url } | null, total_actual }`.
  `total_actual` (string decimal, calculado en servidor con `PrecuentaCuenta::calcular`, D2) solo
  viaja cuando `estado = desactualizada` (es lo que muestra el aviso del cobro, FR-024); `null` en
  otro caso, para no recalcular en cada guardado.
- **Sala** (`SalaController::estado`, por mesa ocupada): `precuenta_pedida: bool`,
  `precuenta_hace_min: int|null`; `olvidada` es `false` si `precuenta_pedida` (FR-018). Las mesas
  libres llevan `precuenta_pedida: false`, `precuenta_hace_min: null`.
