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

El **registro público** (`RegisterController`) sigue la misma regla: el usuario nuevo se asigna
**siempre al tenant del dominio de la petición** (`tenant('id')`, ya inicializado por
`SetTenantContext`), nunca al "primer tenant que exista en la base". En el dominio central (sin
tenant resuelto) el registro devuelve 404, porque no hay empresa a la que unirse. El alta queda en
estado `Pendiente` / `activo=false` hasta que un administrador de ese tenant la aprueba. Regresión
cubierta por `tests/Feature/RegistroTest.php` (`test_registro_asigna_el_tenant_del_dominio_no_el_primero`,
`test_registro_sin_contexto_de_tenant_devuelve_404`).

El panel Super Admin (CRUD de tenants + su dominio) es la única área de la app que opera **fuera**
del scope de tenant (excepción explícita del Principio I de la constitución): consultas directas a
`tenants`/`domains` en contexto central, con filtrado explícito por `tenant_id` cuando necesita
mirar datos de un tenant concreto (p. ej. comprobar facturas emitidas antes de permitir el borrado).

---

## Decisión 5 — Envío de facturas por email: SMTP por tenant, envío síncrono (017-envio-facturas-email)

**Elegido:** cada tenant configura su propia cuenta SMTP (`App\Support\EmailTenant`, tabla
`configuraciones` grupo `email`); `App\Services\TenantMailer` arma un mailer `tenant_smtp` on-the-fly
con `Config::set('mail.mailers.tenant_smtp', ...)`, sin tocar el mailer `default` ni el `.env`. El
envío (email de prueba y envío de factura) ocurre **de forma síncrona dentro del request**: no hay
cola ni `ShouldQueue`.

**Deuda técnica asumida:** el hosting compartido (Decisión de infraestructura de más abajo) no
garantiza un worker/supervisor para `queue:work`, así que la opción más simple que cumple el
Principio V es enviar en el propio request — un email con un PDF adjunto completa muy por debajo del
timeout típico (~30s). **Migrar a envío en cola (`ShouldQueue` en `FacturaMail`/`EmailPrueba` + driver
`database` o similar) cuando:** (a) se mueva a VPS con supervisor de colas, o (b) el volumen de envíos
por tenant empiece a acercarse al timeout del request o a degradar la respuesta percibida del botón
"Enviar por email". Ver `specs/017-envio-facturas-email/research.md` (D5) para el detalle de
alternativas descartadas.

---

## Decisión 6 — Gestor documental: disco privado por tenant (019-gestor-documental)

**Elegido:** los documentos del gestor documental (`carpetas`/`archivos`) se guardan en un disco de
Laravel nuevo, `documentos` (driver `local`, root `storage_path('app/tenants')`, `visibility`
privado), distinto del disco `public` que ya usan logos/avatars. Los ficheros físicos viven en
`tenants/{tenant_id}/documentos/{uuid}.{ext}` — nombre físico generado (UUID), nunca el nombre
original — y **nunca se sirven por URL pública ni symlink**: toda descarga/preview pasa por
`ArchivoController@descargar`/`@preview`, que resuelve el modelo manualmente bajo el `TenantScope`
activo (nunca binding implícito, ver memoria `project_tenant_route_binding`) antes de devolver el
binario.

**Por qué un disco nuevo en vez de reusar `public`:** el disco `public` se sirve directo por el
webserver vía symlink (`storage/app/public` → `public/storage`) — cualquiera con la URL (aunque el
nombre sea un UUID "impredecible") accede al fichero sin pasar por la app, lo cual es aceptable para
un logo pero no para un documento de negocio potencialmente sensible de un tenant. El disco
`documentos` vive fuera de `public/`, así que solo la app puede leerlo.

**Contrapartida asumida:** sin CDN/caching de borde para estos ficheros (cada descarga pasa por PHP);
aceptable para el volumen esperado (documentos ligeros, ≤10 MB por defecto) en hosting compartido.

---

## Decisión 7 — Datatable server-side real para el log de actividad (021-logs-actividad-usuarios)

**Elegido:** el listado de `logs_actividad` implementa el protocolo server-side de DataTables a
mano en el controlador (`draw`/`start`/`length`/`search`/`order` → `recordsTotal`/`recordsFiltered`/
`data`), sin añadir `yajra/laravel-datatables`. Es el primer listado del proyecto que pagina en el
servidor: `usuarios`/`facturas` siguen cargando el catálogo completo del tenant de una vez y dejan
que DataTables filtre/pagine en el navegador.

**Por qué esta única tabla se desvía del patrón client-side:** `usuarios` y `facturas` están
acotados por el tamaño real del negocio del tenant; `logs_actividad` crece con cada login/logout y
cada alta/baja/modificación de 5 tipos de entidad y es append-only (sin purga), así que no tiene
techo natural. Cargar el histórico completo en el navegador degradaría con el tiempo. Se implementó
a mano en vez de añadir Yajra porque el protocolo es sencillo de replicar con Eloquent
(`skip`/`take`/`orderBy`/`where...like`) y una dependencia nueva para un solo listado no se
justifica (Principio V).

**Regla para features futuras:** un listado nuevo solo debe elegir server-side si, como
`logs_actividad`, no tiene cota natural de crecimiento por tenant. Si el listado es un catálogo de
negocio acotado (clientes, artículos, etc.), seguir el patrón client-side ya establecido.

---

## Decisión 8 — Leaflet vendorizado como única dependencia de mapas (024-control-horario-fichajes)

**Elegido:** el mapa interactivo de la pantalla de fichaje y del formulario de miembros usa
**Leaflet** (JS/CSS vendorizados en `public/vendor/leaflet/`, sin CDN) con tiles de OpenStreetMap.
Es la primera dependencia de mapas del proyecto. La captura de posición la hace la **Geolocation
API** del navegador (`navigator.geolocation`), no el mapa — el mapa es solo visualización y
selector de coordenadas (click/arrastre de marcador); el geofencing (Haversine) se calcula siempre
en backend (Principio III), nunca en el cliente.

**Por qué Leaflet y no Google Maps/Mapbox:** ambos exigen API key + cuenta de facturación externa,
fricción y coste innecesarios para un radio fijo por miembro (Principio V — hosting compartido,
simplicidad). Leaflet + OSM es gratis, sin cuenta ni límite de uso, y vendorizarlo evita depender
de un CDN externo en tiempo de ejecución.

**Contrapartida asumida:** los tiles de mapa (imágenes) sí se sirven desde `*.tile.openstreetmap.org`
(no se vendorizan, sería un volumen de almacenamiento desproporcionado). El proyecto no tiene CSP
implementada todavía; si se añade en el futuro, debe permitir `img-src` para ese host (ver
`specs/024-control-horario-fichajes/research.md` D3).

**Geocoding de direcciones (autocompletado del formulario de miembros):** el formulario de miembros
de equipo autocompleta las direcciones de trabajo y de casa con **Nominatim**
(`nominatim.openstreetmap.org`), el geocoder del mismo ecosistema OSM — gratis, sin API key (mismo
criterio que descartó Google/Mapbox para los tiles). Implementado en
`public/js/plugins-init/miembro-mapa.init.js`: autocompletado con debounce de 500 ms / mínimo 4
caracteres / aborto de la petición previa (política de uso de Nominatim: máx. 1 req/s), más *reverse
geocoding* al fijar un punto por clic/arrastre en el mapa. **Es una segunda llamada a host externo de
esta feature** (además de los tiles) y, a diferencia de los tiles, **envía datos personales**: la
`casa_direccion` del miembro (dato ya marcado como purgable por RGPD en `docs/03-modelo-datos.md`) se
transmite a Nominatim al teclear. Asumido por coherencia con la decisión de tiles; si se añade CSP,
debe permitir `connect-src` para ese host. El geocoding es solo una ayuda de entrada: las
coordenadas y el geofencing (Haversine) se siguen calculando/validando en backend (Principio III).

---

## Infraestructura (estado actual)
- **Hosting:** compartido (cPanel/Hostinger) como punto de partida.
- **Camino de escalado:** VPS (Hostinger/DigitalOcean/Hetzner) + Laravel Forge/Ploi cuando el tráfico lo exija.
