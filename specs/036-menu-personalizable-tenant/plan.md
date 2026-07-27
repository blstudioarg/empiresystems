# Implementation Plan: Menú lateral personalizable por tenant

**Branch**: `036-menu-personalizable-tenant` | **Date**: 2026-07-26 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/036-menu-personalizable-tenant/spec.md`

## Summary

Nueva tab **"Menú"** en `/configuracion` que permite a cada tenant renombrar y reordenar (arrastrando
y soltando) los elementos de su menú lateral. La pieza central es convertir el sidebar, hoy marcado
Blade escrito a mano, en algo **dirigido por un catálogo** (`App\Support\CatalogoMenu`) sobre el que
se aplica una **capa de personalización por tenant** (`App\Support\MenuTenant`) persistida como una
única clave JSON en la tabla `configuraciones` ya existente — sin tabla nueva, sin migración de
datos y sin tocar permisos ni rutas. Los `@can`/`@canany` que hoy gobiernan la visibilidad se
mantienen íntegros: pasan de estar escritos por elemento a evaluarse a partir del permiso declarado
en el catálogo, de modo que el conjunto de entradas visibles por rol no cambia (SC-003).

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, `BelongsToTenant`), `spatie/laravel-permission` (permisos `ver-*` ya existentes), jQuery + jQuery UI `sortable` (a vendorizar desde el banco del template), Blade

**Storage**: MySQL/MariaDB — tabla `configuraciones` existente (clave/valor por tenant). **Ninguna tabla ni migración nueva.**

**Testing**: PHPUnit (`tests/Feature/...`), con la convención de test de aislamiento multi-tenant (≥2 tenants) del Principio I

**Target Platform**: aplicación web servida por Laravel (navegadores modernos de escritorio y tablet)

**Project Type**: aplicación web monolítica Laravel + Blade (no hay separación front/back)

**Performance Goals**: el pintado del sidebar añade **como mucho 1 consulta por carga de página** (lectura de la clave de personalización), memoizada por request; sin coste medible frente al estado actual

**Constraints**: hosting compartido (Principio V) — sin dependencias que exijan VPS, sin proceso de build de front nuevo; el plugin de arrastre se vendoriza como los demás (`public/vendor/`)

**Scale/Scope**: 10 elementos de primer nivel + 26 entradas de segundo nivel (36 en total); 1 vista nueva (tab), 1 clase de catálogo, 1 clase de soporte, 2 rutas nuevas, reescritura de 1 partial (`sidebar.blade.php`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Estado | Justificación |
|-----------|--------|---------------|
| **I. Aislamiento Multi-Tenant** (NON-NEGOTIABLE) | ✅ PASA | La personalización vive en `configuraciones`, que ya lleva `tenant_id` y usa `BelongsToTenant`. Toda lectura/escritura filtra por `tenant()->getTenantKey()`, igual que `ConfigCrm`/`ConfigFichajes`. Se exige test de aislamiento con 2 tenants (FR-012, SC-004). |
| **II. Cumplimiento Normativo España-First** | ✅ NO APLICA | No toca facturación, impuestos, series ni Verifactu. Los datos guardados (etiquetas de menú y orden) **no son datos personales**, así que no aplican minimización ni retención/purga RGPD. Sí aplica el registro en `logs_actividad` del cambio de configuración, por coherencia con el resto de tabs (FR-022). |
| **III. Integridad Financiera Server-Side** | ✅ NO APLICA (pero se respeta su espíritu) | No hay importes. El equivalente aquí: **el orden y el conjunto de elementos los reconstruye el servidor desde el catálogo**, nunca se confía en la lista que envía el cliente (FR-018, edge case de identificadores desconocidos). El enforcement de permisos sigue siendo server-side (FR-013). |
| **IV. Test-First en Lógica Crítica** (NON-NEGOTIABLE) | ✅ PASA | El área crítica tocada es el aislamiento multi-tenant: sus tests se escriben antes de la implementación (tareas marcadas explícitamente en `tasks.md`). El resto (UI de la tab, arrastre) sigue el flujo flexible que permite el propio principio. |
| **V. Simplicidad y Hosting Compartido** | ✅ PASA | Sin tabla nueva (se reutiliza `configuraciones`), sin migración, sin dependencia externa nueva (jQuery UI sale del banco del template ya presente en `template/`). Alcance deliberadamente recortado en `/speckit-clarify`: no se puede ocultar, crear ni mover elementos entre grupos. |

**Resultado del gate (pre-Phase 0)**: PASA sin violaciones. Sección "Complexity Tracking" vacía.

**Re-evaluación post-Phase 1**: PASA. El diseño de Phase 1 no introdujo tablas, dependencias ni
capas adicionales respecto a lo evaluado arriba; la única decisión con impacto de guías es la
excepción documentada a "Listados: SIEMPRE DataTable", que no es un principio constitucional sino
una convención de front, y queda registrada en `docs/04-front-guidelines.md` como parte de la
feature (ver research.md, D3).

**Nota de alcance verificada**: el conteo del menú actual son **10 elementos de primer nivel y 26
entradas de segundo nivel** (36 en total), comprobado sobre `sidebar.blade.php`, no estimado.

## Project Structure

### Documentation (this feature)

```text
specs/036-menu-personalizable-tenant/
├── plan.md              # Este archivo
├── research.md          # Phase 0
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1
├── contracts/
│   └── configuracion-menu.md   # Contrato de los endpoints de la tab
├── checklists/
│   └── requirements.md
├── spec.md
└── tasks.md             # Phase 2 (/speckit-tasks — no lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Support/
│   ├── CatalogoMenu.php              # NUEVO — catálogo estático del menú (fuente de verdad)
│   └── MenuTenant.php                # NUEVO — capa de personalización por tenant (leer/guardar/restaurar)
├── Http/
│   ├── Controllers/
│   │   └── ConfiguracionController.php   # MODIFICADO — updateMenu() + restaurarMenu() + datos en show()
│   └── Requests/
│       └── ActualizarMenuRequest.php     # NUEVO — validación de nombres y orden
resources/views/
├── configuracion/
│   ├── index.blade.php               # MODIFICADO — nueva tab "Menú"
│   └── _tab_menu.blade.php           # NUEVO — lista jerárquica arrastrable + Guardar + Restaurar
├── partials/
│   └── sidebar.blade.php             # MODIFICADO — pasa a iterar el catálogo personalizado
└── ayuda/
    └── configuracion.blade.php       # MODIFICADO — guía in-app de la tab nueva
public/
├── vendor/jqueryui/                  # NUEVO (vendorizado desde template/) — sortable
├── css/configuracion-menu.css        # NUEVO — estilos de la lista arrastrable
└── js/plugins-init/
    └── configuracion-menu.init.js    # NUEVO — sortable anidado + submit AJAX + restaurar
resources/ia/conocimiento/
└── configuracion.md                  # MODIFICADO — FR-013 de la feature 030 (base de conocimiento IA)
routes/
└── web.php                           # MODIFICADO — 2 rutas dentro del grupo can:ver-configuracion
docs/
└── 04-front-guidelines.md            # MODIFICADO — excepción documentada a la regla DataTable
tests/Feature/Configuracion/
├── MenuPersonalizadoTest.php         # NUEVO — guardado, validación, restauración, permisos
├── MenuAislamientoTenantTest.php     # NUEVO — Principio I (2 tenants), test-first
└── MenuSidebarRenderTest.php         # NUEVO — render del sidebar: nombres, orden y permisos intactos
```

**Structure Decision**: monolito Laravel existente; no se crea ningún directorio de nivel superior
nuevo. Cada pieza cae en el lugar que ya usan sus homólogas: la lógica de configuración por tenant
en `app/Support/` (junto a `ConfigCrm`, `ConfigFichajes`, `AparienciaTenant`), la tab como un
partial `_tab_*.blade.php` más de `resources/views/configuracion/`, y el JS como un
`*.init.js` de `public/js/plugins-init/`.

## Convenciones de front citadas (REGLA DE ORO de `CLAUDE.md`)

Leídas antes de escribir este plan; cada una condiciona una tarea concreta:

| Sección de `docs/04-front-guidelines.md` | Qué exige | Dónde se aplica |
|---|---|---|
| "Listados: SIEMPRE DataTable, nunca una `<table>` plana" | Todo listado va en DataTable, **incluidos los que viven dentro de una tab de configuración** | **Excepción explícita y documentada**: el editor de menú es una lista jerárquica arrastrable, no un listado de registros (sin búsqueda/paginación/orden por columna, y DataTables impediría el arrastre anidado). Se anota la excepción en la propia guía. Ver research.md D3. |
| "Tamaño de formularios: todo sm por defecto" | No añadir `.form-control-sm`/`.btn-sm` a mano | Los inputs de nombre de la tab van sin clase de tamaño |
| "Estado de carga en botones (AJAX/fetch)" | Todo botón que dispare petición usa `window.withButtonLoading` | Botones "Guardar" y "Restaurar valores por defecto" |
| "Confirmación de acciones irreversibles" | Nunca `confirm()` nativo; usar `window.confirmDelete(msg, onConfirm, opciones)` y que `onConfirm` **devuelva** el `$.ajax` | "Restaurar valores por defecto", con `confirmLabel: 'Restaurar'` y `confirmClass: 'btn-primary'` (no es un borrado: no debe verse la papelera roja) |
| "Notificaciones" | Siempre toastr / `window.showToast` | Resultado de guardar y de restaurar |
| "Modales: siempre centrados verticalmente" | `modal-dialog-centered` sin excepción | Aplica al modal de confirmación reutilizado (ya lo cumple) |
| "Menú lateral: tamaño de ícono y texto" | Los `<x-lordicon>` del sidebar van a `size="30"` y cada `.nav-text` lleva `ms-2` | Al pasar el sidebar a bucle, el `size="30"` y `class="nav-text ms-2"` se emiten desde la plantilla del bucle, no por elemento |
| "Nueva entrada de menú ⇒ nuevo permiso" | Toda sección nueva del sidebar necesita permiso propio | **No aplica**: esta feature no añade ninguna entrada al sidebar, solo una tab dentro de `/configuracion`, ya gateada con `ver-configuracion`. Sí aplica su corolario inverso: el catálogo debe declarar el permiso de cada elemento para que la visibilidad no cambie |
| "Ayuda contextual" | Una vista con guía que cambia debe actualizar `resources/views/ayuda/<slug>.blade.php` en el mismo cambio | `ayuda/configuracion.blade.php` gana el paso de la tab Menú |
| "Padding/gutter, overrides globales" | No repetir overrides por vista | La tab no añade CSS de espaciados; solo el CSS propio de la lista arrastrable |

## Phase 0 — Research

Ver [research.md](./research.md). Decisiones resueltas: formato de persistencia (D1), estrategia de
render del sidebar dirigido por catálogo (D2), excepción a la regla DataTable y librería de arrastre
(D3), reconstrucción server-side del orden (D4), accesibilidad y táctil del arrastre (D5),
tratamiento de los casos especiales del sidebar actual — badge de Alertas y entradas de primer nivel
sin hijos (D6).

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md): catálogo, capa de personalización, formato JSON persistido,
  reglas de validación y de fusión.
- [contracts/configuracion-menu.md](./contracts/configuracion-menu.md): los dos endpoints nuevos
  (guardar y restaurar) y el contrato de la respuesta.
- [quickstart.md](./quickstart.md): cómo verificar la feature de punta a punta.

## Complexity Tracking

> Sin violaciones de la constitución que justificar. Tabla intencionalmente vacía.
</content>
