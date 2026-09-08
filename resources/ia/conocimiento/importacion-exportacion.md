# Importación y exportación de Excel

Los listados de **clientes, artículos, facturas, albaranes, leads y logs de actividad** se pueden
exportar a `.xlsx` desde el botón "Exportar" de cada pantalla; el fichero respeta la
búsqueda/filtro que el usuario tenga activo en ese momento, nunca exporta el listado completo si
hay un filtro aplicado.

Solo **clientes, artículos y proveedores** se pueden importar desde un Excel/CSV propio, con una
previsualización obligatoria antes de confirmar (se ven cuántas filas son válidas, cuáles se
rechazan y por qué motivo) — nada se guarda hasta que el usuario confirma. Cada uno de esos tres
módulos tiene una plantilla descargable con las columnas exactas y una fila de ejemplo.

**Facturas, albaranes y pagos NO se pueden importar bajo ningún concepto** — no existe esa
funcionalidad y no hay forma de habilitarla desde la interfaz. Si el usuario pide importar
facturas o albaranes, hay que explicarle que esos documentos se generan siempre desde la propia
aplicación (numeración correlativa e impuestos calculados por el sistema), nunca por carga masiva.

## Importar hablando con el asistente

Además de la pantalla de importación, **los tres módulos importables (clientes, artículos y
proveedores) se pueden importar conversando con el asistente**, sin pasar por esa pantalla.

Cómo funciona:

1. El usuario dice qué quiere importar ("necesito importar clientes desde un Excel"). En ese
   momento aparece el clip para adjuntar en el panel del asistente. El clip **solo** existe para
   importar: no sirve para adjuntar ficheros a cualquier otra cosa.
2. Se adjunta el material. Se admiten hojas de cálculo (`.xlsx`, `.xls`, `.csv`) y también
   documentos que no son tablas: PDF, imágenes (`.jpg`, `.png`, `.webp`) y texto (`.txt`). Máximo
   5 MB por fichero y 2.000 filas por importación; los PDF tienen además un tope de páginas
   (20 por defecto), porque interpretarlos cuesta y tarda mucho más que leer un Excel.
3. El asistente analiza el material y cuenta qué leyó, qué es válido y qué falla y por qué,
   **nombrando los registros afectados** ("a Acme y Beta les falta el NIF"), no números de fila.
4. El usuario aporta los datos que faltan escribiendo en el chat, o pide descartar registros
   concretos. Tras cada corrección el asistente vuelve a dar el recuento actualizado.
5. Cuando está de acuerdo, el asistente propone la importación en una tarjeta de confirmación. El
   ojo del detalle abre la tabla con **todos** los campos de todos los registros que se van a
   crear, y una caja con el estado del análisis (origen, leídas, válidas, descartadas y lo que no
   se pudo interpretar).
6. Al confirmar se importan los válidos y se informa de los rechazados con su motivo, sin perder
   el resto. La importación queda registrada en el historial de actividad.

Reglas importantes que el asistente **no puede romper**:

- **No inventa datos.** Si un documento no dice el NIF de alguien, ese hueco queda vacío y se
  pregunta; nunca se rellena con un valor plausible. Un dato inventado en el maestro de clientes no
  lo detecta nadie.
- **Nada se escribe hasta la confirmación.** Analizar y corregir cuantas veces haga falta no crea
  ni un registro.
- Se importa siempre **la última versión acordada**, no un análisis intermedio.
- Las validaciones son exactamente las mismas que las del alta manual y las de la pantalla de
  importación: no hay un camino más permisivo por hablar con el asistente.
- Solo clientes, artículos y proveedores. **Facturas, albaranes y pagos siguen sin poder
  importarse**, tampoco por esta vía.
- El material adjunto es privado de quien lo subió (ni siquiera lo ve otra persona de la misma
  empresa), pertenece a la conversación en la que se adjuntó y se borra al importarlo, al
  descartarlo o a las 24 horas.
- Los documentos que no son tablas necesitan que el asistente esté configurado con su clave de
  API. Las hojas de cálculo no: se leen igual aunque no haya clave.
