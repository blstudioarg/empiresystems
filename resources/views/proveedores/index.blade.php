@extends('layouts.app')

@section('title', 'Proveedores')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		#proveedores-table_wrapper .dataTables_paginate .paginate_button.previous,
		#proveedores-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		#proveedores-table_wrapper .dt-buttons {
			display: inline-block;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="proveedores-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de proveedores</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="empresa" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Proveedores</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="proveedores-colvis"></div>
								<button type="button" class="btn btn-primary btn-add-proveedor" data-bs-toggle="modal" data-bs-target="#proveedorModal">
									+ Agregar proveedor
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="proveedores-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre / Razón social</th>
											<th>NIF</th>
											<th>Email</th>
											<th>Teléfono</th>
											<th>Ciudad</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="proveedorModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
					<form id="proveedor-form" method="POST" action="{{ route('proveedores.store') }}">
						@csrf
						<input type="hidden" name="_method" id="proveedor_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="proveedorModalLabel">Agregar proveedor</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							@include('proveedores._form')
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
		window.proveedorFormState = {
			storeUrl: @json(route('proveedores.store')),
			updateUrlTemplate: @json(route('proveedores.update', ['proveedor' => '__ID__'])),
			destroyUrlTemplate: @json(route('proveedores.destroy', ['proveedor' => '__ID__'])),
			localidadesUrl: @json(route('localidades.index')),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/proveedores-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/proveedores-modal.init.js') }}"></script>
@endpush
