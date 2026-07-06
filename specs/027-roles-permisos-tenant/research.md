# Research: Sistema de roles y permisos por tenant

**Feature**: 027-roles-permisos-tenant | **Date**: 2026-07-06

## D1 — Paquete de roles/permisos

**Decision**: `spatie/laravel-permission` v6 con la feature **teams** activada
(`'teams' => true`, `'team_foreign_key' => 'tenant_id'` en `config/permission.php`).

**Rationale**: es el estándar de facto en Laravel, se integra con el Gate nativo
(`$user->can()`, `@can`, middleware `can:`), y su feature teams resuelve exactamente el
particionado por tenant en single-database que exige el Principio I. `tenants.id` es
`BIGINT` autoincrement (`app/Models/Tenant.php`: `$keyType = 'int'`), compatible directo con
la columna team FK sin ajustes.

**Alternatives considered**:
- *Gates/Policies + enum `rol`*: descartado — el requisito es roles dinámicos gestionables
  por tenant desde UI; con enum habría que construir todo el CRUD y el particionado a mano.
- *Roles caseros (tablas propias)*: reinventar spatie sin su testing/madurez; viola YAGNI al
  revés (más código propio para lo mismo).
- *spatie sin teams + global scope propio en Role*: frágil — las queries internas del paquete
  (cache de permisos, `assignRole`) no pasan por scopes de Eloquent del proyecto.

## D2 — Seteo del contexto de team (tenant activo)

**Decision**: setear `setPermissionsTeamId()` dentro del middleware existente
`SetTenantContext` (`app/Http/Middleware/SetTenantContext.php`), justo tras
`tenancy()->initialize($tenant)`; en la rama de contexto central, setear `null`.

**Rationale**: `SetTenantContext` ya es el único punto donde se resuelve el tenant por host
y ya está priorizado antes de `auth` en `bootstrap/app.php`. Reutilizarlo evita un middleware
nuevo que pueda olvidarse en algún grupo de rutas — el riesgo de fuga señalado en la spec
(FR-002). El reset explícito a `null` en contexto central replica el patrón ya existente de
`tenancy()->end()` para procesos PHP reutilizados (tests, octane).

**Alternatives considered**: middleware dedicado `SetSpatieTeamContext` — más piezas, mismo
efecto, y un grupo de rutas podría incluir uno sin el otro.

## D3 — Catálogo de permisos: naming y siembra

**Decision**: permisos **globales** (la tabla `permissions` NO lleva team FK en spatie teams;
solo `roles` y los pivotes la llevan), clave kebab-case `ver-{seccion}` con agrupación por
módulo en un catálogo PHP central (`App\Support\CatalogoPermisos`) que es la única fuente de
verdad. Seeder idempotente (`PermisosSeeder`, `firstOrCreate` + limpieza de cache de spatie)
re-ejecutable en cada deploy. Catálogo inicial (por módulo → permiso):

| Módulo | Permiso | Cubre (rutas/vistas) |
|---|---|---|
| Dashboard | `ver-dashboard` | dashboard |
| Control de fichaje | `ver-jornada` | jornada, calendario, miembros-equipo, horarios, asignaciones, correcciones, alertas |
| Clientes | `ver-clientes` | clientes, localidades |
| Stock | `ver-articulos` | articulos, unidades |
| Stock | `ver-stock` | stock (kardex) |
| Stock | `ver-proveedores` | proveedores |
| Stock | `ver-compras` | compras (+facturae compras) |
| Facturas | `ver-facturas` | facturas, pagos, facturae |
| POS | `ver-pos` | pos |
| Archivos | `ver-archivos` | archivos, carpetas |
| Marketing | `ver-campanas` | campañas |
| Marketing | `ver-plantillas-email` | plantillas-email |
| Usuarios | `ver-usuarios` | usuarios (aprobar/rechazar) |
| Roles | `ver-roles` | gestión de roles (nueva) |
| Configuración | `ver-configuracion` | configuracion (todas las pestañas) |
| Logs | `ver-logs` | logs de actividad |
| Bancos | `ver-bancos` | bancos, cuentas-bancarias |

Quedan **sin permiso** (todo usuario autenticado del tenant): perfil, fichar, mi-jornada,
logout — coherente con la assumption de la spec (secciones personales).

**Rationale**: granularidad = vista del sidebar (spec). Un permiso por *sección funcional*,
no por sub-ruta: las acciones internas (crear/editar) van con la sección en esta fase.
`ver-jornada` agrupa todo el bloque admin de fichajes porque hoy ya es un único gate
(`gestiona-fichajes`) — mantiene equivalencia de comportamiento (FR-010, SC-005).

**Alternatives considered**: permiso por ruta individual (~60 permisos) — granularidad que
nadie pidió, UI de checkboxes inmanejable; viola simplicidad (Principio V).

## D4 — Bypass del super admin

**Decision**: `Gate::before(fn (User $u) => $u->isSuperAdmin() ? true : null)` en
`AppServiceProvider::boot()`.

**Rationale**: patrón documentado por spatie; devuelve `null` (no `false`) para no
cortocircuitar el resto de checks. El super admin es central (sin `tenant_id`), nunca tiene
roles spatie. Las rutas `super_admin.*` conservan además su middleware `EnsureSuperAdmin`
(defensa en profundidad, sin cambios).

## D5 — Gate existente `gestiona-fichajes`

**Decision**: eliminar el `Gate::define('gestiona-fichajes', ...)` de `AppServiceProvider` y
reemplazar el middleware `can:gestiona-fichajes` del grupo de rutas por `can:ver-jornada`.

**Rationale**: si conviviera, habría dos fuentes de verdad. spatie registra sus permisos en
el mismo Gate, así que la sintaxis de rutas/Blade no cambia, solo el nombre.

## D6 — Provisión al crear tenant y migración de existentes

**Decision**:
1. **Alta nueva**: en `TenantController@store`, dentro de la transacción existente, crear
   `Role` "Administrador" con `tenant_id` del tenant nuevo + `syncPermissions(todo el
   catálogo)` + `assignRole` al usuario admin. Encapsulado en un servicio
   (`App\Support\ProvisionadorRoles`) para reutilizarlo en los 3 puntos (alta, migración,
   seeder de tests). Ojo: como el alta corre en contexto central, hay que fijar
   `setPermissionsTeamId($tenant->id)` temporalmente (y restaurar después) para que
   `assignRole` escriba el pivote con el team correcto.
2. **Tenants existentes**: migración de datos (`php artisan migrate`) que recorre tenants y
   crea su rol "Administrador" (todos los permisos → usuarios `rol=admin`) y rol "Usuario"
   base (sin permisos de gestión → usuarios `rol=usuario`), garantizando SC-005.
3. **Permisos nuevos futuros**: el seeder, tras sembrar el catálogo, sincroniza el rol
   "Administrador" de cada tenant con el catálogo completo (edge case de la spec: solo
   Administrador recibe permisos nuevos automáticamente).

**Alternatives considered**: listener del evento `TenantCreated` de stancl — válido, pero el
alta ya es explícita y transaccional en un solo controller; un listener escondería la lógica
sin ganancia (YAGNI).

## D7 — Enum `UserRole` y anti-lockout

**Decision**: conservar el enum solo para `SuperAdmin` (distinción central/tenant, ya usada
por `EnsureSuperAdmin` y el registro). Los checks `rol === UserRole::Admin` en sidebar,
rutas y controllers de tenant migran a permisos. El badge del sidebar
(`sidebar-user-role`) pasa a mostrar el nombre del rol spatie (o el label del enum para el
super admin). Anti-lockout (FR-006): en `RolController@update/destroy` y en la asignación de
roles, validar server-side que tras la operación quede ≥1 usuario activo del tenant con
`ver-roles` y `ver-usuarios`; el rol "Administrador" no permite quitarse esos dos permisos ni
eliminarse.

**Rationale**: quitar el enum del todo tocaría auth/registro/aprobaciones sin necesidad;
la spec ya lo asume ("deja de ser la fuente de verdad de acceso a vistas de tenant").

## D8 — Cache de permisos de spatie

**Decision**: usar el cache por defecto de spatie (store `database` ya configurado en el
proyecto) y limpiar (`app(PermissionRegistrar::class)->forgetCachedPermissions()`) en el
seeder y tras todo CRUD de roles.

**Rationale**: el cache de spatie es global (todos los teams juntos, se filtra en memoria),
compatible con hosting compartido (Principio V, sin Redis). Los cambios de rol se reflejan
"en la siguiente carga de página" (spec US1-3) porque el pivote usuario-rol se lee fresh.

## D10 — Rol por defecto para usuarios registrados (FR-014)

**Decision**: columna propia `es_defecto` (boolean) en la tabla `roles` de spatie; uno por
tenant garantizado server-side (transacción que desmarca el anterior). `RegisterController`
asigna ese rol al usuario recién creado. El rol "Usuario" de la migración queda marcado
como defecto inicial. Toggle desde la datatable de roles (`PATCH /roles/{rol}/defecto`).

**Rationale**: la alternativa (guardar `rol_defecto_id` en la configuración del tenant)
separa el dato de su entidad y complica el borrado (FK huérfana); una columna en `roles`
cae en cascada con el rol y se lista sin joins. spatie permite columnas extra en sus tablas
sin fricción (usa `Role::create` estándar).

**Alternatives considered**: `configuraciones.rol_defecto_id` — descartado por lo anterior;
convención por nombre ("Usuario" hardcodeado) — frágil si el tenant renombra o quiere otro.

## D11 — Landing sin permiso de dashboard (edge case spec)

**Decision**: la ruta `/` no usa middleware `can:`; `DashboardController@index` (o un
middleware ligero) redirige a `mi-jornada.index` si el usuario no tiene `ver-dashboard`.

**Rationale**: un 403 como primera pantalla tras login es un dead-end de UX; mi-jornada es
la sección personal garantizada para todo usuario de tenant.

## D9 — UI de gestión de roles

**Decision**: vista `resources/views/roles/index.blade.php` siguiendo el patrón NexaDash ya
establecido (referencia: `super_admin/tenants/index` y `usuarios/index`): cards informativas
arriba, datatable con checkboxes de selección, botón "Agregar" → modal con nombre + permisos
agrupados por módulo (checkboxes con "marcar módulo completo"), editar reutiliza el mismo
modal. Endpoints JSON del mismo controller (patrón `wantsJson()` existente). Aplicar el
override de paginación de datatables de `docs/04-front-guidelines.md` y toastr para
notificaciones. Skills de diseño (`frontend-design` / `ui-ux-pro-max`) se invocan en la fase
de implementación de la vista, según CLAUDE.md.
