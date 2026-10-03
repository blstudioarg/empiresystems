# Quickstart — validar el cierre de caja

## Prerrequisitos

- Migraciones nuevas aplicadas con `php artisan migrate` (solo **añaden**: 2 tablas + 1 columna
  nullable en `ticket_pagos`). **Nunca** `migrate:fresh` (CLAUDE.md: datos de demo).
- `php artisan db:seed --class=PermisosSeeder` para sembrar `ver-pos-caja` y sincronizar el rol
  Administrador.
- Acceso del tenant "Empire Demo" según `ACCESOS.local.md`.

## Tests automáticos

```bash
php artisan test --filter=Caja
php artisan test tests/Feature/Pos tests/Feature/PosTicketEmisionTest.php tests/Feature/PosPagoDivididoTest.php tests/Feature/TicketStockTest.php tests/Feature/RegistroVerifactuTest.php
php artisan test   # suite completa: los tests de POS existentes deben seguir en verde solo con abrir caja en setUp
```

## Escenario manual principal (tablet o ventana 1280×800)

1. POS → **Caja**: aparece "La caja está cerrada". Tocar **Abrir caja**, teclear `100` → "Abrir caja
   con 100,00 €". La barra pasa a "Caja abierta · desde HH:MM".
2. POS → **Crear ticket**: el chip dice "Caja abierta". Emitir:
   - ticket A: 40,00 € en efectivo;
   - ticket B: 25,00 € con tarjeta;
   - ticket C: 30,00 € repartido 10,00 € efectivo + 20,00 € tarjeta.
3. Volver a **Caja**: Vendido 95,00 €, 3 tickets; Efectivo 50,00 €, Tarjeta 45,00 €. No aparece en
   ningún sitio "efectivo esperado".
4. **− Salida** 30,00 € "Pago a proveedor". **+ Entrada** 50,00 € "Cambio".
5. **Cerrar caja** → bandeja: contar 3 × 50 € + 1 × 20 € = 170,00 €. Ver que no hay esperado en
   pantalla. **Confirmar conteo**.
   - Esperado = 100 + 50 + 50 − 30 = **170,00 €** → veredicto **Cuadra**.
6. **Imprimir 80 mm**: el PDF se abre en el modal con totales, desglose por método e impuesto,
   S-…primero/último, movimientos, esperado/contado/diferencia.
7. POS → Caja → **Historial**: aparece el cierre con badge "Cuadra"; "Ver informe" reabre el PDF.

## Escenarios de borde

| Escenario | Resultado esperado |
|-----------|--------------------|
| Crear ticket con la caja cerrada y tocar Cobrar | Se abre "Abrir caja"; tras abrir, sigue al cobro; el ticket armado no se pierde |
| Cerrar contando 162,60 € (umbral 5 €) | 422 provisional "Faltan 7,40 €" + campo obligatorio "¿Qué pasó?"; sin texto no cierra |
| Dos pestañas: cerrar en una, confirmar conteo en la otra | La segunda recibe "Esta caja ya la cerró …" y el enlace al informe |
| Cobrar en una pestaña del TPV abierta antes del cierre | 409 `caja_cerrada`; el ticket sigue armado; ofrece abrir caja |
| Abrir caja en dos tablets a la vez | Solo una sesión; la otra ve la sesión abierta |
| Usuario de otro tenant pide `/pos/caja/sesiones/{id}/informe` | 404 |
| Usuario sin `ver-pos-caja` ni `ver-pos-crear` entra a `/pos/caja` | 403; no ve la entrada de menú |
| Tenant IGIC | Desglose por impuesto muestra IGIC, no IVA |
| Anular un ticket de una sesión ya cerrada | El informe del cierre no cambia |
