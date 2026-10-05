# Implementation Plan: Precuenta en el POS de hostelería

**Branch**: `049-precuenta-pos` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/049-precuenta-pos/spec.md`

## Summary

Se añade la **precuenta** a las cuentas abiertas del módulo de hostelería: un documento de 80 mm,
no fiscal, con lo pendiente de la cuenta, que se ve en un modal y se imprime antes de cobrar. Cada
emisión queda en una tabla append-only nueva (`pos_precuentas`) con una foto de lo impreso. El
estado "precuenta vigente / desactualizada" no se almacena: se **deriva** comparando una huella
SHA-256 del consumo de la cuenta con la de la última precuenta, de modo que añadir líneas, unir
cuentas o cambiar de suplemento la desactualizan y un cobro parcial no. La Sala gana un cuarto
estado de mesa ("precuenta pedida", borde violeta) decidido en servidor. El total sale del mismo
camino de cálculo que el cobro (`CobradorCuenta` + `CalculadoraFactura`), para que coincida al
céntimo con el ticket. No se toca la emisión, la numeración ni Verifactu.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JS vanilla del POS (sin build step)

**Primary Dependencies**: `stancl/tenancy` (`BelongsToTenant`), `barryvdh/laravel-dompdf` (ya usado
por el ticket), Bootstrap 5 del template NexaDash, toastr. Sin dependencias nuevas.

**Storage**: MySQL/MariaDB — 1 tabla nueva (`pos_precuentas`). Ninguna tabla existente cambia.

**Testing**: PHPUnit (`php artisan test`), tests de feature en `tests/Feature/Pos/`, ≥ 2 tenants para
aislamiento

**Target Platform**: hosting compartido cPanel (empiresass.gestionley.com); tablet en horizontal

**Project Type**: aplicación web monolítica Laravel (Blade + JS por vista)

**Performance Goals**: emitir precuenta < 500 ms; la Sala sigue con una sola consulta extra (todas
las precuentas de las cuentas abiertas de una vez), sin N+1

**Constraints**: ningún importe desde el cliente; la precuenta no puede llevar número, serie ni QR;
registro append-only; módulo apagado ⇒ cero cambios visibles

**Scale/Scope**: decenas de mesas por tenant, pocas precuentas por cuenta; 2 endpoints nuevos,
1 plantilla PDF, 1 módulo JS, cambios en la Sala y en el TPV

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant** | `pos_precuentas` con `tenant_id` indexado + `BelongsToTenant`; cuenta y precuenta resueltas manualmente bajo scope en el controlador (no binding implícito). Tests con 2 tenants: no se emite precuenta sobre la cuenta de otro (404), no se ve su PDF (404), la Sala de A no refleja precuentas de B. | ✅ |
| **II. Cumplimiento normativo** | La precuenta no es factura: no crea `facturas`, no consume serie, no registra en Verifactu (research D1). Plantilla propia sin el partial `verifactu-qr`, sin número ni serie, con título y leyenda "Documento no válido como factura" (D6). Mención del impuesto según `regimen_impositivo` del tenant (IVA/IGIC/IPSI), nunca IVA fijo. Se documenta en `docs/02-facturacion-espana.md` **antes** del código. RGPD: solo `usuario_id`, mismo tipo que `abierta_por` (D10). | ✅ |
| **III. Integridad financiera server-side** | Líneas y total de la precuenta calculados en servidor con el mismo método de construcción de líneas que `CobradorCuenta` y la misma `CalculadoraFactura` (D2). El cliente solo manda `version`. | ✅ |
| **IV. Test-first en lógica crítica** | Aplica a aislamiento y cálculo de importes (y a "no toca numeración/Verifactu"): los tests de total = ticket, de numeración intacta, de aislamiento y de la huella se escriben antes que el servicio y deben fallar primero. | ✅ |
| **V. Simplicidad / hosting compartido** | Sin dependencias ni archivos en disco: PDF regenerado desde la fila (D3). Estado derivado en vez de flags mantenidos a mano (D4). Sin interruptor de configuración (D8). Precuenta parcial fuera de alcance (D9). | ✅ |

**Re-check post-diseño**: sin violaciones. Complexity Tracking vacío.

**Revisión obligatoria (Development Workflow)**: (1) scope de tenant ✅, (2) tests de aislamiento y de
cálculo planificados ✅, (3) inmutabilidad de facturas intacta y el registro de precuentas es además
append-only ✅, (4) sin complejidad fuera del MVP ✅, (5) sin tabla nueva de datos personales que
exija purga (D10) ✅.

## Documentación leída y convenciones aplicadas (regla de oro, CLAUDE.md)

Leídas con la herramienta de lectura antes de la spec: `.specify/memory/constitution.md`,
`docs/02-facturacion-espana.md` (completo) y `docs/04-front-guidelines.md` (índice completo y las
secciones que aplican). Convenciones que condicionan el plan y las tareas:

| Sección de `docs/04-front-guidelines.md` | Qué exige | Dónde se aplica |
|---|---|---|
| «Ver» un documento … SIEMPRE en modal | vista previa en modal con iframe, centrado, reset del `src` al cerrar | `#posPrecuentaModal` (D7). Tamaño `modal-lg` como el "Ver ticket" ya existente del TPV (rollo de 80 mm); la desviación de `modal-xl` se anota en la guía |
| Tarjeta de mesa y sus tres estados | borde = estado; servidor decide | cuarto estado `precuenta`, decidido en `SalaController` (D5) |
| Dos vistas de los mismos datos | una sola función decide la clase visual | `PosPlanoDibujo.claseEstado()` + conteo de métricas |
| Feedback de bloqueo cuando el borde ya comunica estado | no chocar con colores reservados | violeta `--pos-precuenta` (D5) |
| Franja central de la botonera del POS | la botonera no cambia de estructura | botón en el chip de mesa del `card-header` (D7) |
| Suplemento de zona: nunca un aumento silencioso | el suplemento se ve antes de cobrar | línea de suplemento en el documento |
| Partición de un archivo JS grande | módulo registrado en `PosApp` | `public/js/pos-precuenta.js` |
| Estado de carga en botones / Botones con markup interno | `withButtonLoading`, sin `data-loading-text` si hay markup | botón Precuenta |
| Notificaciones | toastr / `showToast` | avisos de desactualizada y errores |
| CSRF en peticiones AJAX sin formulario | header `X-CSRF-TOKEN` | `POST …/precuentas` |
| Bloqueo por estado del servidor… | dos modales nunca a la vez | precuenta ↔ cobro |
| Assets propios: siempre `@assetv` | versión por mtime | `pos-precuenta.js` |
| Ayuda contextual | guía actualizada en el mismo cambio | `ayuda/pos-crear`, `ayuda/pos-sala` |
| Nueva entrada de menú ⇒ nuevo permiso | — | **no aplica**: no hay entrada de menú (D8) |

## Project Structure

### Documentation (this feature)

```text
specs/049-precuenta-pos/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── precuenta.md
├── checklists/
│   └── requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
database/migrations/
└── 2026_10_05_100000_create_pos_precuentas_table.php        # nueva

app/
├── Models/
│   ├── PosPrecuenta.php                                      # nuevo (append-only)
│   └── PosCuenta.php                                         # + precuentas()
├── Services/
│   ├── PrecuentaCuenta.php                                   # nuevo: huellas, estado, emitir
│   └── CobradorCuenta.php                                    # extraer lineasACobrar() (sin cambio de comportamiento)
└── Http/Controllers/Pos/
    ├── PrecuentaController.php                               # nuevo: store + pdf
    ├── CuentaController.php                                  # payload + bloque precuenta
    └── SalaController.php                                    # precuenta_pedida / precuenta_hace_min

database/factories/PosPrecuentaFactory.php                    # nuevo
routes/web.php                                                # 2 rutas en el grupo ver-pos-sala

resources/views/pos/
├── precuenta-80mm.blade.php                                  # nuevo
├── create.blade.php                                          # botón + indicador en chip, modal, aviso en cobro
└── sala.blade.php                                            # estilos .precuenta + métrica

public/js/
├── pos-precuenta.js                                          # nuevo módulo PosApp
├── pos-cuenta.js                                             # aviso desactualizada, estado en chip
├── pos-cobro.js                                              # franja de aviso en el modal de cobro
└── plugins-init/
    ├── pos-plano-dibujo.js                                   # claseEstado() → 'precuenta'
    └── pos-sala.init.js                                      # tarjeta + métrica "Precuenta"

resources/views/ayuda/pos-crear.blade.php, pos-sala.blade.php # guías
resources/ia/conocimiento/pos-hosteleria.md                   # base de conocimiento
docs/02-facturacion-espana.md, 03-modelo-datos.md, 04-front-guidelines.md

tests/Feature/Pos/
├── PrecuentaEmisionTest.php
├── PrecuentaTotalIgualTicketTest.php
├── PrecuentaSinEfectoFiscalTest.php
├── PrecuentaVigenciaTest.php
├── PrecuentaSalaTest.php
├── PrecuentaDocumentoTest.php
└── AislamientoPrecuentaTest.php
```

**Structure Decision**: monolito Laravel existente. Todo cuelga del módulo de hostelería ya
existente (feature 038): mismos controladores bajo `App\Http\Controllers\Pos`, mismo grupo de rutas,
mismo orquestador `PosApp` en el TPV.

## Complexity Tracking

Sin violaciones que justificar.
