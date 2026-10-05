# Research: Traducción del POS al chino

Decisiones técnicas tomadas antes del diseño. Cada una resuelve un punto que la spec deja abierto o
un riesgo detectado al leer el código existente.

## D1 — Marcar los textos con el traductor estándar de Laravel (`__()`), clave = texto en español

- **Decision**: todo texto propio del POS se escribe como `__('Texto en español')` (Blade y PHP) o
  `__t('Texto en español')` (JS). La clave es el propio texto en español: con el POS en español no
  hay ninguna búsqueda que encuentre traducción y `__()` devuelve la clave tal cual, así que **el
  español no cambia en nada** (SC-002). Partes variables con la convención de Laravel
  (`__('Mesa :nombre', ['nombre' => $mesa])`, y lo mismo en JS).
- **Rationale**: es el mecanismo estándar del framework; la clave-texto hace que cambiar un texto en
  español produzca automáticamente una clave nueva (FR-011, US3) sin ningún archivo que mantener.
  Extensible al resto de la app pantalla a pantalla: lo que no se marca sigue en español.
- **Alternatives considered**: claves simbólicas (`pos.cobro.titulo`) + archivos por idioma
  (descartado: es exactamente el mantenimiento manual que el usuario rechazó); traducir la página
  entera en el navegador (descartado en la conversación previa: traduce importes y datos del
  negocio, rompe el contenido dinámico de los JS y manda todo a un tercero).

## D2 — Las traducciones viven en base de datos, cargadas por un `TranslationLoader` propio

- **Decision**: se sustituye el loader de traducciones de Laravel (`translation.loader`) por uno que,
  para un idioma distinto del español, combina: (1) los archivos de `lang/` si existieran (no hay
  hoy), (2) la tabla global `traducciones` (traducción automática) y (3) la tabla
  `traduccion_correcciones` del tenant activo, que prevalece (FR-018). Para español no consulta
  nada. Se carga **una sola vez por request** (el `Translator` ya cachea lo cargado por idioma).
- **Rationale**: `__()` funciona igual en Blade, PHP y en los mensajes de validación sin tocar cada
  llamada; la consulta es una por request (≈ cientos de filas cortas) y no depende de la API
  (SC-003).
- **Alternatives considered**: generar `lang/zh.json` en el deploy (descartado: las correcciones son
  por tenant y se hacen en caliente desde la app, no pueden vivir en un archivo del repo); caché de
  aplicación (descartado: `optimize:clear` del deploy la vacía y no aporta frente a una consulta
  indexada; se puede añadir después sin cambiar el contrato).

## D3 — Idioma por request: middleware en las rutas del POS + idioma explícito en el menú y los PDFs

- **Decision**: un middleware `IdiomaPos` aplicado a los grupos de rutas del POS (listado, crear,
  cuentas, sala, plano, caja, cierres, opciones, precuenta, ticket) hace `app()->setLocale('zh')`
  si `ConfigPos::idioma()` lo es; si no, no toca nada. El menú lateral traduce **solo** las entradas
  del grupo POS y el botón de ayuda pasando el idioma explícito (`__($etiqueta, [], $idiomaPos)`)
  en cualquier pantalla (FR-009). Los PDFs bilingües (D9) piden el idioma de forma explícita, no
  dependen del locale del request.
- **Rationale**: fuera del POS nada cambia (FR-008); dentro, todo lo que use `__()` (vistas, ayuda,
  mensajes del servidor) sale traducido sin pasar el idioma a mano.
- **Alternatives considered**: cambiar el locale global del tenant (descartado: traduciría a medias
  el resto de la app); idioma por usuario (fuera de alcance, el diseño lo admite cambiando solo de
  dónde lee el middleware).

## D4 — Traducción en el deploy + respaldo "al primer uso" sin esperar a la API

- **Decision**:
  - Comando `traducciones:sincronizar` (se añade a los comandos de deploy, `docs/08`): extrae las
    claves de los archivos del ámbito POS (D5), inserta las nuevas como pendientes y traduce las
    pendientes en lotes de hasta 50 textos por llamada. Idempotente; no falla el deploy si la API
    no responde o se agotó el cupo (queda pendiente para el próximo intento) (FR-012, edge case).
  - Respaldo: cuando en un request en chino `__()` no encuentra una clave
    (`Lang::handleMissingKeysUsing`), se apunta. **Después de enviar la respuesta** (`terminating`,
    con `fastcgi_finish_request` de PHP-FPM) se insertan como pendientes y se traducen con un
    timeout corto. El usuario ve ese texto en español la primera vez y en chino desde la siguiente
    carga, **sin esperar nunca a la API** (US3-2, SC-003, SC-005).
- **Rationale**: SC-003 y FR-013 prohíben que la carga de una pantalla dependa de un tercero; el
  deploy cubre el 99 % y el respaldo cubre olvidos sin intervención manual.
- **Alternatives considered**: traducir dentro del request (descartado: latencia y fallo visible en
  pleno servicio); colas (descartado por Principio V: el hosting no tiene worker permanente).

## D5 — Ámbito de extracción declarado en configuración, no "toda la app"

- **Decision**: `config/traduccion.php` declara el ámbito `pos`: lista de archivos/carpetas (vistas
  del POS y sus parciales, guías `ayuda/pos*.blade.php`, plantillas de ticket/precuenta/informe de
  caja, JS del POS, controladores/servicios/requests del POS) y claves extra no extraíbles por
  regex (etiquetas del grupo POS de `CatalogoMenu`). El extractor busca `__('…')`, `__("…")`,
  `@lang('…')` y `__t('…')` con expresiones regulares. Añadir otra parte de la app = añadir sus
  rutas a un ámbito nuevo.
- **Rationale**: el usuario pidió solo el POS con un mecanismo extensible; acotar la extracción
  evita traducir (y gastar cupo en) textos que nadie verá en chino.

## D6 — Proveedor: DeepL vía cliente HTTP de Laravel, sin SDK

- **Decision**: llamadas directas con `Http::` a `POST {DEEPL_API_URL}/v2/translate` (`source_lang=ES`,
  `target_lang=ZH-HANS`, hasta 50 `text[]` por llamada, `glossary_id`), con timeout y sin
  reintentos agresivos. Interfaz propia `ProveedorTraduccion` con implementación `TraductorDeepl`,
  para poder cambiar de proveedor (Azure como alternativa evaluada) o simularlo en tests con
  `Http::fake()` — **ningún test llama a la API real**.
- **Rationale**: Principio V (sin dependencia nueva: el SDK oficial `deeplcom/deepl-php` no aporta
  nada que dos endpoints no resuelvan). Clave de plataforma en `.env` (`DEEPL_API_KEY`,
  `DEEPL_API_URL`), leída solo vía `config/traduccion.php` (compatible con `config:cache`).
- **Verificado**: cuenta creada y probada el 2026-10-05 (`/v2/usage` OK; ES→ZH-HANS OK). Según la
  documentación oficial, ZH-HANS admite glosarios y Spanish también. **Confirmado el 2026-10-05
  (T005)**: `GET /v2/glossary-language-pairs` incluye `{"source_lang":"es","target_lang":"zh"}`;
  no hace falta el plan B (términos aplicados localmente). La cuenta no tenía glosarios creados.

## D7 — Glosario versionado en el repo, sincronizado con DeepL sin estado local

- **Decision**: la lista de términos (FR-015) vive en `config/traduccion.php` (`glosario.zh`:
  español → chino). `traducciones:sincronizar` crea en DeepL un glosario llamado
  `empire-pos-es-zh-<hash8 de las entradas>`; si ya existe uno con ese nombre lo reutiliza, y borra
  los antiguos con el prefijo. El id se cachea para el respaldo de D4 (si la caché se vacía, se
  vuelve a buscar por nombre). Los textos que **son exactamente** un término del glosario se
  traducen localmente con el glosario, sin llamar a la API.
- **Rationale**: el glosario cambia con el código y se revisa en PR; no hace falta tabla nueva ni
  guardar estado (el nombre con hash identifica la versión). Caso real: «Precuenta» → «预算»
  («presupuesto») sin glosario.
- **Contenido inicial** (a validar con el cliente, Assumptions de la spec): precuenta 预结单, ticket
  小票, factura simplificada 简易发票, cuenta 账单, cuenta aparcada 挂单, mesa 餐桌, sala 餐厅,
  zona 区域, suplemento de zona 区域附加费, comensales 用餐人数, caja 收银台, fondo de cambio 备用金,
  arqueo 盘点现金, cierre de caja 收银结账, cobrar 收款, cobro por partes 分开收款, opción 选项,
  grupo de opciones 选项组, reimpresión 重新打印. Las siglas (IVA, IGIC, IPSI, NIF, VERI*FACTU, TPV)
  se protegen como no traducibles (D8).

## D8 — Proteger variables, siglas y HTML con `tag_handling`

- **Decision**:
  - Variables `:nombre` → se sustituyen antes de enviar por etiquetas XML `<v n="nombre"/>` y se
    pide `tag_handling=xml`; al volver se restauran. Las siglas no traducibles se envuelven en
    `<k>…</k>` con `ignore_tags=k`. Si la traducción vuelve sin alguna variable, se marca como
    `error` y se sigue mostrando en español (FR-014).
  - Textos de las guías de ayuda (con `<strong>`, `<em>`): `tag_handling=html`. Al mostrarse se
    imprimen sin escapar (`{!! !!}`), igual que hoy; por eso las **correcciones manuales** de
    textos con HTML se sanean al guardarse (solo `strong`, `em`, `br`) — un tenant no puede
    inyectar HTML en su propio POS.
- **Rationale**: DeepL respeta las etiquetas; las variables de Laravel en texto plano se traducirían
  o reordenarían mal.

## D9 — Documentos bilingües con un helper explícito y fuente CJK con subsetting

- **Decision**:
  - Las plantillas de ticket (80 mm), factura simplificada A4 y precuenta usan
    `bilingue('Total')`: devuelve `Total` si el idioma del POS es español, y `Total / 合计` (o en
    dos líneas donde no quepa) si es chino. No depende del locale del request: el PDF se puede
    generar desde cualquier ruta. El partial `verifactu-qr` **no se toca** (menciones reguladas).
    La plantilla A4 es compartida con las facturas ordinarias: lo bilingüe solo aplica si el
    documento es `simplificada`.
  - Fuente: **Noto Sans SC** (OFL) en TTF estático regular + negrita, registrada para dompdf. Como
    dompdf **no** hace sustitución por glifo entre fuentes, cuando el documento contiene algún
    carácter chino (en los datos o por ser bilingüe) la plantilla usa Noto Sans SC para **todo** el
    texto (incluye latín). Si el documento no contiene ninguno, sigue con DejaVu Sans y se ve
    exactamente igual que antes (FR-019, SC-002). La detección la hace el controlador sobre el
    texto del documento (líneas, cliente, notas y etiquetas bilingües) con una expresión de rango
    CJK, y pasa a la vista un flag `$fuenteCjk`. Con el flag, se activa `isFontSubsettingEnabled`:
    sin subsetting cada ticket incrustaría la fuente entera (~10 MB).
  - El informe de cierre de caja (80 mm y A4) es un documento interno: se traduce entero (no
    bilingüe), igual que la pantalla de Caja.
- **Riesgo**: generar las métricas de una fuente CJK la primera vez consume memoria y tiempo en
  dompdf. Mitigación: la caché de métricas se genera en el deploy (paso del comando de
  sincronización o `load_font`) y se mide en la validación del quickstart en el hosting.
- **Alternatives considered**: fuente solo para el texto chino con `<span>` (descartado: los datos
  del negocio mezclan idiomas en el mismo campo y dompdf no hace fallback por glifo).

## D10 — Corrección manual por tenant, desde Configuración → POS

- **Decision**: sección «Traducciones del POS» en la pestaña POS de Configuración, visible solo si el
  idioma del POS no es español: DataTable (§ "Listados: SIEMPRE DataTable") con texto en español,
  traducción efectiva y origen (automática / corregida), filtro de búsqueda, y edición en modal
  (§ "CRUD simple: alta/edición en modal + AJAX"). Guardar crea o actualiza la fila de
  `traduccion_correcciones` del tenant; «Restaurar automática» la borra (es un dato del propio
  tenant, no un registro fiscal). Permiso `ver-configuracion`, sin entrada de menú nueva (no aplica
  § "Nueva entrada de menú ⇒ nuevo permiso").
- **Rationale**: FR-016/017/018. La traducción automática no se toca nunca desde aquí, así que la
  sincronización no puede pisar una corrección (FR-017): son tablas distintas.
- **Historial** (US4-3): la corrección queda asociada al hash del texto que corregía; si el español
  cambia, esa fila deja de aplicarse pero no se borra.

## D11 — Datos personales y aislamiento

- `traducciones` es **tabla central de la plataforma** (sin `tenant_id`): guarda textos de la app,
  no datos de ningún tenant, igual que el catálogo de permisos. Se justifica en el Constitution
  Check del plan.
- `traduccion_correcciones` lleva `tenant_id` + `BelongsToTenant` y `corregida_por` (FK nullable a
  `users`, `nullOnDelete`), mismo tipo de dato personal que `abierta_por`; sin IP ni user-agent. Es
  configuración del tenant, sin purga propia.
