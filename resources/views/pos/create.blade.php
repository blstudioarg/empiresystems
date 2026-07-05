@extends('layouts.app')

@section('title', 'POS · Crear ticket')

@push('styles')
	<style>
		/* Vista POS pensada para tablet/TPV: objetivos táctiles grandes, dos paneles. */
		.pos-wrap { display: flex; gap: 1.25rem; align-items: flex-start; }
		.pos-catalogo { flex: 1 1 60%; }
		/* Especificidad ".pos-ticket.card" a propósito: style.css del template define ".card {
		   position: relative }" y, como carga después de este bloque (ver docs/04-front-guidelines.md
		   §"Overrides de color de NexaDash"), gana por orden de cascada ante ".pos-ticket" sola con la
		   misma especificidad — el sticky no llegaba a aplicarse y el "top" quedaba como offset relative. */
		.pos-ticket.card { flex: 1 1 38%; min-width: 320px; position: sticky; top: 1rem; }
		@media (max-width: 991px) { .pos-wrap { flex-direction: column; } .pos-catalogo, .pos-ticket { width: 100%; flex-basis: auto; min-width: 0; } }

		.pos-search-wrap { position: relative; }
		.pos-search-wrap .pos-search-icon {
			position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9aa0a6; pointer-events: none;
		}
		.pos-search { font-size: 1.05rem; padding: 0.85rem 1rem 0.85rem 2.75rem; border-radius: 0.9rem; }

		.pos-filtros { display: flex; gap: .5rem; margin: 1rem 0 1.25rem; flex-wrap: wrap; }
		.pos-filtro {
			display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--bs-border-color, #e6e6e6);
			background: #fff; border-radius: 2rem; padding: .5rem 1rem; font-weight: 600; font-size: .9rem;
			color: #555; cursor: pointer; transition: background .15s ease, color .15s ease, border-color .15s ease;
		}
		.pos-filtro .badge-count { background: rgba(0,0,0,.06); border-radius: 1rem; padding: .05rem .5rem; font-size: .78rem; }
		.pos-filtro.active { background: var(--primary, #1d69d6); border-color: var(--primary, #1d69d6); color: #fff; }
		.pos-filtro.active .badge-count { background: rgba(255,255,255,.25); color: #fff; }

		.pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.9rem; }
		.pos-articulo {
			position: relative; border: 1px solid var(--bs-border-color, #ebebeb); border-radius: 1rem; background: #fff;
			padding: 1rem 1rem 0.85rem; text-align: left; cursor: pointer;
			transition: transform .08s ease, box-shadow .18s ease, border-color .18s ease;
			min-height: 108px; display: flex; flex-direction: column; justify-content: space-between;
			overflow: hidden;
		}
		.pos-articulo::before {
			content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
			background: var(--pos-accent, #1d69d6); opacity: .85;
		}
		.pos-articulo:hover { box-shadow: 0 8px 20px rgba(20, 30, 60, .10); border-color: transparent; transform: translateY(-2px); }
		.pos-articulo:active { transform: scale(.96); }
		.pos-articulo .cabecera { display: flex; align-items: flex-start; justify-content: space-between; gap: .4rem; }
		.pos-articulo .nombre { font-weight: 650; line-height: 1.25; font-size: .95rem; }
		.pos-articulo .tipo-icon { flex: none; opacity: .8; }
		.pos-articulo .precio-row { display: flex; align-items: baseline; justify-content: space-between; margin-top: .6rem; }
		.pos-articulo .precio { color: var(--primary, #1d69d6); font-weight: 700; font-size: 1.05rem; }
		.pos-articulo .iva { font-size: .72rem; color: #9aa0a6; font-weight: 600; }
		.pos-articulo.just-added { animation: pos-pop .25s ease; }
		@keyframes pos-pop { 0% { box-shadow: 0 0 0 0 rgba(29,105,214,.35); } 100% { box-shadow: 0 0 0 10px rgba(29,105,214,0); } }

		.pos-empty-catalogo { grid-column: 1 / -1; text-align: center; padding: 3rem 1rem; color: #9aa0a6; }

		.pos-ticket .card-header { display: flex; align-items: center; justify-content: space-between; }
		.pos-ticket-count { background: var(--primary, #1d69d6); color: #fff; border-radius: 1rem; font-size: .78rem; font-weight: 700; padding: .1rem .55rem; }

		.pos-lineas-scroll { max-height: 38vh; overflow-y: auto; margin: -.25rem -.25rem 0; padding: 0 .25rem; }
		.pos-linea { display: flex; align-items: center; gap: .6rem; padding: .7rem 0; border-bottom: 1px solid #f2f2f2; }
		.pos-linea:last-child { border-bottom: none; }
		.pos-linea .concepto { flex: 1 1 auto; min-width: 0; }
		.pos-linea .concepto .nombre-linea { font-weight: 600; font-size: .92rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pos-linea .concepto small { color: #9aa0a6; }
		.pos-linea .qty-group { display: flex; align-items: center; gap: .4rem; flex: none; }
		.pos-linea .qty-btn {
			width: 30px; height: 30px; border-radius: 50%; border: 1px solid #e2e2e2; background: #fafbfc;
			font-weight: 700; line-height: 1; display: inline-flex; align-items: center; justify-content: center;
			transition: background .12s ease, transform .08s ease; color: #444;
		}
		.pos-linea .qty-btn:hover { background: #eef2fb; }
		.pos-linea .qty-btn:active { transform: scale(.9); }
		.pos-linea .qty { min-width: 22px; text-align: center; font-weight: 700; }
		.pos-linea .importe { min-width: 76px; text-align: right; font-weight: 700; flex: none; font-size: .92rem; }
		.pos-linea .del {
			color: #c0392b; border: none; background: none; font-size: 1.25rem; line-height: 1; padding: 0 .1rem; flex: none;
			opacity: .55; transition: opacity .12s ease;
		}
		.pos-linea .del:hover { opacity: 1; }

		.pos-empty-ticket { text-align: center; padding: 2.25rem 1rem 1.5rem; color: #9aa0a6; }
		.pos-empty-ticket .lord-icon-wrap { opacity: .55; margin-bottom: .5rem; }

		.pos-resumen { margin-top: 1rem; padding-top: .85rem; border-top: 1px dashed #e6e6e6; }
		.pos-resumen-row { display: flex; justify-content: space-between; font-size: .85rem; color: #777; margin-bottom: .25rem; }
		.pos-total-row { display: flex; align-items: center; justify-content: space-between; margin-top: .4rem; }
		.pos-total-row .label { font-weight: 600; color: #555; }
		.pos-total { font-size: 2rem; font-weight: 800; letter-spacing: -.02em; }

		.pos-tope-alert { display: none; align-items: center; gap: .5rem; }
		.pos-tope-alert.show { display: flex; }

		#pos-emitir { border-radius: .9rem; padding: .9rem; font-weight: 700; font-size: 1.05rem; letter-spacing: .01em; }
		#pos-emitir:disabled { opacity: .45; }

		.pos-receptor-toggle { border-top: 1px solid #f0f0f0; margin-top: 1.1rem; padding-top: 1rem; }
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="pos-wrap">

				<div class="pos-catalogo card">
					<div class="card-body">
						<div class="pos-search-wrap">
							<i class="fas fa-search pos-search-icon"></i>
							<input type="search" id="pos-search" class="form-control pos-search" placeholder="Buscar artículo...">
						</div>

						<div class="pos-filtros" id="pos-filtros">
							<button type="button" class="pos-filtro active" data-filtro="todos">
								Todos <span class="badge-count">{{ $articulos->count() }}</span>
							</button>
							<button type="button" class="pos-filtro" data-filtro="producto">
								<x-lordicon icon="producto" size="18" trigger="hover" />
								Productos <span class="badge-count">{{ $articulos->where('tipo', \App\Enums\TipoArticulo::Producto)->count() }}</span>
							</button>
							<button type="button" class="pos-filtro" data-filtro="servicio">
								<x-lordicon icon="servicio" size="18" trigger="hover" />
								Servicios <span class="badge-count">{{ $articulos->where('tipo', \App\Enums\TipoArticulo::Servicio)->count() }}</span>
							</button>
						</div>

						<div class="pos-grid" id="pos-grid">
							@forelse ($articulos as $articulo)
								<button type="button" class="pos-articulo"
									style="--pos-accent: {{ $articulo->tipo->value === 'servicio' ? '#8a5cf6' : '#1d69d6' }};"
									data-id="{{ $articulo->id }}"
									data-nombre="{{ $articulo->nombre }}"
									data-precio="{{ (float) $articulo->precio }}"
									data-unidad="{{ $articulo->unidad }}"
									data-tipo-impositivo="{{ (float) $articulo->tipo_impositivo }}"
									data-tipo-articulo="{{ $articulo->tipo->value }}">
									<div class="cabecera">
										<span class="nombre">{{ $articulo->nombre }}</span>
										<span class="tipo-icon">
											<x-lordicon icon="{{ $articulo->tipo->value === 'servicio' ? 'servicio' : 'producto' }}" size="20" trigger="hover" target=".pos-articulo" />
										</span>
									</div>
									<div class="precio-row">
										<span class="precio">{{ number_format((float) $articulo->precio, 2, ',', '.') }} €</span>
										<span class="iva">{{ (float) $articulo->tipo_impositivo }}%</span>
									</div>
								</button>
							@empty
								<p class="text-muted">No hay artículos en el catálogo. Añádelos en Productos/Servicios.</p>
							@endforelse
						</div>
						<p class="pos-empty-catalogo d-none" id="pos-empty-catalogo">No hay artículos que coincidan con la búsqueda.</p>
					</div>
				</div>

				<div class="pos-ticket card">
					<div class="card-header">
						<h4 class="card-title mb-0">Ticket</h4>
						<span class="pos-ticket-count d-none" id="pos-ticket-count">0</span>
					</div>
					<div class="card-body">
						<div id="pos-lineas">
							<div class="pos-empty-ticket" id="pos-vacio">
								<div class="lord-icon-wrap">
									<x-lordicon icon="wired-outline-1910-beverages" size="48" trigger="loop-on-hover" />
								</div>
								<p class="mb-0">Toca un artículo para añadirlo.</p>
							</div>
							<div class="pos-lineas-scroll d-none" id="pos-lineas-scroll"></div>
						</div>

						<div class="pos-resumen">
							<div class="pos-total-row">
								<span class="label">Total ({{ $regimen['label'] }} incl.)</span>
								<span class="pos-total" id="pos-total">0,00 €</span>
							</div>
						</div>

						<div class="alert alert-danger mt-3 pos-tope-alert" id="pos-tope-alert">
							<i class="fas fa-exclamation-triangle"></i>
							<span>
								Supera el máximo de una factura simplificada
								(<strong id="pos-tope-valor">{{ number_format($topeAplicable, 2, ',', '.') }}</strong> € {{ $regimen['label'] }} incl.).
								Emite una factura ordinaria.
							</span>
						</div>

						<div class="pos-receptor-toggle">
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="pos-cualificada-toggle">
								<label class="form-check-label" for="pos-cualificada-toggle">Añadir datos del receptor (factura cualificada)</label>
							</div>

							<div id="pos-receptor" class="mt-2" style="display:none;">
								<div class="mb-2">
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
								<input type="text" id="pos-direccion" class="form-control mb-2" placeholder="Domicilio">
							</div>
						</div>

						<button type="button" id="pos-emitir" class="btn btn-primary w-100 mt-3" disabled>
							Emitir ticket
						</button>
					</div>
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
			tope: {{ $topeAplicable }},
			regimen: @json($regimen),
		};
	</script>
	<script src="{{ asset('js/pos-form.js') }}"></script>
@endpush
