# Research: Importación conversacional con el asistente

**Feature**: 046-importacion-conversacional-asistente | **Fecha**: 2026-09-07

---

## D1 — El importador existente sigue siendo la autoridad

**Decisión**: no se crea una segunda vía de escritura. El asistente orquesta y conversa; quien valida
y quien escribe sigue siendo el pipeline de la feature 031 (`DefinicionImportable::validador()` y
`::crear()`).

**Rationale**: ese pipeline ya garantiza cosas que sería un disparate reimplementar: `validador()`
delega en el **FormRequest del alta manual**, así que las reglas son literalmente las mismas y no un
juego paralelo más laxo; `crear()` fuerza `tenant_id` ignorando cualquier columna que pretenda
fijarlo (Principio I); y la confirmación revalida desde cero en vez de fiarse del análisis previo.
Un camino propio del asistente empezaría desalineado y se desalinearía más con cada cambio.

**Alternativa descartada**: que el asistente use sus tools de escritura (`crear_cliente`, etc.) una
por registro. Funcionaría para 5 filas y sería inviable para 200, y además duplicaría la detección de
duplicados y el reporte de rechazos.

---

## D2 — Hace falta una costura por filas, no por fichero

**Decisión**: extraer de `ImportadorExcel` el análisis de **filas ya normalizadas**
(`analizarFilas()`) y exponer dos entradas nuevas que trabajan sobre filas en vez de sobre un
`UploadedFile`. La ruta de fichero existente pasa a apoyarse en la misma pieza.

**Rationale**: la corrección conversacional (US2) es imposible sobre un fichero: cuando la persona
dice "el NIF de Acme es B12345678", no se puede reescribir el `.xlsx` original. Lo que se corrige son
filas. Y el material no estructurado (US3) **nunca fue un fichero tabular**: la interpretación
produce filas directamente.

**Cómo se preserva la garantía de la confirmación**: al confirmar se **revalidan las filas** contra la
base de datos en ese momento, no se reutiliza el veredicto del análisis. Es la misma propiedad que
hoy da revalidar el fichero (research D2 de la 031), aplicada al nuevo soporte.

---

## D3 — Interpretación de material no estructurado

**Decisión**: una pieza aislada (`InterpretadorMaterialImportable`) que recibe el documento y
devuelve filas conformes a las columnas de la definición, con salida forzada por JSON Schema
estricto. No conoce Eloquent ni valida negocio.

**Rationale**: es exactamente el patrón que ya funcionó en la feature 044
(`InterpretadorDocumentoCompra` habla con el proveedor; `ProponedorCompraDesdeDocumento` traduce a
negocio). Permite probar todo lo que no es la llamada sin red ni clave de API, y deja un único punto
que toca al proveedor.

**Regla que hereda de la 044 y aquí es crítica**: *"no inventes ningún valor; si un dato no se lee
con seguridad, devolvé null"*. Un NIF inventado entra en el maestro de clientes y nadie lo detecta
(FR-009). El prompt debe prohibirlo explícitamente y el schema debe permitir `null` en todo lo que no
sea imprescindible para identificar la fila.

**Formato de envío**: imagen como `image_url` con data URI, PDF como parte `file` con `file_data`,
igual que la 044 — son los dos formatos que el proveedor acepta sin conversión previa, y el hosting
compartido no tiene Imagick ni Ghostscript para rasterizar (Principio V).

---

## D4 — Dónde vive la importación en curso

**Decisión**: un borrador por token, guardado con `AlmacenImportaciones` (el almacén de la 031), que
contiene el módulo, las filas de trabajo y el registro de correcciones aplicadas. El documento
original se guarda solo mientras hace falta interpretarlo.

**Rationale**: reutiliza el almacén y, con él, la purga `importaciones:purgar` que ya existe y que la
constitución obliga a reutilizar (Principio II, Additional Constraints). Guardar las **filas de
trabajo** y no solo el original es lo que hace posible la corrección incremental: cada respuesta de la
persona muta el borrador, y el análisis se rehace sobre él.

**Alternativa descartada**: guardar solo el original y reaplicar todas las correcciones en cada
análisis. Más "puro", pero cada ronda tendría que reinterpretar el documento —volviendo a pagar la
llamada al proveedor— para acabar en el mismo sitio.

---

## D5 — Cómo llega el material al asistente

**Decisión**: el panel gana un clip que sube el fichero a un endpoint propio **antes** de enviar el
mensaje; el mensaje viaja con el token del material, no con el fichero.

**Rationale**: el endpoint de mensaje es SSE por streaming dentro del request; meterle un
`multipart/form-data` con un PDF de 5 MB complica el flujo sin ganar nada. Separar la subida además
permite validar tipo y tamaño y responder un error claro (FR-003) antes de gastar un turno.

**Alcance deliberado**: el clip **solo** aparece y solo se acepta material en el contexto de una
importación. Adjuntar ficheros para cualquier otra cosa sigue fuera de alcance (assumption del spec).

---

## D6 — Cómo conversa el asistente sobre el análisis

**Decisión**: una tool de lectura (`analizar_material_importable`) devuelve al modelo el análisis
estructurado —leídos, válidos, rechazados con motivo y fila—, y una tool de corrección
(`corregir_filas_importables`) aplica los cambios que la persona dicta. La confirmación final es una
escritura, con su tarjeta.

**Rationale**: mantiene el reparto que ya funciona en el asistente: las lecturas se ejecutan solas,
las escrituras se proponen y se confirman (D4 de la 030). El modelo redacta el "3 clientes no tienen
NIF, ¿me lo das?" a partir de datos reales; no lo deduce ni lo inventa.

**El recuento nunca lo hace el modelo**: los números salen del análisis. Pedirle a un modelo que
cuente filas de una lista larga es una forma conocida de equivocarse.

---

## D7 — Confirmación: una tarjeta, no doscientas

**Decisión**: la importación se propone como **una sola acción** cuya tarjeta lleva el ojo al detalle
—la tabla con todos los campos— que ya existe desde la feature 045.

**Rationale**: la infraestructura ya está: propuestas con varias acciones, tabla de campos y la caja
de "estado del análisis" del modal, que se creó vacía justamente esperando a esta feature. Aquí se
rellena con el resultado del análisis (qué fichero, cuántas filas, qué no se pudo interpretar).

---

## D8 — Sugerencias del estado vacío

**Decisión**: catálogo en servidor, agrupado por categoría, filtrado por los permisos de la persona,
con textos fijos.

**Rationale**: filtrar en servidor es lo mismo que ya hace `CatalogoTools::paraUsuario`, y evita
ofrecer algo que al pulsarlo respondería "no tenés permiso". Textos fijos porque generarlos con IA
costaría una llamada en cada apertura del panel para un beneficio nulo.

---

## D9 — Límites del material interpretado

**Decisión**: el límite de 2.000 filas de la 031 se mantiene para todo. Para documentos
interpretados se añade un tope propio de páginas/imágenes por importación, porque el coste y la
latencia crecen con el documento y no con las filas resultantes.

**Rationale**: es el hueco que el checklist del spec marcó como pendiente de decidir aquí. Un PDF de
40 páginas puede producir 30 filas y aun así costar cuarenta veces más que un Excel con 2.000. El
tope va en `config/`, no hardcodeado, para poder ajustarlo sin tocar código.

---

## D10 — Estrategia de tests

**Decisión**: test-first en aislamiento (Principio IV) y en la lógica de corrección de filas. La
interpretación se prueba sin red sustituyendo el punto que habla con el proveedor, como en la 044 y
en `CompactadorConversacion`.

**Rationale**: el aislamiento es no negociable y aquí hay material subido por personas concretas. La
aplicación de correcciones sobre el borrador es cálculo puro y es donde un error silencioso —corregir
la fila equivocada— haría un daño difícil de detectar.
