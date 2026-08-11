@extends('layouts.app')

@section('title', 'Productos/Servicios')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		#articulos-table_wrapper .dataTables_paginate .paginate_button.previous,
		#articulos-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		#articulos-table_wrapper .dt-buttons {
			display: inline-block;
		}

		.campos-stock[hidden] {
			display: none !important;
		}

		/* ── Opciones de artículo (hostelería, feature 038) ──────────────────
		   Panel propio dentro del modal de alta/edición, separado visualmente de los campos de
		   catálogo de arriba con un tinte del color de marca. Los chips de grupo reutilizan el
		   lenguaje visual de `.pos-filtro` (Sala/catálogo del POS) en vez de inventar un tag
		   nuevo — mismo sistema en toda la feature, no una pantalla aparte. */
		.pos-opts {
			margin-top: 1.25rem; padding: 1rem 1.1rem 1.1rem; border-radius: 1rem;
			background: color-mix(in srgb, var(--primary, #1d69d6) 4%, #fff);
			border: 1px solid color-mix(in srgb, var(--primary, #1d69d6) 14%, #e6e6e6);
		}
		.pos-opts-head { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: 1rem; }
		.pos-opts-icon {
			flex: none; width: 2.25rem; height: 2.25rem; border-radius: .75rem;
			background: color-mix(in srgb, var(--primary, #1d69d6) 14%, #fff);
			color: var(--primary, #1d69d6); display: flex; align-items: center; justify-content: center;
			font-size: 1rem;
		}
		.pos-opts-title { margin: 0 0 .15rem; font-size: .95rem; font-weight: 700; color: #2b2f36; }
		.pos-opts-sub { margin: 0; font-size: .8rem; color: #6b7280; line-height: 1.4; }

		.pos-opts-section { margin-bottom: 1.1rem; }
		.pos-opts-section:last-of-type { margin-bottom: 0; }
		.pos-opts-section-label {
			font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
			color: #8a8f98; margin-bottom: .55rem;
		}

		/* Chips de grupo asignado. */
		.pos-opts-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .6rem; min-height: 1px; }
		.pos-opts-chip {
			display: inline-flex; align-items: center; gap: .45rem;
			background: var(--primary, #1d69d6); color: #fff; border-radius: 1rem;
			padding: .35rem .8rem; font-size: .82rem; font-weight: 650;
			animation: pos-opts-in 180ms cubic-bezier(0.23, 1, 0.32, 1);
		}

		.pos-opts-empty { font-size: .8rem; color: #9aa0a6; font-style: italic; padding: .3rem 0; }

		/* Filas de opción: lista de tarjetas en vez de <table> desnuda. */
		.pos-opts-list { display: flex; flex-direction: column; gap: .4rem; margin-bottom: .6rem; }
		.pos-opts-row {
			display: flex; align-items: center; gap: .7rem; padding: .55rem .7rem;
			background: #fff; border: 1px solid #e9ebef; border-radius: .7rem;
			animation: pos-opts-in 180ms cubic-bezier(0.23, 1, 0.32, 1);
		}
		.pos-opts-row-info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
		.pos-opts-row-name { font-weight: 650; font-size: .86rem; color: #2b2f36; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.pos-opts-row-group { font-size: .72rem; color: #9aa0a6; }
		.pos-opts-row-price { flex: none; display: flex; align-items: center; gap: .3rem; }
		.pos-opts-row-price input {
			width: 70px; border: 1px solid #dfe2e8; border-radius: .5rem; padding: .3rem .4rem;
			font-weight: 650; font-size: .84rem; text-align: right; font-variant-numeric: tabular-nums;
			transition: border-color 120ms ease, box-shadow 120ms ease;
		}
		.pos-opts-row-price input:focus {
			outline: none; border-color: var(--primary, #1d69d6);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #1d69d6) 15%, transparent);
		}
		.pos-opts-row-price span { font-size: .78rem; color: #9aa0a6; }
		.pos-opts-row-remove {
			flex: none; width: 30px; height: 30px; border-radius: .55rem; border: none;
			background: #fdf1f0; color: #c0392b; display: inline-flex; align-items: center; justify-content: center;
			font-size: .8rem; transition: background 120ms ease, transform 120ms ease;
			-webkit-tap-highlight-color: transparent;
		}
		.pos-opts-row-remove:hover { background: #f9dedb; }
		.pos-opts-row-remove:active { transform: scale(.9); }

		/* Controles de "añadir": select + (precio) + botón, en una tira compacta. */
		.pos-opts-add { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
		.pos-opts-select {
			flex: 1 1 200px; min-width: 160px; border: 1.5px dashed #d7dae0; border-radius: .65rem;
			padding: .5rem .7rem; font-size: .84rem; color: #6b7280; background: #fff;
			transition: border-color 140ms ease, color 140ms ease;
		}
		.pos-opts-select:hover { border-color: var(--primary, #1d69d6); color: var(--primary, #1d69d6); }
		.pos-opts-select:focus { outline: none; border-style: solid; border-color: var(--primary, #1d69d6); color: #2b2f36; }
		.pos-opts-add-opcion .pos-opts-select { flex-basis: 220px; }
		.pos-opts-price-input {
			flex: none; display: flex; align-items: center; gap: .25rem; border: 1px solid #dfe2e8;
			border-radius: .65rem; padding: .4rem .6rem; background: #fff; margin: 0;
		}
		.pos-opts-price-input input {
			width: 64px; border: none; padding: 0; font-weight: 650; font-size: .84rem;
			text-align: right; font-variant-numeric: tabular-nums;
		}
		.pos-opts-price-input input:focus { outline: none; }
		.pos-opts-price-input span { font-size: .78rem; color: #9aa0a6; }
		.pos-opts-btn-add {
			flex: none; width: 38px; height: 38px; border-radius: .65rem; border: none;
			background: var(--primary, #1d69d6); color: #fff; font-size: .9rem;
			display: inline-flex; align-items: center; justify-content: center;
			transition: transform 120ms ease, box-shadow 140ms ease;
			box-shadow: 0 3px 8px color-mix(in srgb, var(--primary, #1d69d6) 35%, transparent);
		}
		.pos-opts-btn-add:hover { box-shadow: 0 5px 12px color-mix(in srgb, var(--primary, #1d69d6) 45%, transparent); }
		.pos-opts-btn-add:active { transform: scale(.93); }
		.pos-opts-btn-add:disabled { background: #c9ccd1; box-shadow: none; cursor: not-allowed; }

		.pos-opts-footer { display: flex; align-items: center; gap: .75rem; margin-top: 1rem; padding-top: .9rem; border-top: 1px dashed #e6e6e6; flex-wrap: wrap; }
		.pos-opts-save { display: inline-flex; align-items: center; gap: .45rem; transition: transform 160ms ease-out; }
		.pos-opts-save:active:not(:disabled) { transform: scale(.97); }
		.pos-opts-footer-hint { font-size: .74rem; color: #9aa0a6; }

		@keyframes pos-opts-in { from { opacity: 0; transform: translateY(4px) scale(.98); } to { opacity: 1; transform: none; } }

		@media (prefers-reduced-motion: reduce) {
			.pos-opts-chip, .pos-opts-row { animation: none; }
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="articulos-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de artículos</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Productos</h6>
									<h3 class="mb-0" data-metric="productos">0</h3>
								</div>
								<div>
									<x-lordicon icon="producto" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Servicios</h6>
									<h3 class="mb-0" data-metric="servicios">0</h3>
								</div>
								<div>
									<x-lordicon icon="servicio" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">Catálogo de productos/servicios</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="articulos-colvis"></div>
								<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importarModal">Importar</button>
								<button type="button" id="btn-exportar-articulos" class="btn btn-outline-secondary">Exportar</button>
								<button type="button" class="btn btn-primary btn-add-articulo" data-bs-toggle="modal" data-bs-target="#articuloModal">
									+ Agregar artículo
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="articulos-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Código</th>
											<th>Nombre</th>
											<th>Tipo</th>
											<th>Precio</th>
											<th>Tipo impositivo</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="articulos-table-body"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="articuloModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
					<form id="articulo-form" method="POST" action="{{ route('articulos.store') }}" enctype="multipart/form-data">
						@csrf
						<input type="hidden" name="_method" id="articulo_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="articuloModalLabel">Agregar artículo</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							@include('articulos._form')
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-primary">Guardar</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		@include('excel._importar_modal', ['modulo' => 'articulos', 'etiqueta' => 'artículos'])
	</div>
@endsection

@section('ayuda-titulo', 'Catálogo de artículos')
@section('ayuda')
	@include('ayuda.articulos')
@endsection

@push('scripts')
	<script>
		window.articuloFormState = {
			indexUrl: @json(route('articulos.index')),
			storeUrl: @json(route('articulos.store')),
			updateUrlTemplate: @json(route('articulos.update', ['articulo' => '__ID__'])),
			destroyUrlTemplate: @json(route('articulos.destroy', ['articulo' => '__ID__'])),
			tiposImpositivosValidos: @json(\App\Support\TiposImpositivos::validosPara(tenant()->regimen_impositivo)),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/articulos-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/articulos-modal.init.js') }}"></script>
	@if ($opcionesActivas ?? false)
		<script src="{{ asset('js/plugins-init/pos-articulo-opciones.init.js') }}"></script>
	@endif
	<script src="{{ asset('js/plugins-init/excel-export.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/excel-importar-modal.init.js') }}"></script>
	<script>
		window.initExportacionExcel({
			boton: '#btn-exportar-articulos',
			url: @json(route('articulos.exportar', ['modulo' => 'articulos'])),
			table: function () { return $('#articulos-table').DataTable(); },
		});
		window.initImportacionModal({
			previsualizarUrl: @json(route('articulos.importar.previsualizar', ['modulo' => 'articulos'])),
			confirmarUrl: @json(route('articulos.importar.confirmar', ['modulo' => 'articulos'])),
			tabla: '#articulos-table',
		});
	</script>
@endpush
