# Tasks: Logs de actividad de usuarios

**Feature**: 021-logs-actividad-usuarios | **Branch**: `021-logs-actividad-usuarios`
**Input**: [plan.md](./plan.md) · [spec.md](./spec.md) · [data-model.md](./data-model.md) ·
[contracts/http.md](./contracts/http.md) · [research.md](./research.md) · [quickstart.md](./quickstart.md)

**Nota de flujo (Constitución, Principio IV — NON-NEGOTIABLE)**: el aislamiento multi-tenant del
listado (US2) es lógica crítica → su test se trata como red-green dedicado sobre el endpoint ya
construido, igual que hizo la feature 010 con su fase de aislamiento. El resto (instrumentación de
controladores, UI) sigue el flujo estándar del proyecto, con tests igualmente primero por
consistencia con el resto de features.

---

## Phase 1: Setup

- [X] T001 Crear migración `database/migrations/2026_07_04_000000_create_logs_actividad_table.php`
  con las columnas de [data-model.md](./data-model.md): `id`, `tenant_id` (unsignedBigInteger,
  índice), `usuario_id` (foreignId nullable → `users`, `nullOnDelete()`), `usuario_nombre`
  (string 150), `accion` (string 20), `entidad_tipo` (string 20 nullable), `entidad_id`
  (unsignedBigInteger nullable, sin FK), `descripcion` (string 255), `ocurrido_at` (dateTime),
  `timestamps`; índices `(tenant_id, ocurrido_at)`, `(tenant_id, entidad_tipo)`,
  `(tenant_id, accion)`. NO usar `softDeletes` (append-only, FR-008).
- [X] T002 [P] Crear enum `app/Enums/AccionLogActividad.php` (string backed): `Login = 'login'`,
  `Logout = 'logout'`, `Alta = 'alta'`, `Baja = 'baja'`, `Modificacion = 'modificacion'`, con
  método `label(): string` en español (ver tabla en [data-model.md](./data-model.md)).
- [X] T003 [P] Crear enum `app/Enums/EntidadLogActividad.php` (string backed): `Cliente = 'cliente'`,
  `Articulo = 'articulo'`, `Factura = 'factura'`, `Configuracion = 'configuracion'`,
  `Usuario = 'usuario'`, con método `label(): string` en español.

**Checkpoint**: migración corre (`php artisan migrate`) y ambos enums existen.

---

## Phase 2: Foundational (BLOQUEA las historias)

- [X] T004 Crear modelo `app/Models/LogActividad.php`: traits `BelongsToTenant`, `HasFactory`
  (SIN `SoftDeletes` — append-only); `protected $table = 'logs_actividad'`;
  `$fillable = [tenant_id, usuario_id, usuario_nombre, accion, entidad_tipo, entidad_id,
  descripcion, ocurrido_at]`; casts `accion => AccionLogActividad::class`,
  `entidad_tipo => EntidadLogActividad::class`, `ocurrido_at => datetime`; relaciones `tenant()` y
  `usuario(): BelongsTo` (a `User`, nullable).
- [X] T005 [P] Crear `database/factories/LogActividadFactory.php`: genera `usuario_nombre` (faker
  name), `accion` aleatorio del enum, `entidad_tipo`/`entidad_id` coherentes con la acción (`null`
  para `login`/`logout`), `descripcion` de ejemplo, `ocurrido_at` reciente.
- [X] T006 Crear servicio `app/Services/RegistradorActividad.php` con
  `registrar(User $usuario, AccionLogActividad $accion, ?EntidadLogActividad $entidadTipo,
  ?int $entidadId, string $descripcion): LogActividad`: crea la fila con `tenant_id` tomado de
  `$usuario->tenant_id` (explícito, igual que `EmisorFacturas` con `factura_id`), `usuario_id` y
  `usuario_nombre` de `$usuario`, y `ocurrido_at = now()`. Sin lógica de validación de negocio (no
  lanza excepciones propias); se invoca siempre después de que la operación principal ya se
  confirmó con éxito (ver D3 en [research.md](./research.md)).
- [X] T007 [P] Test `tests/Feature/LogActividadUsuarioEliminadoTest.php` (edge case, D4): crear un
  `LogActividad` vía `RegistradorActividad::registrar()` para un usuario dado; eliminar
  físicamente ese `User`; verificar que la fila sigue existiendo, `usuario_id` queda `null`
  (`nullOnDelete`) y `usuario_nombre` conserva el nombre original.

**Checkpoint**: T007 en verde. Modelo, enums y servicio listos; base para instrumentar
controladores y construir el listado.

---

## Phase 3: User Story 1 — Consultar el historial de actividad del tenant (Priority: P1) 🎯 MVP

**Goal**: cualquier usuario del tenant abre "Logs" desde el dropdown y ve una datatable
server-side con el historial cronológico de login/logout y altas/bajas/modificaciones de
clientes, artículos, facturas, configuración y usuarios.

**Independent Test**: con datos reales generados por el uso normal del CRM, entrar como cualquier
usuario, abrir el dropdown, hacer clic en "Logs" y verificar que la tabla muestra esos eventos,
más recientes primero.

### Tests para User Story 1 (primero)

- [X] T008 [P] [US1] Test `tests/Feature/LogActividadRegistroTest.php`: para cada punto de
  instrumentación de la tabla en [data-model.md](./data-model.md) (login, logout, alta/
  modificación/baja de cliente, de artículo, modificación de cada una de las 4 pestañas de
  configuración, alta por registro + aprobar/rechazar de usuario, alta/modificación/baja/emitir/
  rectificar de factura), ejecutar la acción real vía HTTP y verificar que queda una fila en
  `logs_actividad` con `accion`, `entidad_tipo` y `usuario_nombre` correctos.
- [X] T009 [P] [US1] Test `tests/Feature/LogActividadListadoTest.php` (caso base): sembrar varias
  filas de `logs_actividad` con la factory en distintos `ocurrido_at`; `GET /logs` (JSON) devuelve
  `data` ordenado por `ocurrido_at` descendente por defecto, `recordsTotal`/`recordsFiltered`
  correctos, y respeta `start`/`length` (paginación); un tenant sin eventos devuelve `data: []` y
  `recordsTotal: 0` sin error.

### Implementación de User Story 1

- [X] T010 [US1] Modificar `app/Listeners/LogAuthenticationActivity.php`: en `handleLogin`, además
  del `Log::info` existente, llamar a `RegistradorActividad::registrar($event->user,
  AccionLogActividad::Login, null, null, 'Inició sesión')`; en `handleLogout`, si `$event->user`
  no es `null`, llamar con `AccionLogActividad::Logout` y `'Cerró sesión'`. No tocar
  `handleFailed`/`handleLockout` (fuera de alcance, FR-002 no los pide).
- [X] T011 [P] [US1] En `app/Http/Controllers/ClienteController.php`, tras confirmar cada
  operación en `store`/`update`/`destroy`, llamar a `RegistradorActividad::registrar(auth()->user(),
  Alta|Modificacion|Baja, EntidadLogActividad::Cliente, $cliente->id, "Creó/Modificó/Eliminó el
  cliente {$cliente->nombre}")` (ver tabla de descripciones en [data-model.md](./data-model.md)).
- [X] T012 [P] [US1] En `app/Http/Controllers/ArticuloController.php`, ídem sobre `store`/`update`/
  `destroy` con `EntidadLogActividad::Articulo`.
- [X] T013 [P] [US1] En `app/Http/Controllers/ConfiguracionController.php`, tras `update`
  (apariencia), `updateFacturacion`, `updateEmail` y `updateArchivos`, llamar a
  `RegistradorActividad::registrar` con `Modificacion` / `EntidadLogActividad::Configuracion` y una
  descripción específica por pestaña (ver tabla en data-model.md).
- [X] T014 [P] [US1] En `app/Http/Controllers/UsuarioController.php`, en `aprobar` llamar con
  `Alta`/`EntidadLogActividad::Usuario` ("Aprobó al usuario {nombre}") y en `rechazar` con `Baja`
  ("Rechazó al usuario {nombre}"); usuario responsable = `auth()->user()` (quien aprueba/rechaza),
  no el usuario afectado.
- [X] T015 [P] [US1] En `app/Http/Controllers/Auth/RegisterController.php@store`, tras crear el
  usuario pendiente, llamar con `Alta`/`EntidadLogActividad::Usuario` y usuario responsable el
  propio usuario recién creado ("Se registró {nombre} (pendiente de aprobación)").
- [X] T016 [P] [US1] En `app/Http/Controllers/FacturaController.php`, tras `store`/`update`/
  `destroy` (factura en borrador) y tras `emitir`/`rectificar`, llamar a
  `RegistradorActividad::registrar` con `EntidadLogActividad::Factura` y la acción/descripción
  correspondiente de la tabla en [data-model.md](./data-model.md).
- [X] T017 [US1] Crear `app/Http/Controllers/LogActividadController.php@index`: filtra
  `LogActividad::where('tenant_id', auth()->user()->tenant_id)` (defensivo, además del global
  scope de `BelongsToTenant`); si la petición trae parámetros de DataTables (`draw`), implementa
  el protocolo server-side de [contracts/http.md](../021-logs-actividad-usuarios/contracts/http.md):
  `search[value]` contra `usuario_nombre`/`descripcion`/label de `accion` (LIKE case-insensitive),
  `order` sobre columnas permitidas (`ocurrido_at` por defecto desc, `usuario_nombre`, `accion`),
  `start`/`length` para paginar; responde `draw`/`recordsTotal`/`recordsFiltered`/`data`. Si no
  trae `draw`, retorna la vista `logs.index`.
- [X] T018 [US1] Registrar ruta `GET /logs` → `LogActividadController@index` (name `logs.index`)
  en `routes/web.php`, dentro del grupo `['tenant.context', 'auth']` ya existente.
- [X] T019 [US1] Crear vista `resources/views/logs/index.blade.php` (layout NexaDash, mismo patrón
  que `resources/views/usuarios/index.blade.php`/`facturas/index.blade.php`): tabla `#logs-table`
  con columnas Fecha, Usuario, Acción, Detalle.
- [X] T020 [US1] Crear `public/js/plugins-init/logs-datatable.init.js`: inicializa `#logs-table`
  con `serverSide: true`, `ajax.url = window.location.href`, columnas mapeadas a
  `fecha`/`usuario_nombre`/`accion` (badge con label)/`descripcion` (no orderable), mensajes en
  español igual que `usuarios-datatable.init.js`/`facturas-datatable.init.js`. Incluir el script
  en `resources/views/logs/index.blade.php` (stack `@push('scripts')` si aplica, como en las
  vistas existentes).
- [X] T021 [US1] En `resources/views/partials/header.blade.php`, añadir un ítem "Logs" en el
  dropdown de usuario (junto a Profile/Configuración/Cerrar sesión, línea ~258-291), enlazando a
  `route('logs.index')`, visible para cualquier rol autenticado (sin `@if` de permisos).

**Checkpoint (STOP y VALIDAR)**: T008/T009 en verde. Desde el dropdown se llega a una datatable
real con eventos generados por el uso normal del CRM. US1 entregable de forma independiente (MVP).

---

## Phase 4: User Story 2 — Aislamiento multi-tenant del log (Priority: P1)

**Goal**: garantizar que ningún parámetro de la datatable (búsqueda, orden, paginación) puede
exponer eventos de un tenant distinto al del usuario autenticado.

**Independent Test**: con ≥2 tenants con actividad registrada, confirmar que un usuario del
tenant A solo ve eventos de A en `GET /logs`, incluso variando `start`/`length`/`search`/`order`.

### Tests para User Story 2 (Principio IV — NON-NEGOTIABLE, primero)

- [X] T022 [P] [US2] Test `tests/Feature/LogActividadTenantIsolationTest.php`: crear ≥2 tenants,
  sembrar `LogActividad` (factory) en ambos; usuario del tenant A hace `GET /logs` (JSON) y solo
  recibe filas del tenant A (`recordsTotal` coincide con el conteo real de A, ningún `data[]`
  pertenece a B); repetir variando `start`, `length`, `search[value]` y `order` y confirmar que
  el acotado a tenant A se mantiene en todos los casos.

### Implementación de User Story 2

- [X] T023 [US2] Revisar `LogActividadController@index` (T017): confirmar que el filtro explícito
  por `tenant_id` se aplica **antes** de `search`/`order`/`start`/`length` (no como post-filtro) —
  memoria `project_tenant_route_binding`. Ajustar si T022 detecta algún camino sin el filtro.

**Checkpoint (STOP y VALIDAR)**: T022 en verde — sin fuga entre tenants confirmada.

---

## Phase 5: User Story 3 — Buscar y acotar el historial (Priority: P2)

**Goal**: la búsqueda y el ordenamiento de la datatable devuelven resultados correctos y
consistentes entre sí.

**Independent Test**: con eventos de varios tipos sembrados, buscar por un término y ordenar por
columna, verificando que ambos filtros se combinan correctamente.

### Tests para User Story 3

- [X] T024 [P] [US3] Ampliar `tests/Feature/LogActividadListadoTest.php`: `search[value]` con un
  nombre de usuario, una acción o una entidad concreta devuelve solo las filas coincidentes
  (`recordsFiltered` correcto, `recordsTotal` sin cambiar); `order` por `ocurrido_at` asc/desc y
  por `usuario_nombre` reordena `data` manteniendo el `search[value]` aplicado.

### Implementación de User Story 3

- [X] T025 [US3] Si algún caso de T024 falla, ajustar en `LogActividadController@index` el
  `LIKE` de búsqueda (case-insensitive, incluye label de `accion`/`entidad_tipo` traducidos, no
  solo el valor crudo del enum) y la whitelist de columnas ordenables. Normalmente ya cubierto por
  T017; este task es de ajuste, no de reescritura.

**Checkpoint**: T024 en verde — búsqueda y orden funcionando en la UI real.

---

## Phase 6: Polish & validación final

- [X] T026 [P] Ejecutar `php artisan test --filter=LogActividad` y confirmar que los 5 archivos
  (`LogActividadUsuarioEliminadoTest`, `LogActividadRegistroTest`, `LogActividadListadoTest`,
  `LogActividadTenantIsolationTest`) pasan en verde.
- [X] T027 Ejecutar la suite completa `php artisan test` y confirmar 0 regresiones en el resto de
  features (especialmente 001/002/004/005/006/008/009, cuyos controladores se tocaron).
- [X] T028 [P] Recorrer los escenarios de [quickstart.md](./quickstart.md) y tachar los verificados
  manualmente en el navegador (dropdown → vista → datatable).
- [X] T029 [P] Confirmar que no se introdujo ningún alert Bootstrap ad-hoc ni div `.alert-*`; si
  la feature dispara algún flash de sesión, debe resolverse vía `partials/flash-toastr.blade.php`
  ya existente (CLAUDE.md).
- [X] T030 [P] Actualizar `docs/03-modelo-datos.md` documentando la tabla `logs_actividad` (nueva)
  y su propósito (auditoría general de uso, distinta de `factura_eventos`/Verifactu); revisar si
  procede una nota en `docs/01-arquitectura.md`.

---

## Phase 7: Enmienda — Registro de accesos RGPD/LOPDGDD (post-implementación)

**Contexto**: tras el MVP, se investigó la normativa de "registro de eventos" de Verifactu (SIF) y
se determinó que **no aplica** a `logs_actividad` (ese registro formal con hash/firma solo es
obligatorio en sistemas NO-VERI\*FACTU y cubre eventos de integridad del propio SIF, no actividad
de usuario). En cambio, se identificó que `logs_actividad` sí debe cumplir la función de **registro
de accesos** (RGPD Art. 32 + LOPDGDD, ref. técnica RD 1720/2007): resultado autorizado/denegado, IP
de origen, agente de usuario, registro de intentos fallidos, y una política de retención con purga.

- [X] T031 Migración `add_resultado_ip_user_agent_to_logs_actividad_table`: columnas `resultado`
  (string(10) default `exito`), `ip_origen` (string(45) nullable), `user_agent` (string(255)
  nullable); índice `(tenant_id, resultado)`.
- [X] T032 [P] Enum `App\Enums\ResultadoLogActividad` (`Exito`/`Fallo`) + actualizar
  `LogActividad` (fillable/casts) y `LogActividadFactory` (default `Exito` + estado `fallo()`).
- [X] T033 `RegistradorActividad::registrar` acepta `ResultadoLogActividad $resultado = Exito` y
  captura `ip_origen`/`user_agent` de `request()` automáticamente; nuevo método
  `registrarIntentoFallido(int $tenantId, string $emailIntentado, string $descripcion)` para logins
  fallidos sin `User` asociado.
- [X] T034 `LogAuthenticationActivity::handleFailed` llama a `registrarIntentoFallido` cuando
  `tenancy()->initialized` (sin tenant en dominio central, no genera fila — mismo criterio que
  login/logout exitosos).
- [X] T035 `LogActividadController@index`: expone `resultado`/`resultado_label`/`ip_origen` en el
  JSON; búsqueda también matchea `ip_origen` y el label de `resultado`.
- [X] T036 `logs-datatable.init.js`: badge rojo "(denegado)" cuando `resultado=fallo`; IP visible
  como subtexto bajo la descripción.
- [X] T037 `App\Support\RetencionLogsTenant` (clave `logs.retencion_dias`, default 730 días) +
  comando `logs:purgar` (borrado por lotes de 500, por tenant) + `bootstrap/app.php` →
  `withSchedule` diario.
- [X] T038 Tests: `LogActividadRegistroTest` (login fallido con password incorrecta, con email
  inexistente, en dominio central sin tenant) + `LogActividadPurgaTest` (retención por defecto,
  retención configurada por tenant, aislamiento entre tenants, purga por lotes >500 filas).
- [X] T039 Actualizar `data-model.md`/`contracts/http.md` de este spec y `docs/03-modelo-datos.md`
  (ya actualizado por el usuario, origen de esta enmienda) con los campos nuevos.

**Pendiente de decisión (no bloquea esta enmienda)**: si el registro de eventos formal SIF
(hash encadenado + firma electrónica) llega a ser necesario en el futuro (p. ej. si el proyecto
opera en modalidad NO-VERI\*FACTU), sería una feature nueva y separada de `logs_actividad` — ver
nota en `docs/01-arquitectura.md`/`docs/02-facturacion-espana.md`. Esta enmienda tampoco fue
propagada a la Constitución (`.specify/memory/constitution.md`): ninguno de los 5 principios
existentes cubre retención de datos personales/RGPD explícitamente; evaluar `/speckit-constitution`
si el proyecto quiere una regla no-negociable formal sobre esto.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias — arranca de inmediato.
- **Foundational (Phase 2)**: depende de Setup (T001-T003) — BLOQUEA las historias.
- **US1 (Phase 3)**: depende de Foundational (necesita `LogActividad` + `RegistradorActividad`).
- **US2 (Phase 4)**: depende de US1 (el endpoint `GET /logs` ya debe existir para poder probar que
  no filtra entre tenants).
- **US3 (Phase 5)**: depende de US1 (mismo endpoint; añade cobertura de búsqueda/orden).
- **Polish (Phase 6)**: al final, depende de US1+US2+US3.

### User Story Dependencies

- **US1 (P1)**: la base — sin el pipeline de escritura + el endpoint de lectura no hay nada que
  aislar (US2) ni que buscar (US3).
- **US2 (P1)**: depende de US1; junto con US1 forma el MVP (una vista sin fuga de datos entre
  tenants es un requisito, no un extra).
- **US3 (P2)**: depende de US1; refina la UX ya construida.

### Parallel Opportunities

- T002, T003 (Phase 1) en paralelo tras T001.
- T005 en paralelo con T006 (archivos distintos) tras T004; T007 en paralelo con ambos.
- T008, T009 (tests US1) en paralelo entre sí.
- T011-T016 (instrumentación de 6 controladores/listener, archivos todos distintos) en paralelo
  entre sí, tras T006. T010 (listener) también es independiente de los demás.
- T022 (test US2) puede escribirse en paralelo con T024 (test US3), ambos sobre el endpoint ya
  construido en US1.

---

## Parallel Example: User Story 1 (instrumentación)

```bash
# Tras T006 (servicio RegistradorActividad), lanzar en paralelo (archivos distintos):
Task: "Instrumentar LogAuthenticationActivity (login/logout)"
Task: "Instrumentar ClienteController (store/update/destroy)"
Task: "Instrumentar ArticuloController (store/update/destroy)"
Task: "Instrumentar ConfiguracionController (4 acciones)"
Task: "Instrumentar UsuarioController (aprobar/rechazar)"
Task: "Instrumentar Auth/RegisterController (store)"
Task: "Instrumentar FacturaController (store/update/destroy/emitir/rectificar)"
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

1. Phase 1: Setup (tabla `logs_actividad`, enums).
2. Phase 2: Foundational (modelo, factory, servicio, test del edge case D4).
3. Phase 3 (US1): instrumentar los 7 puntos de escritura + endpoint/vista/JS/dropdown, con sus
   tests primero.
4. Phase 4 (US2): test dedicado de aislamiento multi-tenant sobre el endpoint ya construido.
5. **STOP y VALIDAR**: dropdown → vista → datatable con datos reales, sin fuga entre tenants.

### Incremental Delivery

1. Setup + Foundational → base lista.
2. US1 → historial visible y poblado por el uso real del CRM → validar (MVP funcional).
3. US2 → confirmar aislamiento → validar (MVP seguro, cierre del alcance P1).
4. US3 → búsqueda/orden refinados → validar.
5. Phase 6 (polish) → cierre sin regresiones y documentación actualizada.
