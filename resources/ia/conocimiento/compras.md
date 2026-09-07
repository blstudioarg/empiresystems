# Compras

Las compras registran las **facturas y albaranes que la empresa recibe de sus proveedores**. Sirven
para llevar el gasto y para **reponer inventario**.

Una compra pasa por tres estados: **borrador** (editable y sin efecto en el stock), **confirmada**
y **anulada**. Confirmar una compra **suma unidades al inventario** por cada línea cuyo artículo sea
un producto con gestión de stock; anularla genera el movimiento inverso y devuelve el stock a como
estaba. Mientras la compra siga en borrador no toca el inventario, por más líneas que tenga. Los
importes (base, cuota del impuesto y total) los calcula siempre el servidor a partir de las líneas:
nunca se toman los que llegue a mandar el navegador.

Cada línea puede ir **asociada a un artículo del catálogo o quedar libre**. Una línea sin artículo
es perfectamente válida (portes, un servicio puntual, algo que no está en el catálogo): simplemente
no mueve stock. Solo las líneas con un artículo de tipo producto y gestión de stock activada
mueven inventario al confirmar.

## Formas de registrar una compra

1. **A mano**, con «+ Nueva compra»: se elige el proveedor y se cargan las líneas.
2. **Importando un XML Facturae** que el proveedor haya enviado: la compra se crea sola con sus
   datos e importes y el proveedor se asocia por NIF. Estas compras además llevan un **estado B2B**
   (recibida, aceptada, rechazada, pagada) que refleja el ciclo del documento electrónico.
3. **Importando un documento (PDF o foto)** interpretado por IA, descrito abajo.

## Importar un documento (PDF o imagen) con IA

Desde el botón **«Importar documento»** del listado de compras se suben una o varias facturas o
albaranes de proveedor en PDF o imagen. La IA los lee y propone los datos ya cargados —proveedor,
número, fecha y líneas—; el usuario revisa esa propuesta, corrige lo que haga falta y solo entonces
se crea la compra, **en borrador**, igual que cualquier otra.

Si el asistente IA no está configurado para la empresa, el botón aparece deshabilitado: hace falta
cargar la clave en **Configuración › Asistente IA**.

Tres reglas de este flujo que conviene tener claras al explicarlo:

- **La propuesta no guarda nada.** Subir un documento e interpretarlo no crea ninguna compra, ni
  ninguna línea, ni ningún proveedor. Hasta que el usuario no confirma, la base de datos no se
  toca. Descartar una propuesta no deja rastro.
- **El proveedor nunca se crea solo.** Si la IA no reconoce al emisor del documento entre los
  proveedores existentes, el usuario tiene que elegir uno o darlo de alta explícitamente. Esto es
  deliberado: un NIF mal leído en un escaneo ensuciaría el catálogo de forma permanente.
- **Los importes los recalcula el servidor.** Lo que el documento diga como total sirve solo para
  avisar al usuario si no cuadra con la suma de las líneas; nunca se guarda como importe.

Otras cosas que la propuesta señala: los **campos que no se pudieron leer** quedan vacíos y
marcados, para que el usuario los complete —en particular, si el documento no indica el tipo
impositivo, el campo queda vacío y **nunca se rellena con un 21 % por defecto**—; avisa si el tipo
leído **no es habitual en el régimen fiscal** de la empresa (por ejemplo un 21 % de IVA en una
empresa canaria que tributa por IGIC); avisa si la moneda no es el euro; y avisa si ya existe una
compra con el mismo proveedor, número y fecha, aunque en ese caso **deja decidir al usuario** si la
crea igualmente, porque una renumeración del proveedor puede ser legítima.

Se pueden subir varios documentos de una tacada: se revisan de a uno, y que uno salga ilegible no
invalida el resto del lote. El documento original queda guardado y se puede **volver a descargar**
desde el detalle de la compra.

Los documentos subidos que no llegan a confirmarse se borran solos a las 24 horas.
