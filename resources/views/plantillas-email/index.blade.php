@extends('layouts.app')

@section('title', 'Plantillas de email')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#plantillas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#plantillas-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
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
									<h6 class="mb-1">Plantillas</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="invoice" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Activas</h6>
									<h3 class="mb-0" data-metric="activas">0</h3>
								</div>
								<div>
									<x-lordicon icon="system-regular-48-favorite-heart" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Inactivas</h6>
									<h3 class="mb-0" data-metric="inactivas">0</h3>
								</div>
								<div>
									<x-lordicon icon="system-regular-28-info" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Plantillas de email</h4>
							<button type="button" class="btn btn-primary btn-add-plantilla" data-bs-toggle="modal" data-bs-target="#plantillaModal">
								+ Nueva plantilla
							</button>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="plantillas-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Título</th>
											<th>Asunto</th>
											<th>Estado</th>
											<th>Modificado</th>
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

		<div class="modal fade" id="plantillaModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
					<form id="plantilla-form" method="POST" action="{{ route('plantillas-email.store') }}">
						@csrf
						<input type="hidden" name="_method" id="plantilla_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="plantillaModalLabel">Nueva plantilla</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">
							<div class="row">
								<div class="col-12">
									<label class="form-label" for="plantilla_titulo">Título</label>
									<input type="text" class="form-control" id="plantilla_titulo" name="titulo" maxlength="150">
									<div class="invalid-feedback d-block" data-error-for="titulo"></div>
								</div>
								<div class="col-12">
									<label class="form-label" for="plantilla_asunto">Asunto</label>
									<input type="text" class="form-control" id="plantilla_asunto" name="asunto" maxlength="255">
									<div class="invalid-feedback d-block" data-error-for="asunto"></div>
								</div>
								<div class="col-12">
									<label class="form-label" for="plantilla_cuerpo">Cuerpo</label>
									<textarea class="form-control" id="plantilla_cuerpo" name="cuerpo" rows="10" placeholder="Contenido del correo. Puedes usar HTML."></textarea>
									<div class="invalid-feedback d-block" data-error-for="cuerpo"></div>
								</div>
								<div class="col-12">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" id="plantilla_activa" name="activa" value="1" checked>
										<label class="form-check-label" for="plantilla_activa">Activa (disponible al crear campañas)</label>
									</div>
								</div>
							</div>
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

@section('ayuda-titulo', 'Plantillas de email')
@section('ayuda')
	@include('ayuda.plantillas-email')
@endsection

@push('scripts')
	<script>
		window.plantillaFormState = {
			storeUrl: @json(route('plantillas-email.store')),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/plantillas-email-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/plantillas-email-modal.init.js') }}"></script>
@endpush
