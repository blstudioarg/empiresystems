# Arquitectura y decisiones técnicas

## Decisión 1 — Multi-tenancy: base de datos compartida

**Elegido:** base de datos **única compartida**, con columna `tenant_id` en cada tabla de negocio y filtrado automático por el tenant activo (global scope de Eloquent).

**Descartado:** database-per-tenant (una base por cliente).

### Por qué
- **Volumen esperado:** 50–80 tenants, miles de registros cada uno → orden de magnitud de ~1–2 millones de filas en la tabla más grande. Para MySQL con índices correctos es un volumen bajo; no es el cuello de botella.
- **Hosting compartido:** el usuario de MySQL en cPanel/Hostinger **no tiene permiso para crear bases de datos** por código, e imponen prefijos y límites. El database-per-tenant requeriría un VPS. La base compartida corre en cualquier hosting.
- **Alta de tenant instantánea:** el Super Admin crea un tenant insertando filas, sin tocar MySQL ni el panel del hosting.
- **Reportes cross-tenant** (uso global del SaaS) son triviales con una sola base.

### Contrapartidas asumidas
- El aislamiento es **lógico**, no físico → hay que blindar el filtrado por `tenant_id` (global scopes + tests) para evitar fugas entre clientes.
- Backups por cliente individual son más costosos (se resuelve con export por tenant).

### Regla de oro
El límite de crecimiento del hosting compartido es el **tráfico concurrente (CPU/RAM/conexiones)**, no el volumen de datos. Cuando ese límite llegue, la solución es mover el hierro a un **VPS**, sin rehacer la arquitectura de datos.

---

## Decisión 2 — Paquete de multi-tenancy

Aunque usemos base compartida, conviene apoyarse en un paquete maduro para el scoping y el cambio de contexto de tenant.

- **Candidato principal:** `stancl/tenancy` (v3.10+, compatible con Laravel `^10|^11|^12|^13`). Soporta modo single-database (con traits de scoping por modelo) y multi-database, por si en el futuro se migra a VPS con database-per-tenant.
- **Alternativa:** `spatie/laravel-multitenancy`.

> Nota: la decisión de base compartida no cierra la puerta a database-per-tenant en el futuro; `stancl/tenancy` permite convivir/migrar entre ambos modos.

---

## Decisión 3 — Separación "central" vs "tenant"

Aun en base compartida, conviene separar conceptualmente:

- **Datos globales del SaaS (central):** tenants, planes, suscripciones, usuarios-dueños, facturación del propio SaaS.
- **Datos de negocio (por tenant):** clientes, facturas, líneas, series, pagos, etc. → todos con `tenant_id`.

---

## Decisión 4 — Resolución de tenant por dominio + panel Super Admin (007-super-admin-tenants)

**Elegido:** el tenant activo se resuelve por el **host de la petición**, no por el `tenant_id` del
usuario autenticado. Cada tenant tiene un registro 1:1 en la tabla `domains` (estándar de
`stancl/tenancy`, modelo `Domain::class`, grupo central). El middleware `SetTenantContext`
consulta `domains` por host en cada request:

- Host en `central_domains` (`config/tenancy.php`) → contexto central, sin tenant. Es el único
  contexto donde vive el panel `super_admin` (prefijo de ruta, guard `EnsureSuperAdmin`: rol
  `super_admin` + `tenant_id` null + dominio central).
- Host con registro en `domains` → `tenancy()->initialize($tenant)` (o corta el acceso si el
  tenant está `activo=false`).
- Host sin registro ni central → 404 controlado (nunca expone qué tenants existen).

El login se refuerza en consecuencia: un usuario solo autentica desde el dominio de **su propio**
tenant (`user.tenant_id == tenant del dominio resuelto`); `super_admin` solo autentica en el
dominio central. Detalle de la decisión y alternativas descartadas en
`specs/007-super-admin-tenants/research.md` (D1–D7).

El panel Super Admin (CRUD de tenants + su dominio) es la única área de la app que opera **fuera**
del scope de tenant (excepción explícita del Principio I de la constitución): consultas directas a
`tenants`/`domains` en contexto central, con filtrado explícito por `tenant_id` cuando necesita
mirar datos de un tenant concreto (p. ej. comprobar facturas emitidas antes de permitir el borrado).

---

## Infraestructura (estado actual)
- **Hosting:** compartido (cPanel/Hostinger) como punto de partida.
- **Camino de escalado:** VPS (Hostinger/DigitalOcean/Hetzner) + Laravel Forge/Ploi cuando el tráfico lo exija.
