---

description: "Task list for feature 035-perfil-cliente"

---

# Tasks: Perfil del cliente

**Input**: Design documents from `specs/035-perfil-cliente/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, quickstart.md

**Tests**: Se incluyen tareas de test para aislamiento multi-tenant y permisos (Principio I y IV
de la constitución, NON-NEGOTIABLE: toda feature que toque datos de tenant debe incluir tests de
aislamiento, y deben escribirse antes que la implementación del `show()`). El resto de la
cobertura (listados, resumen financiero, timeline) también se cubre con Feature tests, siguiendo
la convención ya establecida en `tests/Feature/*` de este repo.

**Organization**: Tareas agrupadas por user story (spec.md) para poder implementar y probar cada
una de forma independiente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias entre sí)
- **[Story]**: A qué user story pertenece (US1, US2, US3, US4)
- Rutas de archivo exactas en cada descripción

## Path Conventions

Proyecto único Laravel ya existente: `app/`, `resources/views/`, `routes/web.php`,
`public/js/plugins-init/`, `tests/Feature/` en la raíz del repo (ver plan.md → Project Structure).

---

## Phase 1: Setup

**Purpose**: No hay inicialización de proyecto nueva (repo Laravel ya existente); esta fase solo
prepara el terreno mínimo compartido por todas las user stories.

- [X] T001 Crear el directorio de tests `tests/Feature/Cliente/` (si no existe) para alojar
      `PerfilClienteTest.php`.

**Checkpoint**: Sin bloqueos — se puede pasar directo a la fase Foundational.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Relaciones de modelo y ruta base que TODAS las user stories necesitan antes de poder
implementarse.

**⚠️ CRITICAL**: Ninguna user story puede completarse sin esta fase.

- [X] T002 [P] Agregar relación `facturas(): HasMany` a `app/Models/Cliente.php` (`hasMany(Factura::class)`).
- [X] T003 [P] Agregar relación `presupuestos(): HasMany` a `app/Models/Cliente.php` (`hasMany(Presupuesto::class)`).
- [X] T004 [P] Agregar relación `albaranes(): HasMany` a `app/Models/Cliente.php` (`hasMany(Albaran::class)`).
- [X] T005 [P] Agregar relación `oportunidades(): HasMany` a `app/Models/Cliente.php` (`hasMany(Oportunidad::class)`).
- [X] T006 Agregar la ruta `Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show')`
      dentro del grupo `Route::middleware('can:ver-clientes')` en `routes/web.php` (junto al
      `Route::resource('clientes', ...)` existente, línea ~68-69).
- [X] T007 Crear el esqueleto del método `show(string $cliente): View` en
      `app/Http/Controllers/ClienteController.php`, resolviendo el cliente manualmente con
      `Cliente::findOrFail($cliente)` (mismo patrón que `update`/`destroy` de ese controller —
      ver comentario existente sobre `SubstituteBindings` vs `TenantScope`) y devolviendo por
      ahora solo la vista `clientes.show` con el cliente, sin datos de las pestañas todavía.
- [X] T008 Crear el esqueleto de la vista `resources/views/clientes/show.blade.php` con la
      estructura de pestañas Bootstrap `nav-tabs`/`tab-pane` (mismo patrón que
      `resources/views/profile/show.blade.php`, sección `#perfil-tabs`), con una única pestaña
      "Datos generales" de momento (contenido real en T012).

**Checkpoint**: Con esto ya existe `GET /clientes/{id}` navegable, protegido por `ver-clientes`,
con la estructura de pestañas lista para poblarse por cada user story.

---

## Phase 3: User Story 1 - Ver ficha completa de un cliente (Priority: P1) 🎯 MVP

**Goal**: Desde el listado de clientes, poder abrir "Ver perfil" y ver, en pestañas, los datos
generales del cliente y su resumen financiero (total facturado, pendiente de cobro, facturas
vencidas, ticket medio).

**Independent Test**: Entrar al listado de clientes, abrir "Ver perfil" de un cliente con
facturas emitidas, y comprobar que la pestaña de datos generales muestra la ficha completa y que
el resumen financiero refleja cifras coherentes con las facturas reales de ese cliente.

### Tests for User Story 1 ⚠️

> Escribir estos tests PRIMERO y confirmar que fallan antes de implementar (Principio IV —
> aislamiento multi-tenant es lógica crítica no negociable).

- [X] T009 [P] [US1] Test de aislamiento multi-tenant en `tests/Feature/Cliente/PerfilClienteTest.php`:
      crear cliente en Tenant B, autenticar usuario de Tenant A, `GET /clientes/{id-de-B}` debe
      devolver 404.
- [X] T010 [P] [US1] Test de permiso en `tests/Feature/Cliente/PerfilClienteTest.php`: usuario sin
      `ver-clientes` recibe 403/redirect al intentar `GET /clientes/{id}` de un cliente válido de
      su propio tenant.
- [X] T011 [P] [US1] Test de contenido en `tests/Feature/Cliente/PerfilClienteTest.php`: cliente
      con datos completos → la respuesta incluye nombre/razón social, NIF, dirección, contacto,
      recargo de equivalencia y notas; cliente con 3 facturas (una vencida sin cobrar, una
      cobrada, una parcialmente cobrada) → el resumen financiero calculado coincide con la suma
      manual esperada de `totalCobrable()`/`saldoPendiente()` de esas facturas.

### Implementation for User Story 1

- [X] T012 [US1] Completar la pestaña "Datos generales" en `resources/views/clientes/show.blade.php`
      con nombre/razón social, tipo, NIF, dirección completa, email, teléfono, recargo de
      equivalencia y notas (depende de T007/T008).
- [X] T013 [US1] Implementar el cálculo del resumen financiero en
      `app/Http/Controllers/ClienteController.php::show()`: total facturado, pendiente de cobro,
      facturas vencidas (cantidad + importe) y ticket medio, a partir de `$cliente->facturas`
      (excluyendo `tipo = simplificada`, mismo criterio que `FacturaController::index`), usando
      `totalCobrable()`, `saldoPendiente()` ya existentes en `Factura` (ver data-model.md).
- [X] T014 [US1] Añadir la pestaña "Resumen financiero" en
      `resources/views/clientes/show.blade.php`, con estado vacío ("Sin facturas registradas")
      cuando el cliente no tenga facturas (depende de T013).
- [X] T015 [US1] Añadir el ítem "Ver perfil" al dropdown de acciones en
      `public/js/plugins-init/clientes-datatable.init.js::renderAcciones()`, como primer ítem del
      menú, enlazando a `route('clientes.show', row.id)` (agregar `perfil_url` al payload JSON de
      `ClienteController::index()` en `app/Http/Controllers/ClienteController.php`, igual que ya
      existen `update_url`/`delete_url`).

**Checkpoint**: User Story 1 funcional de forma independiente — perfil navegable con datos
generales + resumen financiero correctos y aislados por tenant/permiso.

---

## Phase 4: User Story 2 - Consultar el historial documental del cliente (Priority: P2)

**Goal**: Pestañas de facturas, presupuestos y albaranes del cliente, paginadas, con acceso al
detalle de cada documento.

**Independent Test**: Abrir el perfil de un cliente con facturas, presupuestos y albaranes
previos, y verificar que cada pestaña lista únicamente los documentos de ese cliente, con enlace
a cada uno, y que un módulo sin permiso oculta su pestaña.

### Tests for User Story 2 ⚠️

- [X] T016 [P] [US2] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: cliente con 2 facturas,
      2 presupuestos y 2 albaranes de un tenant, y 1 factura/presupuesto/albarán de otro cliente
      del mismo tenant → cada pestaña del perfil solo lista los documentos del cliente correcto
      (no fuga cruzada entre clientes del mismo tenant).
- [X] T017 [P] [US2] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: usuario sin
      `ver-facturas` → la respuesta del perfil no incluye datos de facturación (pestaña ausente/
      sin datos), aunque sí tenga `ver-clientes`.

### Implementation for User Story 2

- [X] T018 [US2] En `ClienteController::show()`, cuando `Auth::user()->can('ver-facturas')`,
      construir `$cliente->facturas()->where('tipo', '!=', TipoFactura::Simplificada->value)->orderByDesc('fecha_expedicion')->paginate(15, ['*'], 'facturas_page')`
      y pasarlo a la vista; `null` si no tiene permiso.
- [X] T019 [US2] En `ClienteController::show()`, cuando `Auth::user()->can('ver-presupuestos')`,
      construir `$cliente->presupuestos()->orderByDesc('fecha_emision')->paginate(15, ['*'], 'presupuestos_page')`
      y pasarlo a la vista; `null` si no tiene permiso.
- [X] T020 [US2] En `ClienteController::show()`, cuando `Auth::user()->can('ver-albaranes')`,
      construir `$cliente->albaranes()->orderByDesc('fecha_entrega')->paginate(15, ['*'], 'albaranes_page')`
      y pasarlo a la vista; `null` si no tiene permiso.
- [X] T021 [US2] Añadir la pestaña "Facturas" en `resources/views/clientes/show.blade.php`
      (envuelta en `@can('ver-facturas')`), tabla con número/fecha/importe/estado de cobro,
      paginación y enlace a `route('facturas.pdf', $factura)` como detalle de cada factura
      (NO usar `facturas.edit`: `FacturaController::edit()` hace `abort(403)` para cualquier
      factura que no esté en estado `borrador` — el patrón ya usado en
      `facturas-datatable.init.js` para "Ver" es precisamente `pdf_url`), con estado vacío si no
      hay facturas.
- [X] T022 [US2] Añadir la pestaña "Presupuestos" en `resources/views/clientes/show.blade.php`
      (envuelta en `@can('ver-presupuestos')`), tabla con número/fecha/importe/estado, paginación
      y enlace a `route('presupuestos.pdf', $presupuesto)` como detalle de cada presupuesto (NO
      usar `presupuestos.edit`: `PresupuestoController::edit()` hace `abort(403)` cuando
      `! $presupuesto->estado->esEditable()`; tampoco existe una ruta `presupuestos.show`), con
      estado vacío si no hay presupuestos.
- [X] T023 [US2] Añadir la pestaña "Albaranes" en `resources/views/clientes/show.blade.php`
      (envuelta en `@can('ver-albaranes')`), tabla con número/fecha/importe/estado, paginación y
      enlace a `route('albaranes.show', $albaran)`, con estado vacío si no hay albaranes.

**Checkpoint**: User Stories 1 y 2 funcionando juntas — perfil con datos generales, financiero y
los tres listados documentales, cada uno respetando su propio permiso de módulo.

---

## Phase 5: User Story 3 - Ver oportunidades CRM y actividad reciente del cliente (Priority: P3)

**Goal**: Pestaña de oportunidades (pipeline) y pestaña de actividad con línea de tiempo
combinada de facturas/presupuestos/albaranes/oportunidades.

**Independent Test**: Abrir el perfil de un cliente con oportunidades abiertas y documentos de
distintos tipos, y comprobar que la pestaña de oportunidades muestra el pipeline correcto y que
la pestaña de actividad mezcla los cuatro tipos de evento en orden cronológico.

### Tests for User Story 3 ⚠️

- [X] T024 [P] [US3] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: cliente con
      oportunidades en distintas etapas → la pestaña de oportunidades del perfil lista etapa y
      valor estimado de cada una, solo las del cliente correcto.
- [X] T025 [P] [US3] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: cliente con eventos de
      los 4 tipos en fechas distintas → la línea de tiempo combinada del perfil los devuelve
      ordenados por fecha descendente, con el tipo correctamente identificado en cada uno.

### Implementation for User Story 3

- [X] T026 [US3] En `ClienteController::show()`, cuando `Auth::user()->can('ver-oportunidades')`,
      construir `$cliente->oportunidades()->orderByDesc('created_at')->paginate(15, ['*'], 'oportunidades_page')`
      y pasarlo a la vista; `null` si no tiene permiso.
- [X] T027 [US3] Añadir la pestaña "Oportunidades" en `resources/views/clientes/show.blade.php`
      (envuelta en `@can('ver-oportunidades')`), tabla con título/etapa/valor estimado,
      paginación y enlace a `route('oportunidades.show', $oportunidad)`, con estado vacío si no
      hay oportunidades.
- [X] T028 [US3] Implementar la construcción de la línea de tiempo combinada en
      `ClienteController::show()`: tomar hasta 10 elementos más recientes de cada colección ya
      cargada (facturas/presupuestos/albaranes/oportunidades para las que el usuario tiene
      permiso), normalizar a `{fecha, tipo, etiqueta, url}`, combinar, ordenar por fecha
      descendente y cortar a 10 (ver research.md §2 y data-model.md → Timeline).
- [X] T029 [US3] Añadir la pestaña "Actividad" en `resources/views/clientes/show.blade.php`, tabla
      simple (mismo patrón que la pestaña "Actividad" de `resources/views/profile/show.blade.php`)
      con fecha/tipo/etiqueta y enlace por fila, estado vacío ("Sin actividad registrada") si la
      línea de tiempo está vacía.

**Checkpoint**: User Stories 1, 2 y 3 funcionando juntas — perfil completo de lectura (todas las
pestañas de información).

---

## Phase 6: User Story 4 - Crear un documento nuevo directamente desde el perfil (Priority: P3)

**Goal**: Accesos rápidos en el perfil para crear factura/presupuesto/albarán/oportunidad
preseleccionando el cliente actual.

**Independent Test**: Abrir el perfil de un cliente y hacer clic en cada acceso rápido,
verificando que lleva al formulario de alta correspondiente con el cliente ya preseleccionado.

### Tests for User Story 4 ⚠️

- [X] T030 [P] [US4] Test en `tests/Feature/FacturaControllerTest.php` (o el archivo de tests de
      facturas ya existente): `GET /facturas/crear?cliente_id={id}` con usuario con
      `ver-facturas-crear` → la vista recibe el cliente preseleccionado.
- [X] T031 [P] [US4] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: usuario sin
      `ver-facturas-crear` (pero con `ver-clientes`) → el perfil no incluye el acceso rápido
      "Nueva factura"; ídem para `ver-presupuestos`/`ver-albaranes`/`ver-oportunidades` y sus
      accesos rápidos respectivos.

- [X] T031b [P] [US4] Test en `tests/Feature/Cliente/PerfilClienteTest.php`: cliente eliminado
      (soft delete) entre que se renderiza el perfil y que se hace clic en un acceso rápido →
      `GET /facturas/crear?cliente_id={id-borrado}` (y equivalentes de presupuesto/albarán/
      oportunidad) no debe crear un documento huérfano ni romper con un error no controlado;
      debe fallar de forma explícita (404 o cliente no preseleccionado con aviso).

### Implementation for User Story 4

- [X] T032 [US4] Extender `FacturaController::create()` en
      `app/Http/Controllers/FacturaController.php` para leer `?cliente_id=` de la query string
      usando `Cliente::find($request->query('cliente_id'))` (NO `findOrFail`: mismo patrón ya
      usado en `PresupuestoController::create()`/`AlbaranController::create()`, que devuelven
      `null` en vez de abortar si el cliente no existe o fue eliminado — así T031b puede pasar
      sin necesitar manejo de error adicional) y pasar el cliente preseleccionado (o `null`) a la
      vista `facturas.create`.
- [X] T033 [US4] Añadir los 4 accesos rápidos en `resources/views/clientes/show.blade.php`:
      "Nueva factura" (`route('facturas.create', ['cliente_id' => $cliente->id])`, visible con
      `@can('ver-facturas-crear')`), "Nuevo presupuesto" (`route('presupuestos.create', ['cliente_id' => $cliente->id])`,
      `@can('ver-presupuestos')`), "Nuevo albarán" (`route('albaranes.create', ['cliente_id' => $cliente->id])`,
      `@can('ver-albaranes')`), "Nueva oportunidad" (`route('oportunidades.index', ['cliente_id' => $cliente->id])`,
      `@can('ver-oportunidades')`).

**Checkpoint**: Las 4 user stories completas — perfil de cliente de lectura + accesos rápidos de
creación, todo con permisos y aislamiento de tenant respetados.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Cierre del feature según las reglas transversales de documentación de CLAUDE.md.

- [X] T034 [P] Revisar `resources/views/ayuda/clientes.blade.php` (si existe guía in-app del
      listado de clientes) y añadir/actualizar la mención al botón "Ver perfil" y a las pestañas
      del perfil, si aplica.
- [X] T035 [P] Revisar `resources/ia/conocimiento/*.md` (base de conocimiento del asistente IA,
      feature 030) y actualizar el archivo del módulo de clientes (o crear uno si no existe) para
      que el asistente conozca la nueva pantalla de perfil y qué puede consultarse en ella.
- [X] T036 Ejecutar la validación manual de `quickstart.md` (los 5 escenarios) contra el entorno
      local antes de dar la feature por cerrada.
- [X] T037 Ejecutar la suite completa de tests (`php artisan test` o equivalente) y confirmar que
      no hay regresiones en `tests/Feature/*` existentes de clientes/facturas/presupuestos/
      albaranes/oportunidades.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sin dependencias.
- **Foundational (Phase 2)**: Depende de Setup. BLOQUEA todas las user stories.
- **User Story 1 (Phase 3)**: Depende solo de Foundational.
- **User Story 2 (Phase 4)**: Depende solo de Foundational (no de US1, aunque comparte archivo de
  vista y controller — ver nota de conflicto de archivo más abajo).
- **User Story 3 (Phase 5)**: Depende solo de Foundational (comparte archivo de vista/controller
  con US1/US2).
- **User Story 4 (Phase 6)**: Depende solo de Foundational; no depende de US1-US3 salvo que
  comparte la vista `clientes/show.blade.php` para insertar los accesos rápidos.
- **Polish (Phase 7)**: Depende de que las user stories que se vayan a entregar estén completas.

### User Story Dependencies

Las 4 user stories son conceptualmente independientes (cada una añade una sección/pestaña
distinta), pero **comparten dos archivos físicos** (`ClienteController::show()` y
`clientes/show.blade.php`), por lo que en la práctica conviene implementarlas en orden
secuencial (US1 → US2 → US3 → US4) para evitar conflictos de merge, aunque no exista una
dependencia funcional entre ellas.

### Parallel Opportunities

- T002-T005 (las 4 relaciones nuevas en `Cliente.php`) tocan el mismo archivo pero métodos
  distintos — marcadas `[P]` porque son ediciones independientes sin conflicto lógico entre sí,
  aunque si se ejecutan con herramientas de edición automática conviene aplicarlas en una sola
  pasada sobre el archivo.
- Los tests de cada user story (T009-T011, T016-T017, T024-T025, T030-T031) pueden escribirse en
  paralelo entre sí dentro de la misma fase, antes de la implementación de esa fase.
- T034 y T035 (documentación) son independientes entre sí y del resto de Polish.

---

## Implementation Strategy

### MVP First (User Story 1 únicamente)

1. Completar Phase 1: Setup.
2. Completar Phase 2: Foundational (bloqueante).
3. Completar Phase 3: User Story 1.
4. Validar de forma independiente (Escenario 1 parcial de `quickstart.md`, solo datos generales +
   financiero) y hacer demo si corresponde.

### Entrega incremental

1. Setup + Foundational → base lista.
2. US1 → perfil con datos generales + resumen financiero (MVP demostrable).
3. US2 → + facturas/presupuestos/albaranes.
4. US3 → + oportunidades + timeline de actividad.
5. US4 → + accesos rápidos de creación.
6. Polish → documentación (ayuda in-app, base de conocimiento IA) y validación final.

---

## Notes

- `[P]` = archivos distintos o ediciones independientes sin dependencia entre sí.
- La etiqueta `[Story]` mapea cada tarea a su user story para trazabilidad.
- Los tests de aislamiento multi-tenant y permisos (T009, T010, T016, T017, T024, T025, T031) se
  escriben antes que su implementación correspondiente, por el Principio IV de la constitución.
- Cerrar el feature exige repasar las 4 capas de documentación de CLAUDE.md — cubierto en T034
  (guía in-app) y T035 (base de conocimiento IA); no se anticipan cambios en `docs/00-vision.md`
  a `docs/03-modelo-datos.md` porque no se agregan tablas ni se cambia el modelo de datos
  persistido (solo relaciones Eloquent sobre columnas ya existentes).
