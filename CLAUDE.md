# Empire Systems CRM

SaaS de facturación para España (multi-tenant). Ver `docs/00-vision.md` para la visión completa
del producto y `docs/01-arquitectura.md`, `docs/02-facturacion-espana.md`, `docs/03-modelo-datos.md`
para las decisiones técnicas, normativa y modelo de datos.

Las reglas no negociables del proyecto (aislamiento multi-tenant, cumplimiento normativo,
integridad financiera, test-first, simplicidad) están en `.specify/memory/constitution.md`.
Léela antes de tocar código de negocio; toda spec/plan/PR debe respetarla.

## Stack

- Backend: Laravel 12, MySQL/MariaDB, `stancl/tenancy` (single-database).
- Layout base: `resources/views/layouts/app.blade.php` + `resources/views/partials/*`, con los
  assets globales del template NexaDash ya importados (`public/css`, `public/js`,
  `public/vendor`, `public/icons`). El template completo queda en `template/` (fuera de git,
  no lo borres) como banco de piezas para ir trasplantando vistas puntuales.
- El template solo tiene 2 layouts reales (`default` con sidebar, `fullwidth` sin sidebar para
  login/errores); las ~150 "demos" son páginas de ejemplo sobre esos 2 layouts, no estructuras
  distintas. El motor de estilo real es `dzSettingsOptions` en `public/js/deznav-init.js`
  (sidebar full/mini/compact/modern/overlay/icon-hover, layout vertical/horizontal, 15 esquemas
  de color, light/dark) — ya soportado por `style.css`. Default actual: sidebar `full`, modo
  claro con toggle persistido en `localStorage` (`public/js/theme-persist.js`). No volver a
  descartar este motor al simplificar: lo que se sacó a propósito fue el `config/dz.php` que
  cargaba CSS/JS distinto por cada demo page, no el selector de layout.

## Flujo de trabajo (Spec-Driven Development / spec-kit)

Todo feature nuevo entra por el flujo de spec-kit, en este orden:

1. `/speckit-specify` — crear la especificación de la feature.
2. `/speckit-clarify` (opcional pero recomendado si hay ambigüedad) — antes de planificar.
3. `/speckit-plan` — plan de implementación técnico.
4. `/speckit-tasks` — desglose en tareas accionables.
5. `/speckit-analyze` (opcional) — chequeo de consistencia entre spec/plan/tasks antes de implementar.
6. `/speckit-implement` — ejecutar las tareas.

No implementar código de negocio nuevo directo, sin pasar por spec → plan → tasks, salvo que el
usuario pida explícitamente saltarse el flujo para algo trivial (fix menor, config, etc.).

### Al cerrar un spec/feature

Cuando una feature terminó de implementarse (`/speckit-implement` completo y validado):

- Revisar si cambió algo respecto a lo documentado en `docs/00-vision.md`, `01-arquitectura.md`,
  `02-facturacion-espana.md` o `03-modelo-datos.md` (nuevas tablas, decisiones técnicas, alcance).
  Si cambió, **actualizar esos docs** para que sigan siendo la fuente de verdad.
- Si el cambio afecta a un principio de la constitución (nuevo dato no cubierto, contradicción,
  alcance ampliado/reducido de forma material), correr `/speckit-constitution` para enmendarla
  con el bump de versión correspondiente (ver reglas de semver en el propio archivo).
- Si no cambió nada respecto a lo documentado, no hace falta tocar `docs/` — evitar
  actualizaciones innecesarias.

## Notas

- Multi-tenant con `tenant_id`: cualquier query o modelo de negocio nuevo debe pasar por el
  global scope de tenant (Principio I de la constitución). No hay excepciones sin justificar.
- Los cálculos de importes/impuestos/Verifactu siempre en backend (Principio III).
