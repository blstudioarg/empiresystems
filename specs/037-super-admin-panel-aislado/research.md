# Phase 0 — Research: Panel de Super Admin aislado + home de estadísticas

Fecha: 2026-07-27. Feature: `037-super-admin-panel-aislado`.

Todas las incógnitas de Technical Context quedan resueltas aquí; no hay NEEDS CLARIFICATION abiertos.

---

## D1 — Dónde y cómo se corta el acceso del Super Admin al área de tenant

**Contexto (causa raíz verificada en código, no supuesta):**

- `app/Providers/AppServiceProvider.php:35` → `Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null)`.
  El Super Admin pasa **todos** los `can:` del catálogo (cubierto hoy por
  `tests/Feature/SuperAdminBypassTest.php`, que lo afirma como comportamiento deseado).
- `routes/web.php:63` → todo el área de tenant cuelga de
  `Route::middleware(['tenant.context', 'auth'])->group(...)`, con `can:ver-*` por sección.
- `SetTenantContext` en dominio central **no inicializa tenancy** y sigue adelante (es lo correcto:
  el panel de super admin vive en ese grupo de dominios).

Resultado: Super Admin autenticado en el dominio central + `Gate::before` que aprueba cualquier
`can:` ⇒ `/clientes`, `/facturas`, `/configuracion`… responden 200 sin tenant activo. Ese es
exactamente el síntoma reportado.

**Decisión:** middleware nuevo `App\Http\Middleware\BloquearSuperAdminAreaTenant` (alias
`sin_super_admin`), añadido al **grupo** `['tenant.context', 'auth']` de `routes/web.php`, con una
allowlist mínima de nombres de ruta.

- Navegación de pantalla (`! $request->expectsJson()`) → `redirect()->route('super_admin.home')`
  con `->with('warning', ...)` (toast por `partials/flash-toastr.blade.php`, convención de CLAUDE.md).
- Petición AJAX/JSON (`$request->expectsJson()`) → `response()->json(['message' => ...], 403)`, que
  el front ya sabe mostrar con `window.showToast('danger', ...)`.
- Allowlist: `logout`, `profile.*`, `localidades.index`. Nada más.

**Rationale:** ir al **grupo** y no a cada sección es lo que hace que FR-002/SC-002 se cumplan solos:
cualquier ruta futura que se añada dentro de ese grupo (que es donde se añaden todas) nace
bloqueada, sin que nadie tenga que acordarse. Es una regla de contexto ("este usuario no tiene
tenant"), no de permiso, así que vive en el middleware y no en el gate.

**Alternativas descartadas:**

| Alternativa | Por qué no |
|---|---|
| Restringir `Gate::before` para que el Super Admin no pase los `can:ver-*` | Rompe `SuperAdminBypassTest` y el propio panel (`ProvisionadorRoles`, comprobaciones internas), y **no cubre** las rutas del grupo que no llevan `can:` (`/`, `/perfil`, `/localidades`). Deja agujeros. |
| Comprobación `abort_if($user->isSuperAdmin())` en cada controller | Viola FR-002: cada sección futura tendría que acordarse. Es el patrón que produjo el bug actual. |
| Bloquear en `SetTenantContext` (si no hay tenant y el usuario es super admin → cortar) | Mezcla dos responsabilidades en un middleware que también sirve al panel de super admin (mismo dominio central) y obligaría a distinguir prefijos de ruta ahí dentro. Middleware separado y explícito es más simple de leer y de testear. |
| Segundo grupo de dominios / subdominio propio para el panel | Cambio de infraestructura y de `config/tenancy.php` para un problema que se resuelve con un middleware. Contra Principio V. |

**Nota sobre el orden de middleware:** `sin_super_admin` va **después** de `auth` en el grupo (el
middleware asume usuario autenticado; sin sesión, `auth` ya redirige a login).

---

## D2 — Simetría de dominio (FR-006)

**Decisión:** no se añade nada nuevo. `SetTenantContext` ya inicializa el tenant por host y
`LoginController` ya exige que el usuario autentique en el dominio de su propio tenant (y el Super
Admin solo en el central, decisión D4 de `docs/01-arquitectura.md`). Lo que sí se añade es
**cobertura de test explícita** del escenario 6 de US1: sesión de Super Admin presentada en un
dominio de tenant no obtiene pantallas de ese tenant — con el middleware nuevo el corte es
independiente del host, así que la garantía queda por partida doble.

**Rationale:** FR-006 es una regresión a proteger, no funcionalidad nueva. Evitamos tocar el login
(Principio V).

---

## D3 — Aterrizaje del Super Admin tras login

**Decisión:** no se toca `LoginController` (hoy `redirect()->intended('/')`). La raíz `/` pertenece al
grupo del área de tenant, así que el propio middleware de D1 la intercepta y manda el Super Admin a
`super_admin.home`. Adicionalmente se **elimina** la rama `if ($request->user()->isSuperAdmin())` de
`DashboardController@index` (queda inalcanzable) para no dejar dos fuentes de verdad del aterrizaje.

**Alternativa descartada:** añadir la lógica en `LoginController`/`ResolvedorLanding` — dejaría el
aterrizaje correcto solo para el camino "acabo de loguearme", y cualquier otra llegada a `/`
(marcador, logo del sidebar) seguiría dependiendo del middleware igualmente. Una sola puerta.

---

## D4 — Registro de los intentos bloqueados (FR-008)

**Contexto:** `logs_actividad.tenant_id` es **NOT NULL** (`database/migrations/2026_07_04_000000_create_logs_actividad_table.php:13`)
y la tabla es de negocio, con `TenantScope` y listado por tenant en `/logs`. Un intento bloqueado del
Super Admin **no pertenece a ningún tenant**: no hay `tenant_id` que poner, y meterlo con un tenant
inventado contaminaría el log que ve un cliente. Además `AccionLogActividad` no tiene un caso para
"acceso denegado a sección" y añadirlo repercutiría en la UI de `/logs` de todos los tenants.

**Decisión:** los intentos bloqueados se registran en el **log de aplicación** de Laravel
(`Log::warning('super_admin.acceso_area_tenant_bloqueado', [...])`) con: id y email del usuario,
método y URI solicitados, nombre de ruta, IP y user-agent. **No** se escriben en `logs_actividad` ni
se altera esa tabla.

**Rationale:** cumple el espíritu del Principio II (registro de accesos denegados con IP y
user-agent) sin migrar una tabla de negocio para un evento que es del SaaS, no de un tenant.
Principio V: cero migraciones, cero cambios en `/logs`.

**Consecuencia sobre la spec:** FR-008 y SC-008 se reformulan para hablar de "registro de
aplicación" en vez de "el registro de actividad consultable en `/logs`". Actualizado en `spec.md` en
el mismo cambio.

**Alternativa descartada:** hacer `tenant_id` nullable en `logs_actividad` + nuevo caso de enum.
Migración destructiva de índices, cambio de semántica de una tabla auditada, y un tipo de evento que
ningún tenant debería ver — desproporcionado.

---

## D5 — Fuente de los indicadores de la home

Todo sale de datos existentes; **cero tablas y cero columnas nuevas** (FR "sin datos nuevos" de las
Assumptions). Servicio nuevo `App\Services\EstadisticasTenants`, espejo estructural de
`App\Services\DashboardEstadisticas`.

| Indicador | Fuente | Nota |
|---|---|---|
| Total / activos / inactivos | `tenants` (`activo`) | Contexto central: `Tenant` no lleva `TenantScope`. |
| Altas del mes en curso | `tenants.created_at` | Mes natural (Assumption de spec). |
| Total de usuarios | `users` con `tenant_id` no nulo | El Super Admin no se cuenta. |
| Evolución de altas 12 meses | `tenants.created_at` agrupado por mes | Meses sin altas rellenados a 0 en PHP, no en SQL (portabilidad MySQL/MariaDB). |
| Últimos tenants creados | `tenants` + `domains`, `orderByDesc('created_at')->limit(5)` | Enlaza a la gestión del tenant. |
| Ranking por tamaño | `users` agrupado por `tenant_id` + `facturas` (estado ≠ borrador) agrupado por `tenant_id` | **Agregados**, nunca detalle de negocio (FR-019). |
| Requieren atención | (a) `activo = false`; (b) sin `users` aprobados+activos; (c) sin `logs_actividad` en 30 días | Un tenant puede aparecer por varios motivos; se muestra el conjunto de motivos. |

**Decisión de rendimiento:** los recuentos por tenant se resuelven con **consultas agregadas
agrupadas** (`selectRaw('tenant_id, count(*)')->groupBy('tenant_id')`) y se cruzan en PHP contra la
colección de tenants — nunca una consulta por tenant dentro de un bucle (N+1). Con 50–80 tenants el
total queda en ~6 consultas fijas, holgadamente dentro de SC-006.

**Alternativa descartada:** tabla de métricas precalculada / caché. Contra FR-014 (datos vivos) y
contra Principio V para el volumen esperado.

---

## D6 — Convenciones de front que condicionan el diseño (docs/04-front-guidelines.md)

Leídas antes de escribir este plan (REGLA DE ORO de `CLAUDE.md`). Las que aplican y cómo:

1. **"Listados: SIEMPRE DataTable, nunca una `<table>` plana"** — aplica al **listado de gestión de
   tenants**, que ya es DataTable y no se toca. **No** aplica a los bloques de resumen de la home
   ("últimos tenants creados", "requieren atención", "ranking por tamaño"): son *widgets de
   dashboard* de tamaño fijo (top-5), no el listado de registros del módulo, exactamente igual que
   "Facturas recientes" / "Top clientes" / "Alertas de stock" en
   `resources/views/partials/dashboard-contenido.blade.php`, que ya usan `@foreach` sobre una
   `<table class="table table-borderless">`. **Se documenta como excepción explícita en
   `docs/04-front-guidelines.md`** (tarea de la feature) para que la regla no vuelva a leerse como
   "todo `<table>` es un bug".
2. **Icono flotante en cards de métricas** — el `<x-lordicon>` va en un `<div>` **sin** clases
   (nunca `icon-box bg-primary-light`), `size="50"`, `trigger="hover"`, `target=".card"`.
3. **Colores de los lordicon** — no se pasa `colors` a mano; lo resuelve el componente.
4. **Gap `.row` / `margin-bottom` de `.card`** — no añadir `mb-3`/`g-3` a las columnas; ya es global.
5. **Tamaño "sm" por defecto** — no añadir `.btn-sm`/`.form-control-sm`.
6. **Modales centrados** — si la home abre algún modal, `modal-dialog-centered` siempre.
7. **Notificaciones** — el aviso del bloqueo es un flash de sesión (`warning`) que
   `partials/flash-toastr.blade.php` convierte en toast; nunca un `.alert` ad-hoc.
8. **Override de paginación "Anterior/Siguiente"** — solo relevante si se añade un DataTable; la
   home no añade ninguno (el de tenants ya lo tiene).
9. **Ayuda contextual** — la home nueva declara `@section('ayuda-titulo')` + `@section('ayuda')` con
   `resources/views/ayuda/super-admin-panel.blade.php` (capa 3 de la regla de documentación).
10. **Nueva entrada de menú ⇒ nuevo permiso** — **no aplica** al menú del Super Admin: su rama del
    `sidebar.blade.php` está fuera de `CatalogoMenu` por diseño (feature 036) y su acceso se rige por
    `EnsureSuperAdmin`, no por permisos de tenant. Se añade la entrada "Inicio" a esa rama a mano,
    sin tocar el catálogo ni `CatalogoPermisos`.

---

## D7 — Gráfico de evolución de altas

**Decisión:** Chart.js, ya vendorizado en `public/vendor/chartjs/` y en uso por el dashboard del
tenant (`public/js/plugins-init/dashboard*.js`, `new Chart(...)`). Gráfico de barras, 12 puntos, datos
serializados por el controller en el JSON que consume el init JS de la vista.

**Alternativa descartada:** Morris/Raphael (también vendorizados, pero legacy del template y sin uso
en pantallas nuevas) y cualquier librería nueva (Principio V).

---

## D8 — Alcance de la ayuda in-app y de la base de conocimiento del asistente IA

El asistente IA **ya no se emite para el Super Admin**: verificado en
`resources/views/partials/asistente-chat.blade.php`, la condición de render incluye
`! $usuarioIa->isSuperAdmin()`. No hay nada que hacer ahí. Aun así, `resources/ia/conocimiento/` describe la app al usuario de tenant: esta
feature **no** añade ni cambia ninguna pantalla de tenant, así que la capa 4 se resuelve con una nota
breve, no con un archivo nuevo. Se decide en `tasks.md` con una tarea explícita de verificación para
no saltarse la regla por omisión.
