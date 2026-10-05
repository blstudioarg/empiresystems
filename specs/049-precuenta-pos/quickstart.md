# Quickstart: validar la precuenta

## Prerrequisitos

- Tenant "Empire Demo" (credenciales en `ACCESOS.local.md`) con el módulo de hostelería activo en
  Configuración → POS, al menos una zona con suplemento (p. ej. Terraza +10 %) y otra sin él.
- Caja abierta (feature 048), para poder cobrar al final.
- Migración nueva aplicada con `php artisan migrate` (solo crea `pos_precuentas`; **no** usar
  `migrate:fresh`).

## Tests automáticos

```bash
php artisan test --filter=Precuenta
php artisan test tests/Feature/Pos
```

Esperado: todo en verde, incluidos los tests existentes del POS (cobro, numeración, Sala).

## Validación manual (tablet o navegador en horizontal)

1. **Precuenta básica (US1)**: Sala → mesa de Terraza libre → añadir 2 cañas y una tapa con opción →
   pulsar **Precuenta** en el chip de mesa. Se guarda y aparece la vista previa: título PRECUENTA,
   leyenda "Documento no válido como factura" arriba y abajo, líneas con la opción bajo el plato,
   línea de suplemento de zona, total, "IVA incluido". Sin número, sin QR, sin VERI*FACTU.
   Imprimir → diálogo de impresión del navegador. Cerrar → TPV en cero.
2. **Sin efecto fiscal (SC-003)**: Facturas/Tickets → no hay documento nuevo. Anotar el número del
   último ticket.
3. **Sala (US2)**: la mesa se ve con borde violeta y "Precuenta", en tarjetas y en plano, y la tira de
   métricas cuenta 1 en "Precuenta".
4. **Desactualizada (US3)**: tocar la mesa → añadir un café → Guardar. Toast de aviso; el chip
   muestra "Precuenta desactualizada"; la Sala vuelve a mostrarla como ocupada. Abrir Cobrar → franja
   con el total de la precuenta y el actual.
5. **Reimpresión (US4)**: pulsar Precuenta (nueva, sin "Reimpresión") y otra vez Precuenta (con
   "Reimpresión").
6. **Cobro (SC-002)**: cobrar la cuenta entera → el ticket emitido tiene el número siguiente al
   anotado en el paso 2 y su total es exactamente el de la última precuenta. La mesa queda libre.
7. **Módulo apagado (SC-008)**: con un tenant sin hostelería, Crear ticket no muestra nada nuevo.
