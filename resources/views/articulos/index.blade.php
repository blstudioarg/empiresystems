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
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<form id="articulo-form" method="POST" action="{{ route('articulos.store') }}">
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
	</div>
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
@endpush
