# Research — 048 Cierre de caja del POS

Decisiones técnicas tomadas antes de diseñar. Ninguna queda abierta.

## D1. Dónde vive la atribución ticket → sesión de caja

- **Decision**: columna nueva `caja_sesion_id` (nullable, FK) en **`ticket_pagos`**, no en `facturas`.
- **Rationale**: `ticket_pagos` es ya el "desglose interno de cómo se cobró en caja" (docs/03,
  feature 2026-07-19): es exactamente lo que el arqueo lee, todo ticket tiene ≥ 1 fila, y es una
  tabla **no fiscal**. El esquema de facturación no se toca si se puede evitar (criterio ya fijado
  en la feature 038, docs/03 "Impacto en tablas existentes"). El efectivo esperado sale de sumar
  `ticket_pagos.importe` con `metodo = efectivo` de la sesión; el nº de tickets es
  `COUNT(DISTINCT factura_id)`.
- **Alternatives considered**:
  - `facturas.caja_sesion_id`: más directo, pero mete un concepto de control interno en la tabla
    fiscal núcleo del sistema (Principio II, inmutabilidad: habría que escribirlo dentro de la misma
    emisión y razonar sobre si forma parte del registro). Descartado.
  - Atribuir por rango horario (`created_at` entre apertura y cierre): frágil ante relojes, cierres
    que cruzan medianoche y tickets emitidos durante el propio cierre. Descartado (FR-004 exige
    atribución permanente).
  - Tabla pivot `caja_sesion_tickets`: una tabla más para lo que una columna resuelve. Descartado
    (Principio V).

## D2. Una sola sesión abierta por tenant, también en concurrencia

- **Decision**: doble barrera.
  1. Columna `abierta_marca` (tinyint nullable): `1` mientras la sesión está abierta, `NULL` al
     cerrarse, con `UNIQUE (tenant_id, abierta_marca)`. MySQL/MariaDB admiten varios `NULL` en un
     índice único, así que solo puede existir una fila con `1` por tenant: la segunda apertura
     simultánea revienta en el `INSERT` y se traduce a "ya hay una caja abierta" (FR-002).
  2. Toda operación que dependa de la sesión abierta (emitir, movimiento, cerrar) la lee con
     `lockForUpdate()` dentro de su transacción.
- **Rationale**: el `UNIQUE` hace imposible el estado inválido aunque falle la lógica; el bloqueo
  serializa cierre vs. emisión (edge case "ticket emitido mientras se cuenta el cierre").
- **Alternatives considered**: columna generada (`GENERATED ALWAYS AS (IF(estado='abierta',1,NULL))`):
  equivalente, pero las columnas generadas tienen soporte desigual entre versiones de MariaDB de
  hosting compartido (Principio V). Se mantiene explícita y la escribe el servicio.

## D3. Cobrar exige caja abierta (FR-020): dónde se aplica

- **Decision**: en `App\Services\RegistroTicket::registrar()`, dentro de su transacción: busca la
  sesión abierta del tenant con `lockForUpdate()`; si no hay, lanza `CajaCerradaException`; si hay,
  escribe su id en cada `ticket_pagos` creado. `PosController@store` y
  `Pos\CuentaController@cobrar` capturan la excepción junto a las ya existentes
  (`TicketFueraDeTopeException|PagoTicketDescuadradoException`) y responden **409** con
  `{ message, codigo: "caja_cerrada" }`.
- **Rationale**: `RegistroTicket` es el **único** punto por el que pasa toda emisión de ticket
  (`CobradorCuenta` lo reutiliza, docs/03 feature 038), así que la regla se cumple por construcción
  para el TPV y para las cuentas de mesa. El bloqueo compartido con el cierre (D2) es lo que hace
  que un ticket nunca caiga "entre" dos sesiones.
- **409 y no 422**: no es un error de validación de lo enviado, es un conflicto de estado del
  servidor (como el 409 de versión obsoleta de `pos_cuentas`). El `codigo` permite al JS ofrecer la
  apertura inline en vez de un toast genérico.
- **Consecuencia en tests**: ~13 tests de feature emiten tickets (`PosTicketEmisionTest`,
  `PosPagoDivididoTest`, `TicketStockTest`, `Pos/CobroParcial*`, `RegistroVerifactuTest`, …). Se crea
  un trait `Tests\Concerns\ConCajaAbierta` cuyo hook `setUpConCajaAbierta()` abre caja a cada tenant creado en el test (los tests crean tenants en helpers propios, no en `setUp`). No se
  cambia ninguna aserción: si un test necesita tocar algo más que abrir caja, la feature está
  cambiando comportamiento y hay que revisarlo.

## D4. Congelar las cifras del cierre (FR-013, FR-017, SC-005)

- **Decision**: al cerrar, `CierreCaja` calcula el informe y lo persiste en la propia sesión:
  columnas para las cifras que se listan/filtran (`num_tickets`, `total_facturado`,
  `efectivo_ventas`, `entradas`, `salidas`, `efectivo_esperado`, `efectivo_contado`, `descuadre`) y
  un JSON `resumen` con los desgloses (por método, por impuesto, anulados, primer/último número).
  El informe Z de una sesión cerrada se pinta **siempre** desde lo congelado, nunca recalculando.
  `CajaSesion` rechaza cualquier `update`/`delete` una vez cerrada (evento `updating`/`deleting`
  del modelo, igual criterio que la inmutabilidad de facturas emitidas).
- **Rationale**: un ticket anulado días después no puede cambiar el informe de un día cerrado: el
  cierre refleja lo que había en el cajón en ese momento. Recalcular haría que el histórico "se
  moviera".
- **Ticket anulado después del cierre**: queda contado en el cierre original (era una venta cuando
  se cerró). Su anulación es un hecho de otro momento y no se arquea retroactivamente. Documentado
  en la guía in-app.
- **Alternatives considered**: tabla `caja_sesion_desglose` normalizada (método/impuesto por fila).
  Más tablas para algo que solo se lee entero para pintar el informe. Descartado (Principio V);
  las cifras que sí se listan en el DataTable van como columnas.

## D5. Informe X (en vivo) y Z (cierre) comparten el cálculo

- **Decision**: un único servicio `App\Services\ResumenCaja::calcular(CajaSesion): ResumenCajaDto`
  produce el desglose. La pantalla en vivo lo llama sobre la sesión abierta; `CierreCaja` lo llama
  dentro de la transacción de cierre y congela su salida. Mismo código, mismo resultado (patrón
  "la decisión vive en un solo sitio", docs/04 feature 041).
- **Desglose por impuesto**: agrega `factura_impuestos` de los tickets no anulados de la sesión por
  `(tipo_impuesto, porcentaje)`. `tipo_impuesto` ya distingue IVA/IGIC/IPSI/recargo, así que es
  agnóstico al régimen por construcción (Principio II).
- **Anulados**: tickets de la sesión con `estado = anulada` → lista aparte (número, importe), fuera
  de totales y del efectivo esperado.

## D6. Arqueo ciego (FR-010)

- **Decision**: el endpoint de estado de la caja (`GET /pos/caja`, JSON) **no incluye** el efectivo
  esperado mientras la sesión está abierta. Solo lo devuelve la respuesta de `POST /pos/caja/cerrar`.
- **Rationale**: si la cifra viajara al navegador "oculta", cualquiera la vería en las herramientas
  de desarrollo; un arqueo ciego que se puede espiar no es ciego. La vista en vivo muestra "efectivo
  vendido" (ventas por método), que no es lo mismo que lo que debe haber en el cajón (le faltan
  fondo y movimientos).

## D7. Denominaciones y conteo

- **Decision**: catálogo fijo en `App\Support\DenominacionesEuro` (15 valores, en céntimos:
  50000…1). El conteo viaja como `{ "5000": 3, "2000": 7, … }` (céntimos → cantidad) o como
  `total` directo; el servidor recalcula el total desde el mapa (Principio III) y lo guarda en
  `conteo_cierre` (JSON) + `efectivo_contado`. Mismo formato para `conteo_apertura`.
- **Rationale**: claves en céntimos evitan coma flotante y claves con punto decimal en JSON.

## D8. Umbral de observación obligatoria (FR-012)

- **Decision**: clave `pos.caja_umbral_descuadre` (decimal, default `5.00`) en `ConfigPos`, editable
  en Configuración → POS (`_tab_pos.blade.php`), siguiendo los 3 pasos de docs/03 "Cómo agregar un
  nuevo elemento de configuración". Validación en servidor: si `|descuadre| > umbral` y no hay
  observación → 422 con `codigo: "observacion_requerida"` y **sin** cerrar. Como el arqueo es ciego,
  el usuario recién conoce el descuadre en ese 422; el JS revela el resultado provisional y pide la
  observación en el mismo paso. El umbral **no** depende del módulo de hostelería (a diferencia de
  las otras claves `pos.*`): la caja aplica a todo POS.

## D9. PDF del informe

- **Decision**: dompdf (ya en uso), dos plantillas: `caja/informe-80mm.blade.php` (rollo, mismo
  ancho 226.77 pt y técnica de alto variable que `facturas/ticket-80mm`) y
  `caja/informe-a4.blade.php`. Ruta `GET /pos/caja/sesiones/{sesion}/informe?formato=ticket|a4`,
  resolución manual del modelo bajo `TenantScope` (memoria `project_tenant_route_binding`). Vista
  previa siempre en modal con `<iframe>` (docs/04 "Ver un documento: SIEMPRE en modal").
- **Solo sesiones cerradas** tienen informe Z en PDF. La sesión abierta se consulta en pantalla.

## D10. Datos personales / retención (Principio II, RGPD)

- **Decision**: las tablas nuevas solo guardan **referencias** a `users` (quién abrió, cerró,
  registró un movimiento) y textos de motivo/observación escritos por el negocio. No hay IP, user
  agent ni datos de clientes. Son registros de **control contable interno**, con la misma vocación
  de conservación que los tickets a los que acompañan (obligación de conservar la documentación
  contable, art. 30 Código de Comercio: 6 años). No se añade purga: purgar un cierre dejaría tickets
  huérfanos de su arqueo. Se documenta en docs/03 como justificación explícita.

## D11. Dirección estética (skills de diseño)

Cargada `frontend-design` en el plan. En `/speckit-implement` se cargan además `ui-ux-pro-max` y
`emil-design-eng` antes de escribir las vistas (CLAUDE.md).

- **Tema**: un cajón de dinero al final del día. La pantalla tiene **un solo trabajo** por estado:
  *abrir* (caja cerrada), *seguir el turno* (abierta), *contar* (cierre) y *saber si cuadra*
  (resultado).
- **Paleta**: hereda la del TPV para que caja y venta se lean como un solo producto:
  `--pos-money #16a34a` / `#22c55e` (dinero, venta), primario del tenant (acciones), y suma
  - `--caja-tinta #1F2430` (texto de cifras grandes),
  - `--caja-papel #FBFAF7` (el informe Z como papel térmico, apenas cálido, solo en la vista previa
    del rollo),
  - `--caja-falta #DC2626` y `--caja-sobra #D97706` (descuadre; siempre con texto, nunca solo color).
- **Tipografía**: la del template para la interfaz, con cifras en `tabular-nums` y peso 800 como ya
  hace el TPV (`.pos-cobro-cab .val`). El informe en pantalla y en PDF usa monoespaciada
  (`ui-monospace` en pantalla, DejaVu Sans Mono en dompdf): es la voz del ticket térmico. Sin
  fuentes externas nuevas (peso de assets, docs/04 "Peso y cacheo").
- **Elemento firma — la bandeja del cajón**: el conteo por denominaciones se dibuja como la bandeja
  real de un cajón: **billetes** como fichas rectangulares apaisadas con el tinte de su color real
  (5 € gris, 10 € rojo, 20 € azul, 50 € naranja, 100 € verde, 200 € amarillo, 500 € violeta) y
  **monedas** como fichas circulares con su metal (cobre para 1-2-5 cént., oro nórdico para
  10-20-50 cént., bimetálicas para 1 € y 2 €). Tocar una ficha la selecciona y el teclado propio
  teclea su cantidad; cada ficha muestra cantidad y subtotal. Es reconocible al instante para
  cualquier persona que haya contado un cajón, y es el riesgo estético justificado: el color de la
  pantalla lo pone el propio dinero, no una decoración.
- **Momento orquestado — el revelado**: tras confirmar el conteo ciego, el resultado aparece en una
  sola secuencia corta (esperado → contado → diferencia, escalonado 60 ms, `cubic-bezier(.23,1,.32,1)`,
  sin `scale(0)`), y el veredicto ("Cuadra" / "Sobran 3,20 €" / "Faltan 2,00 €") se asienta el
  último. Es la única animación con intención de la pantalla; todo lo demás es inmediato.
  `prefers-reduced-motion` → solo fundido.
- **Informe Z como papel**: la vista de resultado muestra el informe como una tira de papel térmico
  (borde inferior dentado con `mask`, monoespaciada) junto a las acciones "Imprimir 80 mm" / "A4",
  para que lo que se ve sea lo que sale por la impresora (mismo principio que "la página ES el
  documento", docs/04 facturas).

### Ajustes tras skills (T001: `emil-design-eng` + `ui-ux-pro-max`)

- **Frecuencia decide la animación**: las fichas de la bandeja y las teclas se pulsan decenas de
  veces por cierre → **sin animación de entrada ni de selección**, solo `:active { scale(.97) }`
  (≤ 160 ms, `ease-out`) y cambio de borde instantáneo. El revelado del resultado ocurre una vez al
  día → es el único sitio con secuencia (≤ 260 ms por pieza, 60 ms de escalonado).
- **Curvas**: `--caja-ease: cubic-bezier(.23, 1, .32, 1)`. Nunca `ease-in`, nunca `transition: all`,
  solo `transform`/`opacity` en lo animado.
- **Entradas**: desde `translateY(8px)` + `opacity: 0` (nunca `scale(0)`); con
  `prefers-reduced-motion` solo opacidad.
- **Táctil**: objetivos ≥ 56 px (por encima del mínimo de 44 px; es un POS de uso con prisa), fichas
  de billete ≥ 76 px de alto, teclas ≥ 64 px. `cursor: pointer` y `:focus-visible` con anillo del
  primario en todo lo pulsable (navegación con teclado físico en caja).
- **Accesibilidad**: cada ficha es un `<button>` con `aria-label` "50 euros, 3 unidades"; el
  veredicto nunca solo por color (icono Font Awesome + texto); el total contado es `aria-live="polite"`.
  Iconos Font Awesome (ya cargado), sin emojis.
