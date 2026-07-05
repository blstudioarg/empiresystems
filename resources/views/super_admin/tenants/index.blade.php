@extends('layouts.app')

@section('title', 'Tenants')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		#tenants-table_wrapper .dataTables_paginate .paginate_button.previous,
		#tenants-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		#tenants-table_wrapper .dt-buttons {
			display: inline-block;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div class="row">
				<div class="col-xl-6 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de tenants</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="empresa" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-6 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Tenants activos</h6>
									<h3 class="mb-0" data-metric="activos">0</h3>
								</div>
								<div>
									<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Tenants</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="tenants-colvis"></div>
								<button type="button" class="btn btn-primary btn-add-tenant" data-bs-toggle="modal" data-bs-target="#tenantModal">
									+ Agregar tenant
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="tenants-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre comercial</th>
											<th>Dominio</th>
											<th>NIF</th>
											<th>Estado</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="tenants-table-body"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="tenantModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
					<form id="tenant-form" method="POST" action="{{ route('super_admin.tenants.store') }}">
						@csrf
						<input type="hidden" name="_method" id="tenant_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="tenantModalLabel">Agregar tenant</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							@include('super_admin.tenants._form')
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
		window.tenantFormState = {
			storeUrl: @json(route('super_admin.tenants.store')),
			updateUrlTemplate: @json(route('super_admin.tenants.update', ['tenant' => '__ID__'])),
			localidadesUrl: @json(route('localidades.index')),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/super-admin-tenants-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/super-admin-tenants-modal.init.js') }}"></script>
@endpush
