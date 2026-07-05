# Implementation Plan: Formulario de factura adaptado al régimen impositivo del tenant

**Branch**: `013-facturas-regimen-impositivo` | **Date**: 2026-07-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/013-facturas-regimen-impositivo/spec.md`

## Summary

Adaptar la capa de presentación de emisión de facturas (formulario ordinario y POS/ticket) para
que respete el `regimen_impositivo` del tenant (IVA/IGIC/IPSI), que hoy está hardcodeada a IVA.
El backend (cálculo, validación, congelado del régimen, PDF) ya es agnóstico al régimen; esta
feature **expone** esos tipos y etiquetas en el frontend y elimina el recargo de equivalencia fuera
de IVA. Enfoque técnico: pasar los metadatos del régimen (nombre, tipos válidos, si aplica recargo)
desde el controlador a la vista, generar el selector de tipo impositivo por línea a partir de esa
lista (input libre solo para IPSI), y parametrizar el JS de previsualización (`facturas-form.js`,
`pos-form.js`) para que lea esos metadatos en vez de constantes IVA.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JavaScript ES5 (jQuery), estilo del proyecto.

**Primary Dependencies**: Laravel Blade, jQuery (ya vendorizado), `stancl/tenancy` (single-database).

**Storage**: MySQL/MariaDB. **Sin cambios de esquema** — no se añaden tablas ni columnas.

**Testing**: PHPUnit (Feature tests) para backend/emisión; validación manual de UI vía quickstart.

**Target Platform**: App web multi-tenant sobre hosting compartido (cPanel/Hostinger).

**Project Type**: Web application (Laravel monolito con Blade + JS vanilla/jQuery).

**Performance Goals**: N/A (cambio de presentación; sin impacto de rendimiento medible).

**Constraints**: Cálculo definitivo siempre en backend (Principio III); el JS es solo preview.
Aislamiento multi-tenant (Principio I): el régimen es siempre el del tenant activo.

**Scale/Scope**: 2 vistas Blade (`facturas/create`, `pos/create`), 2 archivos JS
(`facturas-form.js`, `pos-form.js`), 2 controladores (`FacturaController`, `PosController`),
1 helper existente reutilizado (`TiposImpositivos`). Sin migraciones.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: ✅ El régimen se toma de `tenant()->regimen_impositivo`
  (ya así en `FacturaController` y `TipoImpositivoValido`). No se cruza data entre tenants. Se
  añadirá/mantendrá test de aislamiento del régimen aplicado.
- **II. Cumplimiento Normativo España-First**: ✅ Es precisamente el cumplimiento del principio:
  parametrizar por `regimen_impositivo` en vez de asumir IVA, y recargo de equivalencia solo en IVA.
  No cambia normativa documentada; `docs/02` ya cubre regímenes territoriales → no requiere enmienda.
- **III. Integridad Financiera Server-Side**: ✅ Los totales siguen calculándose en
  `CalculadoraFactura`/`RegistroTicket` (backend). El JS sigue siendo solo previsualización; no se
  convierte en fuente de verdad. No se toca numeración de series.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: ✅ El cálculo de impuestos es área crítica.
  Como el backend ya calcula por régimen, los tests nuevos (emisión IGIC, ausencia de recargo fuera de
  IVA, aislamiento) se escriben **antes** y deben fallar/pasar en verde antes de cerrar tareas.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: ✅ Sin dependencias nuevas, sin
  infraestructura extra. Reutiliza `TiposImpositivos`. IPSI como input libre (YAGNI: no se modela una
  lista cerrada de tipos por ciudad).

**Resultado del gate**: PASS. Sin violaciones → no se requiere Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/013-facturas-regimen-impositivo/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output (sin cambios de esquema; documenta datos expuestos a la vista)
├── quickstart.md        # Phase 1 output (guía de validación manual + tests)
├── contracts/
│   └── regimen-view-contract.md   # Contrato del payload régimen → vista/JS
└── tasks.md             # Phase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   ├── FacturaController.php      # create()/edit(): pasar metadatos de régimen a la vista
│   └── PosController.php          # create(): pasar metadatos de régimen a la vista
├── Support/
│   └── TiposImpositivos.php       # (existente) fuente de tipos válidos por régimen — reutilizado
│                                  # + posible helper de etiqueta/metadatos del régimen
resources/views/
├── facturas/create.blade.php      # cabecera dinámica, selector de tipo por línea, estado JS
└── pos/create.blade.php           # etiqueta de impuesto dinámica, estado JS
public/js/
├── facturas-form.js               # leer régimen de state: default, opciones, recargo condicional
└── pos-form.js                    # etiqueta/tipo desde régimen; sin recargo fuera de IVA
tests/Feature/
├── FacturaEmisionIgicTest.php     # (nuevo) emisión IGIC correcta, desglose "igic"
├── FacturaRecargoRegimenTest.php  # (nuevo) recargo solo en IVA, ausente en IGIC/IPSI
└── (existentes de aislamiento)    # extender para cubrir régimen aplicado por tenant
```

**Structure Decision**: Monolito Laravel existente (Option 2 "web application" en su variante
Blade+JS server-rendered). Se trabaja sobre las carpetas reales listadas arriba; no se crean
módulos ni proyectos nuevos.

## Complexity Tracking

> No aplica: el Constitution Check pasó sin violaciones. No se introduce complejidad que justificar.
