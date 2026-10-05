# Implementation Plan: Anular tickets desde el listado del POS

**Branch**: `051-anular-tickets` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

## Summary

Acción «Anular» en el dropdown del listado de tickets del POS (`/pos`), con modal de motivo. La
lógica de anulación que hoy vive dentro de `FacturaController::anular` se extrae a un servicio
`AnuladorFactura` que usan las dos rutas (facturas ordinarias sin cambios de comportamiento; tickets
con reverso de stock y comprobaciones extra). Ruta propia del POS con un permiso nuevo
`anular-tickets` y el middleware `idioma.pos`. El listado marca los anulados y los excluye de sus
totales. La caja ya excluye los anulados (`ResumenCaja`), así que no se toca.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JS vanilla del POS

**Primary Dependencies**: las existentes (spatie/permission, servicios de Verifactu y stock)

**Storage**: MySQL/MariaDB — **sin tablas nuevas ni migraciones**. Se reutilizan `facturas.estado`
(`anulada`), `factura_eventos`, `movimientos_stock` (origen `devolucion`), registro Verifactu de
anulación y `logs_actividad`. Un permiso nuevo en el catálogo (lo crea `PermisosSeeder`).

**Testing**: PHPUnit; ≥ 2 tenants para aislamiento; Verifactu con su fake existente.

**Constraints**: inmutabilidad de facturas emitidas (solo cambia el estado, como ya hace la
anulación de facturas); numeración intacta; sin colas.

**Scale/Scope**: 1 servicio extraído, 1 método nuevo en `PosController`, 1 ruta, 1 permiso,
cambios en `pos/index.blade.php`, `pos-datatable.init.js`, guía `ayuda/pos` y conocimiento IA.

## Constitution Check

| Principio | Cumplimiento | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant** | El ticket se resuelve a mano bajo el scope de tenant (nunca binding implícito, memoria `project_tenant_route_binding`), igual que `PosController::pdf`. Test con 2 tenants: anular un ticket ajeno da 404 y no cambia nada. | ✅ |
| **II. Cumplimiento normativo** | No se borra ni se renumera nada: solo `estado = anulada` + evento + registro de anulación Verifactu (ya implementado y documentado en `docs/02` §1). Anular ≠ devolver: la devolución sigue siendo rectificativa (fuera de alcance, explicado en la guía). | ✅ |
| **III. Integridad financiera server-side** | Qué se puede anular lo decide el servidor (`anular_url` solo si procede, y el servicio vuelve a comprobarlo con bloqueo de fila). La caja excluye anulados en servidor (`ResumenCaja`, ya existente). | ✅ |
| **IV. Test-first** | Tests de aislamiento, numeración intacta, Verifactu, caja y reverso de stock escritos antes de la implementación. | ✅ |
| **V. Simplicidad** | Se extrae la lógica existente en vez de duplicarla; sin tablas nuevas. | ✅ |

**Revisión obligatoria**: (1) scope de tenant ✅, (2) tests de aislamiento y cálculo ✅,
(3) inmutabilidad: solo cambia el estado, como la anulación existente ✅, (4) sin complejidad fuera
de alcance (sin devoluciones) ✅, (5) sin datos personales nuevos (el motivo es texto de negocio) ✅.

## Documentación leída y convenciones aplicadas (regla de oro, CLAUDE.md)

Leídas: `.specify/memory/constitution.md`, `docs/02-facturacion-espana.md` (§1 anulación Verifactu,
§7 numeración), `docs/04-front-guidelines.md`.

| Sección de `docs/04-front-guidelines.md` | Qué exige | Dónde se aplica |
|---|---|---|
| Columna "Acciones" de los listados | dropdown único; destructivo tras `dropdown-divider`; el backend decide qué ítems (`*_url` solo si procede) | `anular_url` solo en tickets anulables y con permiso; ítem «Anular» en rojo tras divisor |
| Confirmación de acciones irreversibles | nunca `confirm()`; confirmación explícita | modal propio con motivo (mismo patrón que `#anularFacturaModal` de `facturas/index.blade.php`, precedente para anular con motivo), botón rojo «Anular» |
| Modales: siempre centrados | `modal-dialog-centered` | `#ticketAnularModal` |
| Estado de carga en botones | `withButtonLoading` | botón de confirmar |
| Badges de estado | `badge light badge-*` | «Anulado» con `badge light badge-danger` en la columna Tipo |
| Notificaciones | `showToast` | éxito/error |
| Textos traducibles (feature 050) | `__()` / `__t()`; datos fuera | todos los textos nuevos; el motivo no |
| Ayuda contextual | guía por pantalla, bloques traducibles | `ayuda/pos.blade.php`, nuevo `<li>` como bloque `{!! __() !!}` |
| Listados: SIEMPRE DataTable | — | se amplía la DataTable existente; no hay tabla nueva |

## Diseño

### `App\Services\AnuladorFactura` (extraído de `FacturaController::anular`)

`anular(Factura $factura, string $motivo, User $usuario, bool $revertirStock = false): Factura`

1. En transacción, relee la factura con `lockForUpdate` (dos anulaciones simultáneas: la segunda ve
   `anulada` y falla con mensaje claro).
2. Comprueba: estado `emitida`; sin cobros vigentes en `pagos` (`montoCobrado() > 0`); sin
   rectificativa emitida. Si no, lanza `FacturaNoAnulableException` con el motivo (mensaje con
   `__()`).
3. `estado = anulada`; `FacturaEvento` `anulada` con el motivo; registro de anulación Verifactu si
   `VerifactuTenant::activo` y la factura tiene registro (igual que hoy).
4. Si `$revertirStock`: por cada `movimientos_stock` de salida con `factura_id` = factura, registra
   una entrada (`origen = devolucion`, mismo artículo y cantidad, motivo «Anulación del ticket :numero»,
   `factura` = la misma). Cubre líneas y opciones vinculadas (ambas se registraron con la factura).
5. Fuera de la transacción: `RegistradorActividad` («Anuló la factura/el ticket …»).

`FacturaController::anular` pasa a llamarlo con `revertirStock: false` y conserva sus respuestas y
mensajes actuales. Nota: la comprobación "sin rectificativa" es nueva también para las ordinarias
(una factura ya rectificada no debería anularse); se acepta como corrección.

### POS

- Permiso `anular-tickets` (módulo POS) en `CatalogoPermisos`; **no** en el rol base de usuario.
  `PermisosSeeder` lo crea y lo da al rol Administrador de cada tenant (deploy: correr el seeder).
- Ruta `POST /pos/{factura}/anular` → `PosController::anular`, nombre `pos.anular`, middleware
  `can:anular-tickets`, dentro del grupo `idioma.pos`. Responde JSON (`message`) 200 / 422 / 404.
- `PosController::anular`: resuelve `Factura::where('tipo', simplificada)->findOrFail()` (scope de
  tenant), valida `motivo` (`required|string|max:500`, mensajes con `__()`), llama al servicio con
  `revertirStock: true`, captura `FacturaNoAnulableException` → 422.
- `PosController::index`: cada fila con `anulada` (bool) y `anular_url` (solo si el usuario tiene
  `anular-tickets` y el ticket es anulable: emitido, sin cobros en `pagos`, sin rectificativa);
  `totales` calculados solo sobre los no anulados. Carga `rectificativa` y `pagos` en la consulta
  para no hacer N+1.
- `pos/index.blade.php`: modal `#ticketAnularModal` (centrado, textarea motivo, botón rojo) con
  textos `__()`; `pos-datatable.init.js`: badge «Anulado», ítem «Anular» tras divisor, apertura
  del modal, envío con `withButtonLoading`, `showToast`, recarga de la tabla.

### Caja

Sin cambios: `ResumenCaja` ya separa los tickets `anulada` (no suman a ventas ni al efectivo
esperado; van a «anulados» del informe), y el informe de un cierre se pinta desde lo congelado.
Se cubre con tests.

## Project Structure

```text
app/Services/AnuladorFactura.php                    # nuevo (extraído)
app/Exceptions/FacturaNoAnulableException.php       # nuevo
app/Http/Controllers/FacturaController.php          # anular() delega en el servicio
app/Http/Controllers/PosController.php              # index() (anulada, anular_url, totales) + anular()
app/Support/CatalogoPermisos.php                    # + anular-tickets
routes/web.php                                      # pos.anular
resources/views/pos/index.blade.php                 # modal
public/js/plugins-init/pos-datatable.init.js        # badge, acción, modal
resources/views/ayuda/pos.blade.php                 # guía
resources/ia/conocimiento/pos.md                    # conocimiento del asistente
tests/Feature/Pos/AnularTicketTest.php              # flujo, numeración, Verifactu, permisos, aislamiento
tests/Feature/Pos/AnularTicketCajaStockTest.php     # caja y stock
```

## Complexity Tracking

Sin violaciones.
