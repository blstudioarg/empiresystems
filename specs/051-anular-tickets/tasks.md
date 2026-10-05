# Tasks: Anular tickets desde el listado del POS

**Input**: [spec.md](spec.md), [plan.md](plan.md)

**Tests**: TEST-FIRST (Principio IV) en aislamiento, numeración, Verifactu, caja y stock: se
escriben antes que la implementación y deben fallar primero.

## Phase 1: Tests (TEST-FIRST)

- [X] T001 [P] `tests/Feature/Pos/AnularTicketTest.php`: (US1) anular un ticket emitido con motivo → 200, `estado = anulada`, evento `anulada` con el motivo en `factura_eventos`, entrada en el registro de actividad; sin motivo → 422 y no cambia nada; ticket ya anulado → 422; el siguiente ticket de la serie recibe el número siguiente (sin hueco ni reutilización); el listado JSON marca `anulada`, no da `anular_url` al anulado y sus totales no lo cuentan. (US3) con Verifactu activo y registro de alta, se genera el registro de anulación encadenado. (US4) usuario con `ver-pos` sin `anular-tickets` → no recibe `anular_url` y el POST da 403; usuario con `anular-tickets` y sin `ver-facturas` → puede anular; ticket de otro tenant → 404 y queda intacto. Edge: ticket con un cobro vigente en `pagos` → 422; ticket con rectificativa → 422; un ticket de cuenta de mesa cobrada se anula y la cuenta sigue cerrada. Con el POS en chino, el mensaje de éxito sale traducido.
- [X] T002 [P] `tests/Feature/Pos/AnularTicketCajaStockTest.php`: (US2) caja abierta + ticket en efectivo → tras anular, `vendido` y `efectivo_esperado` del turno no lo incluyen y figura en `anulados`; un cierre ya hecho conserva su informe; artículo con `gestion_stock` → tras anular, `stock_actual` vuelve al valor previo y existe un movimiento de entrada con origen `devolucion` vinculado al ticket, sin tocar la salida original; una opción vinculada a otro artículo también devuelve su stock.

## Phase 2: Implementación

- [X] T003 `app/Exceptions/FacturaNoAnulableException.php` y `app/Services/AnuladorFactura.php` según plan § Diseño (bloqueo de fila, comprobaciones, evento, Verifactu, reverso de stock opcional, registro de actividad). Mensajes con `__()`.
- [X] T004 `app/Http/Controllers/FacturaController.php`: `anular()` delega en `AnuladorFactura` (sin reverso de stock) conservando sus respuestas; la suite de facturas existente sigue en verde.
- [X] T005 [P] `app/Support/CatalogoPermisos.php`: permiso `anular-tickets` («Anular tickets», módulo POS), fuera del rol base de usuario.
- [X] T006 `routes/web.php`: `POST /pos/{factura}/anular` → `PosController::anular`, `pos.anular`, `can:anular-tickets`, dentro del grupo `idioma.pos`.
- [X] T007 `app/Http/Controllers/PosController.php`: `anular()` (resolución manual bajo tenant, validación de motivo, servicio con reverso de stock, 422 con el mensaje de la excepción) e `index()` (`anulada`, `anular_url` según permiso y anulabilidad, totales sin anulados, eager loading de `pagos` y `rectificativa`).
- [X] T008 `resources/views/pos/index.blade.php` + `public/js/plugins-init/pos-datatable.init.js`: modal `#ticketAnularModal` (centrado, motivo obligatorio, botón rojo), badge «Anulado» (`badge light badge-danger`), ítem «Anular» tras `dropdown-divider` solo con `anular_url`, envío con `withButtonLoading`, `showToast`, recarga de la tabla. Todos los textos con `__()`/`__t()` (feature 050).

## Phase 3: Documentación y cierre

- [X] T009 [P] `resources/views/ayuda/pos.blade.php`: cómo anular un ticket y en qué se diferencia de una devolución (bloque `{!! __() !!}`, § "Ayuda contextual").
- [X] T010 [P] `resources/ia/conocimiento/pos.md`: anulación de tickets (permiso, efectos en caja y stock, anular ≠ devolver).
- [X] T011 [P] `docs/03-modelo-datos.md` y `docs/01-arquitectura.md` si procede: el reverso de stock por anulación de ticket (origen `devolucion`) y el permiso nuevo; `docs/02-facturacion-espana.md` no cambia (la anulación ya está documentada).
- [X] T012 `php artisan test` completo; `traducciones:sincronizar` en local para los textos nuevos. En el deploy: `db:seed --class=PermisosSeeder --force` (permiso nuevo en el rol Administrador de cada tenant) además de `traducciones:sincronizar`.

## Dependencies

T001–T002 antes que T003–T008. T003 → T004 y T007. T005 → T006 → T007 → T008. Phase 3 al final.
