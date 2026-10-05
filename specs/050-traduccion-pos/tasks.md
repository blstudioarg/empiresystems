# Tasks: Traducción del POS al chino

**Input**: Design documents from `/specs/050-traduccion-pos/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/traducciones.md](contracts/traducciones.md),
[quickstart.md](quickstart.md)

**Tests**: obligatorios donde la constitución lo exige (Principio IV): aislamiento de las
correcciones entre tenants y "el idioma no altera importes, numeración, QR ni Verifactu". Marcados
**[TEST-FIRST]**: se escriben antes que la implementación y deben fallar primero. El resto lleva
tests de feature normales. **Ningún test llama a DeepL**: todo con `Http::fake()`.

**Notas de testing del proyecto** (memorias): las factories fijan `tenant_id => Tenant::factory()`,
hay que forzar el tenant activo; con `SESSION_DRIVER=array` y el `Translator` singleton no mezclar
dos tenants en un mismo método de test (el traductor cachea lo cargado por idioma); el texto de las
guías in-app se renderiza siempre, así que los asserts de vista van sobre marcadores HTML o sobre el
texto traducido falso, nunca sobre texto español que también esté en la ayuda.

**Diccionario falso para tests**: los tests de pantallas insertan en `traducciones` la traducción
`⟦<texto>⟧` de cada clave extraída del ámbito `pos`, de modo que cualquier texto español visible sin
corchetes delata un texto sin marcar.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

**Purpose**: normativa y modelo de datos documentados antes del código (Principio II), configuración
y recursos estáticos.

- [X] T001 [P] Añadir en `docs/02-facturacion-espana.md` una subsección "§3.3 Idioma de las facturas (RD 1619/2012 art. 12.2)": se pueden expedir en cualquier lengua; la Administración tributaria puede exigir traducción al castellano u otra lengua oficial; decisión del proyecto (feature 050): con el POS en chino, ticket y precuenta bilingües español/chino para que el castellano esté siempre en el documento; el partial `verifactu-qr` y las menciones reguladas no se traducen. Citar el BOE (BOE-A-2012-14696) en "Fuentes"
- [X] T002 [P] Añadir a `docs/03-modelo-datos.md` las tablas `traducciones` (central, sin `tenant_id`, con la justificación) y `traduccion_correcciones` (tenant) según [data-model.md](data-model.md), la clave `pos.idioma` en la lista de claves de `ConfigPos` y la nota de datos personales (`corregida_por`)
- [X] T003 Crear `config/traduccion.php`: `idiomas` (`['es' => 'Español', 'zh' => '中文 (chino simplificado)']`), `idioma_origen` `es`, proveedor `deepl` con `api_key` (`env('DEEPL_API_KEY')`), `api_url` (`env('DEEPL_API_URL', 'https://api-free.deepl.com')`), `timeout` (5 s en el respaldo, 30 s en el comando), `lote` 50, `max_intentos` 5; `glosario.zh` con los términos de research D7; `no_traducir` (IVA, IGIC, IPSI, NIF, VERI*FACTU, TPV, €); `ambitos.pos.rutas` con la lista de archivos/carpetas de plan.md § "Marcado de textos" y `ambitos.pos.claves_extra`. Añadir `DEEPL_API_KEY=` y `DEEPL_API_URL=` vacíos a `.env.example`
- [X] T004 [P] Añadir la fuente Noto Sans SC (OFL) en TTF estático, regular y negrita, en `resources/fonts/` junto con su licencia `resources/fonts/OFL-NotoSansSC.txt`
- [X] T005 [P] Verificar con la clave de `.env` (`GET {DEEPL_API_URL}/v2/glossary-language-pairs`) que el par `es→zh` admite glosario y anotar el resultado en [research.md](research.md) D6; si no lo admite, aplicar el plan B de la tabla de riesgos de plan.md (términos aplicados localmente) y reflejarlo en las tareas de US2

---

## Phase 2: Foundational (bloquea todas las historias)

**Purpose**: tablas, modelos, idioma del tenant, cargador de traducciones, cliente DeepL, `__t()` en JS
y middleware. Todo lo demás lo consume.

### Tests (TEST-FIRST)

- [X] T006 [P] [TEST-FIRST] `tests/Feature/Traduccion/CargadorTraduccionesTest.php`: con `pos.idioma` ausente o `es`, `__('Cobrar')` devuelve `Cobrar` y no se hace ninguna consulta a `traducciones`; con locale `zh`, devuelve la traducción de `traducciones` (`estado = traducida`); una fila `pendiente` o `error` devuelve el español; una corrección del tenant activo prevalece sobre la automática; las variables `:mesa` se sustituyen en la traducción; con locale `zh`, cargar N claves distintas en un request hace **una sola** consulta a `traducciones` y una a `traduccion_correcciones` (SC-003)
- [X] T007 [P] [TEST-FIRST] `tests/Feature/Traduccion/AislamientoCorreccionesTest.php` (Principio I), en métodos separados por tenant: la corrección del tenant A no aparece en las traducciones cargadas para el tenant B; `TraduccionCorreccion::all()` en A no ve las de B; (tras US4) `GET /configuracion/pos/traducciones` de B no lista la corrección de A y `PUT`/`DELETE` de B sobre un hash corregido por A no tocan la fila de A

### Implementación

- [X] T008 Migraciones `database/migrations/2026_10_06_100000_create_traducciones_table.php` y `2026_10_06_100100_create_traduccion_correcciones_table.php` según [data-model.md](data-model.md) (índices `UNIQUE(idioma, hash)`, `(idioma, estado)`, `UNIQUE(tenant_id, idioma, hash)`; `corregida_por` `nullOnDelete`). Aplicar con `php artisan migrate` (nunca `migrate:fresh`, CLAUDE.md)
- [X] T009 [P] Modelos `app/Models/Traduccion.php` (central, **sin** `BelongsToTenant`; `hash` calculado con `Traduccion::hashDe(string $texto)`; scopes `traducidas()`, `pendientes()`) y `app/Models/TraduccionCorreccion.php` (`BelongsToTenant`, relación `corregidaPor`); factories en `database/factories/`
- [X] T010 [P] `app/Support/ConfigPos.php`: constante `CLAVE_IDIOMA = 'pos.idioma'`, `idioma(int $tenantId): string` (`es` por ausencia de fila o valor no válido), `idioma` en `todo()` y en `guardar()` validado contra `config('traduccion.idiomas')`. **No** cruzado con `hosteleriaActivo()` (FR-002)
- [X] T011 `app/Traduccion/CargadorTraducciones.php` (implementa `Illuminate\Contracts\Translation\Loader`, delega en el `FileLoader` original para grupos y namespaces): para el grupo JSON (`*`/`*`) y un idioma distinto de `config('traduccion.idioma_origen')`, devuelve `texto => traduccion` de `traducciones` traducidas, sobrescritas por `traduccion_correcciones` del tenant activo (si lo hay). Registrar en `app/Providers/AppServiceProvider.php` con `$this->app->extend('translation.loader', …)`. Hasta que T006 pase
- [X] T012 [P] `app/Traduccion/ProveedorTraduccion.php` (interfaz: `traducir(array $textos, string $idioma, bool $html): array`, `sincronizarGlosario(string $idioma, array $entradas): ?string`) y `app/Traduccion/TraductorDeepl.php`: `Http::withHeaders(['Authorization' => 'DeepL-Auth-Key …'])` a `/v2/translate` con `source_lang=ES`, `target_lang=ZH-HANS`, lotes ≤ `config('traduccion.lote')`, `tag_handling=xml` con variables `:x` → `<v n="x"/>` e `ignore_tags=k` para `no_traducir`, o `tag_handling=html` si `$html`; restaura variables y siglas al volver; lanza una excepción propia `App\Exceptions\TraduccionNoDisponibleException` ante HTTP ≥ 400, 456 (cupo) o timeout. Binding de la interfaz en `AppServiceProvider`
- [X] T013 `app/Traduccion/MemoriaTraducciones.php`: `registrarPendientes(array $textos, string $ambito, string $idioma)` (insert-ignore por `idioma, hash`, marca `es_html` si el texto contiene etiquetas, actualiza `vista_en`), `traducirPendientes(string $idioma, int $limite, int $timeout): array` (lote → proveedor → `traducida` si conserva todas las `:variables`, si no `error`; ante `TraduccionNoDisponibleException` incrementa `intentos`, guarda `ultimo_error` y corta sin lanzar), `diccionario(string $ambito, string $idioma): array` (traducciones efectivas del tenant para `__t`)
- [X] T014 [P] `public/js/traduccion.js`: global `window.__t(texto, params)` según [contracts/traducciones.md](contracts/traducciones.md) (diccionario `window.posI18n`, sustitución `:param`, devuelve el español si no hay traducción); parcial `resources/views/partials/pos-i18n.blade.php` que carga `traduccion.js` con `@assetv` en todas las páginas (para que `__t` exista siempre y devuelva el español) e inyecta `window.posI18n` con `MemoriaTraducciones::diccionario('pos', $idioma)` **solo** cuando el locale del request es distinto de `es` (es decir, en rutas del POS con el middleware `IdiomaPos` y el idioma en chino; § "Peso y cacheo de assets"); incluirlo en `layouts/app.blade.php` antes de `@stack('scripts')`
- [X] T015 `app/Http/Middleware/IdiomaPos.php` (alias `idioma.pos` en `bootstrap/app.php`): si `ConfigPos::idioma(tenant)` ≠ `es`, `app()->setLocale($idioma)`. Aplicarlo en `routes/web.php` a **todos** los grupos de rutas del POS: `ver-pos-caja` (caja, movimientos, cerrar, cierres, informe), `pos.caja.abrir`, `ver-pos-crear` (crear, store, opciones del artículo), `ver-pos-sala` + hostelería (sala, cuentas, precuentas), plano de sala, `ver-pos-opciones` (opciones y grupos) y `ver-pos` (listado, pdf). **No** a Configuración (FR-008)

**Checkpoint**: T006 y T007 (la parte de carga) en verde; la suite existente sigue en verde (con el
POS en español nada cambia).

---

## Phase 3: User Story 1 — Usar el POS en chino (Priority: P1) 🎯 MVP

**Goal**: con el idioma del POS en chino, todas las pantallas, avisos, mensajes del servidor, tablas,
menú del grupo POS y guías de ayuda del POS se ven en chino; en español nada cambia.

**Independent Test**: diccionario falso `⟦…⟧` cargado, recorrer las pantallas del POS y comprobar
que no queda texto español visible propio de la app; con el POS en español, HTML idéntico al de antes.

### Tests

- [X] T016 [P] [US1] `tests/Feature/Traduccion/IdiomaPosConfiguracionTest.php`: `PUT /configuracion/pos` con `idioma=zh` lo guarda y la respuesta lo incluye; valor no permitido → 422; sin el campo, el idioma no cambia; se puede elegir con el módulo de hostelería apagado (FR-002); un tenant sin la clave está en `es` (FR-003); el idioma de A no afecta a B
- [X] T017 [P] [US1] `tests/Feature/Traduccion/PosEnChinoTest.php` con el diccionario falso: `GET /pos`, `/pos/crear`, `/pos/sala`, `/pos/caja`, `/pos/caja/cierres`, `/pos/opciones` y la vista de módulo inactivo devuelven las marcas `⟦…⟧` en títulos/botones (asserts sobre marcadores, no sobre texto de ayuda); `window.posI18n` está presente solo con el POS en chino; un error del servidor del POS (p. ej. guardar una cuenta anulada, cobrar sin caja) devuelve `message` traducido; con el POS en español el `message` es el de siempre; `/clientes` (fuera del POS) sigue en español; el menú lateral en `/perfil` muestra traducidas solo las entradas del grupo POS y el botón de ayuda (FR-009)

### Implementación — idioma en Configuración

- [X] T018 [US1] `app/Http/Controllers/Configuracion/PosConfiguracionController.php`: aceptar `idioma` (`sometimes`, `in:` claves de `config('traduccion.idiomas')`), guardarlo con `ConfigPos::guardar`, devolver `idioma` y `traducciones_pendientes` en la respuesta JSON ([contracts](contracts/traducciones.md))
- [X] T019 [US1] `resources/views/configuracion/_tab_pos.blade.php` + `public/js/plugins-init/configuracion-pos.init.js`: selector «Idioma del POS» (español / 中文) en un bloque propio, fuera de los ajustes del módulo de hostelería, guardado con el mismo flujo AJAX de la pestaña (`withButtonLoading`, `showToast`); si quedan textos pendientes, aviso informativo. La pestaña de Configuración queda en español (FR-008)

### Implementación — marcado de textos (los archivos son distintos: en paralelo)

Regla para todas: textos propios → `__('…')` en Blade/PHP y `__t('…')` en JS, clave = el texto en
español actual **sin cambiarlo**; variables con `:nombre`; datos del negocio, importes y siglas fuera
de `__()`. Las cadenas que ya construyen HTML en JS conservan el escapado (`PosApp.escapeHtml`) del
dato, no del texto traducido.

- [X] T020 [P] [US1] Listado de tickets: `resources/views/pos/index.blade.php`, `public/js/plugins-init/pos-datatable.init.js` (incluido su objeto `language` de DataTables: buscar, mostrar, paginar, sin resultados, info)
- [X] T021 [P] [US1] Crear ticket (vista): `resources/views/pos/create.blade.php` (catálogo, ticket, chip de mesa y de caja, botonera, modales de total, cobro, receptor, éxito, ver ticket, precuenta, mover, opciones) y los parciales `resources/views/pos/_caja-apertura.blade.php`, `resources/views/pos/_caja-bandeja.blade.php`
- [X] T022 [P] [US1] Crear ticket (JS): `public/js/pos-form.js`, `pos-ticket.js`, `pos-catalogo.js`, `pos-cobro.js`, `pos-cuenta.js`, `pos-precuenta.js`, `pos-opciones.js`, `pos-teclado.js`, `pos-caja-bandeja.js`, `pos-caja-apertura.js`, `pos-caja-tpv.js` (toasts, confirmaciones de `confirmDelete`, etiquetas generadas, textos de estado del chip)
- [X] T023 [P] [US1] Sala: `resources/views/pos/sala.blade.php`, `public/js/plugins-init/pos-sala.init.js`, `pos-plano-dibujo.js`, `pos-sala-plano.init.js`, `pos-sala-plano-servicio.init.js`, `pos-sala-plano-gestion.init.js` (estados de mesa, métricas, «Hace :min min», editor del plano y sus avisos)
- [X] T024 [P] [US1] Caja y cierres: `resources/views/pos/caja.blade.php`, `resources/views/pos/caja-cierres.blade.php`, `public/js/plugins-init/pos-caja.init.js`, `pos-caja-cierres.init.js` (con su `language` de DataTables) y los informes `resources/views/caja/informe-80mm.blade.php`, `resources/views/caja/informe-a4.blade.php` (documento interno: se traduce entero, research D9)
- [X] T025 [P] [US1] Opciones de artículo y módulo inactivo: `resources/views/pos/opciones/index.blade.php`, `public/js/plugins-init/pos-opciones-datatable.init.js` (con su `language`), `resources/views/pos/modulo-inactivo.blade.php` y el `message` JSON de `app/Http/Middleware/ModuloHosteleriaActivo.php`
- [X] T026 [P] [US1] Mensajes del servidor del POS: `app/Http/Controllers/PosController.php`, `app/Http/Controllers/Pos/*.php`, `app/Http/Controllers/Pos/Concerns/RespondeConCuenta.php`, `app/Services/CobradorCuenta.php`, `TransferidorCuenta.php`, `PrecuentaCuenta.php`, `AperturaCaja.php`, `CierreCaja.php`, `MovimientosCaja.php`, `RegistroTicket.php` (solo los mensajes), las excepciones `CajaCerradaException`, `CajaYaAbiertaException`, `CajaYaCerradaException`, `TicketFueraDeTopeException`, `PagoTicketDescuadradoException` y los `messages()` de `AbrirCajaRequest`, `CerrarCajaRequest` y `MovimientoCajaRequest` (`CobrarCuentaPosRequest` y `GuardarCuentaPosRequest` no tienen mensajes propios: no se les añaden, Assumptions de la spec). Los mensajes con datos (`«:concepto»`) usan variables
- [X] T027 [P] [US1] Guías de ayuda del POS: `resources/views/ayuda/pos.blade.php`, `pos-crear.blade.php`, `pos-sala.blade.php`, `pos-caja.blade.php`, `pos-caja-cierres.blade.php`, `pos-opciones.blade.php`: cada `<p>`/`<li>` como un bloque `{!! __('…') !!}` con su HTML interno (research D8), y los `@section('ayuda-titulo', …)` de las vistas del POS con `__()`
- [X] T028 [US1] Menú y ayuda global (FR-009): `app/Support/MenuTenant.php` traduce con `__($etiqueta, [], $idiomaPos)` solo las entradas del grupo POS (incluida la etiqueta del grupo); una etiqueta personalizada por el tenant se muestra tal cual, sin traducir (FR-009); `resources/views/partials/sidebar.blade.php` (botón «Ayuda de esta pantalla» y su subtítulo) y `resources/views/partials/ayuda-modal.blade.php` («Guía rápida», estado vacío) con el idioma del POS; `ambitos.pos.claves_extra` en `config/traduccion.php` aporta estas claves al extractor

**Checkpoint**: US1 demostrable con traducciones cargadas a mano o por el comando (quickstart 1–2).

---

## Phase 4: User Story 3 — Cambiar textos sin tocar traducciones (Priority: P1)

**Goal**: el deploy traduce lo nuevo; lo que se escape se traduce tras el primer uso; la API caída no
afecta al POS.

**Independent Test**: añadir un `__('Texto nuevo')` a una vista del POS, correr el comando con
`Http::fake()` y ver la fila traducida; sin correrlo, abrir la pantalla en chino y ver la fila
pendiente creada tras la respuesta.

- [X] T029 [P] [US3] `tests/Feature/Traduccion/SincronizarTraduccionesTest.php`: el extractor encuentra claves en Blade (`__('…')`, `@lang`), PHP y JS (`__t('…')`) del ámbito, con comillas simples/dobles y escapes, y las de `claves_extra`; `--solo-extraer` no llama a la API; traduce en lotes ≤ 50; segunda ejecución sin nuevas no llama a la API (idempotente, SC-009); respuesta 456/500/timeout → código de salida 0, filas `pendiente`/`error` con `intentos` y resumen con el fallo; traducción que pierde una `:variable` → `error`; una corrección de tenant existente no se modifica (FR-017); un texto con HTML se envía con `tag_handling=html`
- [X] T030 [P] [US3] `tests/Feature/Traduccion/RespaldoPrimerUsoTest.php`: con el POS en chino, una clave sin fila en `traducciones` se muestra en español y, tras terminar el request, existe como fila (traducida si `Http::fake()` responde, pendiente si falla); la respuesta HTTP no espera a la API (el fake con retardo no la retrasa: se comprueba que la traducción ocurre en `terminating`); con el POS en español no se registra nada
- [X] T031 [US3] `app/Traduccion/ExtractorClaves.php`: recorre `config('traduccion.ambitos.<ambito>.rutas')` y extrae claves con las expresiones de research D5 (desescapando `\'` y `\"`), más `claves_extra`; devuelve la lista única
- [X] T032 [US3] `app/Console/Commands/SincronizarTraducciones.php` (`traducciones:sincronizar {--ambito=pos} {--idioma=zh} {--solo-extraer}`) según [contracts/traducciones.md](contracts/traducciones.md): extraer → `registrarPendientes` → (glosario, US2) → `traducirPendientes` con `timeout` largo → resumen. Nunca falla por la API; falla si falta la clave y se pidió traducir
- [X] T033 [US3] Respaldo de primer uso en `app/Providers/AppServiceProvider.php`: `Lang::handleMissingKeysUsing` apunta las claves que no encontró el idioma ≠ `es` (solo grupo JSON); `app()->terminating()` las registra con `MemoriaTraducciones::registrarPendientes(…, 'pos', …)` y traduce ese lote con `timeout` corto, todo dentro de `try/catch` (un fallo nunca rompe nada, FR-013)
- [X] T034 [US3] Deploy: añadir `php artisan traducciones:sincronizar` a los comandos de `docs/08-despliegue-empiresass.md` y de `.claude/skills/deploy-empiresass/SKILL.md` (después de `migrate`, antes de las cachés), y las dos claves `DEEPL_*` a la lista del `.env` de producción

**Checkpoint**: US1 + US3 = POS en chino sin mantenimiento manual (quickstart "Traducción real" y 4).

---

## Phase 5: User Story 2 — Términos del POS siempre bien y siempre igual (Priority: P1)

**Goal**: el glosario fija la traducción de los términos del POS en todas sus apariciones.

**Independent Test**: con `Http::fake()` comprobar que toda llamada de traducción lleva el
`glossary_id` del glosario vigente y que un texto que es exactamente un término no llama a la API.

- [X] T035 [P] [US2] Ampliar `tests/Feature/Traduccion/SincronizarTraduccionesTest.php`: el comando crea el glosario `empire-pos-es-zh-<hash8>` si no existe, lo reutiliza si existe y borra los antiguos con el prefijo; las llamadas a `/v2/translate` llevan su `glossary_id`; «Precuenta» (término exacto) se guarda como `预结单` sin llamar a la API; cambiar una entrada del glosario cambia el nombre (nuevo glosario)
- [X] T036 [US2] `app/Traduccion/TraductorDeepl.php`: `sincronizarGlosario()` (listar `/v2/glossaries`, crear con entradas TSV, borrar los obsoletos del prefijo) y uso de `glossary_id` en `traducir()`; id cacheado para el respaldo (si la caché se vacía, se busca por nombre). `MemoriaTraducciones`: los textos idénticos a un término (sin distinguir mayúsculas iniciales) se traducen localmente con el glosario. Llamarlo desde `SincronizarTraducciones` (T032)
- [X] T037 [US2] Revisar y completar `glosario.zh` en `config/traduccion.php` con los términos de FR-015 y research D7, y los que aparezcan repetidos en el resultado de `traducciones:sincronizar --solo-extraer` (p. ej. «Guardar», «Aparcadas», «Transferir», «Unir», «Anular»)

**Checkpoint**: quickstart 3.

---

## Phase 6: User Story 4 — Corregir una traducción (Priority: P2)

**Goal**: un usuario con permiso corrige traducciones de su tenant; nada las pisa.

**Independent Test**: corregir «Cobrar», verla en el TPV, correr el comando, seguir viéndola; otro
tenant ve la automática.

- [X] T038 [P] [US4] `tests/Feature/Traduccion/CorreccionTraduccionTest.php` contra el contrato: `GET` lista solo el ámbito `pos` del idioma del tenant con `origen` correcto y `data: []` en español; `PUT` crea/actualiza la corrección y responde `origen: corregida`; falta de una `:variable` → 422; HTML no permitido se sanea (`<script>` desaparece, `<strong>` queda); hash inexistente → 404; `DELETE` restaura la automática; sin `ver-configuracion` → 403; la corrección se ve en `GET /pos/crear` (diccionario) y sobrevive a `traducciones:sincronizar`. Completar la parte HTTP de T007
- [X] T039 [US4] `app/Http/Controllers/Configuracion/TraduccionPosController.php` (`index`, `update`, `destroy`) + rutas `configuracion.pos.traducciones.*` en `routes/web.php` dentro del grupo `can:ver-configuracion`, sin `modulo.hosteleria`; búsqueda del hash bajo el idioma del tenant; validación de variables y saneado HTML (research D8) en un `FormRequest` propio
- [X] T040 [US4] `resources/views/configuracion/_tab_pos.blade.php` + `public/js/plugins-init/configuracion-pos.init.js`: sección «Traducciones del POS» visible solo con idioma ≠ `es`: DataTable client-side (§ "Listados: SIEMPRE DataTable", con el override de "Anterior/Siguiente" de § "DataTable: botones…") con texto, traducción efectiva y badge de origen (§ "Badges de estado"); modal centrado de edición (§ "CRUD simple…", textarea, aviso de variables a conservar), «Restaurar automática» con `confirmDelete`; `withButtonLoading` y `showToast`

**Checkpoint**: quickstart 5.

---

## Phase 7: User Story 5 — Ticket y precuenta bilingües y con caracteres chinos (Priority: P2)

**Goal**: PDFs legibles con datos en chino siempre; bilingües con el POS en chino; nada fiscal
cambia.

**Independent Test**: emitir ticket y precuenta con un artículo de nombre chino en un tenant en
chino y en otro en español.

- [X] T041 [P] [US5] [TEST-FIRST] `tests/Feature/Traduccion/DocumentosBilinguesTest.php`: con el POS en chino (diccionario fijo), el HTML renderizado de `facturas.ticket-80mm`, de `facturas.pdf` para una simplificada y de `pos.precuenta-80mm` contiene cada etiqueta en español y en chino («Total / …», «Documento no válido como factura» + traducción); la factura simplificada A4 de un tenant en chino que **no** es simplificada (ordinaria) no cambia; total, `numero_completo`, desglose de impuestos y el bloque `verifactu-qr` son idénticos byte a byte en ese fragmento entre el mismo ticket con POS `es` y `zh` (Principio III, FR-021); con POS en español y sin datos chinos el HTML de los tres documentos es idéntico al actual (sigue con DejaVu Sans, sin `$fuenteCjk`); con POS en español pero un artículo de nombre chino, la vista recibe `$fuenteCjk = true` y usa Noto Sans SC (FR-019); `GET /pos/{id}/pdf` y `/pos/precuentas/{id}/pdf` responden `application/pdf` con un artículo de nombre chino
- [X] T042 [US5] `app/Traduccion/Bilingue.php` + helper global `bilingue()` (registrado vía `composer.json` `autoload.files` o en el `AppServiceProvider` como función) según el contrato: lee `ConfigPos::idioma` del tenant del documento, no el locale del request
- [X] T043 [US5] Plantillas: `resources/views/facturas/ticket-80mm.blade.php`, `resources/views/facturas/pdf.blade.php` (solo cuando `tipo` es simplificada) y `resources/views/pos/precuenta-80mm.blade.php` usan `bilingue()` en sus textos propios; si reciben `$fuenteCjk`, `font-family` Noto Sans SC con `@font-face` hacia `resources/fonts/` (si no, DejaVu Sans como hoy); el partial `partials/verifactu-qr.blade.php` **no** se toca. Revisar que las líneas bilingües caben en 80 mm (dos líneas si no)
- [X] T044 [US5] Detección y subsetting: `app/Traduccion/Bilingue.php` expone `contieneCjk(string ...$textos): bool` (rango Unicode CJK); `PosController::pdf` (formatos ticket y A4), `PrecuentaController::pdf` y `FacturaController::pdf` (solo simplificadas) calculan `$fuenteCjk` sobre líneas, receptor, notas y si el documento sale bilingüe, lo pasan a la vista y, si es `true`, activan `isFontSubsettingEnabled`; el comando `traducciones:sincronizar` (o uno propio `pos:preparar-fuentes`) pre-genera la caché de métricas de dompdf para Noto Sans SC en `storage/fonts`, para que el primer ticket en producción no la genere en caliente. Medir tamaño del PDF (objetivo: < 300 KB)

**Checkpoint**: quickstart 6.

---

## Phase 8: Polish & documentación (las 4 capas, CLAUDE.md)

- [X] T045 [P] `resources/views/ayuda/configuracion.blade.php`: el ajuste «Idioma del POS» y cómo corregir traducciones (FR-022)
- [X] T046 [P] `resources/ia/conocimiento/configuracion.md` y `pos.md`: idioma del POS, qué se traduce y qué no, correcciones, documentos bilingües (FR-022; el asistente no se traduce)
- [X] T047 [P] `docs/04-front-guidelines.md`: sección nueva "Textos traducibles: `__()` / `__t()` con el texto en español como clave" (qué se marca, variables, HTML de las guías con `{!! !!}`, datos del negocio nunca, diccionario JS solo en el POS) y nota en "Ayuda contextual" sobre los bloques traducibles
- [X] T048 [P] `docs/01-arquitectura.md`: "Decisión 12 — Traducción automática con memoria en BD y glosario (050-traduccion-pos)" con las decisiones D1–D4, D6, D7 de research; `docs/00-vision.md`: alcance ampliado "Idioma del POS"
- [X] T049 Correr `php artisan test` completo y la validación de [quickstart.md](quickstart.md), incluida la traducción real con DeepL (pedir confirmación antes de usar el navegador, CLAUDE.md)
- [X] T050 Revisar si la constitución necesita enmienda (`/speckit-constitution`): esperado **no** (la tabla central sin `tenant_id` se justifica en Complexity Tracking; ningún principio cambia). Dejar constancia en el cierre

---

## Dependencies & Execution Order

- **Phase 1** primero (T001/T002 por Principio II). T005 condiciona US2.
- **Phase 2** bloquea todo: T008 → T009/T010 → T011 (con T006/T007 escritos antes) → T012 → T013 → T014/T015.
- **US1 (Phase 3)** depende de Phase 2. El marcado (T020–T027) es paralelo entre sí; T028 después de T010.
- **US3 (Phase 4)** depende de Phase 2 (memoria, proveedor). Puede ir en paralelo con el marcado de US1.
- **US2 (Phase 5)** depende de US3 (el comando sincroniza el glosario).
- **US4 (Phase 6)** depende de Phase 2; independiente del resto.
- **US5 (Phase 7)** depende de Phase 2 (idioma) y del diccionario; independiente de US1–US4.
- **Polish** al final; T045–T048 en paralelo.

### Parallel Opportunities

- T001 ∥ T002 ∥ T004 ∥ T005; T006 ∥ T007; T009 ∥ T010; T012 ∥ T014.
- US1: T016 ∥ T017; T020 ∥ T021 ∥ T022 ∥ T023 ∥ T024 ∥ T025 ∥ T026 ∥ T027.
- US3: T029 ∥ T030. US4 y US5 en paralelo con US2.
- Polish: T045 ∥ T046 ∥ T047 ∥ T048.

## Implementation Strategy

1. **MVP**: Phase 1 + Phase 2 + US1 + US3 → el POS se ve en chino y se mantiene solo.
2. **+ US2**: glosario (imprescindible antes de entregarlo al cliente: sin él «Precuenta» sale mal).
3. **+ US5**: documentos bilingües y con caracteres chinos.
4. **+ US4**: corrección manual por tenant.
5. Polish y documentación en el mismo cambio, antes de dar la feature por cerrada.

## Notas de implementación (2026-10-05)

Ajustes respecto al plan, decididos al implementar (todos cubiertos por tests en `tests/Feature/Traduccion/`):

- **T005**: `GET /v2/glossary-language-pairs` confirma `es→zh` (research D6); no hizo falta el plan B.
- **T008–T015**: el `IdiomaPos` resetea lo cargado por el `Translator` al fijar el idioma (en un proceso que atiende varios requests —tests— una corrección recién guardada no se veía). El middleware va primero en el grupo para que también salgan traducidos el cartel de módulo inactivo y los 403. Las rutas `articulos.opciones.*` (ficha del catálogo) quedan fuera; las de `configuracion.pos.zonas/mesas.*` entran, porque solo las usa el editor del plano de la Sala.
- **T020–T028**: además de lo listado se marcaron `StoreTicketRequest`, `ResumenCaja::etiquetaMetodo`, `ObservacionRequeridaException`, `PosZonaController`/`PosMesaController` y el modal global de confirmación (`confirm-delete`), que aparecen en pantallas del POS. Las frases partidas en JS (`'No se encontraron ' + que`) se reescribieron enteras. El test de pantallas comprueba que **no queda ningún texto español visible** propio en las 6 pantallas (SC-001).
- **T024**: el informe de cierre (80 mm/A4) usa la fuente CJK cuando sale en chino (sin ella dompdf pintaba cuadrados).
- **T029/T032**: sin textos pendientes el comando no llama a la API (ni glosario). Opción nueva `--retraducir` (tras cambiar el glosario; no toca correcciones).
- **T036/T037**: tras la traducción real se cambió la protección de variables a `<x>:var</x>` + `ignore_tags` (con etiquetas vacías DeepL las colocaba mal), los textos en MAYÚSCULAS se envían en minúsculas, el glosario local no distingue mayúsculas y admite **frases fijadas con variables** (solo locales, no van al glosario remoto). El glosario viejo se borra **antes** de crear el nuevo (el plan gratuito devuelve 456 si no). Se añadieron términos de caja (cuadra, sobrante, faltante, suplemento…) y frases donde DeepL ignoraba el glosario.
- **T044**: comando propio `pos:preparar-fuentes` (no dentro de la sincronización). `storage/fonts` se versiona con un `.gitignore` (dompdf falla si la carpeta no existe). En el deploy ambos comandos van tras `optimize:clear` (leen la config actual) y antes de las cachés. Medido: PDF de prueba 6–9 KB con subsetting; ~1 s con la caché de métricas.
- **T043**: la fuente CJK se imprime con `Bilingue::estiloFuenteCjk()` pegado al `</style>` (no un partial), para que un documento sin caracteres chinos sea byte a byte el de antes. También se pasa `$fuenteCjk` en el PDF por email y en el de Facturae (`FacturaController`, `EnvioFacturae`).
- **T049**: suite completa y traducción real con DeepL (582 textos, ~29 000 caracteres por pasada, 0 errores; segunda ejecución sin llamadas). El recorrido manual en navegador (quickstart 1–7) queda pendiente de la confirmación del usuario para usar el navegador (CLAUDE.md).
- **T050**: la constitución no necesita enmienda (la tabla central sin `tenant_id` está justificada en Complexity Tracking; ningún principio cambia).
