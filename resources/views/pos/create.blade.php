@extends('layouts.app')

@section('title', 'POS · Crear ticket')

@push('styles')
	<style>
		/* ── POS TPV: pensado tablet-first (landscape), 3 zonas: catálogo · ticket · botonera.
		   El "verde dinero" (--pos-money) marca el total y el botón Cobrar; el primario del tenant
		   marca filtros/acciones.

		   El reparto horizontal y el apilado en pantallas chicas los hace el GRID DE BOOTSTRAP
		   (`.row` + `col-xl-7`/`col-xl-5`), igual que el panel de catálogo de facturas
		   (`col-lg-3`/`col-lg-9`, ver docs/04-front-guidelines.md "Vista full-page de creación con
		   preview en vivo"). No reimplementar el reparto con flex/porcentajes a mano: `.row` y
		   `.card` ya vienen calibrados juntos globalmente (gutter 1rem ↔ card margin-bottom 1rem,
		   ver "Gap entre .row y margin-bottom de .card" en esa misma guía), y salirse de esa
		   calibración obliga a pelearla a `!important`. Los breakpoints tampoco se escriben a mano:
		   `col-xl-*` ya apila por debajo de 1200px. */
		.pos-wrap {
			--pos-primary: var(--primary, #1d69d6);
			--pos-money: #16a34a; --pos-money-2: #22c55e;
		}

		/* Geometría vertical: ÚNICA fuente de verdad para las dos columnas (por eso van juntas en
		   la misma regla — separadas, los valores se desincronizan y las columnas se desalinean).
		   Medido en el navegador, no estimado: el header fijo mide 4rem (64px) y el
		   .container-fluid añade 1rem de padding-top ⇒ el contenido arranca en 5rem. Dejando 1rem
		   de aire abajo, el alto disponible es calc(100vh - 6rem). Ojo: el sticky `top` DEBE
		   coincidir con esa posición natural en el flujo; si es mayor, la columna que tenga holgura
		   se descuelga hacia abajo y las dos dejan de alinearse. */
		@media (min-width: 1200px) {
			/* Va `.pos-catalogo.card` y NO `.pos-catalogo` a secas: este <style> lo inyecta
			   @stack('styles') ANTES de css/style.css, así que a igual especificidad gana
			   style.css — que trae `.card { position: relative }`. Con 0,1,0 el sticky se pierde
			   y el `top` pasa a leerse como desplazamiento relativo: la card se descuelga 5rem. */
			.pos-catalogo.card,
			.pos-cobro {
				position: sticky; top: 5rem; height: calc(100vh - 6rem);
			}
			/* Acá sí hace falta !important (y no basta la especificidad): app-overrides.css trae
			   `.card { height: calc(100% - 1rem) !important }`. Sin esto la card crece con el
			   contenido en vez de quedar fija con scroll interno en la grilla. */
			.pos-catalogo.card { height: calc(100vh - 6rem) !important; }
			.pos-grid { overflow-y: auto; }
		}
		@media (max-width: 1199.98px) {
			.pos-lineas-scroll { max-height: 48vh; }
		}

		.pos-catalogo.card { margin-bottom: 0 !important; display: flex; flex-direction: column; }
		.pos-catalogo .card-body { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }

		/* El ticket llena el espacio y la botonera queda pegada al fondo siempre, aunque el ticket
		   esté vacío; al desbordar de líneas, scrollea solo la lista interna. La separación entre
		   el ticket y la botonera la da el `gap` del contenedor, no un margin-bottom en la card:
		   ese margen lo fija `.card { margin-bottom: 1rem !important }` de app-overrides.css y
		   habría que pelearlo a !important. 1rem = mismo ritmo que el gutter global de `.row`. */
		.pos-cobro { display: flex; flex-direction: column; align-items: stretch; gap: 1rem; }
		/* !important en height/margin-bottom por el mismo `.card` global de app-overrides.css: sin
		   esto la card queda con un alto fijo que no puede crecer dentro del flex column de
		   .pos-cobro, dejándola corta y con un hueco antes de la botonera. */
		.pos-ticket.card { flex: 1 1 auto; min-width: 0; min-height: 0; height: auto !important; margin-bottom: 0 !important; display: flex; flex-direction: column; }
		.pos-botonera { display: flex; flex-direction: row; gap: .6rem; align-items: stretch; }
		.pos-botonera .pos-total-card,
		.pos-botonera .pos-bkey,
		.pos-botonera .pos-cobrar { flex: 1 1 0; min-width: 0; }

		@media (max-width: 575.98px) {
			.pos-botonera { flex-wrap: wrap; }
			.pos-botonera .pos-total-card { flex: 1 1 100%; }
			.pos-botonera .pos-bkey,
			.pos-botonera .pos-cobrar { flex: 1 1 40%; }
		}

		/* ── Catálogo ──────────────────────────────────────────────── */
		.pos-search-wrap { position: relative; }
		.pos-search-wrap .pos-search-icon {
			position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9aa0a6; pointer-events: none;
		}
		.pos-search { font-size: 1.05rem; padding: .85rem 1rem .85rem 2.75rem; border-radius: .9rem; }

		/* Filtros de categoría: botones GRANDES tablet-first (pensados para el dedo, no el mouse).
		   Scrollean en horizontal si no caben, sin romper el layout del catálogo. */
		/* Carrusel de categorías: SIEMPRE una sola fila. Si no caben, scrollean en horizontal
		   (slider) con flechas de navegación que aparecen solo cuando hay desborde. Nunca se
		   apilan en dos líneas. */
		.pos-filtros-wrap { position: relative; margin: 1rem 0 .9rem; }
		.pos-filtros {
			display: flex; flex-wrap: nowrap; gap: .6rem; padding: .55rem .1rem .7rem;
			overflow-x: auto; scroll-behavior: smooth; -webkit-overflow-scrolling: touch;
			scrollbar-width: none; /* Firefox: ocultamos la barra, se navega con flechas/gesto. */
		}
		.pos-filtros::-webkit-scrollbar { display: none; } /* Chrome/Safari */

		/* Flechas del carrusel: superpuestas sobre los extremos, con degradado para insinuar que
		   hay más. Ocultas por defecto; el JS las muestra según la posición de scroll. */
		.pos-filtros-nav {
			position: absolute; top: 50%; transform: translateY(-50%); z-index: 3;
			width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--bs-border-color, #e0e0e0);
			background: #fff; color: #333; box-shadow: 0 4px 12px rgba(20,30,60,.14);
			display: none; align-items: center; justify-content: center; cursor: pointer;
			font-size: 1.4rem; line-height: 1; padding: 0; -webkit-tap-highlight-color: transparent;
			transition: background .12s ease, transform .08s ease;
		}
		.pos-filtros-nav.visible { display: inline-flex; }
		.pos-filtros-nav:hover { background: #f5f8ff; border-color: var(--pos-primary); }
		.pos-filtros-nav:active { transform: translateY(-50%) scale(.9); }
		.pos-filtros-nav.prev { left: -6px; }
		.pos-filtros-nav.next { right: -6px; }
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
		.pos-articulo.filtrado-oculto { display: none !important; }

		/* `overflow-y: auto` NO va acá sino en el media query de ≥1200px: solo con la columna de
		   alto fijo tiene sentido que la grilla scrollee por dentro. Apilado, fluye con la página. */
		.pos-grid {
			display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: .75rem;
			flex: 1 1 auto; min-height: 0; align-content: start;
		}
		.pos-articulo {
			position: relative; border: 1px solid var(--bs-border-color, #ebebeb); border-radius: 1.1rem; background: #fff;
			padding: 0; cursor: pointer; text-align: center; overflow: hidden; display: block;
			 min-height: 128px;
			transition: transform .08s ease, box-shadow .18s ease, border-color .18s ease;
			will-change: transform, opacity;
		}
		.pos-articulo:hover { box-shadow: 0 10px 24px rgba(20,30,60,.12); border-color: var(--pos-accent, var(--pos-primary)); transform: translateY(-3px); }
		.pos-articulo:active { transform: scale(.95); }
		/* Foto (o icono de respaldo) ocupando toda la card; el nombre va en una franja inferior
		   superpuesta, no debajo en flujo — así la imagen manda y el texto queda siempre legible. */
		.pos-art-icono {
			position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
			background: color-mix(in srgb, var(--pos-accent, #1d69d6) 12%, #fff); overflow: hidden;
		}
		.pos-art-imagen { width: 100%; height: 100%; object-fit: cover; display: block; }
		.pos-art-nombre {
			position: absolute; left: 0; right: 0; bottom: 0; z-index: 1;
			background: rgba(255, 255, 255, .94); border-top: 1px solid rgba(0, 0, 0, .05);
			padding: .4rem .35rem; font-weight: 700; font-size: .92rem; line-height: 1.2; color: #2b2f36;
			word-break: break-word; overflow: hidden;
			/* Siempre reserva el alto de 2 líneas (aunque el nombre entre en una), para que todas
			   las franjas midan igual sin importar el largo del texto. */
			height: calc(1.2em * 2 + .8rem);
			display: flex; align-items: center; justify-content: center;
		}
		.pos-art-nombre span {
			display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
		}
		/* Badge de stock: esquina superior del card. "Sin stock" (rojo) para productos
		   gestionados con stock ≤ 0; el card además se atenúa para lectura rápida en tablet. */
		.pos-art-badge {
			position: absolute; top: .5rem; right: .5rem; z-index: 2;
			font-size: .68rem; font-weight: 700; line-height: 1; letter-spacing: .01em;
			padding: .3rem .5rem; border-radius: 1rem; white-space: nowrap;
		}
		.pos-art-badge.sin-stock { background: #fdecea; color: #c0392b; border: 1px solid #f3d2ce; }
		.pos-art-badge.bajo-stock { background: #fff4e5; color: #b26a00; border: 1px solid #ffe0b2; }
		.pos-articulo.agotado { opacity: .58; }
		.pos-articulo.agotado:hover { transform: none; box-shadow: none; border-color: var(--bs-border-color, #ebebeb); }
		.pos-articulo.just-added { animation: pos-pop .25s ease; }
		@keyframes pos-pop { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.4); } 100% { box-shadow: 0 0 0 12px rgba(34,197,94,0); } }

		.pos-empty-catalogo { grid-column: 1 / -1; text-align: center; padding: 3rem 1rem; color: #9aa0a6; }

		/* ── Ticket ────────────────────────────────────────────────── */
		.pos-ticket .card-header { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.pos-ticket .card-header .titulo { display: flex; align-items: center; gap: .5rem; }
		.pos-ticket-count { background: var(--pos-primary); color: #fff; border-radius: 1rem; font-size: .78rem; font-weight: 700; padding: .1rem .55rem; }
		.pos-vaciar {
			border: none; background: none; color: #b0332a; opacity: .6; display: inline-flex; align-items: center;
			padding: .2rem; line-height: 0; transition: opacity .12s ease;
		}
		.pos-vaciar:hover { opacity: 1; }

		.pos-ticket .card-body { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
		#pos-lineas { min-height: 0; }
		.pos-lineas-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; margin: -.25rem -.25rem 0; padding: 0 .25rem; }
		/* Táctil (tablet): cada línea en dos filas — nombre+importe arriba, controles GRANDES
		   abajo (54px, misma estética redondeada de la botonera de abajo). Pensado para el dedo,
		   no el mouse. */
		.pos-linea { display: flex; flex-direction: column; gap: .55rem; padding: .9rem 0; border-bottom: 1px solid #f2f2f2; }
		.pos-linea:last-child { border-bottom: none; }
		.pos-linea .linea-top { display: flex; align-items: baseline; justify-content: space-between; gap: .6rem; }
		.pos-linea .concepto { flex: 1 1 auto; min-width: 0; }
		.pos-linea .concepto .nombre-linea { font-weight: 600; font-size: 1rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pos-linea .concepto small { color: #9aa0a6; }
		.pos-linea .importe { font-weight: 700; font-size: 1rem; flex: none; white-space: nowrap; }
		.pos-linea .linea-controls { display: flex; align-items: center; justify-content: space-between; }
		.pos-linea .qty-group { display: flex; align-items: center; gap: .75rem; }
		.pos-linea .qty-btn {
			width: 54px; height: 54px; border-radius: 14px; border: 1px solid #e2e2e2; background: #fafbfc;
			font-weight: 700; font-size: 1.6rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center;
			transition: background .12s ease, transform .08s ease, border-color .12s ease; color: #333;
			-webkit-tap-highlight-color: transparent;
		}
		.pos-linea .qty-btn:hover { background: #eef2fb; border-color: var(--pos-primary); }
		.pos-linea .qty-btn:active { transform: scale(.92); background: #e6edfb; }
		.pos-linea .qty { min-width: 40px; text-align: center; font-weight: 700; font-size: 1.35rem; }
		.pos-linea .del {
			width: 54px; height: 54px; border-radius: 14px; border: 1px solid #f3d2ce; background: #fdf1f0;
			color: #c0392b; font-size: 1.7rem; line-height: 1; padding: 0;
			display: inline-flex; align-items: center; justify-content: center;
			transition: background .12s ease, transform .08s ease;
			-webkit-tap-highlight-color: transparent;
		}
		.pos-linea .del:hover { background: #f9dedb; }
		.pos-linea .del:active { transform: scale(.92); }

		.pos-empty-ticket { text-align: center; padding: 2.75rem 1rem 2.25rem; color: #9aa0a6; margin: auto 0; }
		.pos-empty-ticket .lord-icon-wrap { opacity: .55; margin-bottom: .5rem; }

		.pos-foot { border-top: 1px dashed #e6e6e6; margin-top: .85rem; padding-top: .75rem; font-size: .8rem; color: #9aa0a6; text-align: center; }

		.pos-tope-alert { display: none; align-items: center; gap: .5rem; margin-top: .85rem; }
		.pos-tope-alert.show { display: flex; }

		/* ── Botonera ──────────────────────────────────────────────── */
		.pos-total-card {
			background: linear-gradient(135deg, var(--pos-money), var(--pos-money-2)); color: #fff; border-radius: 1.1rem;
			padding: 1rem .9rem; text-align: center; box-shadow: 0 8px 20px rgba(22,163,74,.28);
			display: flex; flex-direction: column; gap: .1rem;
			border: none; cursor: pointer; font: inherit;
			transition: box-shadow .18s ease, transform .08s ease;
		}
		.pos-total-card:hover { box-shadow: 0 12px 26px rgba(22,163,74,.4); }
		.pos-total-card:active { transform: scale(.98); }
		.pos-total-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .14em; opacity: .92; font-weight: 700; }
		.pos-total { font-size: 1.85rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.05; }
		.pos-total-sub { font-size: .66rem; opacity: .85; }

		.pos-bkey {
			display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem;
			border: 1px solid var(--bs-border-color, #e6e6e6); background: #fff; border-radius: 1rem; padding: .75rem .5rem;
			font-weight: 600; font-size: .82rem; color: #444; cursor: pointer; min-height: 78px; text-align: center;
			transition: background .14s ease, border-color .14s ease, color .14s ease, box-shadow .14s ease;
		}
		.pos-bkey .pos-bkey-label { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pos-bkey:hover { background: #f5f8ff; border-color: var(--pos-primary); }
		.pos-bkey.active { background: var(--pos-primary); border-color: var(--pos-primary); color: #fff; box-shadow: 0 6px 16px rgba(29,105,214,.25); }

		.pos-cobrar {
			border: none; border-radius: 1.1rem; padding: 1.1rem .5rem; min-height: 92px;
			background: linear-gradient(135deg, var(--pos-money), var(--pos-money-2)); color: #fff; font-weight: 800;
			font-size: 1.2rem; letter-spacing: .02em; cursor: pointer; box-shadow: 0 10px 24px rgba(22,163,74,.35);
			display: flex; align-items: center; justify-content: center; gap: .5rem;
			transition: transform .08s ease, box-shadow .18s ease, background .18s ease;
		}
		.pos-cobrar:hover:not(:disabled) { box-shadow: 0 14px 30px rgba(22,163,74,.45); }
		.pos-cobrar:active:not(:disabled) { transform: scale(.97); }
		.pos-cobrar:disabled { background: #c9ccd1; box-shadow: none; cursor: not-allowed; }

		/* ── Modal desglose del total ──────────────────────────────── */
		.pos-total-modal .row-desglose { display: flex; align-items: baseline; justify-content: space-between; padding: .7rem 0; }
		.pos-total-modal .row-desglose .lbl { color: #6b7280; font-weight: 600; font-size: 1.05rem; }
		.pos-total-modal .row-desglose .val { font-weight: 700; font-size: 1.7rem; font-variant-numeric: tabular-nums; }
		.pos-total-modal .row-desglose.total { border-top: 2px solid #e6e6e6; margin-top: .5rem; padding-top: 1rem; }
		.pos-total-modal .row-desglose.total .lbl { color: #16a34a; font-weight: 800; font-size: 1.2rem; }
		.pos-total-modal .row-desglose.total .val { color: #16a34a; font-size: 2.6rem; letter-spacing: -.02em; }

		/* ── Modal de cobro (pago simple o dividido) — tablet-first ──
		   Patrón TPV real: total arriba, tarjetas grandes de método, teclado numérico en pantalla
		   y lista de pagos añadidos con un "restante" que baja hasta 0. Nada de dropdowns ni inputs
		   diminutos: todo pensado para el dedo. Reutiliza el "verde dinero" y el primario del tenant. */
		.pos-cobro-modal {
			--pos-primary: var(--primary, #1d69d6);
			--pos-money: #16a34a; --pos-money-2: #22c55e;
			--ease-out: cubic-bezier(.23, 1, .32, 1);
		}
		.pos-cobro-modal .modal-body { padding: 1.1rem 1.15rem; }

		/* Total + restante en una tira: el total (verde) manda, el restante informa cuánto falta. */
		.pos-cobro-cab { display: flex; gap: .7rem; margin-bottom: 1rem; }
		.pos-cobro-total, .pos-cobro-restante {
			flex: 1 1 0; border-radius: 1rem; padding: .85rem 1rem; text-align: center;
			display: flex; flex-direction: column; gap: .1rem;
		}
		.pos-cobro-total { background: linear-gradient(135deg, var(--pos-money), var(--pos-money-2)); color: #fff; box-shadow: 0 8px 20px rgba(22,163,74,.28); }
		.pos-cobro-restante { background: #f5f6f8; border: 1px solid var(--bs-border-color, #e6e6e6); color: #2b2f36; }
		.pos-cobro-cab .lbl { font-size: .64rem; text-transform: uppercase; letter-spacing: .13em; font-weight: 700; opacity: .9; }
		.pos-cobro-cab .val { font-size: 1.7rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.05; font-variant-numeric: tabular-nums; }
		.pos-cobro-restante.completo { background: #e9f9ef; border-color: #b6ebc8; color: #14833b; }
		.pos-cobro-restante.completo .val { color: #14833b; }
		/* Con el teclado abierto, tocar "Restante" autocompleta el importe con lo que falta. */
		.pos-cobro-restante.tappable {
			cursor: pointer; border-style: dashed; border-color: var(--pos-primary);
			transition: background .14s var(--ease-out), transform .08s var(--ease-out);
		}
		.pos-cobro-restante.tappable:hover { background: #eef3ff; }
		.pos-cobro-restante.tappable:active { transform: scale(.97); }
		.pos-cobro-restante .tap-hint { font-size: .58rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--pos-primary); margin-top: .1rem; display: none; }
		.pos-cobro-restante.tappable .tap-hint { display: block; }

		/* Pagos ya añadidos (chips): método + importe + quitar. Aparecen sólo cuando hay alguno. */
		.pos-cobro-tenders { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1rem; }
		.pos-cobro-tender {
			display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border-radius: .85rem;
			background: #fff; border: 1px solid var(--bs-border-color, #e6e6e6);
			animation: pos-tender-in .22s var(--ease-out);
		}
		@keyframes pos-tender-in { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
		.pos-cobro-tender .ic { width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
			background: color-mix(in srgb, var(--pos-primary) 12%, #fff); color: var(--pos-primary); font-size: 1.05rem; flex: none; }
		.pos-cobro-tender .nom { flex: 1 1 auto; font-weight: 650; }
		.pos-cobro-tender .imp { font-weight: 800; font-variant-numeric: tabular-nums; }
		.pos-cobro-tender .quitar {
			flex: none; width: 38px; height: 38px; border-radius: 10px; border: none; background: #fdf1f0; color: #c0392b;
			font-size: 1.3rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center;
			-webkit-tap-highlight-color: transparent; transition: background .12s ease, transform .08s var(--ease-out);
		}
		.pos-cobro-tender .quitar:hover { background: #f9dedb; }
		.pos-cobro-tender .quitar:active { transform: scale(.9); }

		.pos-cobro-hint { font-size: .82rem; color: #9aa0a6; font-weight: 600; margin: 0 0 .6rem; }

		/* Tarjetas de método: grid 2×2, grandes y táctiles (misma familia que .pos-bkey/.pos-filtro). */
		.pos-cobro-metodos { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
		.pos-metodo {
			display: flex; align-items: center; gap: .7rem; min-height: 66px; padding: .75rem .9rem;
			border: 1.5px solid var(--bs-border-color, #e6e6e6); background: #fff; border-radius: 1rem;
			font-weight: 700; font-size: 1rem; color: #2b2f36; cursor: pointer; text-align: left;
			-webkit-tap-highlight-color: transparent;
			transition: background .14s var(--ease-out), border-color .14s var(--ease-out), color .14s var(--ease-out), box-shadow .14s var(--ease-out), transform .08s var(--ease-out);
		}
		.pos-metodo .ic { width: 40px; height: 40px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center;
			background: color-mix(in srgb, var(--pos-primary) 12%, #fff); color: var(--pos-primary); font-size: 1.15rem; flex: none; transition: background .14s var(--ease-out), color .14s var(--ease-out); }
		.pos-metodo:hover { border-color: var(--pos-primary); background: #f5f8ff; }
		.pos-metodo:active { transform: scale(.97); }
		.pos-metodo.activo { background: var(--pos-primary); border-color: var(--pos-primary); color: #fff; box-shadow: 0 6px 16px rgba(29,105,214,.25); }
		.pos-metodo.activo .ic { background: rgba(255,255,255,.22); color: #fff; }

		/* Teclado numérico en pantalla: aparece al elegir un método; el foco no sale de la app. */
		.pos-keypad { margin-top: .3rem; }
		.pos-keypad-head { display: flex; align-items: center; justify-content: space-between; gap: .6rem; margin-bottom: .7rem; }
		.pos-keypad-head .metodo-lbl { display: inline-flex; align-items: center; gap: .5rem; font-weight: 700; color: #2b2f36; }
		.pos-keypad-head .metodo-lbl .ic { color: var(--pos-primary); font-size: 1.05rem; }
		.pos-keypad-monto {
			font-size: 1.7rem; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.01em; color: #16a34a;
		}
		.pos-keypad-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .55rem; }
		.pos-key {
			min-height: 60px; border-radius: 14px; border: 1px solid var(--bs-border-color, #e6e6e6); background: #fff;
			font-size: 1.5rem; font-weight: 700; color: #2b2f36; cursor: pointer; -webkit-tap-highlight-color: transparent;
			display: inline-flex; align-items: center; justify-content: center;
			transition: background .12s var(--ease-out), border-color .12s var(--ease-out), transform .07s var(--ease-out);
		}
		.pos-key:hover { background: #f5f8ff; border-color: var(--pos-primary); }
		.pos-key:active { transform: scale(.94); background: #e6edfb; }
		.pos-key.wide { font-size: 1.25rem; }

		.pos-keypad-acciones { display: flex; gap: .55rem; margin-top: .7rem; }
		.pos-keypad-acciones .btn { flex: 1 1 0; min-height: 54px; font-weight: 700; border-radius: 14px !important; font-size: 1rem !important; }
		.pos-keypad-anadir { background: var(--pos-primary); border: none; color: #fff; }
		.pos-keypad-anadir:disabled { background: #c9ccd1; }

		/* Doble clase (.pos-cobro-modal .pos-cobro-emitir) para ganarle en especificidad al
		   `.btn { font-size:.76rem !important }` de app-overrides.css, que carga después. */
		.pos-cobro-modal .pos-cobro-emitir {
			min-height: 68px; font-size: 1.45rem !important; font-weight: 800; padding: 1rem !important; border-radius: 14px !important;
			background: linear-gradient(135deg, var(--pos-money), var(--pos-money-2)); border: none; color: #fff !important;
			letter-spacing: .01em; box-shadow: 0 10px 24px rgba(22,163,74,.32);
			transition: transform .08s var(--ease-out), box-shadow .18s var(--ease-out), background .18s var(--ease-out);
		}
		.pos-cobro-modal .pos-cobro-emitir:active:not(:disabled) { transform: scale(.98); }
		.pos-cobro-modal .pos-cobro-emitir:disabled { background: #c9ccd1; color: #fff !important; box-shadow: none; }

		@media (prefers-reduced-motion: reduce) {
			.pos-cobro-tender { animation: none; }
			.pos-metodo, .pos-key, .pos-cobro-emitir, .pos-cobro-tender .quitar { transition: none; }
		}

		/* ── Modal de éxito al emitir ──────────────────────────────── */
		.pos-exito-icono { margin: .25rem 0 .5rem; }
		/* Acciones grandes pensadas para el dedo en tablet (no mouse). */
		.pos-exito-acciones { gap: .75rem; flex-wrap: wrap; justify-content: center; }
		.pos-exito-acciones .btn {
			flex: 1 1 30%; min-width: 140px; min-height: 66px; margin: 0;
			/* !important: app-overrides.css fuerza font-size/padding/border-radius "sm" en .btn. */
			font-size: 1.15rem !important; font-weight: 700; padding: 1rem !important; border-radius: 14px !important;
			display: inline-flex; align-items: center; justify-content: center;
			-webkit-tap-highlight-color: transparent;
		}
		.pos-exito-acciones .btn:active { transform: scale(.97); }
		.pos-pdf-frame {
			width: 100%; height: 60vh; border: 1px solid var(--bs-border-color, #e6e6e6);
			border-radius: .5rem; background: #f8f9fa;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row pos-wrap">

				{{-- ── Zona 1: catálogo ── --}}
				<div class="col-xl-7">
				<section class="pos-catalogo card">
					<div class="card-body">
						<div class="pos-search-wrap">
							<i class="fas fa-search pos-search-icon"></i>
							<input type="search" id="pos-search" class="form-control pos-search" placeholder="Buscar artículo...">
						</div>

						@if ($categorias->isNotEmpty())
							<div class="pos-filtros-wrap" id="pos-filtros-wrap">
								<button type="button" class="pos-filtros-nav prev" id="pos-filtros-prev" aria-label="Categorías anteriores">‹</button>
								<div class="pos-filtros p-0" id="pos-filtros" role="tablist" aria-label="Filtrar por categoría">
									<button type="button" class="pos-filtro active" data-categoria="" aria-pressed="true">
										Todos
										<span class="badge-count">{{ $articulos->count() }}</span>
									</button>
									@foreach ($categorias as $categoria)
										<button type="button" class="pos-filtro" data-categoria="{{ $categoria['id'] }}" aria-pressed="false">
											{{ $categoria['nombre'] }}
											<span class="badge-count">{{ $categoria['total'] }}</span>
										</button>
									@endforeach
								</div>
								<button type="button" class="pos-filtros-nav next" id="pos-filtros-next" aria-label="Más categorías">›</button>
							</div>
						@endif

						<div class="pos-grid " id="pos-grid">
							@forelse ($articulos as $articulo)
								@php
									$sinStock = $articulo->gestion_stock && $articulo->stock_actual !== null && $articulo->stock_actual <= 0;
									$bajoStock = $articulo->gestion_stock && ! $sinStock && $articulo->stock_minimo !== null && $articulo->stock_actual <= $articulo->stock_minimo;
								@endphp
								<button type="button" class="pos-articulo {{ $sinStock ? 'agotado' : '' }}"
									style="--pos-accent: {{ $articulo->tipo->value === 'servicio' ? '#8a5cf6' : '#1d69d6' }};"
									data-id="{{ $articulo->id }}"
									data-nombre="{{ $articulo->nombre }}"
									data-precio="{{ (float) $articulo->precio }}"
									data-unidad="{{ $articulo->unidad }}"
									data-categoria="{{ $articulo->categoria_id }}"
									data-tipo-impositivo="{{ (float) $articulo->tipo_impositivo }}"
									data-tipo-articulo="{{ $articulo->tipo->value }}">
									@if ($sinStock)
										<span class="pos-art-badge sin-stock">Sin stock</span>
									@elseif ($bajoStock)
										<span class="pos-art-badge bajo-stock">Quedan {{ rtrim(rtrim(number_format((float) $articulo->stock_actual, 2, '.', ''), '0'), '.') }}</span>
									@endif
									<span class="pos-art-icono">
										@if ($articulo->imagenUrl())
											<img src="{{ $articulo->imagenUrl() }}" alt="" class="pos-art-imagen">
										@else
											<x-lordicon icon="{{ $articulo->tipo->value === 'servicio' ? 'servicio' : 'producto' }}" size="56" trigger="hover" target=".pos-articulo" />
										@endif
									</span>
									<span class="pos-art-nombre"><span>{{ $articulo->nombre }}</span></span>
								</button>
							@empty
								<p class="pos-empty-catalogo">No hay artículos en el catálogo. Añádelos en Productos/Servicios.</p>
							@endforelse
						</div>
						<p class="pos-empty-catalogo d-none" id="pos-empty-catalogo">No hay artículos que coincidan con la búsqueda.</p>
					</div>
				</section>
				</div>

				{{-- ── Zonas 2 y 3: ticket + botonera (columna de cobro sticky) ── --}}
				<div class="col-xl-5">
				<div class="pos-cobro">

					<section class="pos-ticket card">
						<div class="card-header">
							<span class="titulo">
								<h4 class="card-title mb-0">Ticket</h4>
								<span class="pos-ticket-count d-none" id="pos-ticket-count">0</span>
							</span>
							<button type="button" class="pos-vaciar d-none" id="pos-vaciar" title="Vaciar ticket" aria-label="Vaciar ticket">
								<x-lordicon icon="wired-outline-185-trash-bin-hover-empty" size="22" trigger="hover" />
							</button>
						</div>
						<div class="card-body">
							<div id="pos-lineas" class="d-flex flex-column flex-grow-1">
								<div class="pos-empty-ticket" id="pos-vacio">
									<div class="lord-icon-wrap">
										<x-lordicon icon="wired-outline-1910-beverages" size="48" trigger="loop-on-hover" />
									</div>
									<p class="mb-0">Toca un artículo para añadirlo.</p>
								</div>
								<div class="pos-lineas-scroll d-none" id="pos-lineas-scroll"></div>
							</div>

							<div class="pos-foot" id="pos-foot">
								<span id="pos-foot-count">0 artículos</span> · {{ $regimen['label'] }} incluido
							</div>

							<div class="alert alert-danger pos-tope-alert" id="pos-tope-alert">
								<i class="fas fa-exclamation-triangle"></i>
								<span>
									Supera el máximo de una factura simplificada
									(<strong id="pos-tope-valor">{{ number_format($topeAplicable, 2, ',', '.') }}</strong> € {{ $regimen['label'] }} incl.).
									Emite una factura ordinaria.
								</span>
							</div>
						</div>
					</section>

					<aside class="pos-botonera" aria-label="Acciones del ticket">
						<button type="button" class="pos-total-card" id="pos-total-card"
							data-bs-toggle="modal" data-bs-target="#posTotalModal"
							aria-label="Ver desglose del total">
							<span class="pos-total-label">Total</span>
							<span class="pos-total" id="pos-total">0,00 €</span>
							<span class="pos-total-sub">{{ $regimen['label'] }} incluido</span>
						</button>

						<button type="button" class="pos-bkey" id="pos-cliente-btn" data-bs-toggle="modal" data-bs-target="#posReceptorModal">
							<x-lordicon icon="person" size="26" trigger="hover" target="#pos-cliente-btn" />
							<span class="pos-bkey-label" id="pos-cliente-btn-label">Cliente</span>
						</button>

						<button type="button" class="pos-cobrar" id="pos-cobrar" disabled
							data-bs-toggle="modal" data-bs-target="#posCobroModal">
							<x-lordicon icon="euro" size="24" trigger="hover" target="#pos-cobrar" colors="primary:#ffffff,secondary:#ffffff" />
							<span>Cobrar</span>
						</button>
					</aside>

				</div>
				</div>

			</div>
		</div>
	</div>

	{{-- Desglose del total: subtotal (base) + impuesto + total, en grande. --}}
	<div class="modal fade" id="posTotalModal" tabindex="-1" aria-labelledby="posTotalModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="posTotalModalLabel">Desglose del total</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body pos-total-modal">
					<div class="row-desglose">
						<span class="lbl">Subtotal</span>
						<span class="val" id="pos-modal-subtotal">0,00 €</span>
					</div>
					<div class="row-desglose">
						<span class="lbl">{{ $regimen['label'] }}</span>
						<span class="val" id="pos-modal-impuesto">0,00 €</span>
					</div>
					<div class="row-desglose total">
						<span class="lbl">Total</span>
						<span class="val" id="pos-modal-total">0,00 €</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	{{-- Cobro: reparte el total en uno o varios métodos de pago (pago dividido). Táctil, tablet-first;
	     el desglose es interno (no se refleja en el PDF del ticket). --}}
	@php
		$metodosPago = [
			'efectivo' => ['label' => 'Efectivo', 'icon' => 'fa-money-bill-wave'],
			'tarjeta' => ['label' => 'Tarjeta', 'icon' => 'fa-credit-card'],
			'transferencia' => ['label' => 'Transferencia', 'icon' => 'fa-building-columns'],
			'domiciliacion' => ['label' => 'Domiciliación', 'icon' => 'fa-file-invoice-dollar'],
		];
	@endphp
	<div class="modal fade pos-cobro-modal" id="posCobroModal" tabindex="-1" aria-labelledby="posCobroModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="posCobroModalLabel">Cobrar</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="pos-cobro-cab">
						<div class="pos-cobro-total">
							<span class="lbl">Total</span>
							<span class="val" id="pos-cobro-total">0,00 €</span>
						</div>
						<div class="pos-cobro-restante" id="pos-cobro-restante" role="button" tabindex="0" aria-label="Autocompletar con el importe restante">
							<span class="lbl" id="pos-cobro-restante-lbl">Restante</span>
							<span class="val" id="pos-cobro-restante-val">0,00 €</span>
							<span class="tap-hint">Tocar para autocompletar</span>
						</div>
					</div>

					{{-- Pagos ya añadidos (aparece sólo cuando hay alguno). --}}
					<div class="pos-cobro-tenders d-none" id="pos-cobro-tenders"></div>

					{{-- Selección de método (visible mientras falte por asignar). --}}
					<div id="pos-cobro-eleccion">
						<p class="pos-cobro-hint" id="pos-cobro-hint">Tocá el método con el que cobrás.</p>
						<div class="pos-cobro-metodos">
							@foreach ($metodosPago as $valor => $meta)
								<button type="button" class="pos-metodo" data-metodo="{{ $valor }}" data-label="{{ $meta['label'] }}">
									<span class="ic"><i class="fas {{ $meta['icon'] }}"></i></span>
									<span>{{ $meta['label'] }}</span>
								</button>
							@endforeach
						</div>
					</div>

					{{-- Teclado numérico: aparece al elegir un método. --}}
					<div class="pos-keypad d-none" id="pos-cobro-keypad">
						<div class="pos-keypad-head">
							<span class="metodo-lbl">
								<span class="ic"><i class="fas" id="pos-keypad-icon"></i></span>
								<span id="pos-keypad-metodo-lbl">Efectivo</span>
							</span>
							<span class="pos-keypad-monto" id="pos-keypad-monto">0,00 €</span>
						</div>
						<div class="pos-keypad-grid">
							<button type="button" class="pos-key" data-key="1">1</button>
							<button type="button" class="pos-key" data-key="2">2</button>
							<button type="button" class="pos-key" data-key="3">3</button>
							<button type="button" class="pos-key" data-key="4">4</button>
							<button type="button" class="pos-key" data-key="5">5</button>
							<button type="button" class="pos-key" data-key="6">6</button>
							<button type="button" class="pos-key" data-key="7">7</button>
							<button type="button" class="pos-key" data-key="8">8</button>
							<button type="button" class="pos-key" data-key="9">9</button>
							<button type="button" class="pos-key wide" data-key=",">,</button>
							<button type="button" class="pos-key" data-key="0">0</button>
							<button type="button" class="pos-key wide" data-key="del" aria-label="Borrar">⌫</button>
						</div>
						<div class="pos-keypad-acciones">
							<button type="button" class="btn btn-light" id="pos-keypad-cancelar">Cancelar</button>
							<button type="button" class="btn pos-keypad-anadir" id="pos-keypad-anadir">Añadir pago</button>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn pos-cobro-emitir w-100" id="pos-cobro-emitir" disabled>
						Emitir ticket
					</button>
				</div>
			</div>
		</div>
	</div>

	{{-- Metadatos de métodos de pago para el JS (etiqueta + icono por valor). --}}
	<script type="application/json" id="pos-metodos-data">@json($metodosPago)</script>

	{{-- Éxito al emitir: OK + mensaje + acciones grandes (táctil). Sin PDF embebido. --}}
	<div class="modal fade" id="posExitoModal" tabindex="-1" aria-labelledby="posExitoModalLabel" aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header border-0 pb-0">
					<h5 class="modal-title visually-hidden" id="posExitoModalLabel">Ticket emitido</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body text-center pt-0 pb-2">
					<div class="pos-exito-icono">
						<x-lordicon icon="wired-outline-267-like-thumb-up-hover-up" size="96" trigger="loop" />
					</div>
					<h4 class="mb-1">¡Ticket emitido con éxito!</h4>
					<p class="text-muted mb-0" id="pos-exito-numero"></p>
				</div>
				<div class="modal-footer pos-exito-acciones">
					<button type="button" class="btn btn-outline-primary" id="pos-exito-ver">Ver ticket</button>
					<button type="button" class="btn btn-outline-primary" id="pos-exito-imprimir">Imprimir</button>
					<button type="button" class="btn btn-primary" id="pos-exito-seguir">Seguir creando</button>
				</div>
			</div>
		</div>
	</div>

	{{-- Ver ticket: PDF del ticket (formato 80mm), solo al pedirlo desde el modal de éxito. --}}
	<div class="modal fade" id="posVerTicketModal" tabindex="-1" aria-labelledby="posVerTicketModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="posVerTicketModalLabel">Ticket</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<iframe id="pos-ver-frame" class="pos-pdf-frame" title="Ticket"></iframe>
				</div>
			</div>
		</div>
	</div>

	{{-- Iframe oculto solo para imprimir (precarga el PDF; nunca se muestra). --}}
	<iframe id="pos-print-frame" title="Impresión" aria-hidden="true" tabindex="-1"
		style="position:fixed; left:-10000px; top:0; width:380px; height:600px; border:0;"></iframe>

	{{-- Datos del receptor: solo para factura simplificada cualificada (opcional). --}}
	<div class="modal fade" id="posReceptorModal" tabindex="-1" aria-labelledby="posReceptorModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="posReceptorModalLabel">Datos del receptor</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted small mb-3">
						Rellena estos datos solo si el cliente pide una factura simplificada <strong>cualificada</strong>.
						Si los dejas vacíos, el ticket se emite a consumidor final.
					</p>
					<div class="mb-2">
						<label class="form-label" for="pos-cliente">Cliente</label>
						<select id="pos-cliente" class="form-control">
							<option value="">— Cliente nuevo / manual —</option>
							@foreach ($clientes as $cliente)
								<option value="{{ $cliente->id }}"
									data-nif="{{ $cliente->nif }}"
									data-nombre="{{ $cliente->razon_social ?: $cliente->nombre }}"
									data-direccion="{{ $cliente->direccion }}">
									{{ $cliente->razon_social ?: $cliente->nombre }}
								</option>
							@endforeach
						</select>
					</div>
					<input type="text" id="pos-nif" class="form-control mb-2" placeholder="NIF">
					<input type="text" id="pos-nombre" class="form-control mb-2" placeholder="Nombre / razón social">
					<input type="text" id="pos-direccion" class="form-control" placeholder="Domicilio">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" id="pos-receptor-quitar">Quitar datos</button>
					<button type="button" class="btn btn-primary" id="pos-receptor-aplicar" data-bs-dismiss="modal">Aplicar</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Crear ticket (POS)')
@section('ayuda')
	@include('ayuda.pos-crear')
@endsection

@push('scripts')
	<script>
		window.posState = {
			storeUrl: @json(route('pos.store')),
			indexUrl: @json(route('pos.index')),
			pdfUrlTemplate: @json(route('pos.pdf', ['factura' => '__ID__', 'formato' => 'ticket'])),
			tope: {{ $topeAplicable }},
			regimen: @json($regimen),
		};
	</script>
	<script src="{{ asset('js/pos-form.js') }}"></script>
@endpush
