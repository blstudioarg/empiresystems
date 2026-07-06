@extends('layouts.app')

@section('title', 'Horarios')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#horarios-table_wrapper .dataTables_paginate .paginate_button.previous,
		#horarios-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		.horario-dia-tramos {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
			align-items: center;
		}

		.horario-tramo-pill {
			display: flex;
			align-items: center;
			gap: 0.25rem;
			background-color: var(--bs-light, #f4f5f9);
			border-radius: 0.375rem;
			padding: 0.25rem 0.5rem;
		}

		.horario-tramo-pill input[type="time"] {
			width: 6.5rem;
			padding: 0.125rem 0.25rem;
		}

		.horario-tramo-pill {
			transition: background-color 200ms ease-out;
		}

		.horario-tramo-pill.horario-tramo-pill-copiado {
			background-color: rgba(13, 110, 253, 0.18);
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h4 class="card-title mb-0">Horarios de trabajo</h4>
							<button type="button" class="btn btn-primary btn-add-horario" data-bs-toggle="modal" data-bs-target="#horarioModal">
								+ Agregar horario
							</button>
						</div>
						<div class="card-body">
							<table id="horarios-table" class="display responsive nowrap w-100">
								<thead>
									<tr>
										<th>Nombre</th>
										<th>Horas/semana</th>
										<th>Asignaciones</th>
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

	<div class="modal fade" id="horarioModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<form id="horario-form" method="POST" action="{{ route('horarios.store') }}">
					@csrf
					<input type="hidden" name="_method" id="horario_method" value="POST">
					<div class="modal-header">
						<h5 class="modal-title" id="horarioModalLabel">Nuevo horario</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="row">
							<div class="col-md-8 mb-2">
								<label class="form-label" for="horario_nombre">Nombre</label>
								<input type="text" name="nombre" id="horario_nombre" class="form-control" required>
								<div class="invalid-feedback" data-error-for="nombre"></div>
							</div>
							<div class="col-md-4 mb-2">
								<div class="form-check form-switch mt-4">
									<input class="form-check-input" type="checkbox" role="switch" id="horario_activo" name="activo" value="1" checked>
									<label class="form-check-label" for="horario_activo">Activo</label>
								</div>
							</div>
						</div>

						<hr>
						<h6>Tramos por día</h6>
						<p class="text-muted small mb-2">Un día sin tramos se interpreta como día libre. Varios tramos el mismo día permiten turnos partidos.</p>

						<div id="horario-dias-container">
							@foreach ([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $numero => $nombreDia)
								<div class="row align-items-start mb-2" data-dia="{{ $numero }}">
									<div class="col-md-2 fw-semibold pt-1">{{ $nombreDia }}</div>
									<div class="col-md-10">
										<div class="horario-dia-tramos" data-dia-tramos="{{ $numero }}"></div>
										<button type="button" class="btn btn-outline-primary btn-add-tramo mt-1" data-dia="{{ $numero }}">+ Añadir tramo</button>
										<button type="button" class="btn btn-outline-secondary btn-copiar-semana mt-1 ms-1" data-dia="{{ $numero }}" title="Copia los tramos de este día a los demás días de la semana">
											Copiar a toda la semana
										</button>
										<div class="invalid-feedback d-block" data-error-for-dia="{{ $numero }}"></div>
									</div>
								</div>
							@endforeach
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

	<template id="horario-tramo-pill-template">
		<div class="horario-tramo-pill">
			<input type="time" class="form-control tramo-hora-inicio" value="09:00">
			<span>—</span>
			<input type="time" class="form-control tramo-hora-fin" value="17:00">
			<button type="button" class="btn-close btn-remove-tramo" aria-label="Quitar tramo"></button>
		</div>
	</template>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/horarios-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/horarios-modal.init.js') }}"></script>
@endpush
