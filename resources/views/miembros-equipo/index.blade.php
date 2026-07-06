@extends('layouts.app')

@section('title', 'Miembros de equipo')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/leaflet/leaflet.css') }}" rel="stylesheet">
	<style>
		#miembros-equipo-table_wrapper .dataTables_paginate .paginate_button.previous,
		#miembros-equipo-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		.miembro-mapa {
			height: 240px;
			border-radius: 0.5rem;
		}

		.geocoder-wrap {
			position: relative;
		}

		.geocoder-suggestions {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			z-index: 1060;
			max-height: 220px;
			overflow-y: auto;
			margin-top: 2px;
			background-color: #fff;
			border-radius: 0.5rem;
			box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
		}

		.geocoder-item {
			cursor: pointer;
			background-color: #fff;
		}

		.geocoder-item:hover {
			background-color: #f4f5f9;
		}
</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="miembros-equipo-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de miembros</h6>
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
									<h6 class="mb-1">Activos</h6>
									<h3 class="mb-0" data-metric="activos">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Con ubicación de trabajo</h6>
									<h3 class="mb-0" data-metric="con_ubicacion">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-27-globe-hover-rotate" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h4 class="card-title mb-0">Miembros de equipo</h4>
							<button type="button" class="btn btn-primary btn-add-miembro-equipo" data-bs-toggle="modal" data-bs-target="#miembroEquipoModal">
								+ Agregar miembro
							</button>
						</div>
						<div class="card-body">
							<table id="miembros-equipo-table" class="display responsive nowrap w-100">
								<thead>
									<tr>
										<th>Nombre</th>
										<th>Puesto</th>
										<th>Distancia máx.</th>
										<th>Distancia casa-trabajo</th>
										<th>Estado</th>
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

	<div class="modal fade" id="miembroEquipoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<form id="miembro-equipo-form" method="POST" action="{{ route('miembros-equipo.store') }}">
					@csrf
					<input type="hidden" name="_method" id="miembro_method" value="POST">
					<div class="modal-header">
						<h5 class="modal-title" id="miembroEquipoModalLabel">Nuevo miembro</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="col-md-6 mb-2">
								<label class="form-label" for="miembro_user_id">Usuario</label>
								<select name="user_id" id="miembro_user_id" class="form-select" required></select>
								<div class="invalid-feedback" data-error-for="user_id"></div>
							</div>
							<div class="col-md-6 mb-2">
								<label class="form-label" for="miembro_puesto">Puesto</label>
								<input type="text" name="puesto" id="miembro_puesto" class="form-control">
								<div class="invalid-feedback" data-error-for="puesto"></div>
							</div>
						</div>

						<hr>
						<h6>Ubicación de trabajo</h6>
						<p class="text-muted small mb-2">Escribí la dirección para buscarla y ubicarla en el mapa, o hacé clic directamente en el mapa para fijar las coordenadas.</p>

						<div class="row">
							<div class="col-md-8">
								<div id="mapa-trabajo-modal" class="miembro-mapa mb-2"></div>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="miembro_trabajo_direccion">Dirección</label>
								<input type="text" name="trabajo_direccion" id="miembro_trabajo_direccion" class="form-control mb-2">

								<label class="form-label" for="miembro_distancia_max_metros">Distancia máxima (m)</label>
								<input type="number" min="1" name="distancia_max_metros" id="miembro_distancia_max_metros" class="form-control mb-2" value="100" required>
								<div class="invalid-feedback" data-error-for="distancia_max_metros"></div>

								<input type="hidden" name="trabajo_latitud" id="miembro_trabajo_latitud">
								<input type="hidden" name="trabajo_longitud" id="miembro_trabajo_longitud">
								<div class="small text-muted" id="trabajo-coords-info-modal"></div>
							</div>
						</div>

						<hr>
						<h6>Dirección de casa <span class="text-muted fw-normal">(opcional, solo para calcular distancia casa-trabajo)</span></h6>

						<div class="row">
							<div class="col-md-8">
								<div id="mapa-casa-modal" class="miembro-mapa mb-2"></div>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="miembro_casa_direccion">Dirección</label>
								<input type="text" name="casa_direccion" id="miembro_casa_direccion" class="form-control mb-2">

								<input type="hidden" name="casa_latitud" id="miembro_casa_latitud">
								<input type="hidden" name="casa_longitud" id="miembro_casa_longitud">
								<div class="small text-muted" id="casa-coords-info-modal"></div>
							</div>
						</div>

						<hr>
						<h6>Horario asignado</h6>
						<div id="miembro-horario-vigente" class="small text-muted mb-2">Guardá el miembro para poder asignarle un horario.</div>

						<div class="row g-2 align-items-end mb-2" id="miembro-horario-asignar" style="display: none;">
							<div class="col-md-6">
								<label class="form-label" for="miembro_horario_id">Asignar horario</label>
								<select id="miembro_horario_id" class="form-select">
									<option value="">— Selecciona un horario —</option>
								</select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="miembro_horario_vigente_desde">Vigente desde</label>
								<input type="date" id="miembro_horario_vigente_desde" class="form-control">
							</div>
							<div class="col-md-2">
								<button type="button" class="btn btn-outline-primary w-100" id="btn-asignar-horario">Asignar</button>
							</div>
							<div class="invalid-feedback d-block" data-error-for="horario_id"></div>
						</div>

						<div class="table-responsive" id="miembro-horario-historico-wrap" style="display: none;">
							<table class="table table-sm mb-0">
								<thead>
									<tr>
										<th>Horario</th>
										<th>Desde</th>
										<th>Hasta</th>
										<th></th>
									</tr>
								</thead>
								<tbody id="miembro-horario-historico"></tbody>
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar</button>
					</div>
				</form>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
	<script src="{{ asset('js/plugins-init/miembro-mapa.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/miembros-equipo-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/miembros-equipo-modal.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/miembro-horario.init.js') }}"></script>
@endpush
