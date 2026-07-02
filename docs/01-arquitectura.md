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

## Infraestructura (estado actual)
- **Hosting:** compartido (cPanel/Hostinger) como punto de partida.
- **Camino de escalado:** VPS (Hostinger/DigitalOcean/Hetzner) + Laravel Forge/Ploi cuando el tráfico lo exija.
