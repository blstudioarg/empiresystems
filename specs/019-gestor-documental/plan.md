# Implementation Plan: Gestor documental por tenant (Drive)

**Branch**: `019-gestor-documental` | **Date**: 2026-07-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/019-gestor-documental/spec.md`

## Summary

Gestor documental tipo "Drive" por tenant: árbol de carpetas anidadas + archivos ligeros, con
subir/descargar/renombrar/mover/borrar y preview inline de PDF e imágenes. Dos tablas nuevas
(`carpetas`, `archivos`) con `tenant_id` + `BelongsToTenant` (mismo patrón que el resto del
negocio). El almacenamiento físico va a un **disco privado** particionado por tenant
(`storage/app/tenants/{tenant_id}/documentos/…`), nunca accesible por URL pública: toda descarga y
preview pasa por un controlador que reafirma el scope de tenant. El nombre físico del fichero es un
UUID generado, independiente del nombre visible (renombrar es solo BD). El límite de tamaño por
archivo (default 10 MB) y la lista blanca de tipos permitidos son configurables vía la tabla
`configuraciones`. La UX es requisito de primer nivel: la vista se diseña con las skills
(`frontend-design`, `ui-ux-pro-max`, `emil-design-eng`) antes de implementar, sobre el banco
NexaDash (demo file-manager).

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database, trait `BelongsToTenant` para el global
scope de tenant), template NexaDash (Blade + jQuery), toastr para notificaciones. Sin librerías
nuevas de servidor: subida con `UploadedFile`, almacenamiento con el filesystem local de Laravel.
Preview: `<iframe>`/`<embed>` para PDF y `<img>` para imágenes servidos por el controlador.

**Storage**: MySQL/MariaDB — 2 tablas nuevas: `carpetas`, `archivos`. Reutiliza `configuraciones`
(grupo nuevo `archivos`) y `users` (`subido_por`). Ficheros en **disco privado** `documentos`
(driver local, root `storage/app/tenants`), partición por `tenant_id` dentro del path.

**Testing**: PHPUnit/Pest (feature tests). Test-first obligatorio (Principio IV) en el aislamiento
multi-tenant de `carpetas` y `archivos` (incluida la barrera de descarga/preview: un tenant no
accede al fichero de otro por id). El resto (CRUD, validación de tipo/tamaño, borrado en cascada)
con feature tests estándar.

**Target Platform**: Hosting compartido tipo Hostinger/cPanel (sin worker de colas, sin root).
Ficheros en el disco del hosting.

**Project Type**: Web application (Laravel monolito con Blade).

**Performance Goals**: Listar el contenido de un nivel y navegar se percibe instantáneo (SC-006).
Cada acción (subir/mover/renombrar/borrar) da feedback inmediato vía AJAX + toastr sin recargas
confusas.

**Constraints**: Los ficheros NUNCA se sirven por URL pública (FR-019): disco privado + controlador
con scope de tenant. Subida acotada por `upload_max_filesize`/`post_max_size` de PHP del hosting;
el límite de app (10 MB default) debe quedar ≤ límites PHP. Lista blanca de tipos (FR-016b). Sin
colas ni cron (Principio V).

**Scale/Scope**: Miles de archivos por tenant, documentos ligeros (≤10 MB). 2 tablas, 1 modelo por
tabla, 1 controlador de carpetas + 1 de archivos (o 1 unificado `ArchivoController` +
`CarpetaController`), 1 vista principal (explorador) + parciales, 1 JS de inicialización.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: PASS — `carpetas` y `archivos` llevan
  `tenant_id` indexado y usan `BelongsToTenant`. Refuerzo crítico: la **barrera de fichero** —
  descarga/preview resuelven el modelo por id acotado al tenant activo (nunca binding implícito, ver
  memoria `project_tenant_route_binding`), y el path físico incluye `tenant_id`. Tests de fuga con
  ≥2 tenants obligatorios (Principio IV), cubriendo también el endpoint de descarga.
- **II. Cumplimiento Normativo España-First**: N/A — no toca facturación, numeración, impuestos ni
  Verifactu.
- **III. Integridad Financiera Server-Side**: N/A — no hay importes. Se mantiene el criterio de que
  el backend es la fuente de verdad (validación de tipo/tamaño y unicidad de carpeta en servidor,
  nunca solo en el cliente).
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: PASS — el aislamiento de ambas tablas y de
  la descarga/preview es área crítica: tests de fuga entre tenants escritos antes de la
  implementación.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: PASS — disco local del hosting, sin
  S3, sin colas, sin cron. Sin versionado, sin papelera navegable, sin permisos por carpeta, sin
  cuota total (YAGNI, todo fuera de alcance en la spec). Mover carpetas (agregado tras el cierre
  inicial del MVP) usa un recorrido recursivo simple del subárbol para validar ciclos, sin
  nested-set/materialized-path, coherente con el volumen esperado. Se reutiliza el patrón de subida
  ya existente (logos/avatars) adaptado a disco privado.

**Resultado**: PASS. Sin violaciones → Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/019-gestor-documental/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1
│   ├── carpetas.md
│   └── archivos.md
└── tasks.md             # Fase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Models/
│   ├── Carpeta.php               # nuevo (BelongsToTenant, SoftDeletes; árbol autorreferenciado)
│   └── Archivo.php               # nuevo (BelongsToTenant, SoftDeletes)
├── Support/
│   └── ArchivosTenant.php        # nuevo: claves/defaults de config (límite tamaño, tipos permitidos)
├── Services/
│   └── AlmacenArchivos.php       # nuevo: guardar/borrar fichero físico en disco privado por tenant,
│                                 #        UUID de nombre físico, path storage/app/tenants/{id}/documentos
├── Http/
│   ├── Controllers/
│   │   ├── CarpetaController.php  # store, update (renombrar), destroy (cascada)
│   │   └── ArchivoController.php  # index (explorador), store (subir), update (renombrar/mover),
│   │                              #        destroy, descargar, previsualizar
│   └── Requests/
│       ├── StoreCarpetaRequest.php
│       ├── UpdateCarpetaRequest.php
│       ├── StoreArchivoRequest.php     # valida tipo (lista blanca) + tamaño (config)
│       └── UpdateArchivoRequest.php    # renombrar / mover (carpeta_id destino del tenant)
config/
└── filesystems.php               # + disco privado 'documentos' (root storage/app/tenants)
database/
├── migrations/
│   ├── 2026_07_04_..._create_carpetas_table.php
│   └── 2026_07_04_..._create_archivos_table.php
├── factories/
│   ├── CarpetaFactory.php
│   └── ArchivoFactory.php
└── seeders/
    └── ConfiguracionSeeder.php   # + claves grupo 'archivos' (limite_mb, tipos_permitidos)
resources/views/
├── archivos/
│   ├── index.blade.php           # explorador: breadcrumbs + grid/lista + acciones
│   └── partials/
│       ├── _grid.blade.php       # vista rejilla (tarjetas con icono por extensión)
│       ├── _lista.blade.php      # vista lista (tabla)
│       └── _preview-modal.blade.php  # modal de preview (iframe PDF / img)
├── configuracion/
│   └── _tab_archivos.blade.php   # inputs límite tamaño + (opcional) tipos permitidos
public/js/plugins-init/
└── archivos-explorer.js          # navegación, subida (drag&drop + progreso), renombrar/mover/borrar
routes/web.php                    # rutas nuevas dentro del grupo ['tenant.context','auth']
tests/Feature/
├── ArchivoAislamientoTenantTest.php   # test-first (Principio IV): listar/descargar/preview
├── CarpetaAislamientoTenantTest.php   # test-first (Principio IV)
├── ArchivoSubidaTest.php              # tipo permitido/rechazado, límite tamaño, huérfanos
├── ArchivoCrudTest.php                # renombrar (contenido intacto), mover, borrar
└── CarpetaCrudTest.php                # crear (unicidad por nivel), renombrar, borrado en cascada
```

**Structure Decision**: Web app Laravel monolito, siguiendo el patrón ya establecido en el repo
(controladores por recurso, Form Requests, modelos con `BelongsToTenant` + `SoftDeletes`, vistas
Blade sobre el layout `default`, JS de inicialización en `public/js/plugins-init/`,
notificaciones toastr). Se añade **un** servicio nuevo (`AlmacenArchivos`) para encapsular la
escritura física en disco privado por tenant (única fuente de escritura de ficheros, análogo a cómo
`RegistroMovimientoStock` es el único punto de escritura del kardex) y **un** helper `Support`
(`ArchivosTenant`) para los defaults de configuración. No se introducen repositorios ni capas extra.

## Complexity Tracking

> Sin violaciones de la constitución. No aplica.
