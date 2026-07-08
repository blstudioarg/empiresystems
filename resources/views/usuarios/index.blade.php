@extends('layouts.app')

@section('title', 'Usuarios')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/css/buttons.dataTables.min.css') }}" rel="stylesheet">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto
		   ("Anterior"/"Siguiente") se rompe en vertical. Dejamos que el ancho
		   se ajuste al texto en una sola línea. */
		#usuarios-table_wrapper .dataTables_paginate .paginate_button.previous,
		#usuarios-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		/* style.css oculta .dt-buttons globalmente (display: none) salvo en .active-projects;
		   lo reactivamos solo dentro de esta tabla para el botón nativo de colVis. */
		#usuarios-table_wrapper .dt-buttons {
			display: inline-block;
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
									<h6 class="mb-1">Total de usuarios</h6>
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
									<h6 class="mb-1">Pendientes de aprobación</h6>
									<h3 class="mb-0" data-metric="pendientes">0</h3>
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
									<h6 class="mb-1">Usuarios activos</h6>
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
							<h4 class="card-title mb-0">Usuarios del tenant</h4>
							<div class="d-flex gap-2 align-items-center">
								<div id="usuarios-colvis"></div>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="usuarios-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre</th>
											<th>Email</th>
											<th>Rol</th>
											<th>Estado</th>
											<th>Rol asignado</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody id="usuarios-table-body"></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Usuarios')
@section('ayuda')
	@include('ayuda.usuarios')
@endsection

@push('scripts')
	<script>
		window.usuariosState = {
			csrfToken: @json(csrf_token()),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/dataTables.buttons.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/js/buttons.colVis.min.js') }}"></script>
	<script src="{{ asset('js/plugins-init/usuarios-datatable.init.js') }}"></script>
@endpush
