# Contrato — QR de cotejo AEAT

**Fase**: 1 · **Feature**: 032 · Ver [research.md](../research.md) R4.

## Composición de la URL

```
QrVerifactu::url(Factura $factura): string
```

Compone la URL **oficial** del servicio de cotejo de la AEAT con los parámetros del registro:
NIF del emisor, número de serie de factura, fecha de expedición e importe total.

La URL base depende de `verifactu.entorno` (preproducción vs. producción) — son distintas.

> **A verificar contra la especificación técnica del QR de la AEAT antes de implementar**: URL base
> exacta de cada entorno, nombre y orden de los parámetros de la query string, y codificación de la
> fecha y el importe. Es un requisito normativo, no una elección de diseño.

El resultado se persiste en `facturas.qr_contenido` en el momento del sellado, para que sea auditable
y no dependa de recomponerlo en cada render.

## Render

```
QrVerifactu::imagen(string $url): string   // data URI embebible en el PDF
```

Genera el QR como imagen embebida (data URI), sin depender de `ext-gd`/`imagick` (Principio V).

## Presentación en el documento

Partial compartido `resources/views/partials/verifactu-qr.blade.php`, usado por:
- `facturas/pdf.blade.php` (A4)
- `facturas/ticket-80mm.blade.php` (ticket POS, tamaño adaptado al ancho de 80 mm)

Debe mostrar **el QR + el texto "VERI*FACTU"** (FR-009). Se renderiza únicamente si la factura tiene
registro (`huella` no nula); las emitidas con el flag apagado no muestran nada.

**Restricción normativa**: la norma fija un rango de tamaño para el QR impreso. Verificar que el
tamaño elegido lo respeta en ambos formatos, en especial en el ticket de 80 mm donde el espacio es
escaso.
