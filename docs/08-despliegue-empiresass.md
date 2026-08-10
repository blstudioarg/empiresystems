# Despliegue en el servidor de pruebas (empiresass.gestionley.com)

Este documento es la referencia técnica de cómo está desplegada la app en el hosting cPanel
compartido de `empiresass.gestionley.com` (dominio del panel de super admin) y sus subdominios de
tenant (ej. `pruebapos.gestionley.com`). Es un servidor de **pruebas**, no el destino final de
producción real (ese sigue siendo Railway, ver `05-despliegue-railway.md`), pero se trabaja sobre
él de forma continua, así que el procedimiento tiene que ser rápido y repetible.

**Credenciales de FTP, base de datos, SSH y accesos de la app**: siempre en `ftp.txt` (raíz del
repo, gitignored). Este documento no las repite.

**Procedimiento accionable paso a paso**: skill `.claude/skills/deploy-empiresass/SKILL.md`. Este
doc es el porqué de cada paso; el skill es el cómo, para que Claude Code lo seudo-automatice sin
tener que redescubrir todo esto cada vez.

## Topología

- Un único código de Laravel vive en `/home/gestionley/empiresass.gestionley.com/`.
- El **document root** del dominio y de TODOS los subdominios de tenant apunta a
  `empiresass.gestionley.com/public` (la misma carpeta física). No es "un subdominio = una
  instalación de Laravel distinta" — es una sola instalación, y `stancl/tenancy` resuelve el
  tenant por el header `Host` de la petición contra la tabla `domains`.
- Alta de un tenant nuevo (manual, sin wildcard todavía — ver `00-vision.md`/`03-modelo-datos.md`
  si eso cambia): 1) crear el subdominio en cPanel → Domains, con Document Root
  `empiresass.gestionley.com/public` (**no** la carpeta por defecto que sugiere el formulario);
  2) crear el tenant desde el panel de super admin, que internamente crea la fila en `domains`.
  El orden entre 1 y 2 no importa, pero ambos tienen que existir antes de visitar la URL.

## Particularidades de este hosting (todas costaron tiempo de debug, no repetir)

1. **PHP**: LiteSpeed, no Apache. El MultiPHP Manager de cPanel (tabla "dominio → versión") es
   más una etiqueta administrativa que la realidad para subdominios que comparten carpeta física
   con otro dominio: el handler real lo decide el `.htaccess` de la carpeta compartida. Verificar
   la versión real servida con un script que imprima `phpversion()` si hay dudas, no confiar en la
   tabla de MultiPHP Manager a ciegas.
2. **`ext-fileinfo` no está instalada** ni en `ea-php82` ni en `ea-php83` en este servidor (se
   verificó explícitamente). Laravel/Flysystem la usa por defecto para detectar el MIME al
   guardar un archivo (`Storage::disk(...)->put()`), y sin ella cualquier subida revienta con
   `Class "finfo" not found`, quedando como error 500 genérico. cPanel no expone ninguna UI para
   activar extensiones en esta cuenta (ni "Select PHP Version" ni el MultiPHP INI Editor la
   tienen) — no depende de un ticket de soporte porque **ya se arregló en código**: ver
   `app/Providers/AppServiceProvider.php::desactivarDeteccionMimePorFinfo()`, que reemplaza el
   detector por `ExtensionMimeTypeDetector` (sin dependencias de extensiones de PHP). No revertir
   ese cambio pensando que es cosa del entorno local — es necesario en cualquier hosting que
   pueda no traer `fileinfo`, así que se queda aunque el hosting de producción real (Railway) sí
   la tenga.
3. **`storage/` no viaja completo en el paquete de deploy.** Solo tiene sentido subir la
   estructura vacía (`framework/{cache,sessions,views}`, `logs`, `app/public`, `app/private`,
   `app/tenants`) — nunca los datos/logs de desarrollo local. Los tres subdirectorios de
   `storage/app/` (`public`, `private`, `tenants`) tienen que existir con permisos de escritura
   ANTES del primer intento de subir un archivo, si no, escrituras al disco `documentos` o
   `local` fallan en silencio (`'throw' => false` en `config/filesystems.php`) sin un error claro
   para el usuario — solo aparece en `storage/logs/laravel.log`.
4. **`CENTRAL_DOMAINS`** tiene que incluir el dominio del panel de super admin
   (`empiresass.gestionley.com`) en el `.env` de este servidor, si no la app central no resuelve
   por Host (ver `config/tenancy.php`).
5. **El Terminal SSH de cPanel usa PHP 7.4 por defecto** (`php` del PATH global), no la versión
   que se le asignó al dominio. Todos los comandos de `artisan`/`composer` en el Terminal deben
   usar el binario explícito de la versión correcta, ej.
   `/opt/cpanel/ea-php82/root/usr/bin/php artisan ...`. El puerto 22 (SSH real) está cerrado desde
   fuera — solo queda el Terminal del navegador.
6. **`php.ini` por dominio (MultiPHP INI Editor) solo permite unas pocas directivas** (memory
   limits, timeouts, etc.), no `extension=...`: cPanel filtra/ignora líneas fuera de esa lista
   aunque se editen el archivo físico directo por FTP. No perder tiempo ahí para temas de
   extensiones.
7. **Al activar una versión de PHP nueva para un dominio, cPanel genera un `php.ini` con límites
   por defecto muy chicos** (`upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`),
   sin heredar los valores que tenía la versión anterior. Subirlos a mano (MultiPHP INI Editor,
   ~50M/50M/512M es razonable dado que el límite de negocio de "Sistema de archivos" es 10MB por
   archivo) cada vez que se cambia de versión de PHP en un dominio.

8. **`public/storage` no debe salir del código empaquetado localmente.** Si el paquete de deploy
   se arma con un iterador recursivo sobre `public/` en una máquina donde `storage:link` ya corrió
   en local (Windows: junction; Linux/Mac: symlink), ese iterador puede *seguir* el enlace y
   copiar su contenido como si fuera una carpeta real dentro del zip — dejando en el servidor una
   carpeta `public/storage` real y "congelada" con los datos de development local, completamente
   desconectada de `storage/app/public/` donde Laravel escribe de verdad. Síntoma: cualquier
   archivo subido DESPUÉS del deploy (avatar, logo, catálogo) da 404 aunque el `Storage::put()`
   haya funcionado bien — el archivo existe en un lado, la web sirve el otro. Se detecta con
   `ls -la public/storage` (si sale `drwxr-xr-x` en vez de `lrwxrwxrwx ... ->`, es carpeta real,
   no symlink). Fix: `rm -rf public/storage` (sí, recursivo — los datos reales viven a salvo en
   `storage/app/public/`, esto solo borra la copia congelada) y recrear el enlace. `php artisan
   storage:link --relative` puede fallar acá con *"please install the symfony/filesystem
   package"* (no viene con `--no-dev`) — usar el comando nativo en su lugar, ya probado y
   funcionando en este servidor:
   ```bash
   ln -s ../storage/app/public public/storage
   ```
   **Excluir `public/storage` del zip en el próximo deploy completo** para no repetir esto.

## Seeds necesarios tras un despliegue nuevo (o una base de datos vacía)

```bash
php artisan migrate --force
php artisan db:seed --class=DeploySeeder --force
```

`DeploySeeder` (ver su docblock) crea el super admin inicial si no existe, el catálogo global de
permisos (necesario para que `ProvisionadorRoles` tenga qué sincronizar al crear tenants desde el
panel) y el catálogo INE de provincias/localidades (alimenta los selects de dirección en toda la
app). Es idempotente — correrlo de nuevo no duplica ni pisa nada.

El super admin de producción NO sale de `AccesoPersonalSeeder` (ese es solo para desarrollo local,
ver `ACCESOS.local.md`) — las credenciales reales de este servidor están en `ftp.txt`.

## Sembrar datos de demo en un tenant sin romper lo que ya hay

`DemoPruebaPosSeeder` (2026-08-09) crea documentos de demostración —leads, presupuestos,
albaranes, facturas ordinarias en borrador y tickets simplificados— en el tenant que resuelva por
dominio (`pruebapos` por defecto, o el que se indique con `DEMO_TENANT_DOMINIO`):

```bash
cd /home/gestionley/empiresass.gestionley.com
DEMO_TENANT_DOMINIO=pruebapos /opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed \
  --class=DemoPruebaPosSeeder --force
```

Reglas que cumple, y que **cualquier seeder de demo futuro debería copiar**:

- **Solo INSERT.** Ni un `delete()`, `truncate()` ni un `update()` sobre registros ajenos. Los
  datos preexistentes del tenant son intocables (regla del proyecto sobre datos de demo).
- **Idempotente por marca.** Cada documento lleva `DEMO-SEED:<código>` en `notas`; antes de crear
  se comprueba esa marca. Correrlo dos veces no duplica. Es también el filtro para localizar y
  limpiar después lo sembrado.
- **Reutiliza el catálogo real.** Si el tenant ya tiene productos con control de stock, los usa;
  solo crea artículos `DEMO-*` si no hay suficientes.
- **Pasa por los servicios de negocio**, no escribe tablas a mano: `RegistroPresupuesto`,
  `RegistroAlbaran`, `EntregadorAlbaran`, `RegistroFacturaBorrador`, `RegistroTicket`. Así los
  importes los calcula `CalculadoraFactura` (Principio III) y la numeración sale del `Numerador`.

Lo que **no se puede deshacer** después, y por eso hay que decidirlo antes de correrlo:

- **Facturas simplificadas (tickets POS)**: `RegistroTicket` las emite en la misma operación en
  que las crea. No existen en borrador y `FacturaController::destroy()` solo admite borradores,
  así que no hay forma de borrarlas desde la app.
- **Movimientos de stock**: el ledger es append-only y no tiene ruta de borrado (las correcciones
  son movimientos inversos). El seeder no crea ninguno a mano; los únicos que aparecen son los que
  genera el flujo al entregar el albarán de demo y al emitir cada ticket.

Las facturas ordinarias se crean **siempre en borrador** precisamente para que sean borrables.

Cobertura en `tests/Feature/DemoPruebaPosSeederTest.php` (crea lo esperado, no toca lo
preexistente, es idempotente, y no hace nada si el tenant no existe).

## Migrar datos de catálogo entre tenants (ej. demo de prueba)

Cuando hace falta llevar un catálogo de artículos/categorías (con imágenes) de un tenant local a
uno de este servidor, el patrón usado es: exportar a JSON desde tinker local, generar un script
PHP con los datos embebidos como arrays literales (resuelve el `tenant_id` destino por dominio,
así no hace falta conocerlo de antemano), subir el script + las imágenes (a una carpeta temporal)
por FTP, y correrlo una vez desde el Terminal. Ver el skill de deploy para el detalle si hace
falta repetirlo.
