# Facturas

Una factura pasa por estados: **borrador** (editable), **emitida** (definitiva e inmutable),
**pagada**, **vencida**, **anulada** y **rectificada**. Los borradores se crean y editan libremente;
al emitir se asigna número de serie definitivo y ya no se puede modificar. Para corregir una factura
emitida se crea una **factura rectificativa**, nunca se edita la original. Los importes (base, IVA/
IGIC, IRPF, total) los calcula siempre el sistema a partir de las líneas.

En el editor, el bloque «Datos del cliente en esta factura» se precarga al elegir el cliente pero
queda **congelado en la factura**: editarlo no cambia la ficha del cliente. La **provincia y la
ciudad** de ese bloque son dos desplegables encadenados sobre el catálogo oficial de provincias y
municipios: hay que elegir primero la provincia y la lista de ciudades se filtra sola. No se
escriben a mano.
