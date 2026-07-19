# Implementation Plan: Importación y exportación de Excel

**Branch**: `031-import-export-excel` | **Date**: 2026-07-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/031-import-export-excel/spec.md`

## Summary

Añadir exportación a `.xlsx` en los cinco listados principales (clientes, artículos, facturas,
albaranes, leads) e importación desde `.xlsx`/`.csv` en los tres maestros (clientes, artículos,
proveedores), con previsualización previa a la confirmación.

El eje del diseño es una **definición de columnas única por módulo** (`DefinicionExcel`) de la que
derivan exportador, plantilla e importador, de forma que las cabeceras no puedan desincronizarse
(FR-024, SC-007) y añadir un módulo no obligue a tocar los existentes (SC-010). Los módulos solo
exportables declaran su definición sin mitad de importación, lo que hace que la prohibición de
importar facturas y albaranes (FR-010) sea cierta **por construcción**.

Dos decisiones de investigación condicionan el resto ([research.md](./research.md)):

1. Los listados **filtran en el navegador**, no en el servidor. Por eso la exportación es un
   `POST` al que el cliente le manda los IDs de las filas visibles, y el backend los resuelve con
   `whereIn` bajo el `TenantScope` (el cliente propone, el servidor dispone). Ver D1.
2. La previsualización guarda el fichero en almacenamiento **privado y transitorio** con un token,
   y la confirmación **revalida todo desde cero** en vez de fiarse del veredicto previo. Ver D2.

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 12

**Primary Dependencies**: `maatwebsite/excel` ^3.1 (ya presente en `composer.json`, en uso por
`App\Services\ImportadorLeads`). **No se añade ninguna dependencia nueva.** En frontend, DataTables
ya vendorizado (`public/vendor/`); no se incorpora la extensión Buttons (ver D1).

**Storage**: MySQL/MariaDB. **Esta feature no crea ni altera ninguna tabla.** Usa
almacenamiento de ficheros privado y transitorio (`storage/app/private/importaciones/`) para el
paso de previsualización.

**Testing**: PHPUnit (Pest no está en uso en el repo). Tests de feature bajo `tests/Feature/`,
unitarios bajo `tests/Unit/`.

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V) y Railway/Docker.

**Project Type**: Aplicación web monolítica (Laravel + Blade + DataTables).

**Performance Goals**: Exportación de 10.000 filas sin degradar la aplicación (SC-009), mediante
`FromQuery` + `WithChunkReading` en lotes de 500. Importación síncrona dentro de la petición.

**Constraints**:

- Sin colas ni procesos en segundo plano (Principio V). Todo síncrono en la petición.
- Límite duro de **2.000 filas por archivo importado** (FR-019), comprobado antes de procesar.
- Payload de IDs en la exportación acotado por `post_max_size`; ~60 KB para 10.000 filas.
- Los ficheros de importación contienen datos personales: almacenamiento privado, borrado tras
  confirmar/cancelar, purga a las 24 h (Principio II).

**Scale/Scope**: 5 módulos exportables, 3 importables. Sin migraciones. 2 casos nuevos en enums
existentes. ~8 clases de definición + 1 servicio de exportación + 1 de importación + 2
controladores + vistas de importación para 3 módulos.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Aplica | Cumplimiento |
|---|---|---|
| **I. Aislamiento multi-tenant (NON-NEGOTIABLE)** | **Sí, críticamente** | La exportación resuelve los IDs recibidos con `whereIn` sobre el modelo, que pasa por el `TenantScope` de `BaseModel`: unos IDs de otro tenant simplemente no devuelven filas. La importación fija `tenant_id` desde `tenant()->id` del usuario autenticado y **descarta cualquier columna del fichero que pretenda fijarlo** (FR-015). Tests obligatorios con ≥2 tenants para exportación (no aparecen filas ajenas ni aun mandando sus IDs a propósito) e importación (los registros creados son del tenant activo). |
| **II. Cumplimiento normativo España-First (RGPD)** | **Sí** | Los ficheros importados contienen datos personales: directorio privado, borrado tras confirmar/cancelar y comando de purga a 24 h reutilizando el patrón `logs:purgar`/`withSchedule` que la constitución exige (no se inventa un mecanismo nuevo). Las exportaciones se registran en el log de actividad: una extracción masiva de datos personales es un evento con valor forense (D6). No se crea ninguna tabla nueva con datos personales. |
| **III. Integridad financiera server-side** | **Sí, por exclusión** | La feature **no calcula ningún importe**: exporta los ya calculados y persistidos. FR-010 prohíbe importar facturas, albaranes, pagos y movimientos de stock, es decir, todo lo que toca numeración correlativa, encadenamiento Verifactu o importes. Esa prohibición no es una comprobación en runtime sino una ausencia estructural: esos módulos no tienen mitad de importación (D7). |
| **IV. Test-first en lógica crítica (NON-NEGOTIABLE)** | **Sí, para el aislamiento** | Los tests de aislamiento multi-tenant (exportación e importación) se escriben **antes** de la implementación y deben fallar primero. El resto (UI de importación, formato de celdas, plantillas) sigue el flujo flexible. Esta feature no toca cálculo de impuestos, numeración de series ni Verifactu. |
| **V. Simplicidad y hosting compartido** | **Sí** | Sin dependencias nuevas, sin colas, sin workers, sin tablas nuevas. Todo síncrono, acotado por el límite de filas precisamente para que sea viable en hosting compartido. Se descartó explícitamente la tabla `importaciones_pendientes` por complejidad desproporcionada (D2). |

**Veredicto: PASA, sin violaciones que justificar.** La sección "Complexity Tracking" queda vacía
a propósito.

**Punto de atención para revisión** (no es una violación, pero es donde se rompería si se hace
mal): el endpoint de exportación recibe IDs del cliente. **Nunca** debe resolverlos con
`withoutGlobalScopes()`, `Model::withoutTenancy()` ni una query cruda. Si alguien "optimiza" esa
consulta saltándose el scope, la feature se convierte en una fuga de datos entre tenants a
petición. El test de aislamiento existe para atrapar exactamente eso.

### Re-evaluación post-diseño (tras Phase 1)

Revisado el diseño completo (data-model, contratos, quickstart): **sigue pasando, sin violaciones
nuevas.** Tres puntos que el diseño cambió respecto a la evaluación inicial y que conviene dejar
registrados:

1. **Principio II (RGPD) — tensión detectada y resuelta.** El diseño de la previsualización (D2)
   introduce persistencia temporal de un fichero con datos personales, cosa que la evaluación
   previa a Phase 0 no contemplaba. Se resuelve con almacenamiento privado + borrado inmediato al
   confirmar/cancelar + comando `importaciones:purgar` a 24 h reutilizando el patrón `logs:purgar`
   que la constitución exige. No es una desviación, pero **no estaba en el radar antes del
   diseño** y es el motivo de que exista el escenario 4b del quickstart.
2. **Principio III reforzado por construcción.** El diseño de `DefinicionExcel` (D7) convirtió
   FR-010 de "una comprobación que hay que acordarse de hacer" a "código que no existe": las
   definiciones de facturas y albaranes no implementan el contrato importable, así que la ruta ni
   se registra. Es más fuerte que lo evaluado inicialmente.
3. **Sin cambios en I, IV y V.** No se añadieron tablas, dependencias, colas ni procesos en
   segundo plano durante el diseño.

## Project Structure

### Documentation (this feature)

```text
specs/031-import-export-excel/
├── plan.md              # Este archivo
├── spec.md              # Especificación funcional
├── research.md          # Phase 0: decisiones D1-D7
├── data-model.md        # Phase 1: entidades y definiciones de columna
├── quickstart.md        # Phase 1: guía de validación end-to-end
├── contracts/
│   ├── exportacion.md   # Contrato del endpoint de exportación
│   └── importacion.md   # Contrato de previsualización + confirmación
├── checklists/
│   └── requirements.md  # Checklist de calidad del spec
└── tasks.md             # Phase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Excel/                                  # NUEVO — núcleo de la feature
│   ├── DefinicionExcel.php                 # Contrato base: módulo, permiso, columnas
│   ├── DefinicionExportable.php            # Interfaz de capacidad: consultaExportacion()
│   ├── DefinicionImportable.php            # Interfaz de capacidad: reglasFila(), crear()
│   ├── ColumnaExcel.php                    # Una columna: clave, etiqueta, lectura, normalización
│   ├── FormatoCelda.php                    # Enum: Texto, Numero, Importe, Fecha, Booleano
│   ├── RegistroDefiniciones.php            # Resuelve módulo (string) → DefinicionExcel
│   └── Definiciones/
│       ├── DefinicionClientes.php          # Exportable + importable
│       ├── DefinicionArticulos.php         # Exportable + importable
│       ├── DefinicionProveedores.php       # Solo importable (no hay listado exportable en alcance)
│       ├── DefinicionFacturas.php          # Solo exportable
│       ├── DefinicionAlbaranes.php         # Solo exportable
│       └── DefinicionLeads.php             # Solo exportable
├── Services/
│   ├── ExportadorExcel.php                 # NUEVO — genera el .xlsx desde una definición
│   ├── ImportadorExcel.php                 # NUEVO — previsualiza y confirma
│   ├── AlmacenImportaciones.php            # NUEVO — guarda/recupera/borra el fichero por token
│   └── ImportadorLeads.php                 # EXISTENTE — no se toca (ver Assumptions del spec)
├── Http/
│   ├── Controllers/
│   │   ├── ExportacionController.php       # NUEVO — POST por módulo
│   │   └── ImportacionController.php       # NUEVO — form, plantilla, previsualizar, confirmar
│   └── Requests/
│       ├── ExportarRequest.php             # NUEVO — valida módulo + lista de IDs
│       └── ImportarExcelRequest.php        # NUEVO — valida fichero (tipo, tamaño)
├── Console/Commands/
│   └── PurgarImportaciones.php             # NUEVO — barre ficheros huérfanos > 24 h
└── Enums/
    ├── AccionLogActividad.php              # MODIFICADO — se añade case Exportacion
    └── EntidadLogActividad.php             # MODIFICADO — se añade case Proveedor

resources/
├── views/
│   ├── excel/
│   │   ├── importar.blade.php              # NUEVO — vista genérica de importación por módulo
│   │   └── _resultado.blade.php            # NUEVO — parcial de previsualización/resumen
│   └── ayuda/
│       ├── importar-exportar.blade.php     # NUEVO — guía transversal (FR-025)
│       ├── clientes.blade.php              # MODIFICADO ─┐ las 6 guías ya existen y quedarían
│       ├── articulos.blade.php             # MODIFICADO  │ desactualizadas al añadir los
│       ├── proveedores.blade.php           # MODIFICADO  ├ botones Exportar/Importar.
│       ├── facturas.blade.php              # MODIFICADO  │ "Una guía desactualizada es peor
│       ├── albaranes.blade.php             # MODIFICADO  │ que no tenerla" (CLAUDE.md).
│       └── leads.blade.php                 # MODIFICADO ─┘
└── ia/conocimiento/
    ├── importacion-exportacion.md          # NUEVO — base de conocimiento del asistente (FR-025)
    ├── clientes.md                         # MODIFICADO — ahora se puede importar
    └── articulos.md                        # MODIFICADO — ahora se puede importar

public/js/plugins-init/
└── excel-export.init.js                    # NUEVO — recoge IDs visibles del DataTable y hace POST

tests/
├── Feature/
│   ├── ExportacionExcelTest.php            # Incluye aislamiento multi-tenant (test-first)
│   ├── ImportacionExcelTest.php            # Incluye aislamiento multi-tenant (test-first)
│   ├── ExcelPermisosTest.php               # FR-004 + FR-020, export e import juntos
│   └── PurgarImportacionesTest.php         # Principio II: retención a 24 h
└── Unit/
    └── DefinicionesExcelTest.php           # FR-024/SC-007 (cabeceras) + SC-010 (extensibilidad)

bootstrap/app.php                           # MODIFICADO — withSchedule: importaciones:purgar
routes/web.php                              # MODIFICADO — rutas bajo los can:ver-* existentes
```

**Structure Decision**: se sigue la estructura estándar de Laravel ya vigente en el repo
(`app/Services`, `app/Http/Controllers`, `resources/views`), añadiendo un namespace nuevo
`app/Excel/` para el núcleo de definiciones.

Ese namespace propio se justifica porque las definiciones **no son servicios ni modelos**: son
declaraciones de datos que consumen tres cosas distintas (exportador, plantilla, importador).
Meterlas en `app/Services/` las mezclaría con lógica ejecutable y haría menos evidente que el
patrón para añadir un módulo es "añadir una clase de definición y nada más" (SC-010).

Las rutas nuevas se cuelgan **dentro de los grupos `can:ver-*` ya existentes** en `routes/web.php`
(`can:ver-clientes`, `can:ver-articulos`, `can:ver-proveedores`, `can:ver-facturas`,
`can:ver-albaranes`, `can:ver-leads`), que es lo que cumple FR-004 y FR-020 sin introducir
permisos nuevos.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

Sin violaciones. El Constitution Check pasa limpio: sin dependencias nuevas, sin tablas nuevas,
sin colas y sin desviaciones de los Principios I-V.
