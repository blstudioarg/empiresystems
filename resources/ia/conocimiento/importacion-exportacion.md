# Importación y exportación de Excel

Los listados de **clientes, artículos, facturas, albaranes y leads** se pueden exportar a `.xlsx`
desde el botón "Exportar" de cada pantalla; el fichero respeta la búsqueda/filtro que el usuario
tenga activo en ese momento, nunca exporta el listado completo si hay un filtro aplicado.

Solo **clientes, artículos y proveedores** se pueden importar desde un Excel/CSV propio, con una
previsualización obligatoria antes de confirmar (se ven cuántas filas son válidas, cuáles se
rechazan y por qué motivo) — nada se guarda hasta que el usuario confirma. Cada uno de esos tres
módulos tiene una plantilla descargable con las columnas exactas y una fila de ejemplo.

**Facturas, albaranes y pagos NO se pueden importar bajo ningún concepto** — no existe esa
funcionalidad y no hay forma de habilitarla desde la interfaz. Si el usuario pide importar
facturas o albaranes, hay que explicarle que esos documentos se generan siempre desde la propia
aplicación (numeración correlativa e impuestos calculados por el sistema), nunca por carga masiva.
