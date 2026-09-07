# Tasks: Compras desde documentos (PDF/imagen) interpretados por IA

**Feature**: `044-compras-desde-documentos-ia` · **Fecha**: 2026-09-06

**Input**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Rama base**: `feat/041-plano-sala-servicio` — **no `main`**.

```powershell
git fetch origin
git switch -c 044-compras-desde-documentos-ia origin/feat/041-plano-sala-servicio
```

**Los tests NO son opcionales en esta feature.** El Principio IV de la constitución es
NON-NEGOTIABLE para **aislamiento multi-tenant** y **cálculo de importes**: sus tests se escriben
primero, deben **fallar** antes de implementar, y ninguna tarea de esas áreas se marca completa sin
sus tests en verde. Las fases de test están marcadas como **bloqueantes** donde aplica.

---

## Phase 1: Setup

- [X] T001 Crear la rama `044-compras-desde-documentos-ia` a partir de `origin/feat/041-plano-sala-servicio` y confirmar con `git log --oneline -1` que la base incluye las features 040-043 (no `main`)
- [ ] T002 Ejecutar `php artisan migrate` para aplicar las 5 migraciones de la rama base que faltan en local, y verificar que la suite existente arranca en verde con `php artisan test` (línea de base antes de tocar nada). **Nunca `migrate:fresh`** (política de datos de demo de `CLAUDE.md`)
- [X] T003 [P] Crear `config/compras.php` con los límites de importación de documentos definidos en research D7: `documentos.max_ficheros` (10), `documentos.max_mb` (10), `documentos.max_paginas_pdf` (10), `documentos.tipos` (`pdf,jpg,jpeg,png,webp`), todos vía `env()` con esos valores por defecto
- [ ] T004 [P] Preparar los cuatro documentos de prueba descritos en `quickstart.md` (§Prerequisitos, punto 5) en `storage/app/pruebas-044/` — fuera de git

---

## Phase 2: Foundational (bloquea todas las historias)

Piezas sin las que ninguna historia puede existir. **Deben completarse antes de la Phase 3.**

### Tests primero (Principio IV — deben fallar antes de implementar)

- [X] T005 [P] Escribir `tests/Unit/AlmacenDocumentosCompraTest.php`: guardar devuelve token; recuperar un token inexistente devuelve null; un fichero de más de 24 h se considera caducado **aunque siga en disco**; `borrar()` es idempotente; `purgarHuerfanos()` borra solo los caducados y devuelve el conteo; y —clave para el Principio I— **un token guardado bajo el tenant A no se resuelve estando activo el tenant B**
- [X] T006 [P] Escribir `tests/Unit/ContadorPaginasPdfTest.php`: cuenta correctamente un PDF de 1 y de varias páginas; ante un fichero ilegible o no-PDF devuelve `null` (no lanza), porque research D7 fija que si no puede determinarse **no se bloquea**

### Implementación

- [X] T007 [P] Añadir el case `Documento = 'documento'` a `app/Enums/OrigenCompra.php` con label `'Documento (IA)'`, y revisar que ningún `match` exhaustivo sobre el enum quede sin cubrir (`CompraFacturaeController` compara `!== OrigenCompra::Facturae`, sigue correcto). **Sin migración**: `compras.origen` ya es `string(20)`
- [X] T008 [P] Crear `app/Exceptions/DocumentoCompraException.php` con un `codigo` (`ia_no_configurada`, `no_es_documento_compra`, `clave_invalida`, `limite_excedido`, `servicio_no_disponible`, `interno`) y un `detalle` técnico opcional, según la tabla de códigos de `contracts/endpoints.md`
- [X] T009 [P] Crear `app/Support/ContadorPaginasPdf.php`: cuenta ocurrencias de `/Type /Page` en los bytes del fichero; devuelve `null` si no puede determinarlo. Sin dependencias externas (Principio V). Hace pasar T006
- [X] T010 Crear `app/Services/AlmacenDocumentosCompra.php` replicando el patrón de `app/Services/AlmacenImportaciones.php`, **con una divergencia obligada**: la carpeta se segmenta por tenant (`compras-documentos/{tenant_id}/{uuid}.{ext}`) y `rutaAbsoluta`/`borrar` resuelven **solo** dentro del subárbol del tenant activo, de modo que un token de otro tenant "no existe". Sin eso no hay nada contra lo que comprobar la pertenencia y el Principio I quedaría sin enforcement (research D4). Caducidad de 24 h comprobada **en lectura**, `purgarHuerfanos()` barre todos los tenants. Hace pasar T005
- [X] T011 Crear `app/Console/Commands/PurgarDocumentosCompra.php` (signature `compras-documentos:purgar`) que delega en `AlmacenDocumentosCompra::purgarHuerfanos()` e informa cuántos borró
- [X] T012 Registrar `$schedule->command('compras-documentos:purgar')->daily();` en `bootstrap/app.php`, junto a los `*:purgar` ya existentes (FR-038, Principio II)

**Checkpoint**: el temporal se guarda, caduca y se purga solo. Nada de negocio todavía.

---

## Phase 3: User Story 1 — Crear una compra a partir de un documento (P1) 🎯 MVP

**Meta**: subir un PDF/imagen y llegar a una compra en borrador sin teclear ningún campo.

**Test independiente**: subir un documento de una página y verificar que se llega a una compra en
borrador cuyos datos coinciden con el documento.

### Tests primero — BLOQUEANTE (Principio IV: cálculo de importes)

- [X] T013 [P] [US1] Escribir `tests/Unit/ProponedorCompraDesdeDocumentoTest.php` (parte de cálculo): a partir de una `LecturaDocumento` fija, la propuesta recalcula `base` y `cuota_impuesto` por línea y los totales; **el `total_documento` que traiga la lectura no influye** en los importes calculados; si difieren, se emite el aviso `totales_no_cuadran`
- [X] T014 [P] [US1] Añadir a ese mismo test: los campos que la lectura trae a `null` aparecen en `campos_ilegibles` con su ruta (`fecha`, `lineas.2.tipo_impositivo`), y un `tipo_impositivo` nulo **no** se rellena con 21 (Principio II)
- [X] T015 [P] [US1] Añadir: `tipo_impositivo_coherente` sale `false` cuando el tipo no es válido para el régimen del tenant, usando `App\Support\TiposImpositivos::esTipoValido()`. Cubrir un tenant IVA y uno IGIC
- [X] T016 [P] [US1] Escribir `tests/Feature/CompraDocumentoCreacionTest.php`: crear desde una propuesta genera compra en `borrador` con `origen = documento`, `formato_recepcion` y `archivo_recibido_path`; **los importes enviados por el cliente se ignoran** (mandar `base`/`total` falseados y afirmar que no se persisten); `estado_b2b` queda `null`
- [X] T017 [P] [US1] Añadir a ese test: los valores **editados** por el usuario (fecha, concepto, cantidad, líneas añadidas/eliminadas) son los que se persisten, no los de la lectura original

### Implementación

- [X] T018 [US1] Crear `app/Services/InterpretadorDocumentoCompra.php`: única pieza que habla con el proveedor de IA. Construye el mensaje según research D1 (imagen → content part `image_url` con data URI; PDF → content part `file` con `file_data` + `filename`), aplica el Structured Output de `contracts/propuesta-compra.schema.json` (`json_schema`, `strict: true`), usa `IaTenant::apiKey()` y `config('ia.modelo')`, **sin streaming ni tools**, y mapea las excepciones de OpenAI a los códigos de `DocumentoCompraException` igual que hace `AsistenteIa::responder()`. **No conoce Eloquent**
- [X] T019 [US1] Crear `app/Services/ProponedorCompraDesdeDocumento.php`: convierte la `LecturaDocumento` en la `PropuestaCompra` de `contracts/propuesta-ui.schema.json` — recalcula importes en servidor, marca `campos_ilegibles`, evalúa `tipo_impositivo_coherente` y emite los `avisos`. Hace pasar T013-T015. **No llama al proveedor de IA** (research D3: así los tests de cálculo corren sin red ni clave)
- [X] T020 [P] [US1] Crear `app/Http/Requests/SubirDocumentosCompraRequest.php`: valida `archivos[]` contra los límites de `config/compras.php` (nº, tamaño, tipos) y, para PDFs, el máximo de páginas vía `ContadorPaginasPdf`, con mensajes que nombren el límite concreto y el archivo (FR-004)
- [X] T021 [P] [US1] Crear `app/Http/Requests/CrearCompraDesdeDocumentoRequest.php` con las reglas de `data-model.md` §4: reutiliza las de `StoreCompraRequest` (incluidos los `Rule::exists(...)->where(tenant_id actual)` de `proveedor_id` y `lineas.*.articulo_id`) y añade `token`, `crear_proveedor`, `proveedor_nuevo.*` y `confirmar_duplicado`. **No acepta importes del cliente**
- [X] T022 [US1] Crear `app/Http/Controllers/CompraDocumentoController.php` con `subir`, `interpretar`, `crear`, `descartar` y `descargar`, conforme a `contracts/endpoints.md`. `crear` envuelve en **una transacción**: (proveedor opcional) → compra → líneas con importes recalculados → mover el fichero al disco `documentos` → log de actividad
- [X] T023 [US1] Registrar las 5 rutas en `routes/web.php` dentro del grupo `can:ver-compras` existente, **antes** de `GET /compras/{compra}` para que `/compras/documentos` no se resuelva como un id de compra (contracts/endpoints.md, nota de orden)
- [X] T024 [US1] Crear `resources/views/compras/_importar_documento_modal.blade.php`: modal `modal-dialog-centered` con los 4 estados alternados por `d-none` (`subir` / `interpretando` / `propuesta` / `resumen`), formularios en tamaño `sm`, tabla de líneas editable (**no DataTable** — es tabla de edición, ver plan.md §desviaciones), y `@csrf` en el form de subida
- [X] T025 [US1] Crear `public/js/plugins-init/compras-importar-documento.init.js`: sube el lote, llama a `interpretar` **en serie** token a token, pinta la propuesta, recalcula los totales en vivo al editar (aproximación solo-UX; el servidor manda) y crea la compra. Usa `window.withButtonLoading` + `data-loading-text`, header `X-CSRF-TOKEN` explícito en las peticiones sin form, y `window.showToast(...)` para todo aviso — **cero `div.alert` ad-hoc**
- [X] T026 [US1] Modificar `resources/views/compras/index.blade.php`: botón **Importar documento** en la cabecera de la card, junto a "Importar Facturae" y **antes** de "+ Nueva compra"; deshabilitado con explicación si `IaTenant::configurada()` es falso (FR-007); `@include` del modal; y cargar el JS con **`@assetv(...)`, nunca `asset()`** (guía "Assets propios"). Al crear, `table.ajax.reload(null, false)`
- [X] T027 [US1] Modificar `resources/views/compras/show.blade.php`: mostrar el origen "Documento (IA)" y el enlace de descarga del documento original cuando `origen = documento` y haya `archivo_recibido_path` (FR-029, FR-030)
- [X] T028 [US1] Escribir `tests/Feature/CompraDocumentoSubidaTest.php`: límites de entrada (nº, tamaño, tipo, páginas) rechazados **antes** de llamar al modelo; respuesta 422 con `codigo: ia_no_configurada` si el tenant no tiene clave; 403 sin permiso `ver-compras`
- [X] T029 [US1] Escribir `tests/Feature/CompraDocumentoInterpretacionTest.php` con un doble de `InterpretadorDocumentoCompra`: propuesta correcta; `es_documento_compra = false` → 422 `no_es_documento_compra` nombrando el archivo; cada excepción del proveedor mapeada a su código y HTTP; `detalle` presente **solo** con permiso `ver-configuracion`; token inexistente o caducado → 404
- [X] T029b [US1] Añadir a ese test la garantía central de FR-011: tras subir e interpretar, `Compra::count()`, `CompraLinea::count()` y `Proveedor::count()` **no han cambiado**. Es la afirmación que sostiene todo el flujo ("nada se persiste hasta el OK") y hoy no la cubre ningún test
- [X] T029c [US1] Añadir a `tests/Feature/CompraDocumentoSubidaTest.php` la cobertura del descarte (FR-012): `DELETE /compras/documentos/{token}` borra el fichero temporal, es **idempotente** (segundo intento también 200) y no toca la base de datos
- [X] T029d [US1] Añadir a `tests/Feature/CompraDocumentoCreacionTest.php`: la descarga del documento original de una compra `origen = documento` devuelve el fichero (FR-030), y devuelve 404 si la compra es de otro origen o el fichero ya no está
- [X] T029e [US1] Añadir a `tests/Feature/CompraDocumentoCreacionTest.php`: crear la compra deja su entrada en el log de actividad (FR-039), con el mismo formato que el alta manual de compras

**Checkpoint**: MVP entregable. Se puede registrar una compra desde un documento de punta a punta.

---

## Phase 4: User Story 2 — Resolver el proveedor sin ensuciar el catálogo (P1)

**Meta**: reconocer al proveedor, y nunca crearlo sin que el usuario lo diga.

**Test independiente**: subir un documento de proveedor existente y otro de uno inexistente, y
verificar que el primero se preselecciona y el segundo exige decisión explícita.

### Tests primero

- [X] T030 [P] [US2] Escribir `tests/Unit/EmparejadorProveedorTest.php`: match por NIF normalizado (mayúsculas, sin espacios/guiones/puntos) → `criterio: 'nif'`; sin NIF, nombre con `similar_text` ≥85 % y candidato **único** → `criterio: 'nombre'`; **dos** candidatos por encima del umbral → `sin_coincidencia` (no elegir arbitrariamente); nada → `sin_coincidencia` con `datos_nuevos`
- [X] T031 [P] [US2] Añadir a `tests/Feature/CompraDocumentoCreacionTest.php`: crear con `crear_proveedor: true` da de alta proveedor **y** compra; si la compra falla la validación, **no queda el proveedor** (atomicidad, FR-021); descartar la propuesta no crea ningún proveedor (SC-006)

### Implementación

- [X] T032 [US2] Crear `app/Support/EmparejadorProveedor.php` con la cascada de research D8 (NIF exacto → nombre ≥85 % único → sin coincidencia), normalizando nombres (minúsculas, sin acentos, sin formas societarias `s.l.`/`s.a.`/`slu`). Hace pasar T030
- [X] T033 [US2] Integrar el emparejador en `ProponedorCompraDesdeDocumento` para poblar el bloque `proveedor` de la propuesta, incluyendo `similitud` y `datos_nuevos`
- [X] T034 [US2] Implementar en `CompraDocumentoController::crear` el alta opcional del proveedor **dentro de la misma transacción** que la compra, y bloquear la creación si no hay proveedor resuelto (FR-020)
- [X] T035 [US2] Añadir al modal y al JS el bloque de proveedor: preseleccionado si `criterio = 'nif'`; marcado visualmente como **sugerencia a confirmar** si es `'nombre'`; sub-formulario de alta si es `'sin_coincidencia'`, siguiendo la guía **"Alta inline: confirmación explícita, nunca por `blur`"** — check para confirmar (deshabilitado en vacío) + X para descartar, **Enter** confirma / **Escape** descarta, el `blur` no hace nada, el formulario **conserva lo escrito** si el alta falla en servidor, y guard de "enviando" contra el alta doble

**Checkpoint**: el catálogo de proveedores no puede ensuciarse sin una decisión del usuario.

---

## Phase 5: User Story 3 — Emparejar las líneas con el catálogo (P2)

**Meta**: que la compra pueda mover stock, sin emparejar mal.

**Test independiente**: subir un documento con una referencia que exista en el catálogo y otra que
no, y verificar que la primera llega emparejada y la segunda como línea libre.

### Tests primero

- [X] T036 [P] [US3] Escribir `tests/Unit/EmparejadorArticuloTest.php`: SKU exacto normalizado → `criterio: 'referencia'`; nombre `similar_text` ≥88 % y candidato único → `'nombre'`; sin match → `articulo_id: null` (línea libre, resultado válido); solo se consideran artículos **activos** y no borrados del tenant; `mueve_stock` es `true` solo para producto con `gestion_stock`
- [X] T037 [P] [US3] Añadir a `tests/Feature/CompraDocumentoCreacionTest.php`: confirmar una compra creada por esta vía **sube el stock** de las líneas con artículo producto+gestión, y anularla lo revierte — usando `RegistroCompra` sin modificarlo (FR-026)

### Implementación

- [X] T038 [US3] Crear `app/Support/EmparejadorArticulo.php` con la cascada de research D9. Umbral 88 % (más alto que el de proveedor: un falso positivo mete stock en el artículo equivocado). Hace pasar T036
- [X] T039 [US3] Integrar el emparejador en `ProponedorCompraDesdeDocumento` para poblar el bloque `articulo` de cada línea, con `criterio`, `similitud` y `mueve_stock`. **Nunca crear artículos** (FR-025)
- [X] T040 [US3] Añadir al modal y al JS el selector de artículo por línea: distingue visualmente match por referencia de sugerencia por nombre, permite cambiarlo o quitarlo, y avisa de qué líneas moverán inventario al confirmar

**Checkpoint**: la compra importada se integra con el control de stock.

---

## Phase 6: User Story 4 — Procesar varios documentos de una tacada (P2)

**Meta**: una cola de propuestas que sobrevive a fallos parciales.

**Test independiente**: subir tres documentos (uno ilegible) y obtener dos compras y un aviso
concreto sobre el tercero.

### Tests primero

- [X] T041 [P] [US4] Añadir a `tests/Feature/CompraDocumentoSubidaTest.php`: un lote mixto devuelve `documentos[]` con un token por fichero válido y `rechazados[]` con motivo por cada inválido; un lote **enteramente** rechazado responde 422 con el mismo cuerpo

### Implementación

- [X] T042 [US4] Extender el JS a la cola: contador "n de N", interpretación **en serie**, avanzar automáticamente al crear o descartar sin cerrar el modal, marcar como fallido el documento que devuelva `no_es_documento_compra` y **continuar** con el siguiente. Abortar la cola solo ante `clave_invalida` o `limite_excedido` (tabla de códigos de `contracts/endpoints.md`)
- [X] T043 [US4] Implementar el estado `resumen` del modal: cuántas compras se crearon, cuántas se descartaron, y cuáles fallaron y por qué, identificando cada documento por su **nombre de archivo**. Al cerrar, recargar tabla y métricas

**Checkpoint**: el lote es utilizable de verdad, no una promesa que se rompe al primer escaneo malo.

---

## Phase 7: User Story 5 — Evitar duplicar una compra ya registrada (P3)

**Meta**: avisar del duplicado sin quitarle la decisión al usuario.

**Test independiente**: subir dos veces el mismo documento y verificar que la segunda vez avisa.

### Tests primero

- [X] T044 [P] [US5] Añadir a `tests/Feature/CompraDocumentoCreacionTest.php`: con una compra ya existente de mismo proveedor + número + fecha, crear sin `confirmar_duplicado` responde **409** con `compra_existente`; con `confirmar_duplicado: true` **crea igualmente** (FR-031: avisa, no bloquea)

### Implementación

- [X] T045 [US5] Implementar la detección de duplicado (mismo criterio que `ImportadorFacturae`: proveedor + `numero_documento` + `fecha` por día) evaluada **dos veces**: como aviso en `ProponedorCompraDesdeDocumento` y de nuevo en `CompraDocumentoController::crear` (dos usuarios pueden subir el mismo documento a la vez)
- [X] T046 [US5] Añadir al JS el manejo del 409: mostrar el aviso con enlace a la compra existente y, si el usuario acepta, reintentar con `confirmar_duplicado: true`

---

## Phase 8: Aislamiento multi-tenant — BLOQUEANTE (Principio I, NON-NEGOTIABLE)

No es "polish": sin esto la feature no puede mergearse. Se escribe test-first como el resto.

- [X] T047 Escribir `tests/Feature/CompraDocumentoAislamientoTest.php` con **≥2 tenants**: un token generado en el tenant A responde **404** al interpretarlo o confirmarlo desde el tenant B (404, no 403: un token ajeno no debe confirmarse como existente)
- [X] T048 Añadir a ese test: enviar en la creación un `proveedor_id` o un `lineas.*.articulo_id` del tenant B desde el tenant A responde **422 de validación**, y no crea ninguna compra con datos cruzados
- [X] T049 Añadir a ese test: la descarga del documento de una compra del tenant A desde el tenant B responde 404, y el listado de compras de B nunca incluye compras de A
- [X] T050 Implementar en `AlmacenDocumentosCompra` y en `CompraDocumentoController` la comprobación de pertenencia del token al tenant activo. Hace pasar T047-T049

---

## Phase 9: Documentación (obligación de cierre, no un extra)

`CLAUDE.md` exige repasar **las 4 capas** en todo cambio. Ninguna de estas tareas es opcional.

- [X] T051 [P] Actualizar `resources/views/ayuda/compras.blade.php` con el flujo nuevo (FR-040), siguiendo la convención de markup de la guía: `<p>` de intro, `<ol>` de pasos, `<strong>` en los términos y un `<p class="ayuda-nota">` final con el error común a evitar — aquí: **dar por buena la propuesta sin revisar el proveedor y los tipos impositivos**. Sin scroll: solo qué hace la pantalla, cómo se usa y qué error evitar
- [X] T052 [P] **Crear** `resources/ia/conocimiento/compras.md` (FR-041). **No existe hoy**: Compras no está cubierto en la base de conocimiento del asistente. Cubrir el módulo completo (alta manual, confirmar/anular y su efecto en stock, importación de Facturae) más este flujo, dejando explícitas las tres reglas que el asistente debe saber explicar: la propuesta no persiste nada, el proveedor nunca se crea solo, y los importes los recalcula el servidor. Un archivo por módulo, sin tocar el resto (invariante SC-007 de la feature 030)
- [X] T053 [P] Añadir a `docs/04-front-guidelines.md` la sección **"Cola de propuestas revisables en un modal"** (FR-042): contador "n de N", avanzar al crear/descartar, resumen final con parciales fallidos, y por qué la tabla de líneas de la propuesta **no** es un DataTable. Es un patrón nuevo en el proyecto (el modal de Excel es de documento único)
- [X] T054 [P] Actualizar `docs/03-modelo-datos.md`: anotar el origen `documento` de `compras` y la reutilización de `formato_recepcion`/`archivo_recibido_path`, dejando constancia de que **no hubo migración**
- [X] T055 Evaluar si algún principio de la constitución necesita enmienda (`/speckit-constitution`). **Evaluado: NO se enmienda** (constitución v1.2.0 intacta). Motivos: (a) sin tablas ni columnas nuevas, así que no hay dato personal no cubierto — el temporal de 24 h con purga programada es exactamente el patrón que "Additional Constraints › Datos personales y retención" ya exige reutilizar; (b) el alcance de Compras ya está recogido en el bullet "Compras"; (c) el cálculo de importes en servidor y el aislamiento por tenant se cumplen con los principios tal como están, sin necesidad de matizarlos. Único apunte para el futuro: la coherencia del tipo impositivo se resolvió con una lista de referencia **informativa** (`TiposImpositivos::tiposHabitualesPara()`), sin endurecer `esTipoValido()`, que sigue siendo permisivo por decisión previa documentada. Criterio original: **no** parecía necesario — no hay tabla nueva, la retención de 24 h del temporal cae bajo el patrón de RGPD ya recogido en "Additional Constraints", y el bullet de **Compras** ya existe. Si la evaluación confirma que no, **dejarlo escrito aquí** y no tocar el archivo (`CLAUDE.md`: evitar actualizaciones innecesarias)

---

## Phase 10: Polish y verificación final

- [ ] T056 Recorrer los 9 escenarios de `quickstart.md` a mano contra la app, incluidos los que no cubre la suite (progreso visible, `blur` que no crea proveedor, aviso de duplicado, botón deshabilitado sin clave de IA)
- [ ] T057 Ejecutar `php artisan test` completa: además de lo nuevo, las suites de las features 040-043 de la rama base deben quedar en verde **sin modificar ni un test existente**
- [ ] T057b Medir la precisión de extracción contra SC-003 (≥90 % en campos de cabecera) y SC-004 (≥85 % en líneas): reunir **al menos 10 documentos reales y legibles** de proveedores distintos, procesarlos, y anotar en una tabla cuántos campos de cabecera y cuántas líneas llegaron correctos sin corrección. Si no se alcanza el umbral, ajustar el *prompt* del sistema en `InterpretadorDocumentoCompra` y repetir. Sin esta medición, SC-003 y SC-004 no son verificables y quedarían como afirmaciones sin comprobar
- [ ] T058 Verificar el rendimiento contra SC-008 (propuesta de un documento de una página en <30 s) y comprobar que ninguna petición HTTP hace más de **una** llamada al modelo
- [ ] T059 Repasar la guía de front sobre el JS y el Blade nuevos: `withButtonLoading` en todo botón que espere, `X-CSRF-TOKEN` en las peticiones sin form, `showToast` en vez de alerts, `@assetv` en el `<script>`, y ningún `decimal:N` de Eloquent impreso directo en Blade

---

## Dependencias

```
Phase 1 (Setup)
   └─> Phase 2 (Foundational)  ← BLOQUEA todo lo demás
          ├─> Phase 3 (US1, P1) ← MVP
          │      ├─> Phase 4 (US2, P1)   — necesita la propuesta de US1
          │      ├─> Phase 5 (US3, P2)   — necesita la propuesta de US1
          │      ├─> Phase 6 (US4, P2)   — necesita el modal de US1
          │      └─> Phase 7 (US5, P3)   — necesita la creación de US1
          └─> Phase 8 (Aislamiento) ← BLOQUEANTE para mergear, en paralelo desde US1
                 └─> Phase 9 (Documentación)
                        └─> Phase 10 (Polish)
```

**Orden dentro de cada fase**: tests → servicios → controlador/rutas → vistas/JS.

**US2, US3, US4 y US5 son independientes entre sí**: una vez cerrada US1, pueden abordarse en
cualquier orden o en paralelo por personas distintas.

---

## Oportunidades de paralelización

- **Phase 1**: T003 y T004 en paralelo.
- **Phase 2**: T005+T006 (tests) en paralelo; después T007+T008+T009 en paralelo (ficheros
  distintos, sin dependencias entre sí).
- **Phase 3**: T013-T017 son cinco tests en tres ficheros distintos → todos en paralelo. Luego
  T020+T021 (dos Form Requests) en paralelo.
- **Phases 4-7**: los tests unitarios de emparejadores (T030, T036) no dependen de nada más y pueden
  escribirse en cuanto termine la Phase 2.
- **Phase 9**: T051-T054 son cuatro ficheros distintos → todos en paralelo.

---

## Estrategia de entrega

1. **MVP = Phases 1-3 + Phase 8.** Entrega el valor central (registrar una compra desde un documento
   sin teclear) con el aislamiento ya garantizado. US1 sola ya es demostrable.
2. **Incremento 1 = Phase 4 (US2).** Es P1 igual que US1: sin ella, un proveedor desconocido corta
   el flujo. Debería ir en la misma entrega salvo urgencia.
3. **Incremento 2 = Phase 5 (US3).** Conecta la feature con el control de stock, que es la razón de
   ser de las compras en el producto.
4. **Incremento 3 = Phases 6-7 (US4, US5).** Multiplican el ahorro de tiempo y protegen la
   integridad, pero el producto ya es útil sin ellas.
5. **Phases 9-10 no son opcionales ni "para después"**: `CLAUDE.md` exige que la documentación entre
   en el mismo cambio. Una guía in-app desactualizada es peor que no tenerla.

---

## Resumen

| Fase | Tareas | Historia |
|---|---|---|
| 1. Setup | T001-T004 | — |
| 2. Foundational | T005-T012 | — |
| 3. Crear compra desde documento | T013-T029e | US1 (P1) — **MVP** |
| 4. Proveedor | T030-T035 | US2 (P1) |
| 5. Líneas y artículos | T036-T040 | US3 (P2) |
| 6. Lote | T041-T043 | US4 (P2) |
| 7. Duplicados | T044-T046 | US5 (P3) |
| 8. Aislamiento | T047-T050 | — (bloqueante) |
| 9. Documentación | T051-T055 | — |
| 10. Polish | T056-T059 | — |

**Total: 64 tareas** (59 iniciales + las 5 añadidas tras `/speckit-analyze` — T029b, T029c, T029d,
T029e y T057b — que cierran los huecos de cobertura de FR-011, FR-012, FR-030, FR-039, SC-003 y
SC-004). 24 marcadas `[P]`. Sin migraciones, sin dependencias nuevas, sin tablas nuevas.
