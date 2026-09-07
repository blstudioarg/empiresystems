# Implementation Plan: Compras desde documentos (PDF/imagen) interpretados por IA

**Branch**: `044-compras-desde-documentos-ia` | **Date**: 2026-09-06 | **Spec**: [spec.md](./spec.md)

**Base branch**: `feat/041-plano-sala-servicio` — **no `main`**. La rama de trabajo real del proyecto
va 27 commits por delante de `main` e incluye las features 040 (mesas redimensionables), 041 (plano
de sala en modo servicio), 042 (forma y medidas) y 043 (cobros de facturas), con 5 migraciones que
`main` no tiene. La rama de esta feature debe salir de ahí. Todo lo verificado en este plan está
contrastado contra esa rama, no contra `main` (ver "Verificación contra la rama base" más abajo).

**Input**: Feature specification from `/specs/044-compras-desde-documentos-ia/spec.md`

## Summary

Añadir a la pantalla de Compras una acción **"Importar documento"** que permite subir uno o varios
PDFs/imágenes de facturas o albaranes de proveedor, enviarlos al proveedor de IA ya configurado por
el tenant, y obtener por cada uno una **propuesta de compra editable** (proveedor, número, fecha,
líneas, importes) que el usuario revisa y confirma para crear una compra **en borrador**, idéntica en
todo lo demás a una cargada a mano.

Enfoque técnico (detalle y justificación en [research.md](./research.md)): el documento se manda en
base64 al modelo con **Structured Outputs** (`json_schema` estricto), sin rasterizar en local, para
no exigir Imagick/Ghostscript en cPanel (Principio V). La lectura cruda del modelo la traduce a
negocio un servicio propio que empareja proveedor (NIF → nombre) y artículos (SKU → nombre),
**recalcula todos los importes en servidor** (Principio III) y detecta duplicados con el mismo
criterio que la importación de Facturae. El lote se resuelve con **una petición HTTP por documento**
(subir todos → interpretar uno a uno → crear), lo que mantiene cada request dentro del
`max_execution_time` de un hosting compartido y hace natural el "un documento ilegible no invalida el
resto". No se toca `RegistroCompra` ni la lógica de stock: esta feature solo añade una forma nueva de
**llegar** a una compra en borrador.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `openai-php/client ^0.20` (ya instalada, feature 030), `stancl/tenancy`,
`spatie/laravel-permission`. jQuery + Bootstrap 5 + DataTables + toastr (bank NexaDash, ya
vendorizados). **Sin dependencias nuevas** — requisito duro del Principio V.

**Storage**: MySQL/MariaDB (single-database, `tenant_id`). Ficheros: disco `local`
(`storage/app/compras-documentos/`) para el temporal de la propuesta; disco `documentos`
(`storage/app/tenants/tenants/{id}/compras-documentos/`) para el justificante de la compra creada.
**Sin migraciones**: `compras.origen` ya es `string(20)`, y `formato_recepcion` /
`archivo_recibido_path` ya existen y están en `$fillable` (feature 022).

**Testing**: PHPUnit (`phpunit.xml`), tests Feature + Unit. `InterpretadorDocumentoCompra` se
sustituye por un doble en todos los tests: **ningún test llama a la API real**.

**Target Platform**: hosting compartido cPanel (`empiresass.gestionley.com`) y Railway. PHP-FPM,
sin colas, sin workers, sin acceso root.

**Project Type**: aplicación web monolítica Laravel + Blade (no hay separación front/back).

**Performance Goals**: propuesta de un documento de una página **en < 30 s** (SC-008), con progreso
visible durante toda la espera. Cada petición HTTP hace **como mucho una** llamada al modelo.

**Constraints**: ejecución **síncrona** dentro del request (sin colas, Principio V). Límites de
entrada: 10 ficheros/lote, 10 MB/fichero, 10 páginas/PDF, tipos `pdf|jpg|jpeg|png|webp`
(configurables en `config/compras.php`). El fichero temporal caduca a las **24 h** y se purga a
diario.

**Scale/Scope**: 1 pantalla existente modificada (listado de compras) + 1 modal nuevo, 3 endpoints
nuevos, 3 servicios nuevos, 1 comando de purga, 1 caso de enum. Catálogos del tenant del orden de
cientos-miles de filas (el emparejamiento por similitud se hace en memoria sobre ese volumen).

## Constitution Check

*GATE: pasa antes de Phase 0 y re-verificado tras Phase 1.*

| Principio | Cómo lo cumple este plan | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant (NON-NEGOTIABLE)** | `Compra` y `Proveedor` ya usan `BelongsToTenant`. `proveedor_id` y `lineas.*.articulo_id` se revalidan con `Rule::exists(...)->where(tenant_id = tenant actual)` (mismo patrón que `StoreCompraRequest`), así que un id de otro tenant es fallo de validación, no acceso. El token del documento temporal es un UUID **más** comprobación de que el fichero pertenece al tenant que lo subió. Tests obligatorios con ≥2 tenants: token de A inutilizable desde B; propuesta de A no puede crear compra en B. | ✅ PASS |
| **II. Cumplimiento normativo España-First** | El tipo impositivo **no se asume IVA**: se valida contra `tenant()->regimen_impositivo` vía `TiposImpositivos::esTipoValido()` y se señala si es incoherente; si el documento no lo indica, el campo queda vacío y marcado como ilegible, nunca a 21 % por defecto. RGPD/LOPDGDD: el documento contiene datos personales del proveedor → temporal de 24 h + `compras-documentos:purgar` diario, reutilizando el patrón `AlmacenImportaciones`/`importaciones:purgar` (no se inventa mecanismo nuevo). La compra creada es **borrador**, mutable: no toca la inmutabilidad de facturas emitidas. | ✅ PASS |
| **III. Integridad financiera server-side** | Los importes que propone el modelo son **exclusivamente UI**. El servidor recalcula base/cuota/totales desde `cantidad × precio_unitario` y `tipo_impositivo` con la lógica que ya tiene `CompraController::guardar()`; cualquier importe que llegue del cliente se **descarta**. No hay numeración de serie ni encadenamiento Verifactu implicado (una compra no se numera ni se firma). | ✅ PASS |
| **IV. Test-first en lógica crítica (NON-NEGOTIABLE)** | Áreas críticas tocadas: **aislamiento** y **cálculo de importes**. Sus tests se escriben primero y deben fallar antes de implementar (fase de tests explícita y bloqueante en `tasks.md`). El emparejamiento proveedor/artículo, aunque no es "crítico" por la constitución, también va test-first por ser pura lógica determinista y barata de probar. | ✅ PASS |
| **V. Simplicidad y hosting compartido** | Cero dependencias nuevas (Composer y npm intactos). Cero extensiones de sistema: el PDF viaja al proveedor en base64 en vez de rasterizarse con Imagick/Ghostscript. Cero colas/workers: síncrono en el request, con **una llamada al modelo por petición** para no reventar `max_execution_time`. Cero tablas nuevas. Cero migraciones. | ✅ PASS |

**Reglas transversales de `CLAUDE.md` verificadas en el diseño**:

- Documentación leída **antes** de escribir el spec y el plan, con las secciones citadas
  explícitamente (spec.md § "Documentación consultada").
- Notificaciones **solo** por `window.showToast(...)`/flash toastr; ningún `div.alert` ad-hoc.
- `@stack('styles')` no se usa (no hay plugin CSS nuevo); el modal usa componentes ya cargados.
- Obligaciones de cierre incluidas como tareas, no como "después": guía in-app
  `resources/views/ayuda/compras.blade.php` (existe, se actualiza), base de conocimiento
  `resources/ia/conocimiento/compras.md` (**no existe: se crea**), y sección nueva en
  `docs/04-front-guidelines.md`.
- **`@assetv`, nunca `asset()`** para el JS propio nuevo (guía de front, sección "Assets propios"):
  el despliegue a este hosting es por FTP y los estáticos se sirven con caché de una semana, así que
  un `asset()` sin versionar equivale a un cambio no desplegado.

### Desviaciones deliberadas, declaradas para que no se lean como incoherencias

Ninguna de estas viola un principio; se declaran porque contradicen aparentemente un patrón
existente y `/speckit-analyze` debe poder distinguirlas de un descuido:

1. **La tabla de líneas de la propuesta no es un DataTable.** La guía exige DataTable para
   *listados*; esta es la tabla de **edición** de las líneas de un documento, mismo caso que
   `compras/_form_lineas.blade.php` y que las líneas del alta de facturas, que tampoco lo son.
2. **El duplicado avisa en vez de abortar**, al revés que `CompraFacturaeController::importar()`
   (que responde 409 y no crea nada). Aquí hay un humano revisando que puede saber que la
   renumeración del proveedor es legítima; abortar le quitaría una decisión que le corresponde
   (FR-031 lo exige explícitamente).
3. **El proveedor no se crea automáticamente**, al revés que `ImportadorFacturae::resolverProveedor()`.
   Un XML Facturae es un documento estructurado y firmado: su NIF es fiable. Una lectura por IA de
   un escaneo no lo es, y un NIF mal leído crearía basura permanente en el catálogo (decisión D2 del
   usuario, FR-019).
4. **No se reutiliza `AsistenteIa`.** Es un orquestador conversacional con sesión, streaming y
   catálogo de tools; nada de eso aplica a una extracción de un solo turno. Sí se reutiliza todo lo
   que hay debajo: `IaTenant::apiKey()`, `config('ia.modelo')` y el mapeo de excepciones a códigos.

*No hay entradas en Complexity Tracking: ninguna desviación requiere justificar complejidad extra.*

## Project Structure

### Documentation (this feature)

```text
specs/044-compras-desde-documentos-ia/
├── spec.md                              # Especificación (ya escrita)
├── plan.md                              # Este archivo
├── research.md                          # Phase 0 — 13 decisiones técnicas
├── data-model.md                        # Phase 1 — entidades y DTOs
├── quickstart.md                        # Phase 1 — guía de validación end-to-end
├── contracts/
│   ├── endpoints.md                     # Contrato HTTP de los 3 endpoints
│   ├── propuesta-compra.schema.json     # Structured Output que se le exige al modelo
│   └── propuesta-ui.schema.json         # Payload servidor → navegador y navegador → servidor
├── checklists/
│   └── requirements.md                  # Checklist de calidad del spec
└── tasks.md                             # Phase 2 (/speckit-tasks — NO lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── OrigenCompra.php                     # MODIFICADO: + case Documento = 'documento'
├── Exceptions/
│   └── DocumentoCompraException.php         # NUEVO: error de interpretación con código
├── Http/
│   ├── Controllers/
│   │   └── CompraDocumentoController.php    # NUEVO: subir / interpretar / crear / descartar / descargar
│   └── Requests/
│       ├── SubirDocumentosCompraRequest.php # NUEVO: tipos, tamaño, nº de ficheros
│       └── CrearCompraDesdeDocumentoRequest.php # NUEVO: extiende las reglas de StoreCompraRequest
├── Services/
│   ├── AlmacenDocumentosCompra.php          # NUEVO: temporal 24 h por token (patrón AlmacenImportaciones)
│   ├── InterpretadorDocumentoCompra.php     # NUEVO: única pieza que habla con el proveedor de IA
│   └── ProponedorCompraDesdeDocumento.php   # NUEVO: lectura cruda → propuesta de negocio
├── Support/
│   ├── EmparejadorProveedor.php             # NUEVO: NIF exacto → nombre ≥85 % único
│   ├── EmparejadorArticulo.php              # NUEVO: SKU exacto → nombre ≥88 % único
│   └── ContadorPaginasPdf.php               # NUEVO: heurística sin dependencias
└── Console/Commands/
    └── PurgarDocumentosCompra.php           # NUEVO: comando compras-documentos:purgar

bootstrap/app.php                            # MODIFICADO: + $schedule->command('compras-documentos:purgar')->daily()
config/compras.php                           # NUEVO: límites de subida
routes/web.php                               # MODIFICADO: 5 rutas nuevas en el grupo can:ver-compras

resources/
├── views/
│   ├── compras/
│   │   ├── index.blade.php                  # MODIFICADO: botón + @include del modal
│   │   ├── show.blade.php                   # MODIFICADO: origen + descarga del documento
│   │   └── _importar_documento_modal.blade.php  # NUEVO: modal de 4 estados
│   └── ayuda/
│       └── compras.blade.php                # MODIFICADO: guía in-app (FR-040)
└── ia/conocimiento/
    └── compras.md                           # NUEVO: no existe hoy — Compras no está cubierto en la
                                             # base de conocimiento del asistente (FR-041)

public/js/plugins-init/
└── compras-importar-documento.init.js       # NUEVO: cola de propuestas, edición, creación
                                             # se carga con @assetv(...), NUNCA con asset()

docs/04-front-guidelines.md                  # MODIFICADO: sección "Cola de propuestas en modal" (FR-042)

tests/
├── Feature/
│   ├── CompraDocumentoSubidaTest.php        # límites de entrada, sin clave de IA, permisos
│   ├── CompraDocumentoInterpretacionTest.php# propuesta, ilegible, errores del proveedor, duplicado
│   ├── CompraDocumentoCreacionTest.php      # creación, edición respetada, proveedor nuevo atómico
│   └── CompraDocumentoAislamientoTest.php   # Principio I — ≥2 tenants
└── Unit/
    ├── EmparejadorProveedorTest.php
    ├── EmparejadorArticuloTest.php
    ├── ProponedorCompraDesdeDocumentoTest.php  # recálculo de importes, campos ilegibles, régimen
    └── AlmacenDocumentosCompraTest.php         # caducidad y purga
```

**Structure Decision**: monolito Laravel existente, sin estructura nueva. Todo el código nuevo entra
en los directorios que el proyecto ya usa para cada rol (`Services` para lógica de negocio con
efectos, `Support` para lógica pura sin estado, `plugins-init` para el JS de una vista). La única
convención nueva es el partial `compras/_importar_documento_modal.blade.php`, que sigue el patrón de
`excel/_importar_modal.blade.php` pero **no se generaliza** a un partial reutilizable: es específico
de compras y no hay un segundo consumidor previsto (YAGNI, Principio V).

## Verificación contra la rama base (`feat/041-plano-sala-servicio`)

El plan se redactó inicialmente leyendo el árbol de `main` y se re-verificó después contra la rama
de trabajo real. Resultado del contraste (`git diff cambios-tute...origin/feat/041-plano-sala-servicio`,
152 ficheros, +11 773/−1 349):

| Supuesto del plan | Estado en la rama base | Acción |
|---|---|---|
| `CompraController`, `RegistroCompra`, `Compra`, `CompraLinea`, `OrigenCompra`, `StoreCompraRequest` sin cambios | **No aparecen en el diff**: idénticos a lo leído | Sin cambios en el plan |
| Grupo `Route::middleware('can:ver-compras')` intacto | `routes/web.php` cambió, pero **solo** por el módulo de Cobros (043): se crea el grupo `can:ver-cobros` y las 3 rutas de pagos se mueven allí. El bloque de compras no se toca | Sin cambios; las 5 rutas nuevas siguen entrando en `can:ver-compras`, **antes** de `GET /compras/{compra}` |
| `compras/index.blade.php` como punto de entrada | Cambió solo cosmética (`h3`→`h4` en las métricas, lordicon `50`→`45`) | Sin cambios; el botón nuevo entra igual en la cabecera de la card |
| `resources/views/ayuda/compras.blade.php` existe | Existe, y además hay `compras-crear`, `compras-detalle`, `compras-editar` | Se actualiza `compras.blade.php` (el flujo vive en el listado) |
| `resources/ia/conocimiento/compras.md` a actualizar | **No existe**: Compras no está cubierto en la base de conocimiento | **Se crea** el archivo del módulo, incluyendo este flujo (FR-041, invariante SC-007 de la 030: añadir una feature = añadir un archivo) |
| Sin migraciones necesarias | Las 5 migraciones nuevas de la rama (mesas/zonas del POS, índice de vencimiento en facturas, permiso `ver-cobros`) **no tocan compras** | Sigue sin hacer falta migración |
| `docs/04-front-guidelines.md` leído | **+383 líneas** con 14 secciones nuevas de las features 040-043 | Tres afectan a esta feature, ya incorporadas (abajo) |

**Convenciones nuevas de la rama que este plan incorpora**:

1. **"Assets propios: siempre `@assetv`, nunca `asset()` a secas"** — el JS nuevo se carga con
   `@assetv('js/plugins-init/compras-importar-documento.init.js')`. Motivo documentado: el hosting
   sirve estáticos con caché de una semana y el deploy es por FTP, así que sin versionar el usuario
   no ve el cambio. (Los vendorizados siguen con `asset()`.)
2. **"Alta inline en un listado: confirmación explícita, nunca por `blur`"** — se aplica al
   sub-formulario de **crear proveedor nuevo** dentro de la propuesta: check para confirmar + X para
   descartar, check `disabled` mientras esté vacío, Enter confirma / Escape descarta, perder el foco
   no hace nada, y si el alta falla en servidor el formulario **conserva lo escrito** en vez de
   vaciarse. Guard de "enviando" contra el doble alta. Refuerza FR-019 con un patrón ya existente
   (`filaDeAlta()` en `pos-sala-plano-gestion.init.js`) en vez de inventar la interacción.
3. **"Extracción de UI compartida entre dos pantallas ya existentes"** — confirma, por contraste,
   la decisión de **no** generalizar `compras/_importar_documento_modal.blade.php` a un partial
   reutilizable: esa sección exige extraer cuando hay **dos** consumidores reales; aquí hay uno.

## Complexity Tracking

*Sin entradas: el Constitution Check pasa sin violaciones. Las cuatro divergencias respecto a
patrones existentes están declaradas arriba con su justificación y ninguna añade complejidad
estructural (ni tablas, ni dependencias, ni capas nuevas).*
