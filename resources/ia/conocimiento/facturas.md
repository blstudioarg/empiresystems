# Facturas

Una factura pasa por estados: **borrador** (editable), **emitida** (definitiva e inmutable),
**pagada**, **vencida**, **anulada** y **rectificada**. Los borradores se crean y editan libremente;
al emitir se asigna número de serie definitivo y ya no se puede modificar. Para corregir una factura
emitida se crea una **factura rectificativa**, nunca se edita la original. Los importes (base, IVA/
IGIC, IRPF, total) los calcula siempre el sistema a partir de las líneas.
