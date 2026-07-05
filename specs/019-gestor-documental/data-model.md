# Data Model — Gestor documental por tenant

Convenciones del proyecto (`docs/03-modelo-datos.md`): `id` BIGINT autoincrement, `timestamps`,
`softDeletes` donde aplique, todo con `tenant_id` indexado + global scope (`BelongsToTenant`).

## Tabla `carpetas`

Árbol de organización autorreferenciado, por tenant.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; global scope |
| parent_id | fk → carpetas | **nullable** = raíz; `onDelete` gestionado en app (cascada con ficheros) |
| nombre | varchar(255) | nombre visible de la carpeta |
| softDeletes, timestamps | | |

**Índices / reglas**:
- Único: `(tenant_id, parent_id, nombre)` sobre registros no borrados → nombre único por nivel
  (FR-005). En MySQL el índice único no distingue `deleted_at`; la unicidad se afirma también en el
  Form Request acotando a `whereNull('deleted_at')`.
- Índice `(tenant_id, parent_id)` para listar el contenido de un nivel.
- `parent_id` debe pertenecer al mismo tenant (validado en request).

**Relaciones (Eloquent)**:
- `Carpeta belongsTo Tenant`
- `Carpeta belongsTo Carpeta as padre` (`parent_id`)
- `Carpeta hasMany Carpeta as subcarpetas`
- `Carpeta hasMany Archivo`

**Estado / ciclo de vida**: crear → renombrar / mover (cambia `parent_id`, validado contra ciclos:
no puede apuntar a sí misma ni a un descendiente, ver `Carpeta::descendientesIds()`) → borrar (soft
delete; en cascada borra subcarpetas y archivos, y elimina los ficheros físicos de esos archivos).

## Tabla `archivos`

Documento almacenado, por tenant, opcionalmente dentro de una carpeta.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | índice; global scope |
| carpeta_id | fk → carpetas | **nullable** = raíz |
| nombre | varchar(255) | nombre visible (renombrable) |
| nombre_original | varchar(255) | nombre del fichero subido (informativo) |
| ruta | varchar(255) | path relativo en disco privado: `tenants/{tenant_id}/documentos/{uuid}.{ext}` |
| mime | varchar(150) | tipo de contenido detectado |
| extension | varchar(20) | para icono/preview |
| tamano | unsignedBigInteger | bytes |
| subido_por | fk → users | **nullable**; `nullOnDelete` |
| softDeletes, timestamps | | `created_at` = fecha de subida |

**Índices / reglas**:
- Índice `(tenant_id, carpeta_id)` para listar el contenido de un nivel.
- **Nombre visible NO único** dentro de una carpeta (permitido, se distinguen por fecha/quién) —
  solo el nombre de carpeta es único por nivel.
- `carpeta_id` (si viene) debe pertenecer al mismo tenant (validado en request).
- `ruta` incluye siempre `tenant_id`: el fichero físico queda particionado por tenant.

**Relaciones (Eloquent)**:
- `Archivo belongsTo Tenant`
- `Archivo belongsTo Carpeta` (nullable)
- `Archivo belongsTo User as subidoPor` (`subido_por`)

**Estado / ciclo de vida**: subir (crea registro + escribe fichero) → renombrar (solo `nombre`, el
fichero no se toca) → mover (cambia `carpeta_id`) → borrar (soft delete del registro + borrado del
fichero físico).

## Configuración (tabla `configuraciones`, grupo `archivos`)

Sigue los 3 pasos de `docs/03-modelo-datos.md` (seeder + `App\Support\ArchivosTenant` +
input en `_tab_archivos.blade.php`).

| clave | grupo | tipo | default | descripción |
|-------|-------|------|---------|-------------|
| `archivos.limite_mb` | archivos | integer | `10` | Tamaño máximo por archivo (MB) |
| `archivos.tipos_permitidos` | archivos | json | lista blanca de `ArchivosTenant` | Extensiones permitidas (avanzado) |

Defaults centralizados como constantes en `App\Support\ArchivosTenant`
(`CLAVE_LIMITE_MB`/`DEFAULT_LIMITE_MB`, `EXTENSIONES_PERMITIDAS`, `MIMES_PERMITIDOS`).

## Lista blanca de tipos (FR-016b)

| Categoría | Extensiones | MIME (ejemplos) |
|-----------|-------------|-----------------|
| PDF | pdf | application/pdf |
| Imágenes | jpg, jpeg, png, webp, gif | image/jpeg, image/png, image/webp, image/gif |
| Ofimática (MS) | docx, xlsx, pptx | application/vnd.openxmlformats-officedocument.* |
| Ofimática (ODF) | odt, ods, odp | application/vnd.oasis.opendocument.* |
| Texto | txt, csv | text/plain, text/csv |

Preview inline soportado: **pdf** e **imágenes**. El resto → solo descarga (FR-016).

## Aislamiento multi-tenant (Principio I)

- Ambas tablas con `tenant_id` + `BelongsToTenant` (global scope automático).
- Descarga/preview resuelven el modelo por id **bajo el scope activo** (nunca binding implícito) —
  ver `research.md` D3 y memoria `project_tenant_route_binding`.
- Path físico contiene `tenant_id` derivado del tenant activo, no de un parámetro del request.
- Tests de fuga con ≥2 tenants (test-first, Principio IV) para listar, descargar y previsualizar.
