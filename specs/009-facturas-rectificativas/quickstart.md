# Quickstart: Validar facturas rectificativas

Guía de validación end-to-end. Los detalles de datos/contratos están en `data-model.md` y
`contracts/`; aquí solo el guion para comprobar que la feature funciona.

## Prerrequisitos

- Migración aplicada: `php artisan migrate` (añade columnas de rectificativa a `facturas`).
- Seeders: `php artisan db:seed` (crea la serie ordinaria **y** la serie rectificativa "R" del tenant demo).
- Al menos una factura ordinaria **emitida** (feature 008) sobre la que rectificar.

## Suite de tests

```bash
php artisan test --filter=Rectificativa
```

Debe dejar en verde: `RectificativaCreacionTest`, `RectificativaEmisionTest`,
`RectificativaDeltaTest`, `RectificativaInmutabilidadTest`, `RectificativaTenantIsolationTest`.

## Escenarios manuales

1. **Crear rectificativa (sustitución)**: sobre una factura emitida, acción "Rectificar", modalidad
   *sustitución*, motivo. → Se crea un borrador `tipo = rectificativa` vinculado, con snapshot del
   receptor y líneas copiadas; redirige al editor. La original sigue `emitida`.
2. **Editar y emitir**: ajustar líneas del borrador, emitir. → Recibe número de la serie **R**
   (`R-2026-0001`), pasa a `emitida` e inmutable; la original pasa a `rectificada`.
3. **Serie separada**: emitir una ordinaria y una rectificativa; verificar que cada contador avanza
   por su lado (la ordinaria no salta por emitir la R, ni viceversa).
4. **Reinicio anual**: con una rectificativa del año anterior, la primera del nuevo año es `R-<año>-0001`.
5. **Por diferencias / delta**: crear rectificativa *por diferencias*, corregir importes; los totales
   persistidos son el delta (corregido − original), admitiendo negativos. Delta cero (solo corrige un
   dato del receptor) también se emite.
6. **Una sola vez**: intentar rectificar una factura ya `rectificada` → rechazado con mensaje claro.
7. **Solo emitidas**: intentar rectificar un borrador → rechazado.
8. **Inmutabilidad**: intentar editar/borrar/re-emitir una rectificativa emitida → rechazado; la
   factura permanece intacta.
9. **Visibilidad**: en listado, detalle y PDF de la rectificativa aparecen número completo, condición
   de "rectificativa", motivo y referencia a la original; en la original, el enlace a su rectificativa.
10. **Aislamiento multi-tenant**: rectificar en el tenant A no cambia la numeración de B ni permite
    referenciar una original de B.

## Criterio de aceptación

Todos los escenarios anteriores se cumplen y la suite `--filter=Rectificativa` está en verde, sin
regresiones en `--filter=Factura` (008).
