# Research: Importación y exportación de Excel

**Feature**: 031-import-export-excel | **Fecha**: 2026-07-18

Resuelve los `NEEDS CLARIFICATION` del Technical Context de [plan.md](./plan.md).

---

## D1 — Cómo hacer que la exportación respete los filtros activos del listado (FR-002, SC-002)

**Hallazgo previo (bloqueante):** los cinco listados del alcance **no tienen filtrado
server-side**. Los `index()` de `ClienteController`, `ArticuloController`, `FacturaController`,
`AlbaranController` y `LeadController` devuelven **el dataset completo del tenant** en un único
JSON (`Cliente::orderBy('nombre')->get()`, etc.) y DataTables filtra, busca, ordena y pagina
**en el navegador**. La única excepción es `LeadController`, que acepta un `?filtro=` con tres
valores (`todos`/`mios`/`sin_asignar`).

Es decir: **no existe un "filtro server-side" que el endpoint de exportación pueda replicar.**

**Decisión: el cliente envía los IDs de las filas actualmente visibles (POST), y el backend
genera el fichero con `whereIn('id', $ids)` bajo el scope de tenant.**

DataTables expone las filas que han sobrevivido a búsqueda y filtros con
`table.rows({ search: 'applied' }).data()`. El botón "Exportar" recoge de ahí los `id`, los
manda por POST al endpoint del módulo, y el backend reconstruye la consulta.

**Rationale**:

- **Fidelidad exacta y gratuita (SC-002)**: el conjunto exportado es, por construcción, el mismo
  que el usuario ve. No hay que reimplementar en PHP la semántica de búsqueda de DataTables
  (normalización de acentos, búsqueda por columna, `smart search` con tokens), que es donde
  aparecerían las discrepancias.
- **El scope de tenant se sigue aplicando en el servidor (FR-003, Principio I)**: el `whereIn`
  pasa por el `TenantScope` del `BaseModel`, así que un cliente que manipule la lista de IDs y
  mande IDs de otro tenant **no recibe esas filas**: simplemente no aparecen en el resultado. El
  cliente propone, el servidor dispone. Esto es lo que hace la opción segura.
- **Las columnas, el formato y el registro de actividad siguen en el backend** (FR-005 a FR-008),
  a diferencia de un export puramente client-side.
- **Coste**: 10.000 IDs son ~60 KB de payload. Aceptable, y muy por debajo de límites de
  `post_max_size` por defecto.

**Alternativas consideradas**:

| Alternativa | Por qué se descarta |
|---|---|
| **Botón `excelHtml5` de DataTables Buttons** (export 100% en el navegador) | Exporta el HTML renderizado de las celdas, así que arrastra los botones de acción y los badges de estado; los importes salen como texto formateado y no como números (rompe FR-006); y no permite cumplir FR-008 (registro de actividad), porque el servidor nunca se entera de la exportación. |
| **Replicar los filtros en el backend** (añadir `?buscar=&estado=...` al endpoint de export) | Obliga a reimplementar la semántica de búsqueda de DataTables en PHP y a mantenerla sincronizada por cada columna de cada uno de los 5 listados. Es exactamente el tipo de duplicación que rompe SC-002 en cuanto alguien toca un listado. Además ninguno de los 5 listados tiene hoy esos filtros server-side, así que habría que construirlos solo para el export. |
| **Exportar siempre la tabla entera, ignorando filtros** | Contradice FR-002 directamente y es lo que el usuario pidió evitar. |

**Consecuencia para el diseño**: el endpoint de exportación es `POST`, no `GET`. Un `GET` con
10.000 IDs en la query string excede el límite práctico de longitud de URL. Se documenta en
[contracts/exportacion.md](./contracts/exportacion.md).

---

## D2 — Cómo mantener la previsualización entre subir el archivo y confirmar (FR-012, FR-014)

**Decisión: guardar el archivo subido en `storage/app/private/importaciones/{uuid}` durante la
previsualización, y borrarlo al confirmar, al cancelar o al caducar (24 h) por comando programado.**

La previsualización devuelve un `token` (el UUID). La confirmación reenvía ese token, el backend
recupera el archivo, **lo vuelve a analizar y validar entero**, e importa. No se confía en nada
de lo calculado durante la previsualización.

**Rationale**:

- **FR-014 (nada se persiste antes de confirmar)** se cumple de forma trivial: la previsualización
  es un análisis puro sin escrituras en tablas de negocio.
- **Revalidar en la confirmación no es redundante**: entre previsualizar y confirmar, otro usuario
  del tenant puede haber creado un cliente con el mismo NIF. Si confiásemos en el veredicto de la
  previsualización, insertaríamos un duplicado. La previsualización es *informativa*; la
  confirmación es *autoritativa*. El resumen final que ve el usuario es el de la confirmación.
- **Guardar el archivo, y no las filas parseadas en sesión**, evita meter en la sesión un payload
  de miles de filas (la sesión va a base de datos/fichero y no está pensada para eso).

**Tensión con el Assumption "sin persistencia del archivo subido" del spec**: el spec asume no
conservar el archivo por RGPD. La persistencia aquí es **transitoria y acotada** (necesaria para
que exista un paso de confirmación), no un archivo histórico. Se resuelve así:

- Directorio **privado**, nunca accesible por URL pública.
- Borrado inmediato tras confirmar o cancelar.
- Comando `importaciones:purgar` que barre lo que quede con más de 24 h (huérfanos de sesiones
  abandonadas), enganchado en `bootstrap/app.php` → `withSchedule`, **exactamente el patrón de
  `logs:purgar` que exige la constitución** (Additional Constraints → "Datos personales y
  retención").

**Alternativas consideradas**:

| Alternativa | Por qué se descarta |
|---|---|
| **Sin previsualización** (subir = importar, como hace hoy `ImportadorLeads`) | Es lo que hace leads y funciona, pero el spec lo pide explícitamente (FR-012) y con razón: en clientes/artículos el usuario carga su cartera entera de una vez y quiere ver qué va a pasar antes de comprometerlo. |
| **Guardar las filas parseadas en la sesión** | Miles de filas en la sesión; además obligaría a serializar y confiar en un veredicto de validación caducado (ver arriba). |
| **Tabla `importaciones_pendientes` en base de datos** | Datos personales en una tabla nueva → plazo de retención, purga, `tenant_id`, scope, migración y tests de aislamiento propios. Complejidad muy superior para el mismo resultado (Principio V, YAGNI). |

---

## D3 — Reutilizar las validaciones del alta manual sin duplicarlas (FR-017)

**Decisión: instanciar el `FormRequest` existente de cada módulo y correr sus `rules()` contra
cada fila con `Validator::make()`.**

Ya existen `StoreClienteRequest`, `StoreArticuloRequest` y `StoreProveedorRequest`. Sus reglas
son la definición autoritativa de "qué es un cliente/artículo/proveedor válido", incluida la
validación de NIF (`ValidadorIdentificacionFiscal`) y la unicidad dentro del tenant.

**Rationale**:

- Cumple FR-017 literalmente: *las mismas* validaciones, no unas equivalentes.
- Si mañana se añade un campo obligatorio al alta manual, la importación lo hereda sin tocarla.
- Los mensajes de error del validador son ya en español y orientados al usuario, así que sirven
  directamente como "motivo del rechazo" (FR-013) sin traducirlos.

**Detalle a resolver en implementación**: las reglas `unique` de esos FormRequests deben
comprobarse **también contra las filas anteriores del propio archivo**, no solo contra la base de
datos, para cubrir el edge case "duplicados dentro del propio archivo". Se lleva un registro en
memoria de los identificadores ya vistos durante el recorrido y se rechaza la segunda aparición.

**Alternativa considerada**: escribir un juego de reglas propio del importador (más laxo, para
"ser tolerante con lo que viene de Excel"). Se descarta: produciría registros que el alta manual
habría rechazado, es decir, datos inválidos entrando por la puerta de atrás.

---

## D4 — Lectura del archivo: en memoria vs. por lotes

**Decisión: lectura síncrona en memoria con `Excel::toArray()` + `WithHeadingRow`, acotada por un
límite duro de 2.000 filas por archivo (FR-019).**

**Rationale**:

- Es el patrón que ya usa `ImportadorLeads` y funciona en producción.
- **Principio V (hosting compartido)**: sin colas, sin workers, sin procesos en segundo plano.
- 2.000 filas es holgado para el caso real (cargar la cartera de clientes de una pyme) y se lee
  muy por debajo de los límites de memoria y tiempo de un hosting compartido típico.
- El límite se comprueba **antes** de procesar nada, así el usuario recibe un mensaje claro en vez
  de un timeout (edge case "archivo muy grande").

**Para la exportación**, en cambio, se usa `FromQuery` + `WithChunkReading` (chunks de 500). La
exportación no tiene límite de filas configurable porque el usuario no controla cuántas tiene, y
`FromCollection` con 10.000 facturas y sus relaciones agotaría la memoria (SC-009). `FromQuery`
con chunking mantiene el consumo plano.

---

## D5 — Formato de importes y fechas en el fichero exportado (FR-006)

**Decisión: escribir importes y fechas como **valores nativos** de la hoja de cálculo (numérico y
fecha), aplicando el formato de visualización español mediante `WithColumnFormatting`.**

**Rationale**: FR-006 exige explícitamente que los importes sean usables en fórmulas. Si se
escriben con `Formato::importe()` como cadena `"1.234,56 €"`, Excel los trata como texto y
`=SUMA()` devuelve 0 — que es justo el motivo por el que el usuario exporta. Escribir el número
crudo y dejar que el *formato de celda* muestre `1.234,56 €` da ambas cosas.

**Consecuencia**: en las columnas monetarias y de fecha, el export **no** reutiliza los helpers de
`App\Support\Formato` que usan las vistas Blade; usa el valor crudo del modelo. En columnas de
texto (estados, etiquetas de enum) sí se reutilizan los `label()` de los enums, para que el
fichero diga "Entregado" y no `entregado`.

---

## D6 — Registro de actividad de exportaciones e importaciones (FR-008, FR-021)

**Decisión: reutilizar `RegistradorActividad` (feature 021), añadiendo un caso `Exportacion` al
enum `AccionLogActividad` y un caso `Proveedor` al enum `EntidadLogActividad`.**

**Rationale**:

- La constitución y el spec obligan a reutilizar el mecanismo existente en vez de crear un
  historial propio.
- `AccionLogActividad` tiene hoy `Login`, `Logout`, `Alta`, `Baja`, `Modificacion`. Una
  exportación **no encaja en ninguno**: no altera datos, pero *extrae datos personales en bloque*,
  que es precisamente el tipo de evento que RGPD quiere trazable (Principio II). Forzarlo dentro
  de `Modificacion` haría el log menos útil y sería mentira.
- Una **importación sí es un alta** (masiva), así que reutiliza `Alta`, igual que ya hace
  `LeadImportacionController`. No se añade un caso nuevo para ella.
- `EntidadLogActividad` no tiene `Proveedor` porque hasta ahora ningún flujo lo registraba;
  hace falta para FR-021.

**Alternativa considerada**: no registrar las exportaciones (solo las importaciones). Se descarta:
FR-008 lo exige, y una exportación masiva de datos personales es el evento con más valor
forense de toda la feature — es la vía por la que se fuga una base de datos de clientes.

---

## D7 — Alineación entre exportación, plantilla e importación (FR-024, SC-007)

**Decisión: una única clase de definición de columnas por módulo (`DefinicionExcel`), de la que
derivan el exportador, la plantilla y el importador.**

Cada columna declara: clave interna, etiqueta en español, si es obligatoria al importar, cómo se
lee del modelo al exportar y cómo se normaliza del fichero al importar.

**Rationale**:

- SC-007 (un fichero exportado se puede reimportar) y FR-024 (cabeceras de plantilla = cabeceras
  de exportación) son **imposibles de garantizar de forma estable** si las tres cosas se escriben
  por separado: se desincronizan en cuanto alguien añade una columna a una sola de ellas.
- SC-010 (añadir un módulo no obliga a tocar los existentes) sale gratis: un módulo nuevo es una
  clase de definición nueva y una entrada en el registro.
- Los módulos solo exportables (facturas, albaranes, leads) declaran su definición **sin** la
  parte de importación. Que la mitad de importación no exista es lo que hace que FR-010 (prohibido
  importar facturas) sea cierto **por construcción** y no por una comprobación que alguien pueda
  olvidar.

**Alternativa considerada**: una clase `Export` de Maatwebsite por módulo, suelta, más un
importador suelto (lo más idiomático en Laravel). Se descarta por lo anterior: es exactamente el
escenario en el que SC-007 se rompe silenciosamente.
