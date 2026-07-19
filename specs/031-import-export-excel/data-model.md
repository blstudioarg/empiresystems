# Data Model: Importación y exportación de Excel

**Feature**: 031-import-export-excel | **Fecha**: 2026-07-18

> **Esta feature no crea ni altera ninguna tabla de base de datos.** Las "entidades" de abajo son
> estructuras en memoria (objetos de valor y definiciones declarativas). Las únicas modificaciones
> persistentes son dos casos nuevos en enums existentes.

---

## 1. `ColumnaExcel` (objeto de valor)

Describe **una columna** de un módulo, en los tres usos a la vez (exportar, plantilla, importar).

| Campo | Tipo | Descripción |
|---|---|---|
| `clave` | `string` | Identificador interno y cabecera normalizada que espera el importador (p. ej. `razon_social`). |
| `etiqueta` | `string` | Cabecera legible en español que se escribe en el fichero (p. ej. `Razón social`). |
| `obligatoria` | `bool` | Si la columna debe existir en el fichero importado. Solo relevante en módulos importables. |
| `formato` | `FormatoCelda` | `Texto` \| `Numero` \| `Importe` \| `Fecha` \| `Booleano`. Determina el formato de celda al exportar (D5). |
| `exportar` | `callable(Model): mixed` | Extrae el valor crudo del modelo. Importes y fechas devuelven el valor **nativo**, no formateado (D5). |
| `importar` | `?callable(mixed): mixed` | Normaliza el valor leído del fichero antes de validarlo. `null` en columnas solo exportables. |
| `ejemplo` | `?string` | Valor de muestra para la fila de ejemplo de la plantilla (FR-023). |

**Regla de invariante**: `etiqueta` es lo que se escribe y lo que se espera leer. La coincidencia
entre exportación, plantilla e importación (FR-024, SC-007) se cumple porque las tres leen esta
misma lista, no porque se mantengan sincronizadas a mano.

**Normalización de cabeceras al importar**: la comparación se hace sobre la etiqueta pasada a
minúsculas, sin acentos y con espacios convertidos en guion bajo. Así `Razón social`, `razon
social` y `RAZON_SOCIAL` se reconocen todas como `razon_social`, que es lo que hace que un fichero
exportado y reeditado por el usuario siga siendo importable.

---

## 2. `DefinicionExcel` (contrato base) + dos interfaces de capacidad

Una definición por módulo. El contrato se parte en **tres piezas**: una base con lo que todo
módulo tiene, y dos interfaces de capacidad que cada módulo implementa **solo si le corresponden**.

### 2.1 `DefinicionExcel` — base, la implementan todas

| Miembro | Tipo | Descripción |
|---|---|---|
| `modulo()` | `string` | Clave del módulo en las rutas: `clientes`, `articulos`, `proveedores`, `facturas`, `albaranes`, `leads`. |
| `etiquetaModulo()` | `string` | Nombre legible para el nombre de fichero y los mensajes. |
| `permiso()` | `string` | Permiso requerido: `ver-clientes`, `ver-articulos`, … (FR-004, FR-020). |
| `columnas()` | `ColumnaExcel[]` | Columnas, en orden de aparición en el fichero. |
| `entidadLog()` | `EntidadLogActividad` | Para el registro de actividad (FR-008, FR-021). |

### 2.2 `DefinicionExportable` — solo módulos con exportación

| Miembro | Tipo | Descripción |
|---|---|---|
| `consultaExportacion(int[] $ids)` | `Builder` | Consulta base para exportar, con sus `with()` de relaciones. **Siempre sobre el modelo Eloquent**, para que el `TenantScope` se aplique (Principio I). |

La implementan: clientes, artículos, facturas, albaranes, leads. **No** la implementa proveedores
(fuera del alcance de exportación).

### 2.3 `DefinicionImportable` — solo módulos con importación

| Miembro | Tipo | Descripción |
|---|---|---|
| `reglasFila(array $fila)` | `array` | Reglas de validación de una fila. Delega en el `FormRequest` del alta manual (D3). |
| `crear(array $datos, int $tenantId)` | `Model` | Persiste una fila validada, forzando `tenant_id` (FR-015). |

La implementan: clientes, artículos, proveedores. **No** la implementan facturas, albaranes ni
leads.

### 2.4 Por qué tres piezas y no una

**Invariante estructural (FR-010)**: en las definiciones no importables, `reglasFila()` y `crear()`
**no existen**. La prohibición de importar facturas y albaranes no es una comprobación en runtime
que alguien pueda saltarse u olvidar: es que el código para hacerlo no está escrito.

La simetría con `DefinicionExportable` no es cosmética: sin ella, `DefinicionProveedores` estaría
obligada a implementar un `consultaExportacion()` que nadie llama, y ese método muerto sería
justamente el que alguien cablearía a una ruta más adelante sin pensarlo. Cada capacidad se
declara explícitamente o no existe.

`RegistroDefiniciones` resuelve cada ruta contra la interfaz que necesita
(`resolverExportable()` / `resolverImportable()`) y devuelve `404` si la definición no la
implementa.

---

## 3. Definiciones por módulo

### 3.1 `DefinicionClientes` — exportable + importable

Columnas derivadas de `StoreClienteRequest` y del listado en pantalla:

| Etiqueta | Clave | Formato | Obligatoria al importar |
|---|---|---|---|
| Tipo | `tipo` | Texto (`Empresa`/`Particular`) | Sí |
| Nombre | `nombre` | Texto | Sí |
| Razón social | `razon_social` | Texto | Sí **si** tipo = Empresa |
| NIF | `nif` | Texto | Sí **si** tipo = Empresa |
| Dirección | `direccion` | Texto | No |
| CP | `cp` | Texto | No |
| Ciudad | `ciudad` | Texto | No |
| Provincia | `provincia` | Texto | No |
| País | `pais` | Texto (ISO-2) | Sí (por defecto `ES` si viene vacía) |
| Email | `email` | Texto | No |
| Teléfono | `telefono` | Texto | No |
| Recargo de equivalencia | `aplica_recargo_equivalencia` | Booleano | No (por defecto `false`) |
| Notas | `notas` | Texto | No |

**Nota sobre `tipo`**: se exporta la etiqueta legible (`Empresa`), no el valor del enum
(`empresa`), y al importar se acepta cualquiera de las dos. Un usuario que rellena una plantilla a
mano escribe "Empresa"; el fichero debe ser legible por humanos y reimportable a la vez (SC-007).

**Nota sobre `cp`**: es texto, nunca número. Un CP como `08001` leído como número pierde el cero
inicial y se convierte en `8001`, que es un código postal distinto.

### 3.2 `DefinicionArticulos` — exportable + importable

| Etiqueta | Clave | Formato | Obligatoria al importar |
|---|---|---|---|
| Tipo | `tipo` | Texto (`Producto`/`Servicio`) | Sí |
| SKU | `sku` | Texto | No |
| Nombre | `nombre` | Texto | Sí |
| Descripción | `descripcion` | Texto | No |
| Unidad | `unidad` | Texto | No |
| Categoría | `categoria` | Texto (nombre) | No |
| Precio | `precio` | Importe | Sí |
| Tipo impositivo (%) | `tipo_impositivo` | Numero | Sí |
| Gestión de stock | `gestion_stock` | Booleano | No (por defecto `false`) |
| Stock actual | `stock_actual` | Numero | Sí **si** gestión de stock = Sí |
| Stock mínimo | `stock_minimo` | Numero | No |
| Recargo de equivalencia | `aplica_recargo_equivalencia` | Booleano | No |
| Activo | `activo` | Booleano | No (por defecto `true`) |

**Categoría por nombre, no por id**: el fichero del usuario nunca va a traer el `categoria_id`
interno. Se resuelve por nombre dentro del tenant; si no existe, la fila se **rechaza** indicando
la categoría desconocida — no se crea la categoría al vuelo (edge case explícito del spec).

**`imagen` queda fuera**: `StoreArticuloRequest` acepta una imagen; una hoja de cálculo no puede
transportar un fichero binario. Ni se exporta ni se importa.

**`stock_actual` al importar**: fija el stock inicial del artículo, igual que el alta manual. No
genera movimientos de stock más allá de lo que ya haga el alta manual — esta feature **no toca**
el ledger `movimientos_stock`, que es append-only por constitución.

### 3.3 `DefinicionProveedores` — importable (sin exportación en el alcance)

Columnas derivadas de `StoreProveedorRequest`: `nombre`, `razon_social`, `nif`, `direccion`, `cp`,
`ciudad`, `provincia`, `pais`, `email`, `telefono`, `notas`. Ninguna obligatoria salvo `pais`
(ISO-2, por defecto `ES`), reflejando fielmente las reglas del alta manual.

> **Asimetría deliberada**: el spec incluye proveedores en importación pero no en exportación
> (el usuario acotó la exportación a los cinco listados principales). Por eso
> `DefinicionProveedores` implementa `DefinicionImportable` pero **no** `DefinicionExportable`
> (§2.2). Habilitar su exportación en el futuro es implementar esa interfaz y añadir la ruta;
> hasta entonces, la capacidad no existe en vez de existir sin usarse.

### 3.4 `DefinicionFacturas` — **solo exportable**

Columnas del listado: Número, Estado, Cliente, NIF cliente, Fecha de expedición, Fecha de
vencimiento, Base imponible, Cuota impositiva, Total, Cobrado, Pendiente, Serie, Rectificativa.

Importes en formato `Importe` (numéricos nativos, D5); fechas en formato `Fecha`. Estados con su
`label()` legible. Excluye las simplificadas, igual que hace hoy el listado (viven en el módulo
POS).

### 3.5 `DefinicionAlbaranes` — **solo exportable**

Columnas: Número, Receptor, Cliente, Estado, Fecha de entrega, Total.

### 3.6 `DefinicionLeads` — **solo exportable**

Columnas: Nombre, Empresa, Email, Teléfono, Estado, Origen, Asignado a, Fecha de alta.

> La **importación** de leads ya existe (`ImportadorLeads`, feature 028) y **no se toca**. Esta
> definición añade únicamente su exportación.

---

## 4. `PrevisualizacionImportacion` (objeto de valor, efímero)

Resultado del análisis de un fichero subido, antes de confirmar. No se persiste en base de datos.

| Campo | Tipo | Descripción |
|---|---|---|
| `token` | `string` (UUID) | Referencia al fichero guardado en almacenamiento privado (D2). |
| `modulo` | `string` | Módulo de destino. |
| `totalFilas` | `int` | Filas de datos leídas (sin la cabecera). |
| `validas` | `int` | Filas que se importarían. |
| `rechazadas` | `FilaRechazada[]` | Detalle de cada rechazo. |
| `muestra` | `array[]` | Primeras 10 filas válidas ya normalizadas, para que el usuario confirme visualmente que las columnas se han interpretado bien. |

**Caducidad**: 24 h. Pasado ese plazo el fichero se purga y confirmar con ese token devuelve un
error pidiendo volver a subirlo (edge case "previsualización caducada").

---

## 5. `FilaRechazada` (objeto de valor)

| Campo | Tipo | Descripción |
|---|---|---|
| `fila` | `int` | Número de fila **en el fichero original**, contando la cabecera como fila 1 (los datos empiezan en la 2). Es el número que el usuario ve en Excel. |
| `motivo` | `string` | Mensaje legible en español. Procede del validador o de las comprobaciones de duplicado/referencia. |

Mismo contrato que las `rechazadas` de `ResultadoImportacionLeads`, para que las vistas y los
mensajes sean coherentes entre ambos importadores.

---

## 6. `ResultadoImportacion` (objeto de valor)

Resultado de una importación **confirmada**.

| Campo | Tipo | Descripción |
|---|---|---|
| `importados` | `int` | Registros creados. |
| `rechazadas` | `FilaRechazada[]` | Filas no importadas, con su motivo. |

Los rechazos se ofrecen como descarga en `.xlsx` (FR-022) para que el usuario corrija y reintente
solo esas filas.

---

## 7. Cambios en estructuras persistentes

Los únicos cambios que tocan código ya existente:

| Archivo | Cambio | Motivo |
|---|---|---|
| `app/Enums/AccionLogActividad.php` | Añadir `case Exportacion = 'exportacion'` con label `'Exportación'` | FR-008. Una exportación no es alta, baja ni modificación; es extracción masiva de datos personales, el evento con más valor forense de la feature (D6). |
| `app/Enums/EntidadLogActividad.php` | Añadir `case Proveedor = 'proveedor'` | FR-021: hasta ahora ningún flujo registraba actividad sobre proveedores. |

Ambos son enums de respaldo (`string`) sin columna de tipo ENUM en base de datos, así que **no
requieren migración**: `logs_actividad` guarda el valor como cadena.
