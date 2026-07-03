# Research — Panel Super Admin y resolución de tenant por dominio

Decisiones de diseño previas a la implementación. Cada una: decisión, motivo, alternativas
descartadas.

## D1 — Almacenamiento del dominio: tabla `domains` de stancl (no columna en `tenants`)

**Decisión**: usar la tabla `domains` estándar de `stancl/tenancy` (modelo `Domain::class`, ya
configurado en `config/tenancy.php`), con una relación `Tenant hasMany Domain` pero **restringida a
1 dominio por tenant** a nivel de aplicación (validación de unicidad + regla de negocio). La
columna `domain` de esa tabla ya es única.

**Motivo**:
- `config/tenancy.php` ya cablea `domain_model => Domain::class`; es el camino idiomático del
  paquete y deja la puerta abierta a alias multi-dominio en el futuro sin migración destructiva.
- La resolución por host contra la tabla `domains` es lo que el paquete espera; evita reinventar un
  lookup ad-hoc sobre una columna en `tenants`.
- Coste bajo: una migración estándar del paquete.

**Alternativas descartadas**:
- **Columna `domain` en `tenants`**: más simple a primera vista, pero contradice la config ya
  presente (`Domain::class`), obliga a un lookup manual y cierra la puerta a multi-dominio. La
  ganancia de simplicidad es marginal frente a salirse del patrón del paquete.

## D2 — Resolución del tenant activo: por host de la petición (modifica `SetTenantContext`)

**Decisión**: cambiar la fuente de verdad del tenant activo de "el `tenant_id` del usuario
autenticado" a "**el host de la petición**". El middleware (evolución de `SetTenantContext`, o un
middleware nuevo que lo reemplaza en el grupo web):

1. Toma el host del request.
2. Si el host está en `central_domains` → **contexto central** (no se inicializa tenancy). Solo el
   área super_admin es válida aquí.
3. Si el host corresponde a un registro de `domains` → resuelve su tenant. Si el tenant no está
   `activo` → aborta / login (según autenticación). Si está activo → `tenancy()->initialize($tenant)`.
4. Si el host no es central ni corresponde a ningún dominio → **404/abort controlado** (no se
   expone ningún tenant), según FR-005.

**Motivo**: es el requisito central (FR-001) y el modo idiomático de stancl. Desacopla el contexto
de tenant de la sesión de usuario, lo que refuerza el aislamiento (Principio I).

**Alternativas descartadas**:
- **Middleware `InitializeTenancyByDomain` de stancl tal cual**: válido, pero el proyecto ya tiene
  `SetTenantContext` con lógica propia (gate de `activo`, headers no-cache, logout forzado). Se
  reutiliza esa lógica añadiéndole la resolución por host, en vez de introducir dos middlewares que
  se pisen. Se puede apoyar internamente en las utilidades del paquete para el lookup.
- **Mantener resolución por `tenant_id` del usuario**: incompatible con el requisito (el dominio
  debe mandar aunque no haya sesión) y más débil en aislamiento.

## D3 — Gate login↔dominio: el usuario debe pertenecer al tenant del dominio

**Decisión**: en el login (y en cada request autenticado), si hay un tenant resuelto por dominio, el
`tenant_id` del usuario autenticado DEBE coincidir con ese tenant; si no, se deniega/cierra sesión.
El `super_admin` (`tenant_id` null) solo es válido en contexto central (sin tenant de dominio).

**Motivo**: FR-006 y la decisión de producto "dominio manda y valida". Evita que un usuario de un
tenant use la sesión en el dominio de otro.

**Alternativas descartadas**:
- **Dominio solo informativo**: descartado explícitamente por el usuario en la fase de clarificación.

## D4 — Área super_admin: prefijo `super_admin`, dominio central, guard por rol

**Decisión**: rutas del panel bajo el prefijo `super_admin`, en un grupo restringido a los
`central_domains` y protegido por un middleware nuevo `EnsureSuperAdmin` que exige usuario
autenticado con rol `super_admin` (`tenant_id` null). Fuera de esas condiciones → 403/redirect a
login.

**Motivo**: FR-007/008/009 + decisión "panel en el dominio central". Aísla el área de gestión del
SaaS del área de negocio de los tenants.

**Alternativas descartadas**:
- **Panel accesible desde cualquier dominio**: rompe el modelo de dominio→tenant y confunde el
  contexto; descartado por decisión de producto.
- **Gate por policy en cada acción**: más disperso; un middleware de grupo es más simple y auditable
  (Principio V).

## D5 — Regla de eliminación: bloquear si hay facturas emitidas

**Decisión**: al eliminar un tenant, comprobar en backend si tiene alguna factura en estado
`emitida` (o cualquier estado no-borrador que implique inmutabilidad fiscal). Si las tiene → se
impide y se ofrece desactivar (`activo=false`). Si no → se elimina.

**Motivo**: Principio II (inmutabilidad Verifactu) + decisión de producto. Nunca se pierden datos
fiscales por una operación de gestión.

**Detalle técnico**: la consulta de facturas del tenant se hace desde el contexto central
(super_admin), donde el global scope de `BelongsToTenant` no está activo; hay que filtrar
explícitamente por `tenant_id` del tenant objetivo (p. ej. inicializando temporalmente el tenant o
consultando con filtro explícito) para no depender del scope. Se cubre con test.

**Alternativas descartadas**:
- **Soft-delete siempre**: simple pero deja "tenants fantasma"; el usuario prefirió permitir el
  borrado real cuando no hay facturas.
- **Hard-delete con cascada**: riesgo de cumplimiento; descartado.

## D6 — Normalización del dominio

**Decisión**: normalizar el dominio antes de guardar/comparar: `trim`, minúsculas, quitar esquema
`http(s)://`, quitar path/query y barra final. Comparaciones y unicidad sobre el valor normalizado.

**Motivo**: FR-003 + edge case (mismo dominio con distinto casing/espacios/protocolo debe tratarse
como uno solo).

**Alternativas descartadas**:
- **Guardar tal cual lo teclea el super_admin**: provoca duplicados lógicos y fallos de resolución
  por host.

## D7 — Unicidad de dominio ante concurrencia

**Decisión**: la unicidad se garantiza a nivel de datos (índice único de la columna `domain` en la
tabla `domains`), no solo por validación de aplicación. La validación de FormRequest da el mensaje
amable; el índice único es la red de seguridad ante altas concurrentes (FR-002, SC-005).

**Motivo**: dos altas simultáneas con el mismo dominio no deben poder coexistir.

**Alternativas descartadas**:
- **Solo validación de aplicación**: hay ventana de carrera entre el check y el insert.
