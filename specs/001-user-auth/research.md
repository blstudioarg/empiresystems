# Research: Autenticación de usuarios (login multi-tenant)

**Feature**: 001-user-auth · **Date**: 2026-07-02

Decisiones técnicas tomadas para resolver los puntos abiertos del Technical Context. Verificado
contra el código instalado (`vendor/stancl/tenancy` v3.10.0, Laravel 12 skeleton) y los assets
reales del template en `template/Laravel-NexaDash-v1.0-28_May_2025/package/`.

---

## D1. Mecanismo de autenticación: sesiones nativas, sin starter kit

**Decision**: implementar login/logout a mano con el guard `web` de Laravel (`Auth::attempt`,
sesiones en driver `database` ya configurado), un único `LoginController` y validación por Form
Request o inline. Sin Breeze, Jetstream ni Fortify.

**Rationale**:
- El skeleton actual no trae scaffolding de auth; los starter kits arrastran registro público,
  verificación de email, reset de password y vistas propias — todo fuera de alcance (FR-011) y
  habría que desmontarlo (viola Principio V, YAGNI).
- `Auth::attempt($credentials, $remember)` cubre login + "Recordarme" (FR-001, FR-005) de forma
  nativa; `Auth::logout()` + invalidación de sesión cubre FR-004.
- El requisito de credenciales extra (`activo = true`) se resuelve pasando la condición en el
  array de `attempt()`; el estado del tenant se verifica tras autenticar (ver D4).

**Alternatives considered**:
- *Laravel Breeze*: scaffolding con Blade, pero incluye registro/reset/perfil que habría que
  borrar; sus vistas usan Tailwind (el proyecto usa Bootstrap/NexaDash). Rechazado.
- *Fortify (headless)*: agrega configuración y features desactivables; más superficie que valor
  para un login simple. Rechazado.

## D2. Throttling de login

**Decision**: usar `RateLimiter` de Laravel con un límite propio: 5 intentos fallidos por
combinación `email + IP`, decaimiento de 60 segundos. Al superar el límite, error 429/mensaje de
"demasiados intentos" con tiempo restante.

**Rationale**: cumple FR-009 y SC-005 con el componente estándar del framework (cache store ya
configurado en driver `database`); sin paquetes extra. Es el mismo patrón del viejo trait
`ThrottlesLogins`.

**Alternatives considered**: middleware `throttle:` genérico por ruta (no distingue
email+IP, menos preciso); paquetes de terceros (innecesario). Rechazados.

## D3. Integración stancl/tenancy en single-database con identificación por usuario

**Decision**:
- Publicar `config/tenancy.php` y registrar `TenancyServiceProvider`.
- `tenancy.tenant_model` → `App\Models\Tenant`, que extiende
  `Stancl\Tenancy\Database\Models\Tenant` con: `$incrementing = true`, `$keyType = 'int'`,
  `id_generator` en `null` (IDs autoincrement BIGINT, coherentes con docs/03-modelo-datos.md),
  y `getCustomColumns()` declarando las columnas reales de la tabla (lo no declarado iría a la
  columna JSON `data`, que se crea nullable para satisfacer el `VirtualColumn` del paquete).
- **Sin bootstrappers**: en single-database no se necesita `DatabaseTenancyBootstrapper`, ni
  cache/filesystem/queue bootstrappers por ahora → lista `bootstrappers` vacía. Inicializar
  tenancy solo establece el tenant actual en el contenedor.
- **Identificación manual por sesión**: middleware propio `SetTenantContext` (no los middleware
  de dominio del paquete): si hay usuario autenticado con `tenant_id`, ejecuta
  `tenancy()->initialize($user->tenant)`; si es super_admin (tenant_id null), no inicializa
  (contexto central). Se registra al final del grupo `web`.
- Los modelos de negocio futuros usarán el trait `BelongsToTenant` del paquete (verificado en
  vendor: aplica `TenantScope` global y autocompleta `tenant_id` al crear cuando tenancy está
  inicializado) — exactamente el mecanismo que exige el Principio I.

**Rationale**: docs/01-arquitectura.md (Decisión 2) y la constitución nombran stancl/tenancy como
paquete elegido; ya está instalado. El modo single-DB con inicialización manual es un patrón
soportado oficialmente y no ata la identificación a dominios/subdominios (el SaaS corre en un
solo dominio). Deja la puerta abierta a multi-DB en VPS sin cambiar modelos.

**Alternatives considered**:
- *Global scope artesanal (sin paquete)*: más simple hoy, pero duplica lo que el paquete ya
  resuelve (scope + autofill + contexto) y se aparta de la decisión documentada. Rechazado.
- *Middleware de identificación por dominio del paquete*: no aplica; el tenant se deriva del
  usuario autenticado, no del host. Rechazado.

## D4. Reglas de acceso: usuario inactivo y tenant inactivo

**Decision**:
- `activo = true` viaja como condición extra en `Auth::attempt()` → usuario inactivo falla igual
  que una contraseña errónea (mensaje genérico, FR-002/FR-008).
- Tras autenticar, si el usuario tiene tenant y `tenant->activo === false` → logout inmediato +
  mismo mensaje genérico. (Chequeo post-attempt porque `attempt()` no soporta condiciones sobre
  relaciones.)
- El middleware `SetTenantContext` repite la verificación de tenant activo en cada request: si el
  tenant fue desactivado a mitad de sesión, se cierra la sesión y se redirige al login (edge case
  de la spec).

**Rationale**: mantiene el mensaje de error indistinguible (no filtra existencia de cuentas) y
cubre la suspensión de clientes del SaaS en caliente.

## D5. Vista de login y layout guest

**Decision**:
- Crear `resources/views/layouts/guest.blade.php` replicando el rol de `layouts/fullwidth` del
  template (body `vh-100`, sin sidebar/header), pero con el head/footer de assets explícito que
  ya usa nuestro `layouts/app.blade.php` (style.css + global.min.js etc.), sin el sistema
  `config/dz.php`.
- `resources/views/auth/login.blade.php` parte de `package/resources/views/page-login.blade.php`
  adaptada: textos en español, branding Empire Systems (SVG del nav-header + nombre, porque
  `logo-full.png` **no existe** en el package del template — verificado), sin bloque "Or continue
  with"/social, sin link "Not registered?/Register", sin link "Forgot Password?" (Assumption de
  la spec), CSRF, `old('email')`, errores de validación en español y checkbox "Recordarme".
- Copiar `package/public/images/login.png` (ilustración de la columna derecha) a
  `public/images/login.png`.
- La clase CSS `authincation` y estilos de `.login-form`/`.pages-left` ya están en
  `public/css/style.css` (verificado) — no hace falta CSS adicional.

**Rationale**: FR-010; reutiliza el shell visual ya montado y mantiene los partials del template
como fuente de verdad visual.

## D6. Roles

**Decision**: enum PHP respaldado por string — `App\Enums\UserRole` (`super_admin`, `admin`,
`usuario`) + columna `rol` `varchar` con default `usuario` y cast a enum en el modelo `User`.
Helper `isSuperAdmin()` en el modelo. Sin sistema de permisos (spatie/permission etc.) en esta
feature (FR-007 solo almacena y expone el rol).

**Rationale**: docs/03-modelo-datos.md define exactamente esos tres roles; un enum nativo es lo
más simple que cumple. String en DB (no enum MySQL) para poder agregar roles sin ALTER.

## D7. Logging de actividad de autenticación (FR-014)

**Decision**: listener único `LogAuthenticationActivity` suscrito a los eventos nativos
`Illuminate\Auth\Events\{Login, Logout, Failed, Lockout}`, escribiendo en el log estándar con
email (sin contraseñas) e IP.

**Rationale**: los eventos ya los dispara el framework en todos los caminos (incluido remember
me); un listener centraliza el formato y no ensucia el controller.

## D8. Seeder de acceso inicial (FR-012)

**Decision**: `AuthSeeder` idempotente (usa `firstOrCreate` por email/nif) que crea:
- super_admin: `admin@empiresystems.es` / password de desarrollo documentada en README, `rol =
  super_admin`, `tenant_id = null`.
- Tenant demo: "Empresa Demo SL" (activo).
- Admin del tenant demo: `demo@empiresystems.es` / password de desarrollo, `rol = admin`.
`DatabaseSeeder` lo invoca. Credenciales y advertencia de cambiarlas en producción → README.

**Rationale**: FR-012/SC-004; idempotente para poder re-ejecutar `migrate:fresh --seed` o `db:seed`
sin duplicados.

## D9. Estrategia de tests (Principio IV — test-first)

**Decision**: feature tests con SQLite `:memory:` (ya configurado en phpunit.xml) escritos
**antes** de implementar, cubriendo: login OK (redirige a `/`, sesión autenticada), credenciales
inválidas (mensaje genérico, sigue guest), usuario inactivo, tenant inactivo (login y a mitad de
sesión), throttling al 6º intento, remember me (cookie recordable), protección de rutas (guest →
redirect a login), logout (sesión invalidada + back no accesible), contexto de tenant
inicializado con el tenant correcto tras login, super_admin sin contexto inicializado, y seeder
(las credenciales sembradas autentican). Factories: `TenantFactory` + estados en `UserFactory`
(`superAdmin()`, `admin()`, `inactive()`).

**Rationale**: la constitución exige Red-Green-Refactor en auth/tenant scoping; SQLite in-memory
hace la suite rápida y sin tocar la DB de desarrollo.
