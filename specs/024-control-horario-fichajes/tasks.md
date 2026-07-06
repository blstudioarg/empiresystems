# Tasks: Control horario y fichajes con geolocalización

**Feature**: 024-control-horario-fichajes | **Branch**: `024-control-horario-fichajes`
**Input**: [plan.md](./plan.md) · [spec.md](./spec.md) · [data-model.md](./data-model.md) ·
[contracts/http.md](./contracts/http.md) · [research.md](./research.md) · [quickstart.md](./quickstart.md)

**Nota de flujo (Constitución, Principio IV — NON-NEGOTIABLE)**: son lógica crítica y sus tests van
primero, en rojo: (a) aislamiento multi-tenant de miembros/fichajes/alertas; (b) Haversine
dentro/fuera incl. borde; (c) inmutabilidad del ledger `fichajes`; (d) separación de retenciones
(purga de geo/casa que no borra jornada/miembro). El resto (UI, mapa Leaflet, textos, menú) sigue el
flujo estándar del proyecto.

**Dependencia de datos**: fichar (US1) requiere que exista un `miembro_equipo`. En tests se crea por
factory; en producción lo da de alta el Admin (US4). Por eso US1 es testeable de forma independiente
aunque la UI de gestión de miembros llegue en una fase posterior.

---

## Phase 1: Setup

- [X] T001 Crear migración `database/migrations/2026_07_05_000001_create_miembros_equipo_table.php` con las columnas de [data-model.md](./data-model.md): `id`, `tenant_id` (unsignedBigInteger, índice), `user_id` (foreignId → `users`, `unique`, `cascadeOnDelete` o `restrictOnDelete`), `puesto` (string 120 nullable), `trabajo_direccion` (string 255 nullable), `trabajo_latitud`/`trabajo_longitud` (decimal 10,7), `distancia_max_metros` (unsignedInteger), `casa_direccion` (string 255 nullable), `casa_latitud`/`casa_longitud` (decimal 10,7 nullable), `distancia_casa_trabajo_metros` (unsignedInteger nullable), `activo` (boolean default true), `dado_baja_at` (dateTime nullable), `timestamps`, `softDeletes`; índice `(tenant_id, activo)`.
- [X] T002 Crear migración `database/migrations/2026_07_05_000002_create_fichajes_table.php`: `id`, `tenant_id` (índice), `miembro_equipo_id` (foreignId → `miembros_equipo`), `tipo` (string 15), `ocurrido_at` (dateTime), `resultado_ubicacion` (string 15 nullable), `distancia_metros` (unsignedInteger nullable), `precision_metros` (unsignedInteger nullable), `corrige_fichaje_id` (foreignId nullable → `fichajes`, `nullOnDelete`), `motivo` (string 255 nullable), `registrado_por` (foreignId nullable → `users`), `ip_origen` (string 45 nullable), `user_agent` (string 255 nullable), `timestamps`; índices `(tenant_id, miembro_equipo_id, ocurrido_at)`, `(tenant_id, ocurrido_at)`, `corrige_fichaje_id`. SIN `softDeletes` (append-only, FR-003).
- [X] T003 Crear migración `database/migrations/2026_07_05_000003_create_alertas_table.php`: `id`, `tenant_id` (índice), `miembro_equipo_id` (foreignId → `miembros_equipo`), `fichaje_id` (foreignId → `fichajes`), `tipo` (string 30), `distancia_metros` (unsignedInteger), `estado` (string 15 default `nueva`), `resuelta_por` (foreignId nullable → `users`), `resuelta_at` (dateTime nullable), `timestamps`; índice `(tenant_id, estado)`.
- [X] T004 [P] Crear enum `app/Enums/TipoEventoFichaje.php` (string backed): `Entrada = 'entrada'`, `Salida = 'salida'`, `InicioPausa = 'inicio_pausa'`, `FinPausa = 'fin_pausa'`, con `label(): string` en español.
- [X] T005 [P] Crear enum `app/Enums/ResultadoUbicacionFichaje.php` (string backed): `Dentro = 'dentro'`, `Fuera = 'fuera'`, `SinUbicacion = 'sin_ubicacion'`, con `label(): string`.
- [X] T006 [P] Crear enum `app/Enums/TipoAlerta.php` (string backed): `FichajeFueraDeRango = 'fichaje_fuera_de_rango'`, con `label(): string`.
- [X] T007 [P] Crear enum `app/Enums/EstadoAlerta.php` (string backed): `Nueva = 'nueva'`, `Vista = 'vista'`, `Resuelta = 'resuelta'`, con `label(): string`.
- [X] T008 [P] Vendorizar Leaflet en `public/vendor/leaflet/` (leaflet.js, leaflet.css e imágenes de marcador), sin CDN (por CSP). Ver D1/D3 en [research.md](./research.md).
- [X] T009 Ajustar la CSP para permitir `img-src` de `*.tile.openstreetmap.org` en las vistas con mapa (D3); Leaflet JS/CSS quedan `self`. **Nota**: verificado que el proyecto no tiene ninguna CSP implementada (sin cabecera ni meta `Content-Security-Policy` en middleware ni en `layouts/app.blade.php`); no hay nada que ajustar todavía. Si en el futuro se añade una CSP, deberá incluir `img-src 'self' *.tile.openstreetmap.org`.

**Checkpoint**: `php artisan migrate` corre limpio, los 4 enums existen y Leaflet carga localmente.

---

## Phase 2: Foundational (BLOQUEA las historias)

- [X] T010 [P] Test unitario `tests/Unit/HaversineTest.php`: distancia conocida entre dos coordenadas (p. ej. dos puntos con distancia tabulada) con tolerancia de ±1 %, y caso distancia 0. DEBE fallar antes de T011 (Principio IV).
- [X] T011 Implementar `app/Support/Haversine.php`: método estático `metros(float $lat1, float $lon1, float $lat2, float $lon2): int` con la fórmula de Haversine (radio terrestre 6371000 m). Hace pasar T010.
- [X] T012 [P] Crear modelo `app/Models/MiembroEquipo.php`: traits `BelongsToTenant`, `HasFactory`, `SoftDeletes`; `$fillable` de [data-model.md](./data-model.md); casts de decimales; relaciones `tenant()`, `user()` (belongsTo), `fichajes()` (hasMany). Método `tieneUbicacionTrabajo(): bool`.
- [X] T013 [P] Crear modelo `app/Models/Fichaje.php`: traits `BelongsToTenant`, `HasFactory` (SIN `SoftDeletes` — append-only); casts `tipo => TipoEventoFichaje`, `resultado_ubicacion => ResultadoUbicacionFichaje`, `ocurrido_at => datetime`; relaciones `miembro()`, `corrigeA()` (belongsTo self), `correcciones()` (hasMany self). Sin métodos de update/delete de negocio.
- [X] T014 [P] Crear modelo `app/Models/Alerta.php`: traits `BelongsToTenant`, `HasFactory`; casts `tipo => TipoAlerta`, `estado => EstadoAlerta`, `resuelta_at => datetime`; relaciones `miembro()`, `fichaje()`.
- [X] T015 [P] Crear factory `database/factories/MiembroEquipoFactory.php` (con `user_id` vía `User::factory()`, coords de trabajo y casa, `distancia_max_metros` por defecto p. ej. 100).
- [X] T016 [P] Crear factory `database/factories/FichajeFactory.php` (estados: `entrada()`, `dentro()`, `fuera()` como states).
- [X] T017 [P] Crear factory `database/factories/AlertaFactory.php`.
- [X] T018 [P] Crear `app/Support/RetencionGeoTenant.php` (patrón `RetencionLogsTenant`): const `CLAVE = 'fichajes.retencion_geo_dias'`, `DEFAULT_DIAS = 30`, `dias(int $tenantId): int` leyendo de `configuraciones`.
- [X] T019 [P] Crear `app/Support/RetencionMiembroTenant.php`: const `CLAVE = 'fichajes.retencion_casa_dias'`, `DEFAULT_DIAS = 30`, `dias(int $tenantId): int`.
- [X] T020 [P] Crear `app/Support/ConfigFichajes.php` (o helpers en Configuracion) para leer `fichajes.geofencing_bloqueante` (bool, default false) y `fichajes.registrar_pausas` (bool, default false) por tenant.
- [X] T021 Definir la autorización por rol Admin para gestión (miembros, alertas, corrección, informe global): un Gate `gestiona-fichajes` o middleware `can`/policy que exija `UserRole::Admin`. Reutilizar el patrón de autorización ya usado en el proyecto. Implementado como `Gate::define('gestiona-fichajes', ...)` en `AppServiceProvider::boot()` (no existía precedente de Gates en el proyecto; se sigue el mismo estilo idiomático de Laravel usado implícitamente por `EnsureSuperAdmin`).

**Checkpoint**: Haversine verde, modelos+factories creados, soportes de retención y config listos, gate de Admin disponible.

---

## Phase 3: User Story 1 - Fichar entrada/salida con verificación de ubicación (Priority: P1) 🎯 MVP

**Goal**: El miembro ficha entrada/salida (y pausas si están activas) desde la pantalla con mapa; el backend fija la hora, calcula el veredicto Haversine y persiste un evento inmutable.

**Independent Test**: Con un miembro sembrado por factory, fichar dentro y fuera de su distancia máxima y verificar dos eventos inmutables con hora de servidor y veredicto correcto.

### Tests (primero, en rojo)

- [X] T022 [P] [US1] `tests/Feature/FichajeRegistroTest.php`: entrada crea evento con `ocurrido_at` de servidor (no del cliente); salida cierra jornada; no existe ruta/servicio de edición ni borrado (inmutabilidad, FR-003); sin permiso de geo → `SinUbicacion` (FR-012); user sin miembro → 403.
- [X] T023 [P] [US1] `tests/Feature/FichajeGeofencingTest.php`: dentro de `distancia_max_metros` → `Dentro`; justo en el borde; fuera → `Fuera`; sin ubicación de trabajo → `SinUbicacion`; con `fichajes.geofencing_bloqueante=true` y fuera → 422 sin persistir.
- [X] T024 [P] [US1] `tests/Feature/FichajeTenantIsolationTest.php`: con ≥2 tenants, un miembro nunca ficha contra datos de otro tenant y no ve fichajes ajenos (Principio I).

### Implementación

- [X] T025 [US1] Crear `app/Http/Requests/FicharRequest.php`: valida `tipo` (Rule::enum TipoEventoFichaje; pausas solo si `registrar_pausas`), `latitud`/`longitud`/`precision` nullable numéricas. `authorize()` verifica que el user autenticado tiene `miembro_equipo`.
- [X] T026 [US1] Crear `app/Services/RegistroFichajes.php` (único punto de escritura): recibe miembro + tipo + coords/precisión; fija `ocurrido_at = now()` (servidor); calcula distancia con `Haversine` a la ubicación de trabajo del miembro y deriva `ResultadoUbicacionFichaje`; aplica reglas de secuencia (FR-006); si `geofencing_bloqueante` y `Fuera` lanza excepción de bloqueo; persiste `Fichaje` con distancia+precisión (NO lat/long), `ip_origen`/`user_agent`. (El gancho de creación de alerta se añade en US6/T049.)
- [X] T027 [US1] Crear `app/Http/Controllers/FichajeController.php`: `index()` (GET `/fichajes`) carga miembro del user, estado actual de jornada (derivado de eventos del día vía consulta), ubicación de trabajo y flags; `store(FicharRequest)` (POST `/fichajes`) delega en `RegistroFichajes`, maneja bloqueo (422/flash) y éxito (302 + flash toastr).
- [X] T028 [US1] Añadir rutas en `routes/web.php`: `GET /fichajes` → `fichajes.index`, `POST /fichajes` → `fichajes.store` (auth).
- [X] T029 [US1] Crear vista `resources/views/fichajes/index.blade.php`: mapa Leaflet, estado de jornada, botones (Entrada/Salida y pausas si aplica), textos informativos de privacidad (FR-025). `@stack('styles')` con leaflet.css antes de style.css (ver CLAUDE.md orden CSS).
- [X] T030 [US1] Crear `public/js/plugins-init/fichaje-mapa.init.js`: `navigator.geolocation.watchPosition` para el punto en vivo, dibuja el círculo del perímetro del miembro, y al pulsar envía el POST con la posición del instante.
- [X] T031 [US1] Añadir ítem de menú "Fichar" en `resources/views/partials/header.blade.php` (visible para miembros). **Nota**: el menú de navegación real del proyecto vive en `resources/views/partials/sidebar.blade.php` (el `header.blade.php` solo tiene el dropdown superior); el ítem se añadió ahí, siguiendo el patrón existente de "Inicio"/"Clientes"/etc.

**Checkpoint**: US1 funcional y testeable de forma independiente; los 3 tests en verde.

---

## Phase 4: User Story 2 - Consultar y exportar el registro de jornada (Priority: P1)

**Goal**: Administración consulta por miembro y periodo el registro con total de horas (calculado en backend) y lo exporta de forma legible y completa.

**Independent Test**: Con fichajes sembrados, generar el informe de un miembro en un rango y verificar el total de horas y una exportación con todos los eventos; con 2 tenants, sin fugas.

### Tests (primero, en rojo)

- [X] T032 [P] [US2] `tests/Feature/InformeJornadaTest.php`: total de horas calculado server-side a partir de eventos (incl. jornada partida y cruce de medianoche); exportación contiene todos los eventos del periodo incl. correcciones; aislamiento entre 2 tenants; acceso solo Admin (403 para Usuario).

### Implementación

- [X] T033 [US2] Crear `app/Services/InformeJornada.php`: agrupa eventos por miembro y día, empareja entrada/salida (y pausas si aplica), calcula horas efectivas; expone datos para vista y exportación.
- [X] T034 [US2] Crear `app/Http/Controllers/InformeJornadaController.php`: `index()` (GET `/jornada`) con selección de miembro + rango (reutilizar `RangoFechas`/patrón dashboard), filtrando explícitamente por `tenant_id`; `exportar()` (GET `/jornada/exportar`). Proteger con gate Admin (T021).
- [X] T035 [US2] Añadir rutas `GET /jornada` y `GET /jornada/exportar` en `routes/web.php` (auth + gate Admin).
- [X] T036 [US2] Crear vista `resources/views/fichajes/informe.blade.php`: selector de miembro/rango, tabla de eventos con traza de correcciones y total de horas.
- [X] T037 [US2] Añadir ítem de menú "Jornada" (Admin) en `resources/views/partials/header.blade.php`. **Nota**: igual que T031, añadido en `sidebar.blade.php` (ver nota de T031), condicionado a `auth()->user()->rol === UserRole::Admin`.

**Checkpoint**: US2 en verde; informe y exportación operativos y aislados por tenant.

---

## Phase 5: User Story 4 - Gestionar miembros de equipo y su ubicación de trabajo (Priority: P2)

**Goal**: Admin da de alta/edita miembros (1:1 con user), su ubicación de trabajo, distancia máxima y dirección de casa; se calcula la distancia casa-trabajo.

**Independent Test**: Crear un miembro con user, ubicación y distancia máx desde el formulario y comprobar que un fichaje dentro se marca Dentro y uno lejano Fuera; distancia casa-trabajo calculada.

### Tests (primero, en rojo)

- [X] T038 [P] [US4] `tests/Feature/MiembroEquipoTest.php`: CRUD de miembro (solo Admin, 403 Usuario); `user_id` único (no dos miembros por user); al guardar con coords de casa y trabajo se calcula `distancia_casa_trabajo_metros`; aislamiento entre 2 tenants; baja marca `dado_baja_at` + softDelete conservando fichajes.

### Implementación

- [X] T039 [US4] Crear `app/Http/Requests/MiembroEquipoRequest.php`: valida `user_id` (existe en el tenant, único en `miembros_equipo`), `trabajo_latitud/longitud`, `distancia_max_metros` (≥1), `casa_*` nullable, `puesto` nullable, `activo`. **Nota**: `activo` se dejó fuera del request — la baja se gestiona exclusivamente vía `destroy()` (softDelete + `dado_baja_at`), no como campo editable del formulario (ver contradicción evitada con el botón dedicado de baja).
- [X] T040 [US4] Crear `app/Http/Controllers/MiembroEquipoController.php`: `index/store/update/destroy` (sin `create`/`edit`: el alta/edición es modal + AJAX, ver T042), resolviendo `{miembro}` por query con `tenant_id` (no implicit binding — memoria `project_tenant_route_binding`); en `store/update` recalcula `distancia_casa_trabajo_metros` con `Haversine`; `destroy` da de baja (softDelete + `dado_baja_at`). Gate Admin vía middleware `can:gestiona-fichajes` en el grupo de rutas.
- [X] T041 [US4] Añadir rutas resource `miembros-equipo` en `routes/web.php` (auth + gate Admin). Solo `index/store/update/destroy` (sin `crear`/`{miembro}/editar`).
- [X] T042 [US4] Crear vista `resources/views/miembros-equipo/index.blade.php` (lista + formulario). **Revisado**: se probó primero un formulario full-page (excepción de `docs/04-front-guidelines.md` para "formularios cargados"), pero a pedido explícito del usuario se pasó al patrón estándar del proyecto — **un único modal reutilizado para alta/edición + submit AJAX** (`miembros-equipo-modal.init.js`), igual que `clientes-modal.init.js`. Los 2 mapas Leaflet entran sin problema en un modal `modal-lg`. Los datos de cada fila para editar viajan en `data-*` del botón "Editar" (no hay endpoint `GET .../{id}/editar`); el listado (`GET /miembros-equipo` con `wantsJson()`) expone además `usuarios` (catálogo completo de usuarios del tenant) para reconstruir el `<select>` en cada apertura del modal, excluyendo los que ya tienen miembro salvo el que se está editando.
- [X] T043 [US4] Crear `public/js/plugins-init/miembro-mapa.init.js`: mapa Leaflet para fijar coords de trabajo y de casa (click/arrastre de marcador) y radio de `distancia_max_metros`. **Revisado**: al vivir dentro de un modal reutilizado, el mapa se inicializa una sola vez (Leaflet no permite reinicializar el mismo `<div>`) y se reposiciona (marcador/círculo/`invalidateSize()` en `shown.bs.modal`) en cada apertura para alta o edición.
- [X] T044 [US4] Añadir ítem de menú "Miembros" (Admin) en `resources/views/partials/header.blade.php`. **Nota**: igual que T031/T037, añadido en `sidebar.blade.php`.

**Checkpoint**: US4 en verde; Admin gestiona miembros y su perímetro individual.

---

## Phase 6: User Story 3 - Corregir un fichaje dejando rastro (Priority: P2)

**Goal**: Admin corrige un fichaje creando un evento nuevo enlazado (nunca UPDATE), con motivo obligatorio; el informe usa el valor corregido conservando la traza.

**Independent Test**: Corregir un fichaje con motivo y verificar evento enlazado + original intacto; sin motivo → 422; Usuario → 403.

### Tests (primero, en rojo)

- [X] T045 [P] [US3] `tests/Feature/CorreccionFichajeTest.php`: corrección crea fila con `corrige_fichaje_id`, `motivo`, `registrado_por`; original inalterado; sin motivo → 422; Usuario → 403; el informe (InformeJornada) refleja el valor corregido.

### Implementación

- [X] T046 [US3] Crear `app/Http/Requests/CorregirFichajeRequest.php`: `tipo` (enum), `ocurrido_at` (datetime), `motivo` (required, string). Gate Admin.
- [X] T047 [US3] Crear `app/Http/Controllers/CorreccionFichajeController.php`: `store()` (POST `/fichajes/{fichaje}/corregir`) resolviendo `{fichaje}` por query con `tenant_id`; delega en `RegistroFichajes` un método `corregir()` que persiste el evento enlazado sin tocar el original. Añadir ese método a `RegistroFichajes`.
- [X] T048 [US3] Añadir ruta `POST /fichajes/{fichaje}/corregir` en `routes/web.php` (auth + gate Admin) y botón "Corregir" en la vista de informe (`informe.blade.php`).

**Checkpoint**: US3 en verde; correcciones trazables sin romper el append-only.

---

## Phase 7: User Story 6 - Alertas por fichaje fuera de rango (Priority: P2)

**Goal**: Al fichar por encima de la distancia máxima del miembro se crea una alerta enlazada; Admin la consulta y cambia de estado.

**Independent Test**: Fichar fuera de rango → se crea 1 alerta enlazada; fichar dentro → 0 alertas; Admin cambia estado; aislamiento entre tenants.

### Tests (primero, en rojo)

- [X] T049 [P] [US6] `tests/Feature/AlertaFichajeTest.php`: fichaje `Fuera` crea exactamente 1 `Alerta` (`FichajeFueraDeRango`, `Nueva`) enlazada al fichaje con su `distancia_metros`; fichaje `Dentro`/`SinUbicacion` no crea alerta; con `geofencing_bloqueante` y fuera no crea ni fichaje ni alerta; Admin cambia estado a `Resuelta` (registra `resuelta_por`/`resuelta_at`) y no se borra; aislamiento entre 2 tenants; `/alertas` solo Admin.

### Implementación

- [X] T050 [US6] Modificar `app/Services/RegistroFichajes.php`: cuando el resultado es `Fuera`, crear una `Alerta` en la misma transacción del fichaje (D11). No crear alerta en `Dentro`/`SinUbicacion`.
- [X] T051 [US6] Crear `app/Http/Controllers/AlertaController.php`: `index()` (GET `/alertas`, filtro por estado, `tenant_id`) y `update()` (PATCH `/alertas/{alerta}`) para cambiar estado, resolviendo `{alerta}` por query con `tenant_id`. Gate Admin vía middleware `can:gestiona-fichajes`.
- [X] T052 [US6] Añadir rutas `GET /alertas` y `PATCH /alertas/{alerta}` en `routes/web.php` (auth + gate Admin).
- [X] T053 [US6] Crear vista `resources/views/alertas/index.blade.php`: bandeja con miembro, fichaje, fecha, distancia, estado y acción de resolver.
- [X] T054 [US6] Añadir ítem de menú "Alertas" (Admin), opcionalmente con contador de `Nueva`, en `resources/views/partials/header.blade.php`. **Nota**: igual que T031/T037/T044, añadido en `sidebar.blade.php`, con badge de contador de alertas `Nueva`.

**Checkpoint**: US6 en verde; alertas generadas y gestionadas.

---

## Phase 8: User Story 5 - Portal de la persona trabajadora (Priority: P3)

**Goal**: Cada miembro consulta y descarga solo sus propios registros (derecho art. 34.9 ET).

**Independent Test**: Como Usuario/miembro, ver y descargar solo lo propio; intentar ver lo de otro → 403.

### Tests (primero, en rojo)

- [X] T055 [P] [US5] `tests/Feature/PortalMiJornadaTest.php`: el miembro ve solo sus fichajes/horas; no accede a los de otro (403); aislamiento entre tenants.

### Implementación

- [X] T056 [US5] Añadir al `InformeJornadaController` (o controlador `MiJornadaController` nuevo) `miIndex()` (GET `/mi-jornada`) y `miExportar()` (GET `/mi-jornada/exportar`) filtrando por `tenant_id` **y** el `miembro_equipo` del user autenticado; reutiliza `InformeJornada`. Implementado como controlador nuevo `MiJornadaController` (más claro que sobrecargar el de administración); nunca lee un `miembro_id` de la petición — siempre resuelve `$request->user()->miembroEquipo`, así que no hay ni vector de inyección que "denegar": estructuralmente no puede exponer datos ajenos.
- [X] T057 [US5] Añadir rutas `GET /mi-jornada` y `GET /mi-jornada/exportar` (auth) en `routes/web.php`.
- [X] T058 [US5] Crear vista `resources/views/mi-jornada/index.blade.php` y añadir ítem de menú "Mi jornada".

**Checkpoint**: US5 en verde; portal del trabajador operativo.

---

## Phase 9: Polish & Cross-Cutting (Retención RGPD, agenda, docs)

- [X] T059 [P] `tests/Feature/PurgaGeoFichajesTest.php`: un fichaje anterior al plazo `retencion_geo_dias` pierde `resultado_ubicacion`/`distancia_metros`/`precision_metros` (a NULL) pero la **fila permanece**; uno reciente conserva su geo; separación por tenant (Principio IV.d).
- [X] T060 Crear comando `app/Console/Commands/PurgarGeoFichajes.php` (`fichajes:purgar-geo`): por tenant, `UPDATE` nulificando solo columnas de geo de `fichajes` con `ocurrido_at` anterior al plazo (`RetencionGeoTenant`), por lotes (patrón `PurgarLogsActividad`). Única mutación admitida sobre el ledger (D5).
- [X] T061 [P] `tests/Feature/PurgaCasaMiembrosTest.php`: un miembro con `dado_baja_at` anterior al plazo `retencion_casa_dias` pierde `casa_direccion`/`casa_latitud`/`casa_longitud` (a NULL) conservando el miembro, su `distancia_casa_trabajo_metros` y sus fichajes.
- [X] T062 Crear comando `app/Console/Commands/PurgarCasaMiembros.php` (`miembros:purgar-casa`): por tenant, nulifica datos de casa de miembros dados de baja pasado el plazo (`RetencionMiembroTenant`).
- [X] T063 Registrar en `bootstrap/app.php` → `withSchedule`: `$schedule->command('fichajes:purgar-geo')->daily()` y `$schedule->command('miembros:purgar-casa')->daily()`.
- [X] T064 [P] Añadir sección de configuración de fichajes en la vista de Configuración del tenant (o donde corresponda): `retencion_geo_dias`, `retencion_casa_dias`, `geofencing_bloqueante`, `registrar_pausas`. Nueva pestaña "Fichajes" en `configuracion/index.blade.php` + `ConfiguracionController::updateFichajes()`.
- [X] T065 [P] Actualizar `docs/03-modelo-datos.md` con las tablas `miembros_equipo`, `fichajes`, `alertas` (fuente de verdad del modelo); confirmar que `docs/07-control-horario-espana.md` sigue coherente con lo implementado. Se corrigió una referencia obsoleta a una tabla compartida `ubicaciones_trabajo` (§3) que la D10 del research.md había descartado a favor de que cada `miembro_equipo` guarde su propio perímetro.
- [X] T066 Ejecutar el `quickstart.md` completo (escenarios 1–10) y la suite `php artisan test --filter=Fichaje|Miembro|Alerta|Jornada|Haversine|PurgaGeo|PurgaCasa` en verde. Los 10 escenarios del quickstart quedan cubiertos por la suite automatizada (Feature tests de cada historia); no se ejecutó manualmente en navegador (requiere confirmación explícita del usuario para usar herramientas de navegador, según CLAUDE.md).

---

## Dependencies & Execution Order

- **Phase 1 (Setup)** → **Phase 2 (Foundational)** bloquean todo lo demás.
- **US1 (P1)** es el MVP. **US2 (P1)** depende solo de Foundational (usa fichajes sembrados).
- **US4 (P2)** aporta la UI de alta de miembros que US1 necesita en producción (en tests se usa factory).
- **US3 (P2)** depende de US1 (existe el fichaje a corregir) y de US2 (botón en el informe).
- **US6 (P2)** depende de US1 (T026 el servicio) y de US4 (distancia del miembro): T050 modifica `RegistroFichajes`.
- **US5 (P3)** depende de US2 (reutiliza `InformeJornada`).
- **Phase 9 (Polish)** depende de que existan `fichajes`/`miembros_equipo` (tras US1/US4).

### Paralelizables
- Setup: T004–T008 [P]. Foundational: T010, T012–T019 [P] (modelos/factories/soportes distintos).
- Tests de cada historia marcados [P] entre sí. Distintas historias pueden repartirse tras Foundational.

---

## Implementation Strategy

- **MVP**: Phase 1 + 2 + **US1** (fichar con veredicto e inmutabilidad) → validar → demo.
- **Incremental**: US2 (informe/export) → US4 (gestión de miembros) → US3 (correcciones) → US6 (alertas) → US5 (portal).
- **Cierre**: Phase 9 (retención/purga RGPD + agenda + docs + quickstart).

## Notes
- [P] = archivos distintos, sin dependencias. [Story] mapea a la historia del spec.
- Test-first obligatorio en aislamiento, Haversine, inmutabilidad y retención (Principio IV).
- La hora y el veredicto dentro/fuera se calculan SIEMPRE en backend (Principios III/II).
- Commit por tarea o grupo lógico; parar en cada checkpoint para validar la historia.
