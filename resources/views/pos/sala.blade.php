@extends('layouts.app')

@section('title', 'POS · Sala')

@push('styles')
	<link rel="stylesheet" href="{{ asset('vendor/jqueryui/css/jquery-ui.min.css') }}">
	<style>
		/* ── Sala: pensada tablet-first, misma familia visual que el catálogo del POS.
		   Las pestañas de zona REUTILIZAN `.pos-filtro` del TPV (52px de alto, badge de conteo,
		   activo con el primario del tenant): no se diseña un selector nuevo para lo mismo. */
		.pos-sala {
			--pos-primary: var(--primary, #1d69d6);
			--pos-money: #16a34a;
			--pos-warn: #d97706;
		}

		.pos-filtros-wrap { position: relative; margin: 0 0 1rem; }
		.pos-filtros {
			display: flex; flex-wrap: nowrap; gap: .6rem; padding: .55rem .1rem .7rem;
			overflow-x: auto; scroll-behavior: smooth; -webkit-overflow-scrolling: touch;
			scrollbar-width: none;
		}
		.pos-filtros::-webkit-scrollbar { display: none; }
		.pos-filtro {
			flex: 0 0 auto; display: inline-flex; align-items: center; gap: .5rem;
			border: 1.5px solid var(--bs-border-color, #e6e6e6); background: #fff; border-radius: 1rem;
			padding: .7rem 1.15rem; min-height: 52px; font-weight: 650; font-size: 1rem; color: #444;
			cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent;
			transition: background .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .08s ease;
		}
		.pos-filtro .badge-count { background: rgba(0,0,0,.06); border-radius: 1rem; padding: .1rem .6rem; font-size: .82rem; font-weight: 700; }
		.pos-filtro:hover { border-color: var(--pos-primary); background: #f5f8ff; }
		.pos-filtro:active { transform: scale(.96); }
		.pos-filtro.active { background: var(--pos-primary); border-color: var(--pos-primary); color: #fff; box-shadow: 0 3px 9px rgba(29,105,214,.22); }
		.pos-filtro.active .badge-count { background: rgba(255,255,255,.25); color: #fff; }

		/* Tarjetas de mesa: el borde comunica el estado de un vistazo desde la barra, sin leer.
		   Gris = libre, verde = ocupada, ámbar = olvidada. El servidor decide "olvidada": la
		   vista NO hace aritmética de fechas (dependería del reloj de la tablet). */
		.pos-mesas-grid {
			display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .9rem;
		}
		.pos-mesa {
			position: relative; display: flex; flex-direction: column; gap: .3rem; justify-content: center;
			min-height: 122px; padding: 1rem .9rem; border-radius: 1.1rem; background: #fff;
			border: 2px solid var(--bs-border-color, #e2e5ea); text-align: left; cursor: pointer;
			-webkit-tap-highlight-color: transparent;
			transition: transform .08s ease, box-shadow .18s ease, border-color .18s ease;
		}
		.pos-mesa:hover { box-shadow: 0 10px 24px rgba(20,30,60,.12); transform: translateY(-3px); }
		.pos-mesa:active { transform: scale(.97); }
		.pos-mesa .nombre { font-weight: 750; font-size: 1.05rem; color: #2b2f36; }
		.pos-mesa .importe { font-weight: 800; font-size: 1.35rem; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
		.pos-mesa .meta { font-size: .8rem; color: #9aa0a6; font-weight: 600; }
		.pos-mesa.libre .importe { color: #9aa0a6; font-size: 1rem; font-weight: 650; }
		.pos-mesa.ocupada { border-color: var(--pos-money); }
		.pos-mesa.ocupada .importe { color: var(--pos-money); }
		.pos-mesa.olvidada { border-color: var(--pos-warn); background: #fffaf2; }
		.pos-mesa.olvidada .importe { color: var(--pos-warn); }
		.pos-mesa.olvidada .meta { color: var(--pos-warn); }
		.pos-mesa .estado-badge {
			position: absolute; top: .55rem; right: .6rem; font-size: .66rem; font-weight: 800;
			letter-spacing: .04em; text-transform: uppercase; padding: .18rem .5rem; border-radius: 1rem;
		}
		.pos-mesa.ocupada .estado-badge { background: #e9f9ef; color: #14833b; }
		.pos-mesa.olvidada .estado-badge { background: #fff4e5; color: #b26a00; }

		.pos-sala-vacia { text-align: center; padding: 3rem 1rem; color: #9aa0a6; }

		.pos-sala-acciones { display: flex; gap: .6rem; flex-wrap: wrap; }
		.pos-sala-acciones .btn { min-height: 48px; display: inline-flex; align-items: center; gap: .45rem; }

		/* Selector de vista (feature 041). Va en la cabecera y NO reutiliza `.pos-filtro`: ese
		   componente está reservado al filtro por zona y este control no filtra nada, cambia cómo
		   se ve lo mismo. Mínimo 44px de alto, como el resto de controles táctiles de la Sala. */
		.pos-sala-vista { display: inline-flex; gap: .25rem; padding: .2rem; border-radius: .8rem; background: #eef0f4; }
		.pos-sala-vista button {
			min-height: 44px; display: inline-flex; align-items: center; gap: .4rem;
			border: none; background: transparent; border-radius: .65rem; padding: .4rem .9rem;
			font-weight: 650; font-size: .92rem; color: #5b6270; -webkit-tap-highlight-color: transparent;
			transition: background .15s ease, color .15s ease;
		}
		.pos-sala-vista button.active { background: #fff; color: var(--pos-primary, #1d69d6); box-shadow: 0 1px 3px rgba(20,30,60,.14); }

		@media (prefers-reduced-motion: reduce) {
			.pos-mesa { transition: none; }
		}

		/* ── Resumen plegable de metricas ─────────────────────────────────────────────────────
		   Curva de salida fuerte: las built-in de CSS son demasiado flojas y el plegado queda
		   blando. NUNCA `ease-in` aqui — arranca lento justo en el instante que el usuario mira,
		   y hace que el mismo tiempo se PERCIBA mas lento. */
		.pos-sala { --pos-ease-out: cubic-bezier(.23, 1, .32, 1); }

		.pos-sala-resumen-bar { display: flex; justify-content: flex-end; margin-bottom: .6rem; }
		.pos-sala-resumen-toggle {
			display: inline-flex; align-items: center; gap: .5rem;
			min-height: 40px; padding: .4rem .9rem;
			border: 1.5px solid #e6e6e6; border-radius: 999px; background: #fff;
			font-size: .82rem; font-weight: 700; color: #8b93a1;
			-webkit-tap-highlight-color: transparent;
			transition: transform 160ms var(--pos-ease-out), border-color 160ms ease, color 160ms ease;
		}
		/* El hover se restringe a puntero fino: en tablet el toque dispara `:hover` y deja el
		   boton "encendido" despues de soltarlo, como si estuviera activo. */
		@media (hover: hover) and (pointer: fine) {
			.pos-sala-resumen-toggle:hover { border-color: var(--pos-primary, #1d69d6); color: var(--pos-primary, #1d69d6); }
		}
		/* Respuesta inmediata al pulsar: sin esto el boton no parece estar escuchando. */
		.pos-sala-resumen-toggle:active { transform: scale(.97); }
		.pos-sala-resumen-toggle[aria-expanded="true"] {
			border-color: var(--pos-primary, #1d69d6); color: var(--pos-primary, #1d69d6);
		}
		.pos-sala-resumen-chevron {
			font-size: .68rem;
			transition: transform 260ms var(--pos-ease-out);
		}
		.pos-sala-resumen-toggle[aria-expanded="true"] .pos-sala-resumen-chevron { transform: rotate(180deg); }

		/* El plegado va con `grid-template-rows: 0fr -> 1fr` y no con `max-height`: anima hasta la
		   altura REAL del contenido, sin numero magico que se quede corto cuando las tarjetas
		   pasan a dos filas en tablet. `visibility` sale del arbol de accesibilidad al terminar de
		   cerrarse (transicion de 0s retrasada), para que un lector de pantalla no lea cuatro
		   metricas invisibles. */
		.pos-sala-cards-collapse {
			display: grid; grid-template-rows: 0fr; visibility: hidden;
			transition: grid-template-rows 200ms var(--pos-ease-out), visibility 0s linear 200ms;
		}
		.pos-sala-cards-collapse-inner { overflow: hidden; min-height: 0; }
		/* Abrir es un poco mas lento que cerrar (260 vs 200): al abrir hay algo que mirar, al
		   cerrar el usuario ya decidio y solo quiere que se quite de en medio. */
		.pos-sala-cards-collapse.abierto {
			grid-template-rows: 1fr; visibility: visible;
			transition: grid-template-rows 260ms var(--pos-ease-out), visibility 0s;
		}

		/* Entrada escalonada de las tarjetas. Nunca desde `scale(0)`: nada en el mundo real
		   aparece de la nada, asi que arrancan casi a tamano completo y solo bajan un pelo. */
		.pos-sala-cards-collapse .row > [class*="col-"] {
			opacity: 0; transform: translateY(-10px) scale(.985);
			transition: opacity 200ms var(--pos-ease-out), transform 200ms var(--pos-ease-out);
		}
		.pos-sala-cards-collapse.abierto .row > [class*="col-"] { opacity: 1; transform: none; }
		/* El escalonado solo existe al ABRIR: al cerrar salen las cuatro a la vez, porque un
		   cierre escalonado se siente como que la interfaz tarda en obedecer. */
		.pos-sala-cards-collapse.abierto .row > [class*="col-"]:nth-child(1) { transition-delay: 40ms; }
		.pos-sala-cards-collapse.abierto .row > [class*="col-"]:nth-child(2) { transition-delay: 85ms; }
		.pos-sala-cards-collapse.abierto .row > [class*="col-"]:nth-child(3) { transition-delay: 130ms; }
		.pos-sala-cards-collapse.abierto .row > [class*="col-"]:nth-child(4) { transition-delay: 175ms; }

		/* Movimiento reducido: se conserva el fundido (ayuda a entender que algo cambio) y se
		   quitan desplazamiento y escalonado, que es lo que marea. */
		@media (prefers-reduced-motion: reduce) {
			.pos-sala-cards-collapse { transition: visibility 0s linear 150ms; }
			.pos-sala-cards-collapse.abierto { transition: visibility 0s; }
			.pos-sala-cards-collapse .row > [class*="col-"] {
				transform: none; transition: opacity 150ms ease; transition-delay: 0ms !important;
			}
			.pos-sala-resumen-chevron { transition: none; }
		}

		/* ── Plano arrastrable (feature 039): rejilla de celdas por zona, con las medidas que la
		   propia zona declara desde la feature 042 (8×6 por defecto). Se posiciona
		   con `position: absolute` (no CSS grid) porque jQuery UI `draggable` con `grid: [w,h]`
		   calcula en píxeles: mover a CSS grid perdería el snap directo a celda. */
		.pos-plano-wrap { display: none; }
		.pos-plano-wrap.activo { display: block; }

		.pos-plano-toolbar { display: flex; justify-content: space-between; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: .8rem; }
		.pos-plano-hint { font-size: .82rem; color: #8b93a1; }

		.pos-plano-canvas-scroll { position: relative; overflow-x: auto; padding-bottom: .5rem; }
		.pos-plano-canvas {
			position: relative;
			/* Medidas por defecto. Las reales las escribe `PosPlanoDibujo.aplicarGeometria()` en
			   ESTE elemento al dibujar cada zona (feature 042, D4), nunca en `documentElement`:
			   el lienzo del editor y el de servicio pueden estar mostrando zonas distintas. */
			--plano-cols: 8;
			--plano-rows: 6;
			width: calc(var(--plano-cols) * (var(--plano-cell) + var(--plano-gap)) - var(--plano-gap));
			height: calc(var(--plano-rows) * (var(--plano-cell) + var(--plano-gap)) - var(--plano-gap));
			background-color: #f7f8fb;
			background-image: radial-gradient(circle, #cfd5e0 1.5px, transparent 1.5px);
			background-size: calc(var(--plano-cell) + var(--plano-gap)) calc(var(--plano-cell) + var(--plano-gap));
			background-position: calc(var(--plano-cell) / 2) calc(var(--plano-cell) / 2);
			border-radius: .9rem;
			border: 1.5px dashed #d7dbe3;
		}

		.plano-mesa {
			position: absolute;
			display: flex; flex-direction: column; align-items: center; justify-content: center;
			background: #fff; border: 2px solid var(--bs-border-color, #e2e5ea);
			box-shadow: 0 3px 8px rgba(20,30,60,.08);
			cursor: default; user-select: none; -webkit-tap-highlight-color: transparent;
			transition: box-shadow .15s ease, border-color .15s ease;
			font-size: .78rem; font-weight: 700; color: #2b2f36; text-align: center; padding: .2rem;
		}
		.plano-mesa.libre { border-color: #d7dbe3; }
		.plano-mesa.ocupada { border-color: var(--pos-money, #16a34a); }
		.plano-mesa.olvidada { border-color: var(--pos-warn, #d97706); background: #fffaf2; }
		.plano-mesa .plano-mesa-nombre { pointer-events: none; }
		.plano-mesa .plano-mesa-handle {
			position: absolute; top: -.5rem; right: -.5rem; width: 1.6rem; height: 1.6rem;
			border-radius: 50%; background: var(--pos-primary, #1d69d6); color: #fff;
			display: flex; align-items: center; justify-content: center; font-size: .7rem;
			cursor: grab; box-shadow: 0 2px 6px rgba(0,0,0,.2);
		}
		.plano-mesa .plano-mesa-handle:active { cursor: grabbing; }
		.plano-mesa.ui-draggable-dragging { box-shadow: 0 10px 26px rgba(20,30,60,.22); z-index: 20; }

		/* Asas de redimensionado (feature 040, D6): 16 px de agarre para acertar el borde con el
		   dedo al primer intento en tablet, sin invadir el interior de una celda de 96 px. Solo
		   existen en modo edición porque el widget solo se activa ahí. `touch-action: none` es
		   imprescindible: sin él el navegador se queda el gesto como scroll de la página. */
		.plano-mesa .ui-resizable-handle { touch-action: none; background: none; }
		.plano-mesa .ui-resizable-n,
		.plano-mesa .ui-resizable-s { left: 0; right: 0; width: auto; height: 16px; cursor: ns-resize; }
		.plano-mesa .ui-resizable-s { bottom: -8px; }
		.plano-mesa .ui-resizable-e,
		.plano-mesa .ui-resizable-w { top: 0; bottom: 0; height: auto; width: 16px; cursor: ew-resize; }
		.plano-mesa .ui-resizable-w { left: -8px; }
		/* Las asas `n` y `e` se APARTAN de la esquina superior derecha, donde vive el asa circular
		   de arrastre (25.6px que sobresalen 8px arriba y a la derecha). No basta con darle a esa
		   un z-index mayor: `@stack('styles')` se carga antes de `css/style.css`, así que un
		   z-index de ahí gana por cascada y el asa de resize se queda el gesto — la mesa se
		   agranda en vez de moverse. Separarlas físicamente no depende de la cascada. */
		.plano-mesa .ui-resizable-n { top: -8px; right: 22px; }
		.plano-mesa .ui-resizable-e { right: -8px; top: 22px; }
		/* Las esquinas van por encima de los lados (redimensionan los dos ejes a la vez), así que
		   cuanto más grandes, más lado se comen. En una mesa de 1×1 (96px) unas esquinas de 18px
		   se llevaban 36px de los 96 del borde —más de un tercio— y era facilísimo agarrar la
		   esquina creyendo agarrar el lado, con lo que la mesa crecía en ambos ejes. A 12px el
		   lado queda despejado y siguen siendo agarrables. */
		.plano-mesa .ui-resizable-se,
		.plano-mesa .ui-resizable-sw,
		.plano-mesa .ui-resizable-nw { width: 12px; height: 12px; z-index: 12; }
		.plano-mesa .ui-resizable-se { right: -6px; bottom: -6px; cursor: nwse-resize; }
		.plano-mesa .ui-resizable-sw { left: -6px; bottom: -6px; cursor: nesw-resize; }
		.plano-mesa .ui-resizable-nw { top: -6px; left: -6px; cursor: nwse-resize; }
		/* El asa circular de arrastre vive sobre la esquina `ne`; por eso el widget renuncia a esa
		   asa (siete en vez de ocho) y esta queda por encima de todas. */
		.plano-mesa .plano-mesa-handle { z-index: 16; }

		/* Bloqueo al crecer (feature 040, D7/FR-009): sombra exterior + micro-desplazamiento de
		   rechazo. NUNCA color de borde: está reservado para el estado libre/ocupada/olvidada, y
		   teñirlo haría parecer que la mesa cambió de estado mientras se arrastra. */
		.plano-mesa.plano-mesa-bloqueada {
			box-shadow: 0 0 0 3px rgba(220,53,69,.35), 0 6px 18px rgba(20,30,60,.18);
			animation: plano-mesa-rechazo .2s ease;
		}
		@keyframes plano-mesa-rechazo {
			0%, 100% { transform: translateX(0); }
			30% { transform: translateX(-3px); }
			70% { transform: translateX(3px); }
		}

		.plano-mesa.forma-redonda { border-radius: 50%; }
		.plano-mesa.forma-cuadrada { border-radius: .6rem; }
		/* Sustituye a la antigua forma `rectangular`: una mesa cuadrada que se estira redondea menos
		   el borde, sin que haya que elegirlo en ningún selector. La redonda estirada se convierte
		   en elipse (el 50% ya lo hace solo) y la barra conserva su radio grande. */
		.plano-mesa.forma-cuadrada.estirada { border-radius: .5rem; }
		.plano-mesa.forma-barra { border-radius: 1.4rem; }

		/* Marcas de sillas alrededor del borde: puntos pequeños generados en JS como spans. */
		.plano-mesa .silla {
			position: absolute; width: .4rem; height: .4rem; border-radius: 50%;
			background: #b7bdc9;
		}
		.plano-mesa.ocupada .silla { background: rgba(22,163,74,.45); }
		.plano-mesa.olvidada .silla { background: rgba(217,119,6,.45); }

		#pos-plano-fuera-rejilla { margin-top: .8rem; }

		/* ── Panel de gestión de zonas/mesas, a la derecha del lienzo. Pensado tablet-friendly:
		   filas altas (min-height 52px, como .pos-filtro), texto editable inline en vez de un
		   modal aparte, sin inputs chicos que fallen al primer toque. */
		.pos-plano-body { display: flex; gap: 1rem; align-items: flex-start; }
		.pos-plano-body-izq { flex: 1 1 auto; min-width: 0; }
		.pos-plano-gestion {
			flex: 0 0 17rem; width: 17rem; display: flex; flex-direction: column; gap: 1rem;
		}
		.pos-plano-gestion-seccion {
			background: #fff; border: 1.5px solid #e6e6e6; border-radius: .9rem; padding: .8rem;
		}
		.pos-plano-gestion-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .6rem; }
		.btn-icon-cuadrado { width: 2.25rem; height: 2.25rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; }

		.pos-plano-gestion-lista { display: flex; flex-direction: column; gap: .4rem; max-height: 15rem; overflow-y: auto; }
		.plano-gestion-item {
			display: flex; align-items: center; gap: .4rem; min-height: 52px;
			border: 1.5px solid #e6e6e6; border-radius: .7rem; padding: 0 .3rem 0 .7rem;
		}
		.plano-gestion-item.activa { border-color: var(--pos-primary, #1d69d6); background: #f5f8ff; }
		.plano-gestion-item input {
			flex: 1 1 auto; min-width: 0; border: none; background: transparent; font-weight: 650;
			font-size: .92rem; padding: .4rem 0;
		}
		.plano-gestion-item input:focus { outline: none; background: #fff; }
		.plano-gestion-item .plano-gestion-meta { font-size: .74rem; color: #9aa0a6; white-space: nowrap; }
		.plano-gestion-item .btn-eliminar {
			width: 2.1rem; height: 2.1rem; flex: 0 0 auto; border: none; background: transparent;
			color: #c33; display: inline-flex; align-items: center; justify-content: center; border-radius: .5rem;
		}
		.plano-gestion-item .btn-eliminar:hover { background: #fbe7e7; }

		/* Fila de alta: el registro se crea al confirmar con el check (o Enter), nunca al perder
		   el foco. El check nace deshabilitado y se habilita en cuanto hay un nombre escrito, para
		   que no se pueda confirmar en vacío. */
		.plano-gestion-item.plano-gestion-alta { border-color: var(--pos-primary, #1d69d6); background: #f5f8ff; }
		.plano-gestion-item .btn-confirmar,
		.plano-gestion-item .btn-cancelar-alta {
			width: 2.1rem; height: 2.1rem; flex: 0 0 auto; border: none; background: transparent;
			display: inline-flex; align-items: center; justify-content: center; border-radius: .5rem;
		}
		.plano-gestion-item .btn-confirmar { color: var(--pos-money, #16a34a); }
		.plano-gestion-item .btn-confirmar:hover:not(:disabled) { background: #e6f6ec; }
		.plano-gestion-item .btn-confirmar:disabled { color: #c4c8ce; cursor: not-allowed; }
		.plano-gestion-item .btn-cancelar-alta { color: #9aa0a6; }
		.plano-gestion-item .btn-cancelar-alta:hover { background: #eef0f3; }
		.plano-gestion-vacio { font-size: .82rem; color: #9aa0a6; padding: .5rem .2rem; }

		@media (max-width: 61.9375rem) {
			.pos-plano-body { flex-direction: column; }
			.pos-plano-gestion { width: 100%; flex-basis: auto; }
		}

		/* Popover de forma/tamaño (T018): un único panel compartido, posicionado junto a la mesa
		   tocada. Patrón propio (no Bootstrap dropdown) porque se reposiciona dinámicamente sobre
		   un elemento con `position: absolute` dentro de un contenedor con scroll horizontal. */
		.plano-popover {
			position: absolute; z-index: 30; background: #fff; border-radius: .8rem;
			box-shadow: 0 10px 30px rgba(16,24,40,.18); border: 1px solid #e6e6e6;
			padding: .7rem; display: none; width: 15rem;
		}
		.plano-popover.abierto { display: block; }
		.plano-popover .plano-popover-titulo { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #9aa0a6; margin-bottom: .35rem; }
		.plano-popover .plano-popover-opciones { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .7rem; }
		.plano-popover .plano-popover-opciones:last-child { margin-bottom: 0; }
		.plano-popover .btn-check + .btn { min-height: 36px; }

		/* ── Lienzo por zona y recorte de la planta (feature 042) ─────────────────────────── */

		.pos-plano-lienzo { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
		.pos-plano-lienzo .pos-plano-lienzo-label { font-size: .78rem; font-weight: 700; color: #8b93a1; margin: 0; }
		.pos-plano-lienzo .pos-plano-lienzo-x { color: #b7bdc9; font-weight: 700; }
		.pos-plano-lienzo input[type="number"] { width: 4.2rem; text-align: center; font-weight: 700; }
		.pos-plano-lienzo .btn { margin-left: .4rem; }
		/* El modo de recorte es un MODO, no una acción suelta (D6): mientras está activo cambia lo
		   que hace el dedo sobre el lienzo, así que el botón tiene que verse encendido. */
		.pos-plano-lienzo .btn[aria-pressed="true"] {
			background: var(--pos-primary, #1d69d6); border-color: var(--pos-primary, #1d69d6); color: #fff;
		}

		/* Capa de celdas. Va por DEBAJO de las mesas (que no llevan z-index propio salvo al
		   arrastrar) y solo captura el dedo cuando el modo de recorte la monta. `touch-action: none`
		   es lo que impide que el navegador se quede el gesto de pintado como scroll de la página,
		   igual que en las asas de redimensionado. */
		.pos-plano-celdas { position: absolute; inset: 0; touch-action: none; }
		/* En modo recorte la capa va POR ENCIMA de las mesas. No es un detalle estético: la celda
		   que está debajo de una mesa es precisamente la que hay que poder tocar para que el
		   sistema conteste "aquí hay una mesa" (FR-010). Si la mesa se quedara el evento, el
		   usuario tocaría y no pasaría nada, que es la peor respuesta posible. Fuera del modo
		   recorte la capa se queda debajo y no captura nada. */
		.pos-plano-celdas.recortando { z-index: 18; }
		.pos-plano-celdas .plano-celda {
			position: absolute; border-radius: .5rem; background: transparent;
			border: 1.5px solid transparent; transition: background-color .12s ease, border-color .12s ease;
		}
		/* Suelo libre en modo recorte: se insinúa al pasar por encima, para que se vea que la celda
		   es un blanco de toque sin tapar el plano. */
		.pos-plano-celdas.recortando .plano-celda:not(.plano-celda-inactiva):hover {
			background: rgba(29,105,214,.08); border-color: rgba(29,105,214,.35);
		}
		/* Celda que NO es sala (FR-005): un hueco, inequívocamente distinto del suelo libre. Se
		   dibuja con la trama diagonal de "aquí no hay planta", no con un gris que podría leerse
		   como "celda ocupada". */
		.pos-plano-celdas .plano-celda-inactiva {
			background-color: #eceef2;
			background-image: repeating-linear-gradient(45deg, transparent 0 6px, rgba(140,148,162,.28) 6px 8px);
			border-color: #dcdfe6; border-style: solid;
		}
		@media (prefers-reduced-motion: reduce) {
			.pos-plano-celdas .plano-celda { transition: none; }
		}

		/* ── Plano en modo servicio (feature 041). Reutiliza `.pos-plano-canvas` y `.plano-mesa`
		   del editor: el contorno tiene que ser el mismo, y para eso el CSS también se comparte.
		   Lo propio de esta vista es el bloque de texto de la mesa y el encaje en pantalla. */
		.pos-plano-servicio-escala { position: relative; overflow: hidden; }
		.pos-plano-servicio-titulo { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #9aa0a6; margin: 1.1rem 0 .55rem; }
		.pos-plano-servicio-vacio { text-align: center; padding: 2.5rem 1rem; color: #9aa0a6; }

		/* En servicio la mesa se toca para abrir su cuenta, así que se comporta como un control:
		   cursor de mano y la misma respuesta al toque que la tarjeta. */
		.plano-mesa.plano-mesa-servicio { cursor: pointer; gap: .05rem; line-height: 1.15; overflow: hidden; }
		.plano-mesa.plano-mesa-servicio:active { filter: brightness(.97); }
		/* El texto NUNCA desborda el contorno: en una mesa de 1×1 (96px) el nombre se trunca con
		   elipsis antes que salirse, y el importe usa cifras tabulares para que cuatro dígitos
		   sigan cayendo dentro (FR-009). */
		.plano-mesa.plano-mesa-servicio .plano-mesa-nombre {
			max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
			font-size: .74rem; font-weight: 750;
		}
		.plano-mesa.plano-mesa-servicio .plano-mesa-importe {
			font-weight: 800; font-size: .92rem; letter-spacing: -.02em; font-variant-numeric: tabular-nums;
			max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.plano-mesa.plano-mesa-servicio .plano-mesa-tiempo { font-size: .68rem; font-weight: 650; color: #9aa0a6; }
		.plano-mesa.ocupada.plano-mesa-servicio .plano-mesa-importe { color: var(--pos-money, #16a34a); }
		.plano-mesa.olvidada.plano-mesa-servicio .plano-mesa-importe,
		.plano-mesa.olvidada.plano-mesa-servicio .plano-mesa-tiempo { color: var(--pos-warn, #d97706); }

		@media (prefers-reduced-motion: reduce) {
			.plano-mesa { transition: none; }
			.plano-mesa.plano-mesa-bloqueada { animation: none; }
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid pos-sala">
			{{-- Resumen plegable (tira de metricas). Nace CERRADO a proposito: en una tablet de
			     sala lo que importa es el plano, y cuatro tarjetas ocupando la primera pantalla
			     empujaban las mesas fuera de la vista. La preferencia se recuerda por usuario,
			     igual que la eleccion de vista (misma razon: varias personas comparten tablet). --}}
			<div class="pos-sala-resumen-bar">
				<button type="button" class="pos-sala-resumen-toggle" id="pos-sala-resumen-toggle"
				        aria-expanded="false" aria-controls="pos-sala-cards-collapse">
					<i class="fas fa-chart-simple" aria-hidden="true"></i>
					<span>Resumen</span>
					<i class="fas fa-chevron-down pos-sala-resumen-chevron" aria-hidden="true"></i>
				</button>
			</div>

			<div class="pos-sala-cards-collapse" id="pos-sala-cards-collapse">
				<div class="pos-sala-cards-collapse-inner">
					<div class="row" id="pos-sala-cards">
						<div class="col-xl-3 col-sm-6">
							<div class="card same-card">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Total de mesas</h6>
											<h3 class="mb-0" data-metric="total">0</h3>
										</div>
										<div>
											<x-lordicon icon="home" size="50" trigger="hover" target=".card" />
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-3 col-sm-6">
							<div class="card same-card">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Libres</h6>
											<h3 class="mb-0" data-metric="libres">0</h3>
										</div>
										<div>
											<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-3 col-sm-6">
							<div class="card same-card">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Ocupadas</h6>
											<h3 class="mb-0 text-success" data-metric="ocupadas">0</h3>
										</div>
										<div>
											<x-lordicon icon="people" size="50" trigger="hover" target=".card" />
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-3 col-sm-6">
							<div class="card same-card">
								<div class="card-body">
									<div class="d-flex justify-content-between align-items-center">
										<div>
											<h6 class="mb-1">Olvidadas</h6>
											<h3 class="mb-0 text-danger" data-metric="olvidadas">0</h3>
										</div>
										<div>
											<x-lordicon icon="wired-outline-3627-mail-open-warning-hover-pinch" size="50" trigger="hover" target=".card" />
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<h4 class="card-title mb-0">Sala</h4>
					<div class="pos-sala-acciones">
						{{-- Sin @can a propósito (FR-004): ver la Sala como plano es parte del servicio;
						     editarlo sigue siendo `ver-configuracion`. --}}
						<div class="pos-sala-vista" id="pos-sala-vista" role="group" aria-label="Vista de la sala">
							<button type="button" data-vista="tarjetas" aria-pressed="true">
								<i class="fas fa-table-cells-large"></i> Tarjetas
							</button>
							<button type="button" data-vista="plano" aria-pressed="false">
								<i class="fas fa-map"></i> Plano
							</button>
						</div>
						<button type="button" class="btn btn-light" id="pos-sala-refrescar">
							<i class="fas fa-rotate"></i> Actualizar
						</button>
						@can('ver-configuracion')
							<button type="button" class="btn btn-outline-primary" id="pos-plano-toggle">
								<i class="fas fa-arrows-up-down-left-right"></i> Editar plano
							</button>
							<button type="button" class="btn btn-success d-none" id="pos-plano-guardar" data-loading-text="Guardando...">
								<i class="fas fa-save"></i> Guardar plano
							</button>
						@endcan
						<a href="{{ route('pos.create') }}" class="btn btn-primary">
							<i class="fas fa-plus"></i> Venta directa
						</a>
					</div>
				</div>
				<div class="card-body">
					<div class="pos-filtros-wrap">
						<div class="pos-filtros p-0" id="pos-sala-zonas" role="tablist" aria-label="Filtrar por zona"></div>
					</div>

					<div class="pos-mesas-grid" id="pos-sala-mesas"></div>

					{{-- Vista de plano en modo servicio (feature 041). Contenedor DISTINTO de
					     `.pos-plano-wrap`, que es el del editor: aquí no se edita nada. --}}
					<div class="d-none" id="pos-plano-servicio">
						<div class="pos-plano-servicio-escala" id="pos-plano-servicio-escala">
							<div class="pos-plano-canvas" id="pos-plano-servicio-canvas"></div>
						</div>

						<p class="pos-plano-servicio-vacio d-none" id="pos-plano-servicio-vacio">
							Esta zona todavía no tiene mesas colocadas en el plano.
						</p>

						<div class="d-none" id="pos-plano-servicio-sin-sitio">
							<p class="pos-plano-servicio-titulo">Sin sitio en el plano</p>
							<div class="pos-mesas-grid" id="pos-plano-servicio-sin-sitio-grid"></div>
						</div>
					</div>

					<p class="pos-sala-vacia d-none" id="pos-sala-vacia">
						Todavía no hay mesas configuradas. Créalas con el botón <strong>Editar plano</strong> de arriba.
					</p>

					<div class="pos-plano-wrap" id="pos-plano-wrap">
						<div class="pos-plano-toolbar">
							<span class="pos-plano-hint" id="pos-plano-hint">Arrastra las mesas por el asa <i class="fas fa-arrows-up-down-left-right"></i> para reordenarlas. Toca una mesa para cambiar su forma y tamaño.</span>

							{{-- Controles del lienzo (feature 042, FR-018): solo con permiso de
							     configuración. El lienzo lo VE todo el mundo (viaja en el payload
							     para la vista de servicio), pero cambiarlo es del encargado.
							     Formato `sm` como el resto de formularios (docs/04-front-guidelines.md,
							     "Tamaño de formularios"). --}}
							@can('ver-configuracion')
								<div class="pos-plano-lienzo" id="pos-plano-lienzo">
									<label class="pos-plano-lienzo-label" for="pos-plano-columnas">Ancho</label>
									<input type="number" class="form-control form-control-sm" id="pos-plano-columnas"
									       min="4" max="24" step="1" inputmode="numeric" aria-label="Columnas de la zona">
									<span class="pos-plano-lienzo-x" aria-hidden="true">×</span>
									<label class="pos-plano-lienzo-label" for="pos-plano-filas">Alto</label>
									<input type="number" class="form-control form-control-sm" id="pos-plano-filas"
									       min="4" max="24" step="1" inputmode="numeric" aria-label="Filas de la zona">

									<button type="button" class="btn btn-sm btn-outline-secondary" id="pos-plano-recorte"
									        aria-pressed="false">
										<i class="fas fa-eraser"></i> Recortar sala
									</button>
								</div>
							@endcan
						</div>
						<div class="pos-plano-body">
							<div class="pos-plano-body-izq">
								<div class="pos-plano-canvas-scroll">
									<div class="pos-plano-canvas" id="pos-plano-canvas"></div>

									<div class="plano-popover" id="plano-popover">
										<div class="plano-popover-titulo">Forma</div>
										<div class="plano-popover-opciones" id="plano-popover-formas"></div>
									</div>
								</div>
								<p class="text-muted small" id="pos-plano-fuera-rejilla"></p>
							</div>

							<aside class="pos-plano-gestion">
								<div class="pos-plano-gestion-seccion">
									<div class="pos-plano-gestion-header">
										<h6 class="mb-0">Zonas</h6>
										<button type="button" class="btn btn-light btn-icon-cuadrado" id="pos-plano-zona-nueva" title="Nueva zona">
											<i class="fas fa-plus"></i>
										</button>
									</div>
									<div class="pos-plano-gestion-lista" id="pos-plano-zonas-lista"></div>
								</div>

								<div class="pos-plano-gestion-seccion">
									<div class="pos-plano-gestion-header">
										<h6 class="mb-0">Mesas</h6>
										<button type="button" class="btn btn-light btn-icon-cuadrado" id="pos-plano-mesa-nueva" title="Nueva mesa">
											<i class="fas fa-plus"></i>
										</button>
									</div>
									<div class="pos-plano-gestion-lista" id="pos-plano-mesas-lista"></div>
								</div>
							</aside>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Sala (POS)')
@section('ayuda')
	@include('ayuda.pos-sala')
@endsection

@push('scripts')
	<script>
		window.posSalaState = {
			estadoUrl: @json(route('pos.sala')),
			umbralOlvidadaMin: {{ $umbralOlvidadaMin }},
			// Compone la clave de la preferencia de vista: varias personas comparten la misma
			// tablet en sala y no deben pisarse la elección (FR-003).
			userId: @json(auth()->id()),
		};
		window.posPlanoState = {
			puedeEditar: @json(auth()->user()?->can('ver-configuracion') ?? false),
			guardarUrlTemplate: @json(route('pos.sala.plano.update', ['zona' => '__ZONA__'])),
		};
		window.posPlanoGestionState = {
			zonasIndexUrl: @json(route('configuracion.pos.zonas.index')),
			zonasStoreUrl: @json(route('configuracion.pos.zonas.store')),
			zonaUpdateUrlTemplate: @json(route('configuracion.pos.zonas.update', ['zona' => '__ZONA__'])),
			zonaDestroyUrlTemplate: @json(route('configuracion.pos.zonas.destroy', ['zona' => '__ZONA__'])),
			mesasIndexUrl: @json(route('configuracion.pos.mesas.index')),
			mesasStoreUrl: @json(route('configuracion.pos.mesas.store')),
			mesaUpdateUrlTemplate: @json(route('configuracion.pos.mesas.update', ['mesa' => '__MESA__'])),
			mesaDestroyUrlTemplate: @json(route('configuracion.pos.mesas.destroy', ['mesa' => '__MESA__'])),
		};
	</script>
	<script src="{{ asset('vendor/jqueryui/js/jquery-ui.min.js') }}"></script>
	{{-- El módulo de dibujo va PRIMERO: lo consumen las tres vistas (tarjetas, plano de servicio y
	     editor). Orden orquestador -> módulos de docs/04-front-guidelines.md. --}}
	<script src="@assetv('js/plugins-init/pos-plano-dibujo.js')"></script>
	<script src="@assetv('js/plugins-init/pos-sala.init.js')"></script>
	<script src="@assetv('js/plugins-init/pos-sala-plano-servicio.init.js')"></script>
	<script src="@assetv('js/plugins-init/pos-sala-plano.init.js')"></script>
	<script src="@assetv('js/plugins-init/pos-sala-plano-gestion.init.js')"></script>
@endpush
