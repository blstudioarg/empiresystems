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

		@media (prefers-reduced-motion: reduce) {
			.pos-mesa { transition: none; }
		}

		/* ── Plano arrastrable (feature 039): rejilla fija de 8×6 celdas por zona. Se posiciona
		   con `position: absolute` (no CSS grid) porque jQuery UI `draggable` con `grid: [w,h]`
		   calcula en píxeles: mover a CSS grid perdería el snap directo a celda. */
		.pos-plano-wrap { display: none; }
		.pos-plano-wrap.activo { display: block; }

		.pos-plano-toolbar { display: flex; justify-content: space-between; align-items: center; gap: .6rem; flex-wrap: wrap; margin-bottom: .8rem; }
		.pos-plano-hint { font-size: .82rem; color: #8b93a1; }

		.pos-plano-canvas-scroll { position: relative; overflow-x: auto; padding-bottom: .5rem; }
		.pos-plano-canvas {
			position: relative;
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

		.plano-mesa.forma-redonda { border-radius: 50%; }
		.plano-mesa.forma-cuadrada { border-radius: .6rem; }
		.plano-mesa.forma-rectangular { border-radius: .5rem; }
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

		@media (prefers-reduced-motion: reduce) {
			.plano-mesa { transition: none; }
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid pos-sala">
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

			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<h4 class="card-title mb-0">Sala</h4>
					<div class="pos-sala-acciones">
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

					<p class="pos-sala-vacia d-none" id="pos-sala-vacia">
						Todavía no hay mesas configuradas. Créalas con el botón <strong>Editar plano</strong> de arriba.
					</p>

					<div class="pos-plano-wrap" id="pos-plano-wrap">
						<div class="pos-plano-toolbar">
							<span class="pos-plano-hint">Arrastra las mesas por el asa <i class="fas fa-arrows-up-down-left-right"></i> para reordenarlas. Toca una mesa para cambiar su forma y tamaño.</span>
						</div>
						<div class="pos-plano-body">
							<div class="pos-plano-body-izq">
								<div class="pos-plano-canvas-scroll">
									<div class="pos-plano-canvas" id="pos-plano-canvas"></div>

									<div class="plano-popover" id="plano-popover">
										<div class="plano-popover-titulo">Forma</div>
										<div class="plano-popover-opciones" id="plano-popover-formas"></div>
										<div class="plano-popover-titulo">Tamaño</div>
										<div class="plano-popover-opciones" id="plano-popover-tamanos"></div>
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
	<script src="{{ asset('js/plugins-init/pos-sala.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/pos-sala-plano.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/pos-sala-plano-gestion.init.js') }}"></script>
@endpush
