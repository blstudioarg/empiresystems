@extends('layouts.app')

@section('title', 'POS · Sala')

@push('styles')
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
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid pos-sala">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
					<h4 class="card-title mb-0">Sala</h4>
					<div class="pos-sala-acciones">
						<button type="button" class="btn btn-light" id="pos-sala-refrescar">
							<i class="fas fa-rotate"></i> Actualizar
						</button>
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
						Todavía no hay mesas configuradas. Créalas en <strong>Configuración → POS</strong>.
					</p>
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
	</script>
	<script src="{{ asset('js/plugins-init/pos-sala.init.js') }}"></script>
@endpush
