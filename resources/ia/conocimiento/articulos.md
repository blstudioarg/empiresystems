# Catálogo (artículos)

El catálogo contiene **productos** y **servicios**, cada uno con nombre, SKU opcional, precio
unitario y tipo impositivo (IVA/IGIC). Los productos pueden gestionar stock (Kardex). El precio y el
impuesto del artículo son los que se aplican al añadirlo a un presupuesto o factura.

El listado se puede **exportar** a Excel y también **importar** desde un Excel/CSV propio (con
previsualización antes de confirmar) — ver `importacion-exportacion.md`. La categoría del fichero
se resuelve por nombre dentro del tenant; si no existe, esa fila se rechaza en vez de crearla sola.
