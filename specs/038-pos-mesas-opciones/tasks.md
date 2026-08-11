---

description: "Task list for 038-pos-mesas-opciones"
---

# Tasks: POS con mesas y opciones de artÃ­culo (hostelerÃ­a)

**Input**: Design documents from `/specs/038-pos-mesas-opciones/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/rutas.md](contracts/rutas.md), [quickstart.md](quickstart.md)

**Tests**: SÃ, obligatorios. El Principio IV de la constituciÃ³n es NON-NEGOTIABLE para aislamiento
multi-tenant, cÃ¡lculo de impuestos, numeraciÃ³n de series y encadenamiento Verifactu. Las tareas
marcadas **[TEST-FIRST]** se escriben antes que su implementaciÃ³n, deben **fallar primero**, y su
historia no se cierra hasta tenerlas en verde.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivos distintos, sin dependencias pendientes)
- **[Story]**: historia de usuario a la que pertenece (US1â€¦US7)
- **[TEST-FIRST]**: test que debe escribirse y fallar antes de implementar

## Reglas transversales (aplican a TODAS las tareas)

1. **Nunca** `migrate:fresh`, `migrate:refresh` ni `db:wipe`. Hay datos de demo con imÃ¡genes que
   ningÃºn factory puede recrear. Solo `php artisan migrate`.
2. **Nunca** route binding implÃ­cito en los controllers: resoluciÃ³n manual bajo `TenantScope`
   (memoria `project_tenant_route_binding`). Un binding implÃ­cito devuelve registros de otro tenant.
3. Toda tabla nueva lleva `tenant_id` indexado y su modelo extiende `BaseModel` (Principio I).
4. Todo importe se calcula en servidor (Principio III).
5. Toda DataTable nueva necesita el override CSS de paginaciÃ³n "Anterior/Siguiente"
   (`docs/04-front-guidelines.md`, memoria `feedback_datatable_pagination_css`), o los botones se
   renderizan como columnas verticales de letras.
6. Los avisos al usuario van por toastr (`window.showToast`), nunca alerts Bootstrap ad-hoc.

## Nota sobre el orden de las fases

Las fases siguen el **orden de entrega del plan**, que no coincide con el orden estricto de
prioridad del spec: **US6 (transferir/unir, P5) va antes que US5 (cobro dividido, P4)**. Es
deliberado y estÃ¡ justificado en [research.md](research.md) D9: el cobro dividido toca a la vez el
estado de la cuenta, el importe de la mesa, el aviso de tope, la anulaciÃ³n y las transferencias, y
construirlo antes de tener esas piezas estables obliga a depurar dos mÃ¡quinas de estado a la vez.

---

## Phase 1: Setup â€” partir `pos-form.js` (Tanda 0)

**Purpose**: dejar el JS del POS en un tamaÃ±o manejable **antes** de aÃ±adir nada. Esta fase no
aÃ±ade ninguna funcionalidad: si el usuario nota cualquier diferencia, es un bug.

**Regla**: no mezclar estas tareas con funcionalidad nueva en el mismo commit (research D8).

- [X] T001 Extraer la lÃ³gica de catÃ¡logo (bÃºsqueda, filtros de categorÃ­a, carrusel) de `public/js/pos-form.js` a `public/js/pos-catalogo.js`
- [X] T002 Extraer la lÃ³gica del ticket (lÃ­neas, cantidades, totales, tope) de `public/js/pos-form.js` a `public/js/pos-ticket.js`
- [X] T003 Extraer la lÃ³gica del modal de cobro (mÃ©todos, teclado, pagos, emisiÃ³n) de `public/js/pos-form.js` a `public/js/pos-cobro.js`
- [X] T004 Dejar `public/js/pos-form.js` como orquestador que expone el estado compartido y cablea los tres mÃ³dulos
- [X] T005 Registrar los archivos nuevos en el `@push('scripts')` de `resources/views/pos/create.blade.php`, respetando el orden de dependencias
- [ ] T006 VerificaciÃ³n manual completa del POS actual sin cambios de comportamiento: buscar, filtrar, aÃ±adir, cambiar cantidad, borrar, vaciar, receptor, pago simple, pago dividido, tope superado, emitir, imprimir, ver PDF

**Checkpoint**: el POS funciona exactamente igual que antes. No seguir si algo cambiÃ³.

---

## Phase 2: Foundational â€” infraestructura del mÃ³dulo (bloqueante)

**Purpose**: los cimientos que TODAS las historias necesitan: flags por tenant, middleware de
mÃ³dulo y permisos. Sin esto no se puede empezar ninguna historia.

**âš ï¸ BLOQUEANTE: ninguna historia puede empezar hasta completar esta fase.**

- [X] T007 Crear `app/Support/ConfigPos.php` clonando el patrÃ³n de `app/Support/ConfigFichajes.php`, con las claves y defaults de [research.md](research.md) D1 (`pos.hosteleria_activo`, `pos.opciones_activo`, `pos.cobro_dividido_activo`, `pos.suplemento_zona_activo`, `pos.mesa_olvidada_min`=45)
- [X] T008 [TEST-FIRST] Test en `tests/Feature/Pos/ConfigPosTest.php`: un tenant sin ninguna fila en `configuraciones` tiene el mÃ³dulo apagado y `mesa_olvidada_min` = 45 (FR-002, sin migraciÃ³n de datos)
- [X] T009 Crear `app/Http/Middleware/ModuloHosteleriaActivo.php` siguiendo el patrÃ³n de `app/Http/Middleware/BloquearSuperAdminAreaTenant.php`; registrar el alias en `bootstrap/app.php`
- [X] T010 [TEST-FIRST] Test en `tests/Feature/Pos/ModuloApagadoTest.php`: con el mÃ³dulo apagado, las rutas nuevas responden 403/404 **aunque el usuario tenga el permiso** (FR-003) â€” debe fallar antes de existir las rutas
- [X] T011 AÃ±adir `ver-pos-sala` ("Sala") y `ver-pos-opciones` ("Opciones de artÃ­culo"), mÃ³dulo `POS`, a `App\Support\CatalogoPermisos::PERMISOS` (paso 1 del procedimiento obligatorio)
- [ ] T012 Ejecutar `php artisan db:seed --class=PermisosSeeder` en local y anotarlo como paso de despliegue (paso 2 del procedimiento)
- [X] T013 AÃ±adir las entradas `pos-sala` y `pos-opciones` como hijos del grupo `pos` en `App\Support\CatalogoMenu::CATALOGO`, **sin tocar `partials/sidebar.blade.php`** (paso 3 del procedimiento)
- [X] T014 Hacer que el menÃº oculte esas entradas cuando el mÃ³dulo estÃ¡ apagado, ademÃ¡s del filtro por permiso ya existente (FR-056)
- [X] T015 Actualizar la contabilidad de tests: `tests/Feature/CatalogoPermisosTest.php` (`claves()` y `clavesUsuarioBase()` suben 2) y `tests/Feature/RutasPermisosTest.php` (`mapaRutas()` con las rutas nuevas)

**Checkpoint**: infraestructura lista; el mÃ³dulo existe, estÃ¡ apagado y nada del POS cambiÃ³.

---

## Phase 3: US1 â€” Activar y configurar el mÃ³dulo (P1) ðŸŽ¯ MVP

**Goal**: un tenant puede activar el mÃ³dulo y configurar zonas, mesas y capacidades desde
ConfiguraciÃ³n â†’ POS; un tenant que no lo activa no nota absolutamente nada.

**Independent Test**: con el mÃ³dulo apagado recorrer el POS actual completo sin encontrar
diferencias; activarlo y ver aparecer las capacidades nuevas.

### Tests (TEST-FIRST)

- [X] T016 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/PosApagadoNoCambiaNadaTest.php`: con el mÃ³dulo apagado, `GET /pos/crear` no expone contexto de mesa ni opciones y `POST /pos` emite igual que antes (SC-012, FR-021)
- [X] T017 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/ConfiguracionPosTest.php`: no se puede apagar el mÃ³dulo con cuentas abiertas; responde 422 indicando cuÃ¡ntas hay (FR-006)
- [X] T018 [P] [US1] [TEST-FIRST] `tests/Feature/Pos/AislamientoConfigPosTest.php`: con 2 tenants, la configuraciÃ³n POS de A no es visible ni modificable desde B (Principio I)
- [X] T018b [P] [US1] [TEST-FIRST] `tests/Feature/Pos/CapacidadDesactivadaConservaDatosTest.php`: apagar el mÃ³dulo o una capacidad concreta **no borra** zonas, mesas, grupos ni opciones, y reactivarla los recupera intactos (FR-007, edge case del spec)

### ImplementaciÃ³n

- [X] T019 [US1] Crear `app/Http/Controllers/Configuracion/PosConfiguracionController.php` con `update`, validando el bloqueo por cuentas abiertas (FR-006) y devolviendo 422 con mensaje legible
- [X] T020 [US1] Registrar las rutas `configuracion.pos.*` en `routes/web.php` con `can:ver-configuracion` y **sin** el middleware de mÃ³dulo activo (si lo llevaran, apagar el mÃ³dulo dejarÃ­a al administrador sin poder reactivarlo â€” ver [contracts/rutas.md](contracts/rutas.md))
- [X] T021 [US1] Crear `resources/views/configuracion/_tab_pos.blade.php` tomando `_tab_fichajes.blade.php` como referencia (flags + umbrales); aplicar `docs/04-front-guidelines.md` Â§ "TamaÃ±o de formularios: todo sm por defecto"
- [X] T022 [US1] Registrar la pestaÃ±a "POS" en `resources/views/configuracion/index.blade.php`
- [X] T023 [US1] Crear `public/js/plugins-init/configuracion-pos.init.js`: guardado AJAX, `window.withButtonLoading` en el botÃ³n (Â§ "Estado de carga en botones") y toastr para el resultado (Â§ "Notificaciones")

**Checkpoint**: US1 entregable y verificable por sÃ­ sola.

---

## Phase 4: US2 â€” Atender varias mesas a la vez (P1)

**Goal**: mantener varias cuentas abiertas por mesa, guardarlas, recuperarlas y cobrarlas enteras.

**Independent Test**: abrir dos cuentas simultÃ¡neas con una sola zona, guardarlas, recuperarlas y
cobrar una; sin opciones ni transferencias.

**Nota de esquema**: las migraciones de esta fase crean **ya** `cantidad_saldada`, `pos_cobros` y
`pos_cobro_lineas`, aunque no se usen hasta la Phase 8 (research D9: aÃ±adirlas despuÃ©s obligarÃ­a a
migrar datos reales y revalidar la numeraciÃ³n).

### Migraciones y modelos

- [X] T024 [P] [US2] MigraciÃ³n `pos_zonas` segÃºn [data-model.md](data-model.md) (nombre Ãºnico por tenant, `suplemento_porcentaje` default 0, `orden`, softDeletes)
- [X] T025 [P] [US2] MigraciÃ³n `pos_mesas` (FK a `pos_zonas`, nombre Ãºnico por `(tenant_id, zona_id)`, `orden`, softDeletes)
- [X] T026 [US2] MigraciÃ³n `pos_cuentas` **sin `numero` ni `serie_id`** (garantÃ­a estructural de FR-017), con `estado`, `abierta_por`, `abierta_en`, `version` y campos de receptor
- [X] T027 [US2] MigraciÃ³n `pos_cuenta_lineas` **incluyendo `cantidad_saldada` desde ahora**, con `concepto`/`precio_unitario`/`tipo_impositivo` congelados y `articulo_id` nullable
- [X] T028 [P] [US2] Migraciones `pos_cobros` y `pos_cobro_lineas` (se crean ahora, se usan en la Phase 8)
- [X] T029 [P] [US2] Ãndices de [data-model.md](data-model.md): `pos_cuentas(tenant_id, estado, mesa_id)`, `pos_cuenta_lineas(cuenta_id, orden)`, `pos_cobros(tenant_id, cuenta_id)`
- [X] T030 [P] [US2] Modelos `PosZona`, `PosMesa`, `PosCuenta`, `PosCuentaLinea`, `PosCobro`, `PosCobroLinea` en `app/Models/`, todos sobre `BaseModel` (TenantScope), con relaciones y casts
- [X] T031 [P] [US2] Factories de los modelos nuevos, **forzando el `tenant_id` activo** en vez de `Tenant::factory()` (memoria `project_factory_tenant_id_pitfall`: genera tenants fantasma)

### Tests (TEST-FIRST)

- [X] T032 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/AislamientoMesasTest.php`: con 2 tenants, ninguna zona, mesa ni cuenta de A es accesible desde B, tampoco por id directo (Principio I, FR-054)
- [X] T033 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/NumeracionSinHuecosTest.php`: abrir y anular N cuentas no consume numeraciÃ³n; los tickets emitidos despuÃ©s siguen correlativos y sin huecos (Principio II, SC-008)
- [X] T034 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/CuentaNoEsTicketTest.php`: una cuenta abierta no aparece en el listado de tickets ni genera registro Verifactu (FR-017)
- [X] T035 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/ConcurrenciaCuentaTest.php`: guardar con `version` obsoleta devuelve 409 y no pisa los cambios del otro usuario (FR-024, research D7)
- [X] T036 [P] [US2] [TEST-FIRST] `tests/Feature/Pos/TopeCuentaTest.php`: el aviso de tope de la simplificada se emite sobre el importe **pendiente**, antes de intentar cobrar (FR-025)

### Servicios y endpoints

- [X] T037 [US2] Crear `app/Services/CobradorCuenta.php`: traduce unidades pendientes al array `lineas[]` que `RegistroTicket::registrar()` ya espera e invoca ese servicio **sin modificarlo** (research D3). Todo dentro de una transacciÃ³n
- [X] T038 [US2] Crear `app/Http/Controllers/Pos/CuentaController.php` (`store`, `show`, `update`, `anular`, `cobrar`) con **resoluciÃ³n manual** de la cuenta bajo `TenantScope`
- [X] T039 [US2] FormRequests de cuenta y cobro en `app/Http/Requests/`, validando `version` y la pertenencia de cada lÃ­nea a la cuenta
- [X] T040 [US2] Crear `app/Http/Controllers/Pos/SalaController.php` devolviendo el JSON de [contracts/rutas.md](contracts/rutas.md) en **una sola consulta** con `withSum`/`withCount`, sin N+1 (SC-001: < 2 s con 20 cuentas)
- [X] T041 [US2] Registrar las rutas de sala y cuentas en `routes/web.php` con `can:ver-pos-sala` + `modulo.hosteleria`
- [X] T042 [US2] Modificar `app/Http/Controllers/PosController.php`: `create()` acepta `?cuenta={id}` y precarga la cuenta; `store()` **sin cambios** (garantÃ­a de SC-012)

### Zonas y mesas (CRUD en ConfiguraciÃ³n)

- [X] T043 [P] [US2] Controllers `Configuracion/PosZonaController.php` y `PosMesaController.php` (`index`, `store`, `update`, `destroy`), con `destroy` devolviendo **422 con mensaje legible** ante dependencias en vez de dejar reventar la FK (FR-015)
- [X] T044 [US2] SecciÃ³n de zonas y mesas en `resources/views/configuracion/_tab_pos.blade.php`, aplicando Â§ "Listados: SIEMPRE DataTable" + Â§ "CRUD simple: alta/ediciÃ³n en modal + AJAX" (un solo modal para alta y ediciÃ³n, datos en `data-*`, sin rutas `create`/`edit`)
- [X] T045 [US2] Aplicar el override CSS de paginaciÃ³n "Anterior/Siguiente" a las DataTables de zonas y mesas (Â§ "DataTable: botones Anterior/Siguiente verticales")
- [X] T046 [US2] ConfirmaciÃ³n antes de eliminar zona o mesa, aplicando Â§ "ConfirmaciÃ³n de acciones irreversibles (borrado y otras)"

### Vista de Sala

- [X] T047 [US2] Crear `resources/views/pos/sala.blade.php`: pestaÃ±as de zona **reutilizando `.pos-filtro`** del catÃ¡logo del POS (Â§ "CatÃ¡logo del POS (TPV)": 52px, badge de conteo, activo con el primario del tenant) â€” no diseÃ±ar un selector nuevo
- [X] T048 [US2] Grid de tarjetas de mesa: libre (borde gris), ocupada (borde verde con importe pendiente y minutos), olvidada (borde Ã¡mbar). El servidor decide `olvidada`; la vista no hace aritmÃ©tica de fechas
- [X] T049 [US2] Crear `public/js/plugins-init/pos-sala.init.js`: carga del JSON, filtro por zona client-side y navegaciÃ³n a la cuenta
- [X] T050 [US2] Crear `public/js/pos-cuenta.js`: guardar, recuperar, anular y manejo del **409** (avisar y recargar, nunca reintentar en silencio)
- [X] T050b [US2] ConfirmaciÃ³n explÃ­cita antes de anular una cuenta, aplicando Â§ "ConfirmaciÃ³n de acciones irreversibles (borrado y otras)" â€” FR-020 lo exige y ninguna otra tarea lo cubrÃ­a
- [X] T051 [US2] Adaptar `resources/views/pos/create.blade.php`: chip de contexto de mesa en el `card-header` del ticket (FR-060) y conversiÃ³n del slot central de la botonera en mini-grid (Cliente / Guardar / Aparcadas), **conservando las tres franjas y la altura tÃ¡ctil** (FR-059)
- [X] T052 [US2] Verificar que la geometrÃ­a sticky de las dos columnas sigue intacta en tablet apaisada (SC-011); el bloque CSS comentado de la vista **no se toca**

**Checkpoint**: ya es un TPV de bar usable. US1 + US2 son un entregable real.

---

## Phase 5: US3 â€” Definir las opciones de los platos (P2)

**Goal**: gestionar grupos y opciones reutilizables y asignarlos a artÃ­culos con precio propio.

**Independent Test**: crear grupos y opciones, asignarlos a un artÃ­culo y comprobar que el precio
del pivot es independiente del precio por defecto de la opciÃ³n.

- [X] T053 [P] [US3] Migraciones `pos_opcion_grupos` y `pos_opciones` segÃºn [data-model.md](data-model.md), con `articulo_vinculado_id` nullable
- [X] T054 [P] [US3] Migraciones de los pivots `pos_articulo_grupo` y `pos_articulo_opcion`, este Ãºltimo con **`precio` propio** (FR-039) e Ã­ndice por `articulo_id`
- [X] T055 [P] [US3] Modelos `PosOpcionGrupo` y `PosOpcion` sobre `BaseModel`, con las relaciones a `Articulo`
- [X] T056 [P] [US3] Factories de grupos y opciones forzando el `tenant_id` activo
- [X] T057 [P] [US3] [TEST-FIRST] `tests/Feature/Pos/AislamientoOpcionesTest.php`: con 2 tenants, ni grupos ni opciones de A son accesibles desde B
- [X] T058 [P] [US3] [TEST-FIRST] `tests/Feature/Pos/PrecioOpcionPorArticuloTest.php`: cambiar el precio de una opciÃ³n en un artÃ­culo no altera su `precio_defecto` ni el precio en otros artÃ­culos (FR-039)
- [X] T059 [P] [US3] [TEST-FIRST] `tests/Feature/Pos/ReglasGrupoTest.php`: un grupo obligatorio sin opciones se rechaza; `min â‰¤ max` se valida (FR-037)
- [X] T060 [US3] Controllers `Pos/OpcionController.php` y `Pos/OpcionGrupoController.php` (`index`, `store`, `update`, `destroy`), con `destroy` respondiendo 422 e indicando en cuÃ¡ntos artÃ­culos se usa (FR-047)
- [X] T061 [US3] Controller `Pos/ArticuloOpcionController.php` con `index` y `sync` por artÃ­culo (el sub-listado no cabe en `data-*`, va por endpoint aparte â€” Â§ "Sub-listado editable embebido en un modal de ediciÃ³n")
- [X] T062 [US3] Rutas de opciones y grupos con `can:ver-pos-opciones` + `modulo.hosteleria`
- [X] T063 [US3] Crear `resources/views/pos/opciones/index.blade.php` con DataTable de grupos y opciones (Â§ "Listados: SIEMPRE DataTable", Â§ "Cabecera de las DataTables en color de marca", Â§ "Columna Acciones de los listados")
- [X] T064 [US3] Alta/ediciÃ³n en un Ãºnico modal reutilizado (Â§ "CRUD simple: alta/ediciÃ³n en modal + AJAX"); el `_form.blade.php` sin `value`/`old()`/`@error`
- [X] T065 [US3] Aplicar el override CSS de paginaciÃ³n a la DataTable de opciones
- [X] T066 [US3] Crear el componente de asignaciÃ³n de opciones a un artÃ­culo clonando `<x-categoria-select>` de punta a punta (Â§ "Select dinÃ¡mico con CRUD inline"), **incluido su CSS con el scope renombrado** â€” reutilizar el CSS de otro componente rompe el layout sin error en consola
- [X] T067 [US3] Integrar ese componente en `resources/views/articulos/index.blade.php` con lista de asignadas reordenable y precio editable por fila

**Checkpoint**: el recetario de opciones queda configurable, aunque todavÃ­a no se venda con Ã©l.

---

## Phase 6: US4 â€” Vender un plato con sus opciones (P3)

**Goal**: al tocar un artÃ­culo con opciones se elige la configuraciÃ³n y el precio la refleja; un
artÃ­culo sin opciones se aÃ±ade en un toque, igual que hoy.

**Independent Test**: vender en el mismo ticket un artÃ­culo con opciones obligatorias y otro sin
opciones, comprobando importe y detalle de lÃ­nea.

- [X] T068 [P] [US4] MigraciÃ³n `pos_cuenta_linea_opciones` con `nombre`, `precio` y `articulo_vinculado_id` **congelados** al aÃ±adir (sobreviven al borrado de la opciÃ³n)
- [X] T069 [P] [US4] Modelo `PosCuentaLineaOpcion` sobre `BaseModel`
- [X] T070 [P] [US4] [TEST-FIRST] `tests/Feature/Pos/VentaConOpcionesTest.php`: los suplementos elegidos se suman al importe **en servidor**; el cliente solo envÃ­a quÃ© eligiÃ³ (Principio III, FR-048)
- [X] T071 [P] [US4] [TEST-FIRST] `tests/Feature/Pos/ReglasGrupoAlVenderTest.php`: no se puede confirmar una lÃ­nea sin cumplir un grupo obligatorio (FR-042, SC-006)
- [X] T072 [P] [US4] [TEST-FIRST] `tests/Feature/Pos/StockOpcionVinculadaTest.php`: la opciÃ³n con artÃ­culo vinculado descuenta stock **sin** generar lÃ­nea propia en el documento (FR-052, research D5); si el artÃ­culo no gestiona stock, no se registra movimiento
- [X] T073 [US4] Registrar el movimiento de stock del artÃ­culo vinculado tras emitir, dentro de la misma transacciÃ³n, respetando que `movimientos_stock` es **append-only** (constituciÃ³n)
- [X] T074 [US4] AÃ±adir `tiene_opciones` a cada artÃ­culo del catÃ¡logo en `PosController@create`, resuelto con el Ã­ndice `pos_articulo_opcion(articulo_id)` y **sin consulta por toque** (SC-004); siempre `false` con la capacidad apagada
- [X] T075 [US4] Crear `public/js/pos-opciones.js`: modal de selecciÃ³n, validaciÃ³n de reglas de grupo en cliente (el servidor revalida), cÃ¡lculo del importe de lÃ­nea y ediciÃ³n de opciones de una lÃ­nea ya aÃ±adida (FR-046)
- [X] T076 [US4] Modal de selecciÃ³n de opciones en `resources/views/pos/create.blade.php` reutilizando la familia visual de `.pos-metodo` (tarjetas grandes tÃ¡ctiles) y Â§ "Modales: siempre centrados verticalmente"
- [X] T077 [US4] Mostrar las opciones bajo el nombre en la lÃ­nea del ticket, en el `small` gris ya previsto en el CSS (FR-044)
- [X] T078 [US4] Tratar como lÃ­neas separadas dos unidades del mismo artÃ­culo con selecciones distintas (FR-045)
- [X] T079 [US4] Reflejar las opciones en el documento emitido (`facturas/ticket-80mm.blade.php` y `facturas/pdf.blade.php`) como detalle bajo la lÃ­nea, sin lÃ­nea propia

**Checkpoint**: comandas correctas y suplementos cobrados.

---

## Phase 7: US6 â€” Mover y juntar cuentas (P5)

**Goal**: transferir una cuenta a otra mesa y unir dos cuentas.

**Independent Test**: con dos cuentas abiertas, transferir una y unir dos, comprobando importes y
estados de mesa resultantes.

- [X] T080 [P] [US6] [TEST-FIRST] `tests/Feature/Pos/TransferirUnirTest.php`: transferir libera la mesa origen; unir conserva todas las lÃ­neas y suma importes; transferir a mesa ocupada ofrece unir y **nunca pierde consumo** (FR-034/035/036)
- [X] T081 [US6] Crear `app/Services/TransferidorCuenta.php` con `transferir()` y `unir()`, en transacciÃ³n, incrementando `version` en ambas cuentas
- [X] T082 [US6] Endpoints `transferir` y `unir` en `Pos/CuentaController.php` con resoluciÃ³n manual de ambas cuentas
- [X] T083 [US6] Registrar autor y momento de cada transferencia y uniÃ³n (FR-023)
- [X] T084 [US6] UI de transferir/unir desde el chip de contexto de mesa, con confirmaciÃ³n (Â§ "ConfirmaciÃ³n de acciones irreversibles") y toastr de resultado

**Checkpoint**: operativa diaria de bar cubierta.

---

## Phase 8: US5 â€” Cobrar la cuenta por partes (P4)

**Goal**: cobrar una cuenta seleccionando quÃ© lÃ­neas se pagan, emitiendo un documento por cobro y
manteniendo la mesa ocupada hasta saldar todo.

**âš ï¸ Depende de que la Phase 4 (US2) estÃ© completa y en verde.** Es el bloque mÃ¡s caro y el corte
natural si hubiera que recortar por tiempo: se pospone sin tocar ni una migraciÃ³n.

**Independent Test**: con una cuenta de 4 lÃ­neas, cobrar 2 en un ticket y 2 en otro, comprobando
importes, numeraciÃ³n y estado final de la mesa.

### Tests (TEST-FIRST)

- [X] T085 [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialInvarianteTest.php`: para cada lÃ­nea, la suma de `pos_cobro_lineas.cantidad` iguala `cantidad_saldada`; es imposible cobrar dos veces la misma unidad (FR-029, FR-033)
- [X] T086 [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialNumeracionTest.php`: N cobros parciales producen N documentos correlativos, sin huecos, cada uno inmutable (Principio II, FR-027)
- [X] T087 [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialCierreTest.php`: la mesa sigue ocupada mostrando el **pendiente**; al saldar la Ãºltima unidad la cuenta se cierra y la mesa se libera (FR-028, FR-031)
- [X] T088 [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialLimitesTest.php`: pedir mÃ¡s unidades de las pendientes â†’ 422; selecciÃ³n vacÃ­a en cobro parcial explÃ­cito â†’ 422; capacidad apagada â†’ 422
- [X] T089 [P] [US5] [TEST-FIRST] `tests/Feature/Pos/TransferirParcialmenteCobradaTest.php`: transferir o unir una cuenta parcialmente cobrada mueve **solo lo pendiente** (FR-032)
- [X] T089b [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialEncadenamientoVerifactuTest.php`: varios cobros parciales seguidos producen una **cadena Verifactu Ã­ntegra** â€” cada registro apunta a la huella del anterior, sin roturas ni saltos, igual que una secuencia de tickets normales. Ãrea NON-NEGOTIABLE del Principio IV que ninguna otra tarea cubrÃ­a: los cobros parciales son el Ãºnico caso de esta feature que genera varias facturas desde un mismo origen
- [X] T089c [P] [US5] [TEST-FIRST] `tests/Feature/Pos/CobroParcialTopeTest.php`: el tope legal de la simplificada se comprueba sobre el importe de **ese** cobro, no sobre el total de la cuenta (edge case del spec)

### ImplementaciÃ³n

- [X] T090 [US5] Extender `CobradorCuenta` para aceptar una selecciÃ³n de unidades por lÃ­nea, actualizar `cantidad_saldada` y crear `pos_cobros` + `pos_cobro_lineas`, todo en la misma transacciÃ³n que la emisiÃ³n
- [X] T091 [US5] Permitir repartir las unidades de una lÃ­nea de cantidad > 1 entre cobros distintos (FR-030)
- [X] T092 [US5] Cerrar la cuenta y liberar la mesa al saldarse la Ãºltima unidad; no emitir documento si el pendiente es cero (edge case)
- [X] T093 [US5] Restringir la anulaciÃ³n de una cuenta parcialmente cobrada a lo pendiente; lo emitido solo se corrige por rectificativa (edge case, Principio II)
- [X] T094 [US5] UI de selecciÃ³n de lÃ­neas a cobrar en el modal de cobro, distinguiendo visualmente lo saldado de lo pendiente (FR-029)
- [X] T095 [US5] Mostrar el pendiente restante tras cada cobro parcial y decidir con `cuenta_cerrada` si volver a Sala o seguir en la cuenta

**Checkpoint**: el caso real de restaurante queda cubierto.

---

## Phase 9: US7 â€” Cobrar distinto segÃºn la zona (P6)

**Goal**: una zona puede llevar un suplemento configurable que se aplica al cobrar.

**Independent Test**: configurar una zona con suplemento y otra sin Ã©l, cobrar el mismo consumo en
cada una y comparar importes y desglose impositivo.

- [X] T096 [P] [US7] [TEST-FIRST] `tests/Feature/Pos/SuplementoZonaTiposMixtosTest.php`: ticket con **dos tipos impositivos distintos** y zona con suplemento; el desglose por tipo suma exactamente el total (Principio III/IV, research D4). Es el test que justifica la decisiÃ³n de aplicar el suplemento por lÃ­nea
- [X] T097 [P] [US7] [TEST-FIRST] `tests/Feature/Pos/SuplementoZonaRegimenTest.php`: el cÃ¡lculo es correcto bajo IVA, IGIC e IPSI; **nada asume IVA** (Principio II)
- [X] T098 [P] [US7] [TEST-FIRST] `tests/Feature/Pos/SuplementoZonaVigenciaTest.php`: se aplica el valor vigente al cobrar y el de la zona donde se cobra, incluso si la cuenta se transfiriÃ³ desde otra zona (FR-051)
- [X] T099 [US7] Aplicar el suplemento en `CobradorCuenta` multiplicando el `precio_unitario` de cada lÃ­nea antes de pasar a `RegistroTicket`, redondeando **por lÃ­nea** para que el total impreso sea la suma exacta de los importes impresos
- [X] T100 [US7] Congelar el porcentaje aplicado en `pos_cobros.zona_suplemento_aplicado`
- [X] T101 [US7] Campo de suplemento por zona en la pestaÃ±a de configuraciÃ³n, visible solo con la capacidad activada
- [X] T102 [US7] Mostrar el suplemento vigente en el contexto de la cuenta y en el `.pos-foot` del ticket, para que no sea un aumento silencioso (escenario 2 de US7)

**Checkpoint**: feature funcionalmente completa.

---

## Phase 10: Pulido y cierre

### Mejoras de cobro aportadas por el usuario

- [X] T103 [P] Campo "Entregado" y cÃ¡lculo de "Devolver" (cambio) en el modal de cobro en efectivo, sobre el teclado numÃ©rico ya existente (FR-063). Es ayuda de caja: **no** altera el importe cobrado ni figura en el documento
- [X] T104 [P] Mostrar mesa y zona en la cabecera del modal de cobro, para no cobrar la mesa equivocada (FR-064)

### VerificaciÃ³n

- [ ] T105 Recorrer la guÃ­a completa de [quickstart.md](quickstart.md), incluida la secciÃ³n 1 ("el mÃ³dulo apagado no cambia nada")
- [X] T106 Verificar SC-001 con 20 cuentas abiertas: la Sala se pinta en menos de 2 s, sin N+1
- [ ] T107 Verificar SC-004 en tablet: un artÃ­culo sin opciones se aÃ±ade en 1 toque
- [ ] T107b Verificar SC-003: cobrar una cuenta de mesa cuesta los mismos toques que cobrar una venta directa hoy â€” sin penalizaciÃ³n por usar mesas
- [ ] T107c Verificar SC-013: partiendo de un tenant con el mÃ³dulo reciÃ©n activado, dejar la sala operativa (zonas y mesas creadas) en menos de 10 minutos sin ayuda externa
- [X] T108 Seeder de demo idempotente (`firstOrCreate`) con zonas, mesas y opciones de ejemplo, **forzando el `tenant_id` activo**

### DocumentaciÃ³n â€” las 4 capas (obligatorio, `CLAUDE.md`)

- [X] T109 [P] `docs/03-modelo-datos.md`: las 10 tablas nuevas, sus relaciones y el **motivo del prefijo `pos_`** (research D2), para que la inconsistencia con el resto del esquema no se lea como descuido
- [X] T110 [P] `docs/01-arquitectura.md`: el patrÃ³n de mÃ³dulo opcional por tenant (flags en `configuraciones` + middleware), por ser una decisiÃ³n tÃ©cnica reutilizable
- [X] T111 [P] `docs/04-front-guidelines.md`: tarjeta de mesa y estados, modal de selecciÃ³n de opciones, mini-grid de la botonera del POS, y la particiÃ³n de `pos-form.js` en mÃ³dulos
- [X] T112 [P] `resources/views/ayuda/pos-sala.blade.php` (nueva) siguiendo la convenciÃ³n de markup: intro, `<ol>` de pasos, `<p class="ayuda-nota">` con el error comÃºn
- [X] T113 [P] `resources/views/ayuda/pos-opciones.blade.php` (nueva), misma convenciÃ³n
- [X] T114 [P] Actualizar `resources/views/ayuda/pos-crear.blade.php`: mesas, opciones y cobro por partes. Una guÃ­a desactualizada le miente al usuario
- [X] T115 [P] Actualizar `resources/ia/conocimiento/pos.md` y aÃ±adir los archivos que falten (FR-058); un archivo por mÃ³dulo funcional, sin tocar el resto (invariante SC-007 de la feature 030)
- [X] T116 Anotar en la guÃ­a de despliegue que hay que ejecutar `php artisan migrate` y `php artisan db:seed --class=PermisosSeeder`, y que **solo el rol "Administrador"** recibe los permisos nuevos automÃ¡ticamente (paso 5 del procedimiento: el resto es opt-in desde `/roles`)

---

## Dependencies

```
Phase 1 (partir JS)  â”€â”€> Phase 2 (infraestructura)  â”€â”€> Phase 3 (US1)
                                                            â”‚
                                                            â–¼
                                                       Phase 4 (US2) â—„â”€â”€ nÃºcleo
                                                            â”‚
                            â”Œâ”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¬â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”¼â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”
                            â–¼               â–¼               â–¼               â–¼
                      Phase 5 (US3)   Phase 7 (US6)   Phase 8 (US5)   Phase 9 (US7)
                            â”‚
                            â–¼
                      Phase 6 (US4)
```

- **US3 â†’ US4**: no se puede vender con opciones sin haberlas definido.
- **US2 â†’ US5, US6, US7**: las tres operan sobre cuentas abiertas.
- **US5, US6, US7 son independientes entre sÃ­** salvo T089 (transferir una cuenta parcialmente
  cobrada), que necesita US5 y US6.

## Parallel opportunities

- **Phase 2**: T011 y T013 (catÃ¡logos distintos) tras T009.
- **Phase 4**: T024, T025, T028, T029, T030, T031 en paralelo; los cinco tests T032â€“T036 en paralelo.
- **Phase 5**: T053â€“T059 casi todo en paralelo.
- **Phase 8 y 9**: los tests T085â€“T089 y T096â€“T098 en paralelo dentro de cada fase.
- **Phase 10**: T109â€“T115 en paralelo (archivos distintos).
- **Entre fases**: US3+US4 (opciones) y US6 (transferir) pueden avanzar en paralelo por personas
  distintas una vez cerrada la Phase 4.

## Implementation Strategy

**MVP = Phase 1 + Phase 2 + Phase 3 + Phase 4.** Con eso el POS ya es un TPV de bar: mesas, cuentas
abiertas y cobro. Todo lo demÃ¡s son incrementos que se pueden entregar y validar por separado.

**Entrega incremental sugerida**:

1. Phases 1â€“3 â†’ mÃ³dulo activable, nada cambia para quien no lo enciende. Riesgo cero.
2. Phase 4 â†’ TPV de bar usable. **Primer entregable con valor real.**
3. Phases 5â€“6 â†’ comandas correctas con modificadores.
4. Phase 7 â†’ operativa diaria (mover y juntar).
5. Phase 8 â†’ cobro por partes. **El corte natural si falta tiempo.**
6. Phase 9 â†’ suplemento por zona.
7. Phase 10 â†’ pulido y las 4 capas de documentaciÃ³n. **No opcional**: sin esto la feature no estÃ¡
   cerrada segÃºn `CLAUDE.md`.
