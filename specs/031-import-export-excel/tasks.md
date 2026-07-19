---
description: "Task list para la feature 031 — Importación y exportación de Excel"
---

# Tasks: Importación y exportación de Excel

**Input**: Design documents from `/specs/031-import-export-excel/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: **SÍ, obligatorios en parte.** El Principio IV de la constitución exige test-first
(escribir, ver fallar, luego implementar) para **aislamiento multi-tenant**. Las tareas marcadas
🔴 **TEST-FIRST** son innegociables y deben verse fallar antes de escribir su implementación. El
resto de tests sigue el flujo flexible y puede escribirse después.

**Organization**: Agrupadas por user story para poder implementar y entregar cada una de forma
independiente.

## Format: `[ID] [P?] [Story] Descripción`

- **[P]**: Paralelizable (ficheros distintos, sin dependencias pendientes)
- **[Story]**: US1 (export), US2 (import), US3 (plantillas)

## Path Conventions

Monolito Laravel: `app/`, `resources/`, `routes/`, `public/js/`, `tests/` en la raíz del repo.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Estructura base. No hay dependencias que instalar — `maatwebsite/excel` ^3.1 ya está.

- [X] T001 Crear el namespace `app/Excel/` con el subdirectorio `Definiciones/`
- [X] T002 [P] Añadir `case Exportacion = 'exportacion'` (label `'Exportación'`) a `app/Enums/AccionLogActividad.php`
- [X] T003 [P] Añadir `case Proveedor = 'proveedor'` a `app/Enums/EntidadLogActividad.php`
- [X] T004 [P] Crear el enum `FormatoCelda` (Texto, Numero, Importe, Fecha, Booleano) en `app/Excel/FormatoCelda.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: El núcleo de definiciones del que dependen las tres user stories.

**⚠️ CRÍTICO**: ninguna user story puede empezar hasta que esta fase esté completa.

- [X] T005 Crear el objeto de valor `ColumnaExcel` en `app/Excel/ColumnaExcel.php` con `clave`, `etiqueta`, `obligatoria`, `formato`, callables `exportar`/`importar` y `ejemplo`, según [data-model.md](./data-model.md) §1
- [X] T006 Implementar la normalización de cabeceras (minúsculas, sin acentos, espacios→guion bajo) como método estático en `app/Excel/ColumnaExcel.php`, para que `Razón social`, `razon social` y `RAZON_SOCIAL` resuelvan a `razon_social`
- [X] T007 Crear el contrato base `DefinicionExcel` en `app/Excel/DefinicionExcel.php` con **solo** `modulo`, `etiquetaModulo`, `permiso`, `columnas` y `entidadLog` — lo que todo módulo tiene ([data-model.md](./data-model.md) §2.1)
- [X] T008 [P] Crear la interfaz de capacidad `DefinicionExportable` en `app/Excel/DefinicionExportable.php` (`consultaExportacion`) — §2.2
- [X] T009 [P] Crear la interfaz de capacidad `DefinicionImportable` en `app/Excel/DefinicionImportable.php` (`reglasFila`, `crear`) — §2.3. **Interfaces separadas, no métodos del contrato base**: que facturas y albaranes no implementen la importable es lo que hace FR-010 cierto por construcción, y que proveedores no implemente la exportable evita dejar un método muerto que alguien cablee a una ruta más adelante (D7, §2.4)
- [X] T010 Crear `RegistroDefiniciones` en `app/Excel/RegistroDefiniciones.php` con `resolverExportable()` y `resolverImportable()`, que lancen `NotFoundHttpException` si la definición no implementa la interfaz correspondiente
- [X] T011 Registrar `RegistroDefiniciones` como singleton en `app/Providers/AppServiceProvider.php`

**Checkpoint**: el núcleo existe; US1, US2 y US3 pueden arrancar a partir de aquí.

---

## Phase 3: User Story 1 — Exportar un listado a Excel (P1) 🎯 MVP

**Goal**: El usuario exporta a `.xlsx` cualquiera de los cinco listados, respetando los filtros
activos y sin ver jamás datos de otro tenant.

**Independent Test**: Filtrar el listado de clientes, pulsar Exportar, y verificar que el fichero
contiene solo las filas filtradas, con cabeceras en español e importes sumables.

### Tests

- [X] T012 🔴 **TEST-FIRST** [US1] Escribir `tests/Feature/ExportacionExcelTest.php::test_no_exporta_filas_de_otro_tenant_aunque_se_manden_sus_ids` — crear 2 tenants con clientes, autenticarse en el A, hacer POST a `/exportar/clientes` con IDs del tenant B, y afirmar que el fichero no contiene ninguna fila de B. **Verlo fallar antes de T015** (Principio I + IV)
- [X] T013 [P] [US1] Test `test_exporta_solo_los_ids_recibidos` en `tests/Feature/ExportacionExcelTest.php` (FR-002, SC-002)
- [X] T014 [P] [US1] Crear `tests/Feature/ExcelPermisosTest.php` con `test_deniega_exportacion_sin_permiso` — usuario sin `ver-clientes` recibe 403 al hacer POST a `/exportar/clientes` (FR-004)

### Implementación

- [X] T015 [US1] Implementar `ExportadorExcel` en `app/Services/ExportadorExcel.php` usando `FromQuery` + `WithChunkReading` (500) + `WithHeadings` + `WithColumnFormatting`. **La consulta va siempre por el modelo Eloquent** para que aplique el `TenantScope`; prohibido `withoutGlobalScopes()` o `DB::table()` (ver [contracts/exportacion.md](./contracts/exportacion.md))
- [X] T016 [US1] Aplicar formatos de celda nativos en `ExportadorExcel`: importes y fechas como valores crudos con formato de celda español, **no** como cadenas de `App\Support\Formato` (FR-006, D5)
- [X] T017 [P] [US1] Crear `DefinicionClientes` en `app/Excel/Definiciones/DefinicionClientes.php` implementando `DefinicionExportable` (columnas de [data-model.md](./data-model.md) §3.1; `cp` como Texto para no perder el cero inicial; `tipo` exporta la etiqueta legible)
- [X] T018 [P] [US1] Crear `DefinicionArticulos` en `app/Excel/Definiciones/DefinicionArticulos.php` implementando `DefinicionExportable` (§3.2; categoría por nombre, sin columna de imagen)
- [X] T019 [P] [US1] Crear `DefinicionFacturas` en `app/Excel/Definiciones/DefinicionFacturas.php` (§3.4; implementa `DefinicionExportable` y **no** `DefinicionImportable`; excluye simplificadas igual que el listado)
- [X] T020 [P] [US1] Crear `DefinicionAlbaranes` en `app/Excel/Definiciones/DefinicionAlbaranes.php` (§3.5; solo exportable)
- [X] T021 [P] [US1] Crear `DefinicionLeads` en `app/Excel/Definiciones/DefinicionLeads.php` (§3.6; solo exportable — la importación de leads ya existe y no se toca)
- [X] T022 [US1] Crear `ExportarRequest` en `app/Http/Requests/ExportarRequest.php` validando `ids` (`required|array|min:1`, elementos `integer`)
- [X] T023 [US1] Crear `ExportacionController` en `app/Http/Controllers/ExportacionController.php` con la acción `POST /exportar/{modulo}`, resolviendo vía `resolverExportable()` y devolviendo la descarga con `Content-Disposition` `{modulo}-AAAA-MM-DD.xlsx` (FR-007)
- [X] T024 [US1] Registrar la actividad en `ExportacionController` vía `RegistradorActividad` con `AccionLogActividad::Exportacion`, contando las **filas realmente escritas**, no los IDs recibidos (FR-008, ver [contracts/exportacion.md](./contracts/exportacion.md))
- [X] T025 [US1] Añadir las rutas de exportación en `routes/web.php` **dentro de los grupos `can:ver-*` existentes** (`ver-clientes`, `ver-articulos`, `ver-facturas`, `ver-albaranes`, `ver-leads`)
- [X] T026 [US1] Crear `public/js/plugins-init/excel-export.init.js` que recoja los IDs visibles con `table.rows({ search: 'applied' }).data()`, haga el POST y dispare la descarga; toast de aviso si el conjunto está vacío
- [X] T027 [US1] Añadir el botón "Exportar" a la cabecera de los cinco listados: `resources/views/clientes/index.blade.php`, `articulos/index.blade.php`, `facturas/index.blade.php`, `albaranes/index.blade.php`, `leads/index.blade.php`

**Checkpoint**: US1 entregable de forma independiente. **Este es el MVP.**

---

## Phase 4: User Story 2 — Importar con previsualización (P2)

**Goal**: El usuario carga clientes, artículos o proveedores desde su propia hoja, viendo antes
qué va a pasar y sin que nada se escriba hasta confirmar.

**Independent Test**: Subir 10 filas con 3 errores conocidos, comprobar que la previsualización
las marca con su motivo y que tras confirmar se crean exactamente 7 registros.

### Tests

- [X] T028 🔴 **TEST-FIRST** [US2] Escribir `tests/Feature/ImportacionExcelTest.php::test_fuerza_el_tenant_activo_ignorando_la_columna_del_fichero` — importar un fichero que incluye una columna `tenant_id` con el id de otro tenant y afirmar que los registros creados pertenecen al tenant activo. **Verlo fallar antes de T035** (FR-015, Principio I + IV)
- [X] T029 [P] [US2] Test `test_la_previsualizacion_no_escribe_nada` en `tests/Feature/ImportacionExcelTest.php` — el recuento de registros es idéntico antes y después de previsualizar (FR-014, SC-006)
- [X] T030 [P] [US2] Test `test_importa_las_validas_y_reporta_las_rechazadas` en `tests/Feature/ImportacionExcelTest.php` (FR-016)
- [X] T031 [P] [US2] Test `test_no_existen_rutas_de_importacion_para_facturas_y_albaranes` en `tests/Feature/ImportacionExcelTest.php` — `/importar/facturas` y `/importar/albaranes` devuelven 404 (FR-010)
- [X] T032 [P] [US2] Tests de ficheros problemáticos en `tests/Feature/ImportacionExcelTest.php`: PDF renombrado, fichero vacío, más de 2.000 filas, cabeceras desconocidas — todos 422 con mensaje, **ningún 500** (SC-008, FR-018, FR-019)
- [X] T033 [P] [US2] Añadir a `tests/Feature/ExcelPermisosTest.php` los tests de importación sin permiso: `form`, `previsualizar` y `confirmar` devuelven 403 para un usuario sin `ver-clientes` (FR-020). **Los tres endpoints, no solo el formulario**: `confirmar` es el que escribe, así que es el que no puede quedar sin cubrir
- [X] T034 [P] [US2] Test `test_confirmar_con_token_caducado_no_importa_nada` en `tests/Feature/ImportacionExcelTest.php` — previsualizar, borrar el fichero, confirmar con ese token: 422 y recuento de registros sin cambios (edge case "previsualización caducada")

### Implementación

- [X] T035 [US2] Crear `AlmacenImportaciones` en `app/Services/AlmacenImportaciones.php`: guardar en `storage/app/private/importaciones/{uuid}`, recuperar por token, borrar, y listar los de más de 24 h
- [X] T036 [US2] Crear los objetos de valor `PrevisualizacionImportacion`, `ResultadoImportacion` y `FilaRechazada` en `app/Excel/` según [data-model.md](./data-model.md) §4-6, con el mismo contrato de `rechazadas` que `ResultadoImportacionLeads` para que los mensajes sean coherentes entre ambos importadores
- [X] T037 [US2] Implementar `ImportadorExcel::previsualizar()` en `app/Services/ImportadorExcel.php`: guardar fichero, comprobar cabeceras obligatorias (rechazo total si faltan), comprobar el límite de 2.000 filas **antes** de procesar, normalizar y validar cada fila
- [X] T038 [US2] Implementar la validación por fila reutilizando las `rules()` del `FormRequest` del alta manual vía `Validator::make()` (D3) — no escribir reglas paralelas
- [X] T039 [US2] Implementar la detección de duplicados **dentro del propio fichero** en `ImportadorExcel`, acumulando identificadores ya vistos durante el recorrido y citando la fila con la que choca
- [X] T040 [US2] Implementar `ImportadorExcel::confirmar()`: recuperar por token, **revalidar el fichero entero desde cero** (no reutilizar el veredicto de la previsualización, D2), persistir las válidas forzando `tenant_id`, borrar el fichero
- [X] T041 [P] [US2] Añadir `DefinicionImportable` a `DefinicionClientes` (`reglasFila` delegando en `StoreClienteRequest`, `crear` forzando `tenant_id`)
- [X] T042 [P] [US2] Añadir `DefinicionImportable` a `DefinicionArticulos`, resolviendo la categoría **por nombre** dentro del tenant y **rechazando** la fila si no existe (nunca crearla al vuelo)
- [X] T043 [P] [US2] Crear `DefinicionProveedores` en `app/Excel/Definiciones/DefinicionProveedores.php` (§3.3) implementando **solo** `DefinicionImportable`, no `DefinicionExportable` (proveedores no está en el alcance de exportación)
- [X] T044 [US2] Crear `ImportarExcelRequest` en `app/Http/Requests/ImportarExcelRequest.php` (`fichero`: `required|file|mimes:xlsx,xls,csv,txt|max:5120`)
- [X] T045 [US2] Crear `ImportacionController` en `app/Http/Controllers/ImportacionController.php` con `form`, `previsualizar`, `confirmar` y `rechazos`, resolviendo vía `resolverImportable()`, según [contracts/importacion.md](./contracts/importacion.md)
- [X] T046 [US2] Capturar en `ImportacionController` todo error de lectura (corrupto, vacío, protegido, tipo falseado) y traducirlo a 422 con mensaje en español (SC-008)
- [X] T047 [US2] Registrar la actividad de importación confirmada con `AccionLogActividad::Alta` y el recuento de importadas/rechazadas (FR-021)
- [X] T048 [US2] Añadir las rutas de importación en `routes/web.php` dentro de `can:ver-clientes`, `can:ver-articulos` y `can:ver-proveedores` — **y solo esas tres**
- [X] T049 [US2] Crear la vista genérica `resources/views/excel/importar.blade.php` (zona de subida, instrucciones, enlace a plantilla)
- [X] T050 [US2] Crear el parcial `resources/views/excel/_resultado.blade.php` con el panel de previsualización (totales + tabla de rechazos + muestra de 10 filas) y el de resumen final
- [X] T051 [US2] Implementar la descarga del detalle de rechazos en `.xlsx` (columnas `Fila` y `Motivo`) en `ImportacionController::rechazos` (FR-022)
- [X] T052 [US2] Añadir el botón "Importar" a `resources/views/clientes/index.blade.php`, `articulos/index.blade.php` y `proveedores/index.blade.php`
- [X] T053 [US2] Crear el comando `importaciones:purgar` en `app/Console/Commands/PurgarImportaciones.php` (borra ficheros de más de 24 h)
- [X] T054 [US2] Programar `importaciones:purgar` a diario en `bootstrap/app.php` → `withSchedule`, siguiendo el patrón de `logs:purgar` que exige la constitución (Principio II)
- [X] T055 [P] [US2] Test `test_purga_borra_los_ficheros_de_mas_de_24h_y_conserva_los_recientes` en `tests/Feature/PurgarImportacionesTest.php`

**Checkpoint**: US1 + US2 entregables juntos. La feature ya es completa funcionalmente.

---

## Phase 5: User Story 3 — Plantillas de importación (P3)

**Goal**: El usuario descarga una plantilla con las cabeceras exactas y una fila de ejemplo.

**Independent Test**: Descargar la plantilla de artículos, rellenarla con 2 filas e importarla sin
errores de formato ni de cabeceras.

- [X] T056 [US3] Test `tests/Unit/DefinicionesExcelTest.php::test_las_cabeceras_de_plantilla_export_e_import_coinciden` — para cada definición importable, afirmar que las cabeceras de exportación, plantilla e importador son idénticas. **Es el test que protege SC-007 y FR-024**: sin él, las tres se desincronizan en cuanto alguien añade una columna a una sola
- [X] T057 [US3] Test `tests/Unit/DefinicionesExcelTest.php::test_registrar_una_definicion_nueva_no_requiere_tocar_las_existentes` — registrar una definición ficticia en `RegistroDefiniciones` y comprobar que se resuelve y exporta sin modificar ninguna clase existente (SC-010)
- [X] T058 [US3] Implementar la generación de plantillas en `app/Services/ExportadorExcel.php` (`plantilla()`): cabeceras de `columnas()` + una fila con los valores `ejemplo`
- [X] T059 [US3] Añadir la acción `plantilla` a `ImportacionController` y su ruta `GET /importar/{modulo}/plantilla`
- [X] T060 [US3] Rellenar el campo `ejemplo` de cada columna en las tres definiciones importables (`DefinicionClientes`, `DefinicionArticulos`, `DefinicionProveedores`)
- [X] T061 [US3] Añadir el enlace "Descargar plantilla" a `resources/views/excel/importar.blade.php`
- [X] T062 [US3] Test `test_un_fichero_exportado_se_puede_reimportar` en `tests/Feature/ImportacionExcelTest.php` — exportar clientes, alterar NIF y nombre, reimportar sin rechazos de formato (SC-007)

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T063 [P] Aplicar el override de CSS de paginación de DataTables a cualquier tabla nueva, según `docs/04-front-guidelines.md` (si no se hace, los botones anterior/siguiente se renderizan como columnas verticales de letras)
- [ ] T064 [P] Verificar con ~10.000 clientes sembrados que la exportación completa no agota memoria y la app sigue respondiendo (SC-009, escenario 4a del [quickstart.md](./quickstart.md))
- [X] T065 [P] Comprobar que acentos, eñes y comillas sobreviven al viaje de ida y vuelta export → import
- [ ] T066 Ejecutar el [quickstart.md](./quickstart.md) completo, escenarios 1 a 4

### Documentación — las 4 capas obligatorias de `CLAUDE.md`

- [X] T067 [P] `docs/` — confirmar por escrito que **no** hacen falta cambios (esta feature no crea tablas ni altera decisiones de arquitectura). Dejarlo dicho explícitamente, no en silencio
- [X] T068 [P] Documentar en `docs/04-front-guidelines.md` la convención del botón "Exportar"/"Importar" en la cabecera de un DataTable, incluido el patrón de recoger los IDs visibles con `rows({search:'applied'})`
- [X] T069 [P] Crear la guía in-app transversal `resources/views/ayuda/importar-exportar.blade.php` (FR-025)
- [X] T070 **Actualizar las seis guías in-app que ya existen** y que esta feature deja desactualizadas: `resources/views/ayuda/clientes.blade.php`, `articulos.blade.php`, `proveedores.blade.php`, `facturas.blade.php`, `albaranes.blade.php` y `leads.blade.php`. Todas describen pantallas a las que se les añade un botón nuevo. `CLAUDE.md`: *"una guía in-app desactualizada es peor que no tenerla (le miente al usuario)"* — no basta con crear T069
- [X] T071 [P] Crear `resources/ia/conocimiento/importacion-exportacion.md` para el asistente IA (FR-025). **Debe decir explícitamente qué NO se puede importar** (facturas, albaranes) o el asistente le prometerá al usuario una función que no existe
- [X] T072 Actualizar `resources/ia/conocimiento/clientes.md` y `articulos.md`, que ya existen y no mencionan que ahora esos módulos se pueden importar y exportar (FR-025)

---

## Dependencies

```
Phase 1 (Setup: T001-T004)
        ↓
Phase 2 (Foundational: T005-T011)  ⚠️ BLOQUEANTE
        ↓
        ├─→ Phase 3 (US1, P1) ── MVP ──┐
        ├─→ Phase 4 (US2, P2) ─────────┤
        └─→ Phase 5 (US3, P3) ─────────┤
                                        ↓
                              Phase 6 (Polish)
```

**Dependencias entre stories**:

- **US1 es independiente** — solo necesita Phase 2. Es el MVP.
- **US2 es independiente de US1** en lo funcional, pero T041/T042 amplían las definiciones que
  crea US1 (T017/T018). Si se implementan en paralelo, coordinar esos ficheros.
- **US3 depende de US2** en la práctica: una plantilla sin importador no sirve para nada. T056
  necesita las definiciones importables de US2.

**Dentro de US1**: T012 (test de aislamiento) **antes** de T015. T017-T021 son paralelos entre sí.
T025-T027 después de T023.

**Dentro de US2**: T028 (test de tenant) **antes** de T035. T041-T043 son paralelos entre sí.
T045-T052 después de T037/T040. T034 necesita T035 (el almacén) para poder borrar el fichero.

**En Phase 6**: T070 y T072 tocan ficheros que también toca quien implemente US1/US2 si documenta
sobre la marcha; hacerlos al final, una sola vez.

## Parallel Execution Examples

**Phase 1** — T002, T003 y T004 tocan ficheros distintos: en paralelo.

**Phase 2** — T008 y T009 son dos interfaces independientes: en paralelo, tras T007.

**Phase 3 (US1)** — las cinco definiciones son ficheros independientes:

```
T017 DefinicionClientes  ┐
T018 DefinicionArticulos ├─ en paralelo, tras T007/T008
T019 DefinicionFacturas  │
T020 DefinicionAlbaranes │
T021 DefinicionLeads     ┘
```

**Phase 4 (US2)** — T041, T042 y T043 en paralelo. Los tests T029-T034 en paralelo entre sí.

**Phase 6** — T067, T068, T069 y T071 son cuatro ficheros distintos: todos en paralelo. T070 y
T072 tocan varios ficheros cada uno; hacerlos en serie para no pisarse.

## Implementation Strategy

### MVP (entrega 1) — solo US1

Phases 1 + 2 + 3 (T001-T027). Entrega **la exportación completa en los cinco listados**. Es la
mitad de la feature que da valor inmediato con riesgo cero: solo lee, no escribe nada. Se puede
desplegar y usar sin US2 ni US3.

### Entrega 2 — US2

Phase 4 (T028-T055). Añade la importación en los tres maestros. Es donde está el riesgo (escribe
datos), y por eso va detrás y con los tests de tenant primero.

### Entrega 3 — US3 + Polish

Phases 5 y 6 (T056-T072). Plantillas y cierre documental.

### Orden de los tests (Principio IV)

T012 y T028 se escriben **antes** que su implementación y deben verse fallar. Si pasan a la
primera, están mal escritos y hay que arreglarlos antes de seguir. El resto de tests (incluidos
T056 y T057, que protegen propiedades arquitectónicas, no aislamiento) sigue el flujo flexible.

## Resumen

| Fase | Story | Tareas | Nº |
|---|---|---|---|
| 1 | — | T001-T004 | 4 |
| 2 | — | T005-T011 | 7 |
| 3 | US1 (P1) | T012-T027 | 16 |
| 4 | US2 (P2) | T028-T055 | 28 |
| 5 | US3 (P3) | T056-T062 | 7 |
| 6 | — | T063-T072 | 10 |
| **Total** | | | **72** |
