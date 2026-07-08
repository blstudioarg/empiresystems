@extends('layouts.app')

@section('title', 'POS · Crear ticket')

@push('styles')
	<style>
		/* ── POS TPV: pensado tablet-first (landscape), 3 zonas: catálogo · ticket · botonera.
		   Se apila en tablet vertical/móvil. El "verde dinero" (--pos-money) marca el total y el
		   botón Cobrar; el primario del tenant marca filtros/acciones. */
		.pos-wrap {
			display: flex; gap: 1rem; align-items: flex-start;
			--pos-primary: var(--primary, #1d69d6);
			--pos-money: #16a34a; --pos-money-2: #22c55e;
		}

		/* Catálogo: mismo alto completo que la columna de cobro (llega hasta abajo aunque haya
		   pocos productos); la grilla scrollea sola por dentro si desborda. El .card global de
		   NexaDash trae height:calc(100% - 1rem) + margin-bottom pensados para grillas de cards,
		   aquí los pisamos. */
		.pos-catalogo.card {
			flex: 1 1 56%; min-width: 320px; margin-bottom: 0;
			position: sticky; top: 6rem;
			/* !important para ganarle al `.card { height: calc(100% - 1rem) !important }` de
			   app-overrides.css: sin esto la card toma el 100% de .pos-wrap (sin alto fijo) y
			   crece con el contenido en vez de quedar fija con scroll interno en la grilla. */
			height: calc(100vh - 7rem) !important;
			display: flex; flex-direction: column;
		}
		.pos-catalogo .card-body { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }
		/* Columna de cobro: alto completo del viewport (menos el header fijo de 5rem + 1rem arriba
		   y 1rem abajo). El ticket llena el espacio y la botonera queda pegada al fondo siempre,
		   aunque el ticket esté vacío; al desbordar de líneas, scrollea solo la lista interna. */
		.pos-cobro {
			flex: 1 1 40%; min-width: 344px; display: flex; flex-direction: column;  align-items: stretch;
			position: sticky; top: 6rem; height: calc(100vh - 7rem);
		}
		.pos-ticket.card { flex: 1 1 auto; min-width: 0; min-height: 0; height: auto; margin-bottom: 0; display: flex; flex-direction: column; }
		.pos-botonera { display: flex; flex-direction: row; gap: .6rem; align-items: stretch; }
		.pos-botonera .pos-total-card,
		.pos-botonera .pos-bkey,
		.pos-botonera .pos-cobrar { flex: 1 1 0; min-width: 0; }

		@media (max-width: 1199.98px) {
			.pos-wrap { flex-direction: column; }
			.pos-catalogo.card, .pos-cobro { width: 100%; flex-basis: auto; min-width: 0; }
			.pos-catalogo.card, .pos-cobro { position: static; height: auto; }
			.pos-grid { overflow: visible; }
			.pos-lineas-scroll { max-height: 48vh; }
		}
		@media (max-width: 575.98px) {
			.pos-cobro { flex-direction: column; }
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

		.pos-grid {
			display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: .75rem;
			flex: 1 1 auto; min-height: 0; overflow-y: auto; align-content: start;
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
			<div class="pos-wrap">

				{{-- ── Zona 1: catálogo ── --}}
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

				{{-- ── Zonas 2 y 3: ticket + botonera (columna de cobro sticky) ── --}}
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

						<button type="button" class="pos-cobrar" id="pos-emitir" disabled>
							<x-lordicon icon="euro" size="24" trigger="hover" target="#pos-emitir" colors="primary:#ffffff,secondary:#ffffff" />
							<span>Cobrar</span>
						</button>
					</aside>

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
