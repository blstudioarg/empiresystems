# Quickstart — Gestor documental por tenant

Guía de validación end-to-end de la feature. Detalles de esquema en [data-model.md](./data-model.md)
y de endpoints en [contracts/](./contracts/).

## Prerrequisitos

- Entorno del proyecto levantado (Laravel 12, MySQL/MariaDB) con al menos 2 tenants con dominio
  (para probar aislamiento), p. ej. vía seeders del repo.
- Disco privado `documentos` configurado en `config/filesystems.php`
  (root `storage_path('app/tenants')`).
- Migraciones y seeders al día:
  ```bash
  php artisan migrate
  php artisan db:seed --class=ConfiguracionSeeder   # claves grupo 'archivos'
  ```

## Escenarios de validación

### 1. Subir y descargar (US1 · P1)
1. Login como usuario aprobado del Tenant A. Ir a `/archivos`.
2. Subir un PDF ≤10 MB → aparece en el listado con nombre, tamaño, tipo, fecha y "subido por".
3. Descargar → el fichero coincide byte a byte con el subido; el nombre de descarga es el visible.
   - Esperado: OK. Verifica FR-008/009/012.

### 2. Rechazo por tipo y por tamaño (FR-010/016b)
1. Intentar subir un `.exe` (o un `.pdf` que en realidad es un ejecutable renombrado) → rechazado
   con mensaje claro, sin registro ni fichero en disco.
2. Intentar subir un archivo > límite configurado → rechazado, sin huérfanos.
   - Esperado: `422`, listado intacto. Verifica FR-010/011/016b.

### 3. Carpetas y navegación (US2 · P2)
1. Crear carpeta "Contratos"; entrar; crear subcarpeta "2026"; subir un archivo dentro.
2. Volver a la raíz por breadcrumbs → el archivo vive en Contratos/2026, no en la raíz.
3. Crear otra carpeta "Contratos" en el mismo nivel → rechazado (nombre duplicado por nivel).
   - Esperado: OK / `422` en el duplicado. Verifica FR-004/005/007/008.

### 4. Renombrar, mover, borrar (US3 · P2)
1. Renombrar un archivo → nombre visible cambia; descargar de nuevo → contenido idéntico (SC-005).
2. Mover el archivo a otra carpeta → desaparece del origen, aparece en destino.
3. Borrar una carpeta con contenido → confirmación reforzada indica cuántos elementos; al aceptar,
   la carpeta y su contenido desaparecen y los ficheros físicos se eliminan.
   - Esperado: OK. Verifica FR-013/014/015/018.

### 5. Preview inline (US4 · P3)
1. Previsualizar un PDF y una imagen → se ven embebidos en la app, sin descargar.
2. Seleccionar un `.docx` → la opción es descargar (no preview).
   - Esperado: OK. Verifica FR-016.

### 6. Aislamiento multi-tenant (Principio I · crítico)
1. Como Tenant B, pedir por id directo la descarga/preview de un archivo del Tenant A
   (`GET /archivos/{idDeA}/descargar`) → `404`, sin binario.
2. Como Tenant B, intentar renombrar/mover/borrar una carpeta o archivo de A → `404`.
3. Subir con `carpeta_id` de A → `422`/`404`.
   - Esperado: 100% rechazado, ninguna fuga. Verifica FR-001/002/019 y SC-002.

## Pruebas automatizadas (referencia)

```bash
php artisan test --filter=Archivo
php artisan test --filter=Carpeta
```
Test-first obligatorio (Principio IV): `ArchivoAislamientoTenantTest`, `CarpetaAislamientoTenantTest`
(incluida la barrera de descarga/preview) escritos antes de la implementación y en verde antes de
cerrar sus tareas.
