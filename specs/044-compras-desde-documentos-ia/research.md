# Research — Compras desde documentos (PDF/imagen) interpretados por IA

**Feature**: `044-compras-desde-documentos-ia` · **Fecha**: 2026-09-06

Resuelve todos los `NEEDS CLARIFICATION` del Technical Context de `plan.md`. Cada decisión indica
qué exigencia del spec o de la constitución la fuerza.

---

## D1 — Cómo llega un PDF/imagen al modelo sin dependencias de sistema

**Decisión**: enviar el documento **al modelo tal cual**, en base64, dentro del mensaje de Chat
Completions:

- **Imágenes** (`jpg`, `jpeg`, `png`, `webp`): content part `image_url` con data URI
  (`data:image/png;base64,…`).
- **PDF**: content part `file` con `file_data` (data URI `data:application/pdf;base64,…`) +
  `filename`. El modelo recibe el PDF completo; la extracción de páginas la hace el proveedor.

**Rationale**: **Principio V (hosting compartido)**. Rasterizar un PDF en local exigiría Imagick +
Ghostscript o `pdftoppm`, ninguno garantizado en cPanel y ninguno instalable sin root. Mandar el
fichero al proveedor mueve ese trabajo fuera del servidor y deja el requisito en "PHP con base64",
que siempre se cumple. `openai-php/client ^0.20` pasa el array `messages` tal cual al endpoint, así
que soportar un content part nuevo no requiere actualizar la librería.

**Alternativas rechazadas**:

- **`smalot/pdfparser`** (extraer texto en PHP y mandar solo texto): no funciona con PDFs escaneados
  (la mitad de los casos reales: el proveedor manda un escaneo), y pierde la disposición en columnas
  de la tabla de líneas, que es justo la señal que permite leer cantidad/precio/tipo correctamente.
- **Rasterizar con Imagick y mandar imágenes**: viola Principio V (dependencia de sistema).
- **API de Responses en vez de Chat Completions**: no aporta nada aquí (no hay estado entre turnos
  ni herramientas) y se aleja del único patrón de IA ya establecido en el proyecto (`AsistenteIa`
  usa Chat Completions).

**Consecuencia para el spec**: FR-004 (rechazo previo por tamaño/páginas) es también una defensa de
coste, no solo de UX: el fichero viaja entero al proveedor.

---

## D2 — Cómo se garantiza que la respuesta del modelo sea una propuesta parseable

**Decisión**: **Structured Outputs** — `response_format: {type: 'json_schema', json_schema: {...,
strict: true}}` con el esquema de la propuesta (ver `contracts/propuesta-compra.schema.json`).
Llamada **no streaming**, una sola iteración, **sin function calling**.

**Rationale**: el resultado es un formulario, no una conversación. Con `strict: true` el proveedor
garantiza la forma del JSON, lo que elimina el parseo defensivo y hace que "el documento no es una
compra" (FR-009) se exprese dentro del propio esquema (campo `es_documento_compra: false` +
`motivo`) en vez de como texto libre a interpretar.

**Alternativas rechazadas**:

- **Reutilizar `AsistenteIa::responder()`**: es un orquestador conversacional con estado en sesión,
  streaming, catálogo de tools y el ciclo de acción pendiente/confirmación (D4 de la feature 030).
  Nada de eso aplica; acoplarse a él arrastraría su sesión de chat a un flujo que no la tiene. Sí se
  reutiliza todo lo de **abajo** de ese servicio: `IaTenant::apiKey()`, `config('ia.modelo')` y el
  mapeo de excepciones a códigos de error.
- **Pedir JSON "por prompt" sin esquema**: obliga a parseo defensivo y a reintentos, y no impide que
  el modelo devuelva prosa.

---

## D3 — Dónde vive la lógica nueva

**Decisión**: tres piezas nuevas, ninguna dentro de `AsistenteIa` ni de `CompraController::guardar()`:

| Pieza | Responsabilidad |
|---|---|
| `App\Services\InterpretadorDocumentoCompra` | Habla con el proveedor de IA: arma el mensaje, aplica el esquema, mapea errores a códigos. **No conoce Eloquent.** |
| `App\Services\ProponedorCompraDesdeDocumento` | Toma la lectura cruda del modelo y la convierte en propuesta de negocio: empareja proveedor, empareja artículos, marca campos ilegibles, recalcula importes, detecta duplicado. **No llama al proveedor de IA.** |
| `App\Http\Controllers\CompraDocumentoController` | Orquesta los tres pasos HTTP (subir / interpretar / crear) y la autorización. |

**Rationale**: separar "hablar con el modelo" de "decidir qué significa para este tenant" permite
testear todo el emparejamiento y el recálculo **sin red y sin clave de API** (Principio IV: los
tests de cálculo y aislamiento no pueden depender de un servicio externo). Solo
`InterpretadorDocumentoCompra` se sustituye por un doble en los tests.

**Nota**: `RegistroCompra` (confirmar/anular/stock) **no se toca**. FR-026 exige explícitamente que
este flujo no introduzca reglas de stock nuevas.

---

## D4 — Persistencia transitoria del documento entre "subir" y "crear"

**Decisión**: replicar el patrón ya probado de `App\Services\AlmacenImportaciones` en un
`App\Services\AlmacenDocumentosCompra`:

- disco `local`, carpeta **`compras-documentos/{tenant_id}/`**, nombre `{uuid}.{ext}`, token = uuid;
- caducidad de 24 h comprobada en lectura (no solo al purgar);
- `borrar($token)` al crear la compra o al descartar la propuesta;
- comando `compras-documentos:purgar` registrado en `bootstrap/app.php` → `withSchedule` con
  `->daily()`, junto a los seis `*:purgar` que ya hay.

Al **crear** la compra, el fichero se **mueve** al disco `documentos` bajo
`tenants/{tenant_id}/compras-documentos/{uuid}.{ext}` y esa ruta se guarda en
`compras.archivo_recibido_path`, exactamente como hace `ImportadorFacturae` con el XML recibido.

**Rationale**: FR-011 (nada se persiste hasta confirmar) + FR-012 (el documento se descarta si se
abandona) + FR-038 (retención y purga, Principio II/RGPD). El patrón ya existe, está probado y ya
tiene su hueco en el scheduler; inventar otro sería exactamente lo que la constitución prohíbe.

**Divergencia obligada respecto a `AlmacenImportaciones`: el tenant va en la ruta.** El almacén de
importaciones guarda en una carpeta plana (`importaciones/{uuid}.{ext}`) sin rastro del tenant. Aquí
**no vale copiarlo tal cual**: sin el tenant en la ruta, un token filtrado sería utilizable desde
cualquier tenant, porque no habría nada contra lo que comprobar la pertenencia — y el Principio I es
NON-NEGOTIABLE. Por eso la carpeta se segmenta por tenant y **toda** operación
(`rutaAbsoluta`/`borrar`) resuelve el fichero **solo** dentro del subárbol del tenant activo: un
token de otro tenant simplemente "no existe" (404), sin comparar identificadores ni revelar que
existe en otro sitio. Es una diferencia deliberada, no un olvido de consistencia con el patrón
original.

**Sobre la retención (FR-038)**: se distinguen dos poblaciones de fichero, y solo una es "dato
personal huérfano":

- **Propuesta no confirmada** → fichero huérfano sin dueño de negocio: retención **24 h fija**,
  purga diaria. No se hace configurable por tenant: no es un archivo del negocio, es un temporal.
- **Propuesta confirmada** → el fichero pasa a ser el **justificante** de una compra existente y
  sigue el ciclo de vida de esa compra (igual que el XML de un Facturae recibido). Su retención es
  la de la compra, no un plazo propio.

**Quirk heredado a respetar**: el disco `documentos` tiene `root => storage_path('app/tenants')` y
`ImportadorFacturae` guarda bajo `'tenants/'.$tenantId.'/…'`, con lo que la ruta física real queda
`storage/app/tenants/tenants/{id}/…`. Es redundante pero es la convención existente y hay datos
guardados así: **se replica tal cual**, no se "arregla" en esta feature.

---

## D5 — Quién es fuente de verdad de los importes (Principio III)

**Decisión**: la propuesta viaja al navegador como JSON y **vuelve editada** en el paso de creación.
El servidor **ignora por completo** cualquier base/cuota/total recibido y recalcula desde
`cantidad × precio_unitario` y `tipo_impositivo`, reutilizando la lógica que ya tiene
`CompraController::guardar()`. Lo único que el servidor conserva entre pasos es el **fichero**
(por token), no los importes.

Además, `proveedor_id` y `lineas.*.articulo_id` se validan con el mismo `Rule::exists(...)->where(
tenant_id = tenant actual)` que ya usa `StoreCompraRequest` — un id de otro tenant es un fallo de
validación, no un 404 (FR-036, Principio I).

**Rationale**: Principio III literal ("el cliente NUNCA es fuente de verdad para un importe"). Que
la propuesta viaje por el cliente es indiferente mientras nada de lo que viene del cliente entre en
un importe persistido sin recalcular ni en una FK sin revalidar contra el tenant.

**Alternativa rechazada**: guardar la propuesta en servidor (sesión o tabla temporal) y confirmarla
por id. Añade estado servidor, una tabla o una sesión gorda, y colisiona con el paso de edición (hay
que reenviar los cambios igualmente). El fichero ya obliga a un token; los datos no lo necesitan.

---

## D6 — Cómo se marca el origen de estas compras

**Decisión**: nuevo caso `OrigenCompra::Documento = 'documento'` (label "Documento (IA)"), con
`formato_recepcion` = `'pdf'` o `'imagen'` y `archivo_recibido_path` apuntando al documento movido.

**Sin migración**: `compras.origen` es `string(20)` con default `'manual'`
(`2026_07_05_130002_add_recepcion_facturae_to_compras_table.php`), y `formato_recepcion` y
`archivo_recibido_path` ya existen y están en `$fillable`. Basta añadir el caso al enum PHP.

**`estado_b2b` queda a `null`**: el ciclo B2B es propio de la recepción por Facturae. El filtro
"Todos los estados B2B" del listado ya trata `null` correctamente (muestra `-`).

**Rationale**: FR-029. Reutilizar las tres columnas que la feature 022 ya creó para exactamente este
propósito ("compra que entró como documento recibido") en vez de añadir columnas paralelas.

---

## D7 — Lote: un documento por petición, no un lote por petición

**Decisión**: tres endpoints y **una llamada al modelo por petición HTTP**:

1. `POST /compras/documentos` — sube **todos** los ficheros del lote, los valida (tipo, tamaño,
   nº de ficheros) y devuelve **un token por fichero**. No llama al modelo.
2. `POST /compras/documentos/{token}/interpretar` — interpreta **un** documento y devuelve su
   propuesta. El navegador la llama **en serie**, una por token, actualizando el progreso.
3. `POST /compras/documentos/{token}/crear` — crea la compra desde la propuesta editada.

**Rationale**: **Principio V**. Un lote de 5 documentos en una sola petición son 5 llamadas al
modelo encadenadas dentro del mismo request: en cPanel, con `max_execution_time` típico de 30-60 s y
timeouts de proxy, eso se corta a media faena y deja ficheros huérfanos sin manera de saber cuáles
se procesaron. Una petición por documento acota cada request a una llamada, hace natural el
"documento ilegible no invalida el resto" (FR-034: es un `.fail()` de una petición, no un error
parcial dentro de una respuesta) y da progreso real ("2 de 5") sin streaming ni polling.

Nada de esto introduce colas ni workers: sigue siendo síncrono dentro del request, como
`AsistenteIa`.

**Límites concretos** (FR-004), configurables en `config/compras.php`:

| Límite | Valor por defecto | Motivo |
|---|---|---|
| Ficheros por lote | 10 | Cota de paciencia y de coste; a ~15 s/documento son ~2,5 min de cola. |
| Tamaño por fichero | 10 MB | Un escaneo A4 en color a 300 dpi ronda 1-3 MB; 10 MB cubre holgado. Base64 infla ~33 %. |
| Páginas por PDF | 10 | Una factura de proveedor rara vez pasa de 3; el tope corta el PDF-catálogo de 80 páginas antes de gastar la llamada. |
| Tipos admitidos | `pdf`, `jpg`, `jpeg`, `png`, `webp` | `webp` porque es lo que producen muchos móviles hoy. |

El conteo de páginas del PDF se hace **sin dependencias**: contando ocurrencias de `/Type /Page` en
los bytes del fichero. Es una heurística; si no puede determinarse, **no se bloquea** (el límite de
tamaño ya actúa de tope duro).

---

## D8 — Emparejamiento de proveedor

**Decisión**, en este orden, primer acierto gana:

1. **NIF/CIF normalizado** (mayúsculas, sin espacios, guiones ni puntos) contra `proveedores.nif`
   → `criterio: 'nif'`, proveedor **preseleccionado**.
2. **Nombre**: `similar_text` (porcentaje) del nombre leído contra `nombre` y `razon_social`
   normalizados (minúsculas, sin acentos, sin formas societarias `s.l.`/`s.a.`/`slu`…). Umbral
   **85 %**, y solo si hay **un único** candidato por encima → `criterio: 'nombre'`, proveedor
   **sugerido, no preseleccionado**.
3. Nada → `criterio: 'sin_coincidencia'` + `proveedor_nuevo` con los datos leídos, editables, que el
   usuario debe confirmar.

**Rationale**: FR-017/FR-018/FR-019. El NIF es identificador fiscal y por tanto fiable; el nombre no
lo es ("Distribuciones García SL" vs "Distribuciones García e Hijos SL"), de ahí que el spec exija
distinguir ambos casos en la UI y que un match por nombre nunca dé por hecho el proveedor. El
requisito de candidato **único** evita elegir arbitrariamente entre dos proveedores parecidos.

**Alternativa rechazada**: `levenshtein` a secas — su distancia absoluta no normaliza por longitud,
así que un mismo umbral se comporta distinto en nombres cortos y largos. `similar_text` devuelve
porcentaje y es suficiente para catálogos de proveedores (decenas o cientos de filas, no millones).

**Alternativa rechazada**: pedirle el emparejamiento al modelo mandándole el catálogo. Multiplica
tokens y coste por documento, y mete datos de proveedores del tenant en el prompt sin necesidad
(minimización de datos, Principio II).

---

## D9 — Emparejamiento de artículo por línea

**Decisión**, mismo esquema de dos niveles:

1. **SKU exacto** (comparado normalizado: `trim` + mayúsculas) contra la referencia leída de la
   línea → `criterio: 'referencia'`.
2. **Nombre**: `similar_text` ≥ **88 %** contra `articulos.nombre` normalizado, candidato único →
   `criterio: 'nombre'` (sugerencia).
3. Nada → `articulo_id: null`, **línea libre**, resultado válido (FR-023).

Solo se consideran artículos **activos** y no borrados del tenant. El umbral es más alto que el de
proveedor porque los nombres de artículo de un mismo catálogo se parecen mucho entre sí ("Tornillo
M6 20mm" vs "Tornillo M6 25mm"): un falso positivo aquí mete stock en el artículo equivocado.

**Rationale**: FR-022/FR-023/FR-024 y el enganche con stock (solo las líneas con artículo
producto + `gestion_stock` mueven inventario en `RegistroCompra::confirmar()`).

**FR-025 (no crear artículos)**: el catálogo es de solo lectura en este flujo. Si el usuario quiere
un artículo nuevo, lo crea en su pantalla; aquí la línea queda libre.

---

## D10 — Tipos impositivos y régimen del tenant

**Decisión**: el tipo leído se muestra siempre tal cual, y se marca `tipo_impositivo_coherente:
false` cuando `App\Support\TiposImpositivos::esTipoValido(tenant()->regimen_impositivo, $tipo)`
falla. La UI lo señala; **no bloquea** la creación (el usuario puede tener razón).

Se **prescinde** de un tipo "por defecto" cuando el documento no lo indica: el campo queda vacío y
marcado como ilegible (FR-010). Asumir 21 % sería exactamente el bug de cumplimiento que el
Principio II prohíbe.

**Sobre la validación al crear**: `StoreCompraRequest` valida hoy `tipo_impositivo` como
`numeric|min:0`, sin `App\Rules\TipoImpositivoValido`. Esta feature **no cambia esa validación**
(sería un cambio de comportamiento del alta manual de compras, fuera de alcance): se limita a avisar
en la propuesta. Queda anotado como observación para una feature futura de coherencia de compras.

---

## D11 — Detección de duplicado

**Decisión**: mismo criterio que `ImportadorFacturae` — misma `proveedor_id` + mismo
`numero_documento` + misma `fecha` (comparada por día). Se evalúa **dos veces**: al generar la
propuesta (aviso informativo con enlace a la compra existente) y **de nuevo al crear** (dos usuarios
del mismo tenant pueden estar subiendo el mismo documento a la vez). En ambos casos **avisa, no
bloquea** (FR-031).

**Diferencia deliberada con Facturae**: allí el duplicado **aborta** la importación (HTTP 409, no se
crea nada). Aquí no puede abortar, porque hay un humano revisando que puede saber que la
renumeración del proveedor es legítima. Esta divergencia se anota en `plan.md` para que no se lea
como una inconsistencia.

---

## D12 — Front: cola de propuestas dentro del modal

**Decisión**: un modal con cuatro estados alternados por `d-none` — `subir`, `interpretando`
(progreso "n de N"), `propuesta` (editable), `resumen` (cierre del lote) — reutilizando
literalmente la mecánica de `excel/_importar_modal.blade.php` + `excel-importar-modal.init.js`
(pasos en el mismo modal, el fichero no se vuelve a pedir, `table.ajax.reload(null, false)` al
final).

Piezas obligatorias de la guía de front que se aplican:

- `window.withButtonLoading` + `data-loading-text` en **Interpretar** y en **Crear compra**
  (sección "Estado de carga en botones"), imprescindible aquí porque la espera es de decenas de
  segundos.
- Header `X-CSRF-TOKEN` explícito en las peticiones sin form serializado
  (`interpretar`, `descartar`) — el precedente del olvido está documentado en `compras-show.init.js`.
- `window.showToast(...)` para todo aviso; **cero** `div.alert` ad-hoc.
- Modal centrado verticalmente (`modal-dialog-centered`), formularios en tamaño `sm`.
- Los importes se pintan formateados desde el JSON de la respuesta, nunca imprimiendo un `decimal:N`
  de Eloquent directo en Blade.
- **`@assetv('js/plugins-init/compras-importar-documento.init.js')`**, nunca `asset()` (sección
  "Assets propios", feature 043): el deploy a este hosting es por FTP y los estáticos se sirven con
  caché de una semana; sin versionar, el JS nuevo no llega al usuario.
- **El sub-formulario de "crear proveedor nuevo" sigue la sección "Alta inline en un listado:
  confirmación explícita, nunca por `blur`"** (feature 041): check para confirmar + X para
  descartar, check `disabled` mientras esté vacío, **Enter** confirma y **Escape** descarta, perder
  el foco no hace nada, y si el alta falla en servidor el formulario **conserva lo escrito**. Guard
  de "enviando" contra el doble alta. Es exactamente el riesgo que FR-019 quiere evitar (crear un
  proveedor que nadie pidió), resuelto con el patrón que ya existe en
  `filaDeAlta()` de `pos-sala-plano-gestion.init.js`.

**Novedad a documentar en `docs/04-front-guidelines.md`** (FR-042): el patrón "**cola de propuestas
revisables en un modal**" (contador "n de N", avanzar al crear/descartar, resumen final con
parciales fallidos) no existe hoy en el proyecto — el modal de importación de Excel es de documento
único. Se anota como sección nueva al cerrar la feature.

**La tabla de líneas de la propuesta NO es un DataTable**, y esto es deliberado: la guía exige
DataTable para **listados**; esto es la tabla de edición de las líneas de un documento, el mismo
caso que la tabla de líneas del alta de compras (`compras/_form_lineas.blade.php`), que tampoco lo
es. Queda registrado en `plan.md` para que `/speckit-analyze` no lo lea como violación.

---

## D13 — Degradación cuando no hay clave de IA

**Decisión**: `IaTenant::configurada()` se consulta **en el Blade del listado** (para renderizar el
botón deshabilitado con un tooltip que indica dónde configurarlo) **y en el controlador** (que
responde 422 con mensaje accionable si alguien llega igual al endpoint). El mapeo de errores del
proveedor replica el de `AsistenteIa::responder()`: `clave_invalida` (401), `limite_excedido` (429),
`servicio_no_disponible` (transporte), `interno` (resto), y el detalle técnico solo se expone a
quien tiene `ver-configuracion` (FR-008, mismo criterio que FR-011 de la feature 030).

**Rationale**: FR-007. Un botón que abre un modal que falla siempre es peor que un botón que explica
por qué no puede usarse.

---

## Resumen de decisiones

| # | Decisión |
|---|---|
| D1 | PDF/imagen en base64 al modelo; sin Imagick ni Ghostscript |
| D2 | Structured Outputs con `json_schema` estricto, sin streaming ni tools |
| D3 | `InterpretadorDocumentoCompra` + `ProponedorCompraDesdeDocumento` + `CompraDocumentoController` |
| D4 | `AlmacenDocumentosCompra` (patrón `AlmacenImportaciones`) + `compras-documentos:purgar` diario |
| D5 | Importes siempre recalculados en servidor; FKs revalidadas contra el tenant |
| D6 | `OrigenCompra::Documento`, sin migración (`origen` ya es `string(20)`) |
| D7 | Un documento por petición HTTP: subir lote → interpretar uno a uno → crear |
| D8 | Proveedor: NIF exacto → nombre ≥85 % único → alta confirmada por el usuario |
| D9 | Artículo: SKU exacto → nombre ≥88 % único → línea libre |
| D10 | Tipo impositivo señalado contra el régimen del tenant, sin bloquear ni asumir 21 % |
| D11 | Duplicado evaluado al proponer y al crear; avisa, no bloquea |
| D12 | Modal de 4 estados con cola de propuestas; patrón nuevo a documentar |
| D13 | Sin clave de IA: botón deshabilitado + 422 accionable; errores mapeados como en `AsistenteIa` |
