---
name: deploy-empiresass
description: "Desplegar cambios al servidor de pruebas cPanel de empiresass.gestionley.com (FTP + Terminal). Usar cuando el usuario pida subir/desplegar/deployar cambios a ese servidor, a empiresass, a pruebapos o a cualquier subdominio de tenant que viva ahí."
allowed-tools: Bash, mcp__ftp__*
---

# Deploy a empiresass.gestionley.com (servidor de pruebas cPanel)

Servidor de pruebas, no el de producción real (ese es Railway, `docs/05-despliegue-railway.md`).
Contexto completo y las particularidades del hosting: `docs/08-despliegue-empiresass.md` — leerlo
si algo de este skill no cuadra con lo que se observa, puede haber cambiado.

**Credenciales**: siempre en `ftp.txt` (raíz del repo, gitignored). No pedirlas al usuario, no
inventarlas — leer el archivo. Si `ftp.txt` no existe o está vacío, parar y pedirlas.

## Antes de nada

Confirmar con el usuario **qué se despliega**: ¿solo uno o dos archivos modificados (fix rápido)
o el paquete completo (composer install + vendor + build de assets)? El flujo es muy distinto en
costo/tiempo — no asumir "todo" si el cambio fue chico.

## A) Deploy de archivos sueltos (el caso más común)

Para cambios de código que no tocan `composer.json`/`composer.lock` ni assets de Vite:

1. Subir cada archivo modificado por FTP a la ruta equivalente dentro de
   `/empiresass.gestionley.com/` (mismo path relativo que en el repo).
2. Si el cambio toca algo que Laravel cachea (`config/`, `routes/`, providers, vistas si hay
   `view:cache` activo), correr en el Terminal de cPanel (dar el comando exacto al usuario, no se
   puede ejecutar por FTP):
   ```bash
   cd /home/gestionley/empiresass.gestionley.com
   /opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php82/root/usr/bin/php artisan config:cache
   ```
   Si el cambio es solo en `app/` (controllers, models, services) sin tocar config/rutas, alcanza
   con subir el archivo — no hace falta cachear nada.
3. Verificar (Chrome DevTools o pidiéndole al usuario que pruebe) antes de dar por cerrado.

## B) Deploy completo (composer.json cambió, o primer deploy)

1. Copiar a un directorio de scratchpad: `app bootstrap config database public resources routes
   composer.json composer.lock artisan`. **No copiar `storage/` del repo local** (tiene datos de
   desarrollo) — crear la estructura vacía. **Tampoco copiar `public/storage`** si existe como
   symlink/junction en la máquina local (lo crea `php artisan storage:link` en desarrollo): un
   iterador recursivo ingenuo lo sigue y lo empaqueta como carpeta real con datos de local,
   dejando el servidor con dos copias desconectadas y cualquier archivo nuevo dando 404 (pasó una
   vez, ver docs/08 punto 8 para el diagnóstico completo). Excluir ese path explícitamente al
   armar el zip.
   ```
   storage/framework/{cache/data,sessions,views}
   storage/logs
   storage/app/{public,private,tenants}
   ```
   Los tres subdirectorios de `storage/app/` son obligatorios (ver docs/08, punto 3) o las subidas
   de archivo fallan en silencio.
2. `composer install --no-dev --optimize-autoloader --no-interaction --no-scripts` dentro del
   staging.
3. `npm run build` en el repo (Vite, no depende del staging).
4. `.env` de producción: partir del que ya está en el servidor si existe (no pisarlo a ciegas —
   descargarlo primero por FTP y diffearlo), o armar uno nuevo con: `APP_ENV=production`,
   `APP_DEBUG=false`, `APP_URL` del dominio, `CENTRAL_DOMAINS=empiresass.gestionley.com`, la
   conexión MySQL de `ftp.txt`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`.
5. Empaquetar en zip con **rutas `/` (forward slash), no `\`** — `Compress-Archive` de PowerShell
   genera rutas con backslash que rompen al descomprimir en Linux. Usar PHP `ZipArchive` (ver
   snippet en el historial de esta conversación / regenerar uno análogo) o `tar`.
6. Subir el zip por FTP a `/empiresass.gestionley.com/`, y darle al usuario los comandos de
   Terminal para descomprimir + permisos + migrar:
   ```bash
   cd /home/gestionley/empiresass.gestionley.com
   unzip -q nombre-del-zip.zip && rm nombre-del-zip.zip
   chmod -R 775 storage bootstrap/cache
   /opt/cpanel/ea-php82/root/usr/bin/php artisan storage:link
   /opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force
   /opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php82/root/usr/bin/php artisan config:cache
   ```
7. Si es la primera vez que se puebla esta base de datos: pedir que corran también
   `php artisan db:seed --class=DeploySeeder --force` (ver docs/08, sección de seeds).
8. Recordarle al usuario cambiar el **Document Root** del dominio/subdominio a
   `.../public` en cPanel si es un dominio nuevo (paso manual, no se puede hacer por FTP/API).

## Migrar catálogo de datos entre tenants (con imágenes)

Cuando se necesita llevar artículos/categorías de un tenant local a uno de este servidor:

1. Exportar desde `php artisan tinker` local a JSON (categorías + artículos de la tabla
   `articulos`/`categorias_articulo` del tenant origen).
2. Generar un script PHP que se sube y corre UNA vez por `tinker --execute="require '...'"`, que:
   - resuelve el `tenant_id` destino por su dominio (`DB::table('domains')->where('domain', ...)`)
     para no depender de conocerlo de antemano,
   - mueve las imágenes de una carpeta temporal (subida antes por FTP) a
     `storage/app/public/articulos/<tenant_id>/`,
   - hace `updateOrInsert` por SKU/nombre (idempotente — no duplica si se corre dos veces).
3. Subir el script + las imágenes (a `storage/app/public/articulos/_import_<algo>/`) por FTP.
4. Dar el comando de Terminal para correrlo, y borrar el script después (`rm`).

## Verificar tras cualquier deploy

- Revisar `storage/logs/laravel.log` por FTP si algo no anda — los discos de `config/filesystems.php`
  tienen `'throw' => false`, así que las fallas de escritura no dan un error visible al usuario.
- Si aparece `Class "finfo" not found`: no es nuevo, ya está resuelto en
  `app/Providers/AppServiceProvider.php` (ver docs/08, punto 2) — si reaparece, alguien revirtió
  ese cambio sin querer, no es un problema del hosting otra vez.
- Nunca dejar scripts de diagnóstico temporales (`phpcheck.php` y similares) en `public/` después
  de usarlos — son superficie de exposición de info del servidor.
