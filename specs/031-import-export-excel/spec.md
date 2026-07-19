# Feature Specification: Importación y exportación de Excel

**Feature Branch**: `031-import-export-excel`

**Created**: 2026-07-18

**Status**: Draft

**Input**: User description: "Import y export de Excel. EXPORT: en los listados principales (clientes, artículos/productos, facturas, albaranes, leads) — exportar a .xlsx respetando los filtros activos del listado y el scope de tenant. IMPORT: solo en tablas maestras (clientes, artículos/productos, proveedores) — subir .xlsx/.csv, previsualizar, validar fila por fila con reporte de errores, y forzar siempre el tenant_id del tenant activo (nunca leerlo del archivo). Queda EXPLÍCITAMENTE fuera del alcance el import de facturas y albaranes (por Verifactu, numeración e integridad financiera). Ya existe maatwebsite/excel ^3.1 en composer.json y un precedente en app/Services/ImportadorLeads.php que sirve de patrón."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Exportar un listado a Excel (Priority: P1)

Un usuario del tenant está viendo un listado (clientes, artículos, facturas, albaranes o leads),
posiblemente con filtros y una búsqueda aplicados. Quiere llevarse esos mismos datos a Excel para
analizarlos, enviarlos a su gestoría o cruzarlos con otra hoja. Pulsa un botón "Exportar" en la
cabecera del listado y recibe un archivo `.xlsx` con exactamente las filas que estaba viendo
(las que cumplen sus filtros), no solo la página actual y no la tabla entera.

**Why this priority**: Es la mitad de la feature que aporta valor inmediato sin ningún riesgo de
corromper datos: solo lee. Puede entregarse y demostrarse sola, y cubre el caso de uso más
frecuente ("necesito estos datos en Excel"). Además define el formato de archivo que la
importación reutiliza después.

**Independent Test**: Aplicar un filtro en el listado de clientes, pulsar Exportar, abrir el
archivo descargado y verificar que contiene solo los clientes filtrados, con las columnas visibles
del listado y sus cabeceras en español.

**Acceptance Scenarios**:

1. **Given** un tenant con 50 clientes y un filtro activo que deja 12 visibles, **When** el usuario pulsa "Exportar" en el listado de clientes, **Then** el archivo descargado contiene 12 filas de datos más una fila de cabecera.
2. **Given** un listado paginado mostrando la página 1 de 5, **When** el usuario exporta, **Then** el archivo contiene todas las filas que cumplen el filtro, no solo las de la página visible.
3. **Given** dos tenants con datos distintos, **When** un usuario del tenant A exporta clientes, **Then** el archivo no contiene ninguna fila del tenant B.
4. **Given** un usuario sin el permiso de ver un módulo, **When** intenta acceder a la exportación de ese módulo, **Then** el sistema deniega la operación y no genera ningún archivo.
5. **Given** un listado cuyo filtro no deja ninguna fila, **When** el usuario exporta, **Then** recibe un archivo válido con solo la fila de cabecera y un aviso de que no había datos.

---

### User Story 2 - Importar un maestro desde Excel con previsualización (Priority: P2)

Un usuario que acaba de darse de alta (o que cambia de programa) tiene su cartera de clientes,
su catálogo de artículos o su lista de proveedores en una hoja de cálculo. Quiere cargarlos en el
sistema sin teclearlos uno a uno. Sube el archivo, el sistema le muestra una previsualización de
lo que va a pasar (cuántas filas son válidas, cuántas se rechazarían y por qué motivo, fila por
fila), y solo cuando confirma se escriben los datos.

**Why this priority**: Elimina la barrera de entrada más grande para un tenant nuevo, pero
requiere que exista antes un formato conocido (User Story 1) y conlleva riesgo de escritura, por
lo que va después. La previsualización es lo que convierte una carga masiva ciega en una operación
que el usuario puede revisar antes de comprometerla.

**Independent Test**: Subir un archivo con 10 filas de clientes, de las cuales 3 tienen errores
conocidos (NIF inválido, falta el nombre, NIF duplicado); comprobar que la previsualización marca
esas 3 con su motivo y que, tras confirmar, se crean exactamente 7 clientes.

**Acceptance Scenarios**:

1. **Given** un archivo con 10 filas válidas de clientes, **When** el usuario lo sube, **Then** la previsualización indica 10 filas válidas y 0 rechazadas, y ningún cliente se ha creado todavía.
2. **Given** esa misma previsualización, **When** el usuario confirma la importación, **Then** se crean 10 clientes en el tenant activo y el sistema informa del resultado.
3. **Given** un archivo con filas inválidas mezcladas con válidas, **When** el usuario confirma, **Then** se importan solo las válidas y el sistema muestra el listado de filas rechazadas con su número de fila y motivo.
4. **Given** un archivo que incluye una columna con el identificador de otro tenant, **When** se importa, **Then** los registros creados pertenecen al tenant activo del usuario y esa columna del archivo se ignora por completo.
5. **Given** un archivo cuyas cabeceras no corresponden a ninguna columna esperada, **When** el usuario lo sube, **Then** el sistema rechaza el archivo entero con un mensaje que indica qué cabeceras se esperaban, sin importar nada.
6. **Given** un usuario sin permiso sobre el módulo, **When** intenta importar en él, **Then** la operación se deniega.

---

### User Story 3 - Descargar una plantilla de importación (Priority: P3)

Antes de importar, el usuario necesita saber qué columnas debe tener su archivo y en qué formato
(fechas, decimales, valores admitidos en campos de tipo lista). Descarga desde la propia pantalla
de importación una plantilla `.xlsx` vacía con las cabeceras correctas y una fila de ejemplo.

**Why this priority**: Reduce drásticamente los intentos fallidos de importación, pero la
importación funciona sin ella (el mensaje de error de cabeceras ya orienta). Es una mejora de
usabilidad sobre una capacidad que ya existe, así que va al final.

**Independent Test**: Descargar la plantilla de artículos, rellenarla con dos filas y comprobar
que se importa sin errores de formato ni de cabeceras.

**Acceptance Scenarios**:

1. **Given** la pantalla de importación de artículos, **When** el usuario pulsa "Descargar plantilla", **Then** obtiene un archivo con las cabeceras exactas que el importador espera y una fila de ejemplo.
2. **Given** una plantilla descargada y rellenada sin modificar las cabeceras, **When** se importa, **Then** no se produce ningún rechazo por cabeceras desconocidas.

---

### Edge Cases

- **Archivo muy grande**: un archivo con más filas de las admitidas debe rechazarse antes de procesarse, con un mensaje que indique el límite, en vez de agotar el tiempo o la memoria del servidor.
- **Exportación muy grande**: un listado con decenas de miles de filas debe seguir produciendo un archivo completo sin agotar la memoria; si supera el umbral admitido, el sistema debe avisar al usuario y pedirle que acote los filtros.
- **Archivo corrupto o de formato no admitido**: subir un PDF renombrado a `.xlsx`, un archivo vacío o uno protegido con contraseña debe producir un error claro, nunca un fallo interno.
- **Duplicados dentro del propio archivo**: dos filas del mismo archivo con el mismo identificador fiscal — la primera se importa, la segunda se rechaza como duplicada.
- **Duplicados contra datos existentes**: una fila cuyo identificador fiscal ya existe en el tenant se rechaza indicando el conflicto.
- **Celdas con formato inesperado**: fechas como texto, números con separador de miles, importes con símbolo de moneda, celdas vacías en campos opcionales — deben normalizarse cuando sea razonable y rechazarse con motivo cuando no.
- **Caracteres especiales y acentos**: nombres con acentos, eñes o comillas deben sobrevivir intactos tanto al exportar como al importar.
- **Previsualización caducada**: si el usuario tarda demasiado entre previsualizar y confirmar, la confirmación debe fallar limpiamente pidiendo volver a subir el archivo, nunca importar datos parciales o desactualizados.
- **Referencias a otros registros**: una fila de artículo que menciona una categoría o unidad inexistente debe rechazarse con ese motivo, no crear silenciosamente la categoría.

## Requirements *(mandatory)*

### Functional Requirements

#### Exportación

- **FR-001**: El sistema MUST ofrecer exportación a formato de hoja de cálculo en los listados de clientes, artículos, facturas, albaranes y leads.
- **FR-002**: La exportación MUST incluir todas las filas que cumplen los filtros, la búsqueda y el orden activos en el listado en ese momento, no solo las de la página visible.
- **FR-003**: La exportación MUST limitarse a los datos del tenant activo del usuario; ningún dato de otro tenant puede aparecer en el archivo bajo ninguna circunstancia.
- **FR-004**: La exportación MUST respetar los permisos de módulo del usuario: quien no puede ver un listado tampoco puede exportarlo.
- **FR-005**: El archivo exportado MUST llevar una fila de cabecera con los nombres de columna en español, coherentes con las etiquetas que el usuario ve en pantalla.
- **FR-006**: Las fechas e importes exportados MUST usar el formato español ya establecido en la aplicación, y los importes MUST exportarse como valores numéricos utilizables en fórmulas, no como texto.
- **FR-007**: El nombre del archivo descargado MUST identificar el módulo y la fecha de generación.
- **FR-008**: El sistema MUST registrar cada exportación en el registro de actividad, indicando módulo, usuario y número de filas exportadas.

#### Importación

- **FR-009**: El sistema MUST ofrecer importación desde hoja de cálculo únicamente en clientes, artículos y proveedores.
- **FR-010**: El sistema MUST NOT ofrecer importación de facturas, albaranes, pagos, movimientos de stock ni ninguna otra entidad con implicaciones de numeración correlativa, encadenamiento Verifactu o integridad financiera.
- **FR-011**: El sistema MUST aceptar archivos en los formatos habituales de hoja de cálculo y de valores separados por comas.
- **FR-012**: El sistema MUST mostrar una previsualización antes de escribir ningún dato, indicando el total de filas leídas, cuántas se importarían y cuántas se rechazarían.
- **FR-013**: La previsualización MUST detallar cada fila rechazada con su número de fila en el archivo original y el motivo concreto del rechazo.
- **FR-014**: Ningún dato MUST persistirse hasta que el usuario confirme explícitamente la previsualización.
- **FR-015**: El sistema MUST asignar siempre el tenant activo del usuario a los registros importados, ignorando cualquier columna del archivo que pretenda fijar un tenant.
- **FR-016**: Las filas inválidas MUST NOT abortar la importación completa: las filas válidas se importan y las rechazadas se reportan, siguiendo el criterio ya establecido en la importación de leads.
- **FR-017**: Cada fila importada MUST pasar por las mismas validaciones de negocio que el alta manual equivalente (obligatoriedad de campos, validez del identificador fiscal, unicidad dentro del tenant, valores admitidos en campos de tipo lista).
- **FR-018**: El sistema MUST rechazar el archivo completo, sin importar nada, cuando sus cabeceras no permitan identificar las columnas obligatorias, indicando cuáles se esperaban.
- **FR-019**: El sistema MUST aplicar un límite máximo de filas por archivo y comunicarlo al usuario cuando se supere.
- **FR-020**: La importación MUST respetar los permisos de módulo del usuario.
- **FR-021**: El sistema MUST registrar cada importación confirmada en el registro de actividad, indicando módulo, usuario, filas importadas y filas rechazadas.
- **FR-022**: Tras confirmar, el sistema MUST mostrar un resumen del resultado y permitir al usuario descargar el detalle de las filas rechazadas para corregirlas y reintentar.

#### Plantillas

- **FR-023**: Cada pantalla de importación MUST ofrecer la descarga de una plantilla con las cabeceras exactas que el importador espera y al menos una fila de ejemplo.
- **FR-024**: Las cabeceras de la plantilla MUST coincidir con las que produce la exportación del mismo módulo, de forma que un archivo exportado y reeditado pueda reimportarse.

#### Documentación

- **FR-025**: La feature MUST añadir o actualizar la guía in-app correspondiente y el archivo de base de conocimiento del asistente IA para los módulos afectados.

### Key Entities

- **Definición de exportación de un módulo**: describe qué columnas se exportan para un listado dado, con qué etiqueta y en qué orden. Es lo que mantiene alineadas exportación, plantilla e importación.
- **Definición de importación de un módulo**: describe qué columnas se esperan, cuáles son obligatorias, cómo se normaliza cada valor y qué reglas de negocio validan la fila.
- **Previsualización de importación**: resultado temporal del análisis de un archivo subido, antes de confirmar. Contiene el recuento de filas válidas y rechazadas y el detalle de cada rechazo. Es efímera: no forma parte del modelo de negocio permanente.
- **Fila rechazada**: número de fila en el archivo original y motivo legible del rechazo.
- **Resultado de importación**: recuento de filas importadas y rechazadas de una importación ya confirmada, junto con el detalle descargable de los rechazos.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede exportar cualquiera de los cinco listados soportados en menos de 3 acciones desde la pantalla del listado.
- **SC-002**: El archivo exportado contiene exactamente el mismo conjunto de registros que el listado muestra con los filtros aplicados, verificado por recuento en los cinco módulos.
- **SC-003**: Ninguna exportación ni importación produce o expone un registro perteneciente a otro tenant, verificado con al menos dos tenants poblados con datos distintos.
- **SC-004**: Un usuario que parte de su propia hoja de cálculo consigue cargar su cartera de clientes sin ayuda externa en menos de 10 minutos.
- **SC-005**: Ante un archivo con errores, el usuario puede identificar cada fila problemática y su motivo sin abrir ningún registro ni consultar soporte.
- **SC-006**: Ninguna importación escribe datos antes de la confirmación explícita del usuario, verificado comprobando que el recuento de registros no varía tras la previsualización.
- **SC-007**: Un archivo exportado de un módulo importable puede reimportarse sin modificar sus cabeceras y sin producir rechazos por formato.
- **SC-008**: Un archivo malformado, vacío, corrupto o de tipo no admitido nunca produce un fallo interno: el usuario siempre recibe un mensaje explicativo.
- **SC-009**: La exportación de un listado de 10.000 filas se completa sin degradar la aplicación para el resto de usuarios.
- **SC-010**: Añadir la exportación o importación de un módulo nuevo en el futuro no obliga a modificar la de los módulos ya existentes.

## Assumptions

- **Alcance decidido por el usuario**: importación limitada a los maestros (clientes, artículos, proveedores) y exportación en los cinco listados principales. Facturas y albaranes se exportan pero nunca se importan, por Verifactu, numeración correlativa e integridad financiera (Principios II y III de la constitución).
- **Leads**: ya cuentan con un importador propio (`ImportadorLeads`), por lo que aquí solo se añade su exportación; su importación existente se mantiene y sirve de patrón de comportamiento (no abortar por filas inválidas, reportar cada rechazo). Se asume que no se rehace ni se migra en esta feature.
- **Formato de salida**: se asume `.xlsx` como formato de exportación por ser el que el usuario pidió y el que preserva tipos numéricos y de fecha; la importación además acepta CSV por ser lo que exportan muchos programas de origen.
- **Sin colas ni procesamiento en segundo plano**: siguiendo el Principio V (simplicidad y compatibilidad con hosting compartido) y el precedente de `ImportadorLeads`, la importación y la exportación se resuelven de forma síncrona dentro de la petición. El límite de filas existe precisamente para que eso sea viable; si en el futuro se necesitan volúmenes mayores, será una decisión documentada aparte.
- **Permisos**: se reutiliza el catálogo de permisos por módulo ya existente (feature 027). Se asume que el permiso de ver un módulo habilita su exportación y su importación, sin introducir permisos nuevos y separados, para no multiplicar la matriz de roles sin necesidad demostrada.
- **Registro de actividad**: se reutiliza el mecanismo de logs de actividad existente (feature 021) en vez de crear un historial propio de importaciones/exportaciones.
- **Persistencia mínima y transitoria del archivo subido**: el archivo importado **no se conserva como histórico**, pero sí existe de forma transitoria entre la previsualización y la confirmación, porque sin él no puede haber un paso de confirmación (el usuario tendría que volver a subirlo). Contiene datos personales (clientes, proveedores), así que esa retención transitoria queda acotada: almacenamiento privado nunca accesible por URL pública, borrado inmediato al confirmar o cancelar, y purga automática de lo que quede huérfano a las 24 h, reutilizando el mecanismo de retención y purga ya establecido en el proyecto (Principio II). Lo que se descarta es conservarlo más allá de eso.
- **Sin actualización de registros existentes**: la importación crea registros nuevos y rechaza los que chocan con uno existente. No se contempla en esta feature el modo "actualizar si ya existe", por el riesgo de sobrescribir datos en masa sin posibilidad de deshacer.
- **Columnas exportadas**: se asume que las columnas exportadas son las del listado en pantalla más los identificadores necesarios para que el archivo sea útil, no la totalidad de los campos de la tabla.
- **Dependencia ya resuelta**: la biblioteca de hojas de cálculo ya está presente en el proyecto y en uso, por lo que no se introduce ninguna dependencia nueva.
