# Data Model — Compras desde documentos (PDF/imagen) interpretados por IA

**Feature**: `044-compras-desde-documentos-ia` · **Fecha**: 2026-09-06

> **Sin migraciones y sin tablas nuevas.** Todo lo que esta feature persiste cabe en columnas que ya
> existen. Las entidades "nuevas" son **transitorias**: viven en memoria durante la petición y en el
> JSON que viaja al navegador, nunca en base de datos.

---

## 1. Cambios sobre entidades persistidas

### 1.1 `compras` (tabla existente) — sin cambios de esquema

| Columna | Uso en esta feature | Estado |
|---|---|---|
| `origen` `string(20)` | Nuevo valor `'documento'` | Enum PHP ampliado, **sin migración** |
| `formato_recepcion` `string(20) NULL` | `'pdf'` o `'imagen'` según el fichero subido | Ya existe (feature 022) |
| `archivo_recibido_path` `string NULL` | `tenants/{tenant_id}/compras-documentos/{uuid}.{ext}` | Ya existe (feature 022) |
| `estado` | Siempre `EstadoCompra::Borrador` al crear | Sin cambios |
| `estado_b2b` / `estado_b2b_fecha` | **Siempre `null`**: el ciclo B2B es propio de Facturae | Sin cambios |
| `base_total`, `cuota_impuesto_total`, `total` | **Calculados en servidor** (Principio III) | Sin cambios |

**Verificación de que no hace falta migración**:
`database/migrations/2026_07_05_130002_add_recepcion_facturae_to_compras_table.php` declara
`$table->string('origen', 20)->default('manual')`. `'documento'` son 9 caracteres.

### 1.2 `App\Enums\OrigenCompra` — modificado

```
Manual    = 'manual'      (existente)
Facturae  = 'facturae'    (existente)
Documento = 'documento'   ← NUEVO — label: "Documento (IA)"
Otro      = 'otro'        (existente)
```

**Impacto a revisar al implementar**: cualquier `match` exhaustivo sobre `OrigenCompra` deja de
compilar/cubrir al añadir un caso. Hoy los consumidores conocidos son `OrigenCompra::label()` y las
comparaciones `!== OrigenCompra::Facturae` de `CompraFacturaeController` (que siguen siendo
correctas: una compra `documento` tampoco tiene ciclo B2B ni XML que descargar).

### 1.3 `compra_lineas` (tabla existente) — sin cambios

Se rellena exactamente igual que en el alta manual: `articulo_id` (nullable), `concepto`, `unidad`,
`cantidad`, `precio_unitario`, `base`, `tipo_impositivo`, `cuota_impuesto`, `orden`.

### 1.4 `proveedores` (tabla existente) — sin cambios

Puede darse de alta una fila desde este flujo, **solo con confirmación explícita del usuario**
(FR-019), con los campos que el modelo consiga leer: `nombre`, `razon_social`, `nif`, `direccion`,
`cp`, `ciudad`, `provincia`, `pais`, `email`, `telefono`. Todos editables antes de confirmar.

### 1.5 `articulos` (tabla existente) — **solo lectura**

Se consulta para emparejar líneas. **Nunca** se crea ni se modifica un artículo desde este flujo
(FR-025).

---

## 2. Almacenamiento de ficheros

| Momento | Disco | Ruta | Vida |
|---|---|---|---|
| Propuesta sin confirmar | `local` | `compras-documentos/{tenant_id}/{uuid}.{ext}` | **24 h**; borrado al crear o descartar; purga diaria |
| Compra creada | `documentos` | `tenants/{tenant_id}/compras-documentos/{uuid}.{ext}` | Ligada al ciclo de vida de la compra |

Al crear la compra el fichero **se mueve** (no se copia) del temporal al definitivo, dentro de la
misma transacción lógica que la creación.

**Nota heredada**: el disco `documentos` tiene `root => storage_path('app/tenants')` y la convención
existente (`ImportadorFacturae`) prefija además `tenants/`, con lo que la ruta física real es
`storage/app/tenants/tenants/{id}/…`. Es redundante pero **se replica tal cual** por consistencia con
los datos ya guardados; no se corrige en esta feature.

---

## 3. Entidades transitorias (no persistidas)

### 3.1 `LecturaDocumento` — lo que devuelve el modelo

Salida cruda del proveedor de IA, conformada por el Structured Output
(`contracts/propuesta-compra.schema.json`). **Datos sin verificar**: nada de aquí se persiste sin
pasar por `ProponedorCompraDesdeDocumento`.

| Campo | Tipo | Notas |
|---|---|---|
| `es_documento_compra` | bool | `false` ⇒ no se propone nada (FR-009) |
| `motivo_descarte` | string\|null | Solo si `es_documento_compra = false` |
| `emisor` | objeto\|null | `nombre`, `razon_social`, `nif`, `direccion`, `cp`, `ciudad`, `provincia`, `pais`, `email`, `telefono` — todos nullable |
| `numero_documento` | string\|null | |
| `fecha` | string (`YYYY-MM-DD`)\|null | |
| `moneda` | string\|null | Informativo; si no es EUR se avisa |
| `lineas[]` | array | `referencia`, `concepto`, `unidad`, `cantidad`, `precio_unitario`, `tipo_impositivo` — todos nullable salvo `concepto` |
| `total_documento` | number\|null | **Solo para contrastar**; nunca se persiste |

### 3.2 `PropuestaCompra` — lo que ve y edita el usuario

Producto de `ProponedorCompraDesdeDocumento`: la lectura ya traducida al catálogo del tenant.

| Campo | Tipo | Origen |
|---|---|---|
| `token` | string (uuid) | Identifica el fichero temporal |
| `archivo_nombre` | string | Nombre original, para identificar el documento en la cola y en los errores (FR-009, FR-034) |
| `proveedor` | `EmparejamientoProveedor` | Ver 3.3 |
| `numero_documento` / `fecha` / `notas` | string\|null | De la lectura, editables |
| `lineas[]` | `LineaPropuesta[]` | Ver 3.4 |
| `campos_ilegibles[]` | string[] | Rutas de los campos que el modelo no pudo leer (FR-010) |
| `avisos[]` | `Aviso[]` | `{tipo, mensaje, url?}` — duplicado, moneda no EUR, tipo impositivo incoherente |
| `totales` | objeto | `base_total`, `cuota_impuesto_total`, `total` — **recalculados en servidor**, informativos |
| `total_documento` | number\|null | Lo que decía el documento, para que el usuario vea la discrepancia si la hay |

### 3.3 `EmparejamientoProveedor`

| Campo | Tipo | Notas |
|---|---|---|
| `criterio` | `'nif' \| 'nombre' \| 'sin_coincidencia'` | Determina cómo lo pinta la UI (FR-018) |
| `proveedor_id` | int\|null | **Preseleccionado** solo si `criterio = 'nif'` |
| `proveedor_nombre` | string\|null | Para mostrar sin otra consulta |
| `similitud` | float\|null | Porcentaje, solo si `criterio = 'nombre'` |
| `datos_nuevos` | objeto\|null | Campos leídos del emisor, editables, para el alta opcional |

**Regla de estados**:

```
nif              → proveedor_id != null   → la UI lo muestra elegido y confirmado
nombre           → proveedor_id != null   → la UI lo muestra como SUGERENCIA a confirmar
sin_coincidencia → proveedor_id == null   → la UI ofrece crear con datos_nuevos
```

En los tres casos, crear la compra exige un `proveedor_id` resuelto o `crear_proveedor = true` con
datos válidos (FR-020).

### 3.4 `LineaPropuesta`

| Campo | Tipo | Notas |
|---|---|---|
| `concepto` | string | Obligatorio al crear |
| `referencia` | string\|null | Leída del documento; solo para emparejar, no se persiste |
| `unidad` | string\|null | |
| `cantidad` | number\|null | Debe ser > 0 al crear |
| `precio_unitario` | number\|null | ≥ 0 al crear |
| `tipo_impositivo` | number\|null | Vacío si el documento no lo indica — **nunca 21 % por defecto** (Principio II) |
| `tipo_impositivo_coherente` | bool | `TiposImpositivos::esTipoValido(tenant()->regimen_impositivo, $tipo)` |
| `articulo` | `EmparejamientoArticulo` | Ver 3.5 |
| `base` / `cuota_impuesto` | number | **Calculados en servidor**, informativos para la UI |

### 3.5 `EmparejamientoArticulo`

| Campo | Tipo | Notas |
|---|---|---|
| `criterio` | `'referencia' \| 'nombre' \| 'sin_coincidencia'` | |
| `articulo_id` | int\|null | `null` ⇒ línea libre, resultado válido (FR-023) |
| `articulo_nombre` | string\|null | |
| `mueve_stock` | bool | `true` si es producto con `gestion_stock`: la UI avisa de que esa línea moverá inventario al confirmar |
| `similitud` | float\|null | Solo si `criterio = 'nombre'` |

---

## 4. Reglas de validación (al crear)

Se reutilizan **íntegras** las reglas de `StoreCompraRequest`, más las específicas del flujo:

| Campo | Regla |
|---|---|
| `token` | Requerido; debe corresponder a un fichero temporal vigente (< 24 h) **del tenant activo** |
| `proveedor_id` | Requerido **si** `crear_proveedor` es falso. `Rule::exists('proveedores','id')->where(tenant_id = actual, deleted_at null)` |
| `crear_proveedor` | Booleano. Si `true`, `proveedor_nuevo.nombre` requerido; el resto opcional |
| `lineas` | `required|array|min:1` |
| `lineas.*.articulo_id` | `nullable` + `Rule::exists('articulos','id')->where(tenant_id = actual, deleted_at null)` |
| `lineas.*.concepto` | `required|string|max:255` |
| `lineas.*.cantidad` | `required|numeric|gt:0` |
| `lineas.*.precio_unitario` | `required|numeric|min:0` |
| `lineas.*.tipo_impositivo` | `required|numeric|min:0` (igual que el alta manual — ver research D10) |
| `fecha` | `required|date` |
| `base`, `cuota`, totales | **No se aceptan del cliente.** Si llegan, se ignoran |

---

## 5. Transiciones y efectos

```
[fichero subido]
      │  POST /compras/documentos        → token, sin llamada al modelo
      ▼
[temporal 24 h]
      │  POST /compras/documentos/{token}/interpretar   → 1 llamada al modelo
      ▼
[propuesta en el navegador]  ← editable, nada persistido
      │
      ├── descartar → DELETE  → fichero borrado, catálogos intactos
      │
      └── crear     → POST .../crear
                       │
                       └─ TRANSACCIÓN:
                            1. (opcional) crear proveedor con los datos confirmados
                            2. crear compra (estado = borrador, origen = documento)
                            3. crear líneas con importes recalculados
                            4. mover fichero temporal → disco documentos
                            5. registrar en el log de actividad
                          (todo o nada — FR-021)
      ▼
[compra en borrador]  → a partir de aquí, ciclo idéntico al de cualquier compra:
                        editar / eliminar en borrador, confirmar (mueve stock),
                        anular (revierte). Sin lógica nueva.
```

**Nada que ocurra antes de "crear" toca la base de datos.** Es la garantía que sostienen FR-011,
FR-016 y SC-006.
