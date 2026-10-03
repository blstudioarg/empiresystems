# Contrato de UI — Caja del POS

Tablet de 10" en horizontal (1280×800 lógico) como objetivo principal; debe seguir siendo usable
apilado por debajo de 1200 px (grid de Bootstrap, `col-xl-*`, igual que `pos/create.blade.php`).
Dirección estética: research D11.

## Convenciones de docs/04-front-guidelines.md que esta UI cumple (trazabilidad)

| Sección de la guía | Qué exige | Dónde aplica aquí |
|---|---|---|
| Entrada numérica en pantallas táctiles | `readonly` + `inputmode="none"`, teclado propio `.pos-key`/`.pos-keypad-grid`, superpuesto dentro del contenedor (no modal anidado), Cancelar restaura, regla de tecleo compartida | Fondo inicial, cantidades por denominación, total contado, importe de movimiento. **Se extrae** `aplicarTecla()` de `pos-cobro.js` a `public/js/pos-teclado.js` (compartido) en vez de duplicarlo. |
| Corolario `.btn` de color propio | variables `--bs-btn-*` con doble clase | Botones de acción grandes (Abrir caja, Cerrar caja, Confirmar conteo). |
| Listados: SIEMPRE DataTable | `<table class="display responsive nowrap w-100">` + JSON + dropdown "Acciones" + override paginación | Histórico de cierres (`caja/cierres.blade.php`). |
| DataTable "Anterior/Siguiente" | override CSS por id de tabla | `#cierres-table_wrapper`. |
| Columna "Acciones" | un dropdown, ítems según `*_url` del backend | "Ver informe (80 mm)", "Ver informe (A4)". |
| "Ver" un documento: SIEMPRE en modal | `modal-xl`, `iframe`, `80vh`, `p-0`, reset de `src` en `hidden.bs.modal` | Informe Z desde el resultado del cierre y desde el histórico. |
| Modales: siempre centrados | `modal-dialog-centered` | Todos (movimiento, apertura inline en TPV, PDF). |
| Estado de carga en botones | `withButtonLoading` | Abrir, registrar movimiento, confirmar conteo, cerrar. |
| Notificaciones | `window.showToast` / flash | Resultados y errores (el texto lo redacta el servidor). |
| CSRF sin formulario | header `X-CSRF-TOKEN` | Abrir / cerrar / movimiento. |
| Badges de estado `.badge.light.badge-*` | variantes existentes, contraste ≥ 4.5:1 | Estado del cierre en el histórico: success "Cuadra", warning "Sobrante", danger "Faltante". |
| Cards de métricas `[data-metric]` | markup estándar, rail de acento automático | Fila en vivo del turno (vendido, tickets, ticket medio). |
| Filtro de un solo valor: `.filtro-segmentado` | control segmentado | Conmutador "Por billetes y monedas / Importe total" del conteo. |
| Assets propios con `@assetv` | `?v=mtime` | Todos los JS/CSS nuevos. |
| `@stack('styles')` antes de `style.css` | especificidad de reglas propias | CSS de la vista con doble clase donde compita con `.card`/`.btn`. |
| Ayuda contextual | `@section('ayuda-titulo')` + `@section('ayuda')` → `ayuda/<slug>` | `ayuda/pos-caja.blade.php` (pantalla de caja) y `ayuda/pos-caja-cierres.blade.php` (histórico). |
| Nunca imprimir un `decimal:N` en Blade | `Formato::moneda()` | Plantillas PDF y Blade. |
| Nueva entrada de menú ⇒ nuevo permiso | catálogo de permisos + menú + rutas `can:` | `ver-pos-caja`, entrada "Caja" en POS. |
| Hover solo con puntero fino | `@media (hover: hover) and (pointer: fine)`; `:active { scale(.97) }` | Todas las fichas y botones táctiles. |

## Pantalla `pos.caja` — tres estados en una sola vista

El estado lo decide el servidor (render inicial) y el JS solo alterna contenedores con `d-none`
tras cada acción (sin recargar la página).

### Estado A — Caja cerrada

```
┌───────────────────────────────────────────────────────────────────────┐
│                                                                       │
│              [icono cajón cerrado]                                    │
│              La caja está cerrada                                     │
│              Ábrela para empezar a cobrar.                            │
│                                                                       │
│              ┌─────────────────────────────┐                          │
│              │        Abrir caja           │  ← CTA grande (72px)     │
│              └─────────────────────────────┘                          │
│                                                                       │
│   Último cierre · ayer 22:14 · Ana López                              │
│   540,00 € facturado · Cuadra            [Ver informe]   [Historial]  │
└───────────────────────────────────────────────────────────────────────┘
```

"Abrir caja" despliega **en la misma card** el panel de fondo inicial (no navega):

```
┌────────────────────────────── Abrir caja ─────────────────────────────┐
│  Fondo de cambio              [Importe total | Billetes y monedas]    │
│  ┌───────────────────────┐    ┌───┬───┬───┐                           │
│  │        150,00 €       │    │ 7 │ 8 │ 9 │                           │
│  └───────────────────────┘    │ 4 │ 5 │ 6 │                           │
│  Con cuánto efectivo          │ 1 │ 2 │ 3 │                           │
│  empieza el cajón.            │ , │ 0 │ ⌫ │                           │
│                               └───┴───┴───┘                           │
│            [Cancelar]                    [Abrir caja con 150,00 €]     │
└───────────────────────────────────────────────────────────────────────┘
```

El botón de confirmar **repite el importe** ("Abrir caja con 150,00 €"): la acción dice
exactamente lo que va a pasar.

### Estado B — Caja abierta (turno en curso)

```
┌ Caja abierta ● · desde 08:02 (6 h 40 min) · Ana López · Fondo 150,00 € ────────────┐
├──────────────────────────────────────────────────────┬─────────────────────────────┤
│  [Vendido 612,40 €] [Tickets 34] [Ticket medio 18,01]│  ┌──────────┐ ┌──────────┐  │
│                                                      │  │ + Entrada│ │ − Salida │  │
│  Cómo te pagaron                                     │  └──────────┘ └──────────┘  │
│  ███████████████▓▓▓▓▓▓▓▓▓░░░                         │                             │
│  ● Efectivo        15 tickets        240,10 €        │  Movimientos del turno      │
│  ● Tarjeta         18 tickets        352,30 €        │  10:12 − 30,00 € Pago pan   │
│  ● Transferencia    1 ticket          20,00 €        │  12:40 + 50,00 € Cambio     │
│  ● Domiciliación    —                  0,00 €        │                             │
│                                                      │  ┌─────────────────────────┐│
│                                                      │  │      Cerrar caja        ││
│                                                      │  └─────────────────────────┘│
└──────────────────────────────────────────────────────┴─────────────────────────────┘
```

- Barra de estado con punto verde pulsante suave (opacidad, 2 s, desactivado con reduced-motion).
  Si `abierta_dia_anterior` → la barra pasa a ámbar con el texto "Abierta desde ayer: ciérrala
  antes de empezar el día" (FR-025).
- Barra apilada de métodos: un segmento por método con su color fijo (efectivo = `--pos-money`,
  tarjeta = primario, transferencia = slate, domiciliación = violeta suave), siempre acompañada de
  la lista con texto (nunca solo color).
- Entrada/Salida abren un **modal** pequeño (`modal-dialog-centered`) con tipo preseleccionado,
  teclado propio superpuesto para el importe y un campo de motivo con 4 chips de motivo frecuente
  ("Pago a proveedor", "Retirada a caja fuerte", "Cambio", "Otro") que rellenan el texto. Motivo
  libre editable; el chip solo acelera.
- Corregir un movimiento equivocado = registrar uno del tipo contrario con motivo "Corrección: …"
  (FR-008). No hay botón de borrar; la guía in-app lo explica.
- La columna derecha es `position: sticky` como `.pos-cobro` del TPV, con "Cerrar caja" anclado
  abajo.
- Botón "Actualizar" discreto en la barra + recarga automática del resumen cada 60 s mientras la
  pestaña es visible (`visibilitychange`), para el uso "¿cómo vamos?" sin tocar nada.

### Estado C — Cierre (dos pasos reales en secuencia: Contar → Resultado)

Ocupa la pantalla de caja entera (sustituye al estado B, con "Volver" mientras no se confirme).
Indicador de pasos "1 Contar · 2 Resultado": aquí la numeración **sí** codifica una secuencia real.

**Paso 1 — Contar (arqueo ciego). Elemento firma: la bandeja del cajón.**

```
┌ 1 Contar · 2 Resultado ─────────────── [Billetes y monedas | Importe total] ────────┐
│ BILLETES                                                    │  50 €               │
│ ┌──────┐┌──────┐┌──────┐┌──────┐┌──────┐┌──────┐┌──────┐   │  ┌───────────────┐  │
│ │ 500  ││ 200  ││ 100  ││  50 ◉││  20  ││  10  ││   5  │   │  │      3        │  │
│ │  ·   ││  ·   ││  ·   ││ ×3   ││ ×7   ││ ×4   ││ ×2   │   │  └───────────────┘  │
│ │      ││      ││      ││150,00││140,00││ 40,00││ 10,00│   │  ┌───┬───┬───┐      │
│ └──────┘└──────┘└──────┘└──────┘└──────┘└──────┘└──────┘   │  │ 7 │ 8 │ 9 │      │
│ MONEDAS                                                     │  │ 4 │ 5 │ 6 │      │
│  (2€) (1€) (50c) (20c) (10c) (5c) (2c) (1c)                 │  │ 1 │ 2 │ 3 │      │
│  ×5   ×8   ×6    ×10   ×3   …                               │  │ C │ 0 │ ⌫ │      │
│                                                             │  └───┴───┴───┘      │
│                                                             │  Total contado      │
│                                                             │  ┌───────────────┐  │
│                                                             │  │   368,40 €    │  │
│ [Volver]                                                    │  └───────────────┘  │
│                                                             │ [Confirmar conteo]  │
└─────────────────────────────────────────────────────────────┴─────────────────────┘
```

- Fichas de **billete**: rectángulo apaisado (proporción ~1.9:1), fondo con el tinte claro del color
  real del billete y una franja del tono saturado a la izquierda; valor grande arriba, `×cantidad`
  y subtotal abajo. Fichas de **moneda**: círculo con gradiente radial del metal (cobre / oro
  nórdico / bimetálica con anillo). La ficha seleccionada se marca con anillo del primario + leve
  elevación; las fichas con cantidad > 0 muestran la cantidad en una píldora.
- Tocar una ficha la selecciona y el teclado teclea **su cantidad** (enteros, sin coma: la tecla
  "," se sustituye por "C" = poner a cero). Tocar otra ficha confirma la anterior. Atajo: tocar dos
  veces seguidas una ficha suma 1 (contar billete a billete).
- Total contado en vivo con `tabular-nums`. **Nunca** se muestra el esperado ni la diferencia en
  este paso (FR-010).
- Modo "Importe total": la bandeja se sustituye por un único display + teclado decimal.
- El conteo en curso se conserva en `sessionStorage` por sesión de caja (clave
  `caja-conteo:<sesion_id>`) para que una recarga accidental de la tablet no obligue a recontar
  (preferencia de interfaz, no viaja al servidor hasta confirmar).

**Paso 2 — Resultado (el revelado).**

```
┌ 1 Contar · 2 Resultado ─────────────────────────────────────────────────────────────┐
│                                         │  ┌──────────────────────────┐             │
│   Esperado          150,00 €            │  │ EMPIRE DEMO SL           │  ← papel   │
│   Contado           148,00 €            │  │ CIERRE DE CAJA  Nº 12    │    térmico │
│   ───────────────────────────           │  │ 03/10/2026 08:02 → 22:14 │            │
│   Faltan            2,00 €   ▼          │  │ ...                      │            │
│   (veredicto, rojo, con icono y texto)  │  │ TOTAL       95,00 €      │            │
│                                         │  └/\/\/\/\/\/\/\/\/\/\/\/\/\┘            │
│   [Imprimir 80 mm]  [Ver en A4]         │                                           │
│   [Volver a la caja]                    │                                           │
└─────────────────────────────────────────┴───────────────────────────────────────────┘
```

- Secuencia del revelado: research D11 (escalonado 60 ms, veredicto último, solo fundido con
  reduced-motion).
- Veredicto: "Cuadra" (verde, check), "Sobran X €" (ámbar), "Faltan X €" (rojo). Texto + icono +
  color, nunca solo color.
- Si el servidor responde `observacion_requerida`, el paso 2 se muestra **provisional**: mismas
  cifras, veredicto, y un campo obligatorio "¿Qué pasó?" + botón "Cerrar caja con esta diferencia".
  "Volver a contar" regresa al paso 1 conservando el conteo.
- La tira de papel es el informe Z renderizado en HTML (misma información que el PDF de 80 mm),
  monoespaciado, fondo `--caja-papel`, borde inferior dentado. Imprimir abre el PDF en el modal
  estándar de vista previa.

## Integración en `pos.create` (TPV)

- En la cabecera del ticket, un **chip de estado de caja**: "Caja abierta" (verde, discreto) o
  "Caja cerrada" (ámbar, con botón "Abrir").
- Con la caja cerrada, el botón **Cobrar** sigue visible pero al tocarlo abre el modal "Abrir caja"
  (mismo panel de fondo inicial, en modal centrado) en vez del modal de cobro; tras abrir, continúa
  directamente al cobro. Sin permiso para abrir: el modal explica "La caja está cerrada. Pide a un
  responsable que la abra." sin botón de abrir.
- Si el servidor responde 409 `caja_cerrada` al emitir (otra tablet cerró la caja mientras tanto),
  el ticket armado **no se vacía** y se abre el mismo modal de apertura.
- El mismo comportamiento en el cobro de cuentas de mesa (`pos-cuenta.js` / `pos-cobro.js`).

## Histórico `pos.caja.cierres`

Página estándar de listado: fila de 3 cards `[data-metric]` (cierres del mes, facturado del mes,
descuadre acumulado del mes con rail según signo) + DataTable con columnas: Fecha (cierre),
Abrió, Cerró, Tickets, Facturado, Esperado, Contado, Diferencia (con badge de estado), Acciones.
Enlace desde la pantalla de caja ("Historial") y entrada propia en el submenú POS no hace falta:
el histórico se alcanza desde la caja (una sola entrada de menú "Caja").
