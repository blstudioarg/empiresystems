@extends('layouts.app')

@section('title', 'Roles')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		#roles-table_wrapper .dataTables_paginate .paginate_button.previous,
		#roles-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		#roles-table_wrapper .dt-buttons {
			display: inline-block;
		}

		/* Checklist de permisos agrupada por módulo: tarjetas compactas en grid de 2 columnas
		   en vez de un acordeón (todo visible de un vistazo para comparar roles) o una pared
		   plana de 17 checkboxes (agrupar da estructura de lectura). */
		.rol-permisos-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 0.75rem;
		}

		@media (max-width: 575.98px) {
			.rol-permisos-grid {
				grid-template-columns: 1fr;
			}
		}

		.rol-modulo-group {
			border: 1px solid var(--bs-border-color, #E4E4E4);
			border-radius: 0.5rem;
			padding: 0.75rem;
		}

		.rol-modulo-group__header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 0.375rem;
			padding-bottom: 0.375rem;
			border-bottom: 1px solid var(--bs-border-color, #E4E4E4);
		}

		.rol-modulo-group__nombre {
			font-size: 0.8125rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.02em;
			color: var(--bs-secondary-color, #6F767E);
		}

		.rol-modulo-group .form-check {
			margin-bottom: 0.25rem;
		}

		.rol-modulo-group .form-check:last-child {
			margin-bottom: 0;
		}

		.rol-modulo-group__master {
			cursor: pointer;
		}

		[data-theme="dark"] .rol-modulo-group,
		[data-theme="dark"] .rol-modulo-group__header {
			border-color: var(--bs-border-color-translucent, rgba(255, 255, 255, 0.1));
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Roles del tenant</h6>
									<h3 class="mb-0" data-metric="roles">0</h3>
								</div>
								<div>
									<x-lordicon icon="person" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Usuarios con rol asignado</h6>
									<h3 class="mb-0" data-metric="usuarios_con_rol">0</h3>
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
									<h6 class="mb-1">Permisos del catálogo</h6>
									<h3 class="mb-0" data-metric="permisos_catalogo">0</h3>
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
							<h4 class="card-title mb-0">Roles</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="roles-colvis"></div>
								<button type="button" class="btn btn-primary btn-add-rol" data-bs-toggle="modal" data-bs-target="#rolModal">
									+ Agregar rol
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="roles-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre</th>
											<th>Permisos</th>
											<th>Usuarios</th>
											<th>Rol por defecto</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="roles-table-body"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		@include('roles._modales')
	</div>
@endsection

@push('scripts')
	<script>
		window.rolesState = {
			indexUrl: @json(route('roles.index')),
			storeUrl: @json(route('roles.store')),
			updateUrlTemplate: @json(route('roles.update', ['rol' => '__ID__'])),
			deleteUrlTemplate: @json(route('roles.destroy', ['rol' => '__ID__'])),
			defectoUrlTemplate: @json(route('roles.defecto.update', ['rol' => '__ID__'])),
			catalogo: @json(\App\Support\CatalogoPermisos::porModulo()),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/roles.init.js') }}"></script>
@endpush
