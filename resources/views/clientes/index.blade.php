@extends('layouts.app')

@section('title', 'Clientes')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto
		   ("Anterior"/"Siguiente") se rompe en vertical. Dejamos que el ancho
		   se ajuste al texto en una sola línea. */
		#clientes-table_wrapper .dataTables_paginate .paginate_button.previous,
		#clientes-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		/* style.css oculta .dt-buttons globalmente (display: none) salvo en .active-projects;
		   lo reactivamos solo dentro de esta tabla para el botón nativo de colVis. */
		#clientes-table_wrapper .dt-buttons {
			display: inline-block;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
	

		<div class="container-fluid">

			<div id="clientes-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de clientes</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="people" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Clientes empresa</h6>
									<h3 class="mb-0" data-metric="empresas">0</h3>
								</div>
								<div>
									<x-lordicon icon="empresa" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Clientes particulares</h6>
									<h3 class="mb-0" data-metric="particulares">0</h3>
								</div>
								<div>
									<x-lordicon icon="person" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Cartera de clientes</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="clientes-colvis"></div>
								<button type="button" class="btn btn-primary btn-add-cliente" data-bs-toggle="modal" data-bs-target="#clienteModal">
									+ Agregar cliente
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="clientes-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre / Razón social</th>
											<th>Tipo</th>
											<th>NIF</th>
											<th>Email</th>
											<th>Teléfono</th>
											<th>Ciudad</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="clientes-table-body"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="clienteModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
					<form id="cliente-form" method="POST" action="{{ route('clientes.store') }}">
						@csrf
						<input type="hidden" name="_method" id="cliente_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="clienteModalLabel">Agregar cliente</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							@include('clientes._form')
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

@section('ayuda-titulo', 'Clientes')
@section('ayuda')
	@include('ayuda.clientes')
@endsection

@push('scripts')
	<script>
		window.clienteFormState = {
			storeUrl: @json(route('clientes.store')),
			updateUrlTemplate: @json(route('clientes.update', ['cliente' => '__ID__'])),
			destroyUrlTemplate: @json(route('clientes.destroy', ['cliente' => '__ID__'])),
			localidadesUrl: @json(route('localidades.index')),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/clientes-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/clientes-modal.init.js') }}"></script>
@endpush
