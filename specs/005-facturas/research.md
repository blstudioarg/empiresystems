# Phase 0 — Research: Facturas (núcleo mínimo)

Decisiones técnicas que resuelven los puntos abiertos del plan. Sin `NEEDS CLARIFICATION`
pendientes (las 4 ambigüedades de negocio se cerraron en `spec.md` › Clarifications).

## D1 — Librería de generación de PDF

- **Decisión**: `barryvdh/laravel-dompdf` (wrapper de dompdf).
- **Rationale**: dompdf es **pure-PHP**, no requiere binarios externos (wkhtmltopdf) ni
  headless Chrome, por lo que corre en hosting compartido tipo cPanel/Hostinger (Principio V).
  Renderiza HTML/CSS desde una vista Blade, lo que encaja con el stack y permite reutilizar el
  logo del tenant y los datos ya persistidos. Es la opción estándar en Laravel.
- **Alternativas descartadas**:
  - `spatie/laravel-pdf` (Browsershot): requiere Node + Puppeteer/Chromium → incompatible con
    hosting compartido.
  - `wkhtmltopdf`/snappy: binario externo, mismo problema.
  - TCPDF a mano: más control tipográfico pero mucho más verboso; innecesario para el MVP.

## D2 — Numeración diferida a la emisión (borrador sin número)

- **Decisión**: el borrador **no** consume número. `facturas.numero` y `numero_completo` son
  `nullable`. La lógica de asignación vive en `NumeradorFacturas` y se **diseña e implementa como
  servicio testeado**, pero solo se invoca en la transición a "emitida" (fuera de alcance de esta
  feature). En esta feature el servicio existe y está cubierto por tests unitarios, sin ruta que
  lo dispare todavía.
- **Rationale**: numeración correlativa **sin huecos** (Principio II) es incompatible con asignar
  número a borradores que pueden borrarse. Diferir a la emisión es lo que exige Verifactu y evita
  huecos por definición (decisión Q1 de Clarifications).
- **Mecanismo**: dentro de una transacción, `Serie::where(...)->lockForUpdate()` lee y
  incrementa `proximo_numero` de forma atómica → evita duplicados/huecos ante concurrencia.
- **Alternativas descartadas**: numerar en borrador y aceptar huecos (incumple norma);
  contador aparte "BORR-x" (complejidad innecesaria para el MVP).

## D3 — Congelado del régimen impositivo en la factura

- **Decisión**: al crear la factura se copia `tenant.regimen_impositivo` a
  `facturas.regimen_impositivo`. Todo el cálculo de esa factura usa el valor **de la factura**, no
  el del tenant en tiempo de lectura.
- **Rationale**: Principio II exige que el régimen se congele igual que el resto de campos
  inmutables; si el tenant cambia de régimen en el futuro, las facturas antiguas deben conservar
  su tratamiento fiscal original.
- **Alternativas descartadas**: leer el régimen del tenant en cada cálculo (rompe la
  reproducibilidad histórica).

## D4 — Servicio único de cálculo (`CalculadoraFactura`)

- **Decisión**: un servicio recibe las líneas (cantidad, precio, descuento, tipo impositivo), el
  régimen congelado, el flag de recargo y el % de IRPF, y devuelve: base por línea, cuota de
  impuesto y de recargo por línea, desglose agrupado por (tipo_impuesto, porcentaje), y totales de
  cabecera (base_total, cuota_impuesto_total, cuota_recargo_total, irpf_cuota, total).
- **Rationale**: Principio III (fuente única server-side) + Principio IV (test-first). Centralizar
  evita divergencia entre el guardado y el PDF.
- **Reglas de cálculo** (decisiones Clarifications):
  - Precios **sin impuesto**: `base_linea = round(cantidad * precio, 2) − descuento`.
  - Cuota impuesto = `round(base_linea * tipo_impositivo / 100, 2)`.
  - **Recargo de equivalencia**: solo si `regimen == iva` y la factura aplica recargo; el % de
    recargo se deriva del tipo de IVA de la línea (21→5.2, 10→1.4, 4→0.5, 0→0).
  - **IRPF**: manual a nivel de factura; `irpf_cuota = round(base_total * irpf% / 100, 2)`, se
    **resta** del total. Sin default de cliente/tenant.
  - **Redondeo**: 2 decimales por línea y por cuota; el total de cabecera es la suma de las cuotas
    ya redondeadas, garantizando que el total cuadra con el desglose.
- **Alternativas descartadas**: cálculo repartido entre controller/modelo/JS (riesgo de
  inconsistencia y de que el cliente sea fuente de verdad).

## D5 — Recargo de equivalencia: origen del flag por factura

- **Decisión**: la factura aplica recargo si el **cliente** seleccionado tiene
  `aplica_recargo_equivalencia = true` (dato ya existente en `clientes`), y el régimen es IVA. Se
  congela en la factura (`aplica_recargo`). El usuario no lo edita manualmente en esta feature.
- **Rationale**: el recargo depende de que el receptor sea minorista en ese régimen; el dato ya
  vive en el cliente. Mantiene la UI simple.
- **Alternativas descartadas**: toggle manual por factura (se puede añadir después si hay demanda).

## D6 — Vista full-page con preview en vivo (arquitectura front)

- **Decisión**: página Blade dedicada (`facturas/create.blade.php`) servida por
  `FacturaController@create`/`@edit`, con JS propio (`public/js/facturas-form.js`) que gestiona el
  alta/baja de líneas y recalcula una **preview** client-side para feedback inmediato. Al guardar,
  el backend **recalcula** con `CalculadoraFactura` e ignora los importes del cliente (el JS es
  solo UX). El autocompletado de líneas desde catálogo usa un endpoint JSON de artículos ya
  existente (`articulos.index` con `wantsJson`) y de clientes (`clientes.index`).
- **Rationale**: la spec marca el front como prioritario y pide una vista cargada de secciones con
  preview; un formulario full-page da el espacio necesario. Reutiliza los endpoints JSON ya
  construidos. Coherente con `docs/04-front-guidelines.md` y NexaDash.
- **Alternativas descartadas**: modal (descartado explícitamente por el usuario); SPA/framework JS
  (fuera del stack, Principio V).

## D7 — UI/UX de la vista de creación (skills de diseño)

- **Decisión**: aplicar las skills de diseño del proyecto (`frontend-design` / `emil-design-eng`)
  para el layout de la vista de creación y la preview, dentro del sistema visual NexaDash ya
  montado (no introducir un tema nuevo). Documentar en `docs/04-front-guidelines.md` cualquier
  patrón reutilizable que surja (layout de líneas editables, preview de documento, sticky totals).
- **Rationale**: CLAUDE.md obliga a leer/actualizar `04-front-guidelines.md` y a mantener la
  coherencia con el template; el front es un objetivo explícito de esta feature.

## D8 — Tablas creadas ahora vs. diferidas

- **Decisión**: se crean **solo** `series`, `facturas`, `factura_lineas`, `factura_impuestos`. Las
  columnas Verifactu y de ciclo B2B se incluyen en `facturas` (nullable, sin usar) para no tener
  que migrar la tabla después. `pagos`, `factura_eventos`, `movimientos_stock` **no** se crean
  (YAGNI, Principio V) hasta la feature que los use.
- **Rationale**: incluir las columnas Verifactu desde el día uno lo exige la constitución; crear
  tablas que aún no se usan, no.
