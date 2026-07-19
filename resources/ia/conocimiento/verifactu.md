# Verifactu

Sistema anti-fraude de la AEAT (RD 1007/2023). Se activa por tenant en **Configuración → Verifactu**
con un flag único (`activo`) y un entorno (`pruebas`/`producción`); por defecto está desactivado y
emitir funciona igual que siempre. Con el flag activo, cada factura emitida queda sellada de forma
atómica con una **huella** SHA-256 encadenada a la del registro anterior del tenant, y se remite en
tiempo real a la AEAT. El entorno no puede cambiarse una vez la cadena del tenant ya tiene algún
registro.

Estado Verifactu de una factura (distinto del estado de la factura): **registrada** (sellada
localmente, envío pendiente), **enviada** (aceptada por la AEAT) o **error** (el envío falló, pero la
factura sigue siendo válida — un fallo de la AEAT nunca bloquea ni deshace la emisión). Desde una
factura en error se puede **reintentar el envío** manualmente; también hay un reintento automático
programado. El QR de cotejo y el texto "VERI\*FACTU" aparecen en el PDF/ticket de toda factura
registrada, incluso si el envío está en error.

La corrección ordinaria de una factura ya emitida sigue siendo la **rectificativa**. La **anulación**
Verifactu (menú Acciones de la factura) es un caso más acotado: solo para un registro erróneo que
todavía no tuvo cobros.
