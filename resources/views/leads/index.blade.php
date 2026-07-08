@extends('layouts.app')

@section('title', 'Leads')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#leads-table_wrapper .dataTables_paginate .paginate_button.previous,
		#leads-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="leads-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de leads</h6>
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
									<h6 class="mb-1">Sin asignar</h6>
									<h3 class="mb-0" data-metric="sin_asignar">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-424-question-bubble-hover-wiggle" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Cualificados</h6>
									<h3 class="mb-0" data-metric="cualificados">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-267-like-thumb-up-hover-up" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Leads</h4>
							<div class="d-flex gap-2 align-items-center">
								<div class="btn-group" role="group">
									<button type="button" class="btn btn-outline-primary btn-filtro-leads active" data-filtro="todos">Todos</button>
									<button type="button" class="btn btn-outline-primary btn-filtro-leads" data-filtro="mios">Mis leads</button>
									<button type="button" class="btn btn-outline-primary btn-filtro-leads" data-filtro="sin_asignar">Sin asignar</button>
								</div>
								<a href="{{ route('leads.importar.form') }}" class="btn btn-outline-secondary">Importar</a>
								<button type="button" class="btn btn-primary btn-add-lead" data-bs-toggle="modal" data-bs-target="#leadModal">
									+ Agregar lead
								</button>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="leads-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nombre</th>
											<th>Empresa</th>
											<th>Email</th>
											<th>Teléfono</th>
											<th>Estado</th>
											<th>Origen</th>
											<th>Asignado a</th>
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

		<div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<form id="lead-form" method="POST" action="{{ route('leads.store') }}">
						@csrf
						<input type="hidden" name="_method" id="lead_method" value="POST">
						<div class="modal-header">
							<h5 class="modal-title" id="leadModalLabel">Agregar lead</h5>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
						</div>
						<div class="modal-body">
							<div class="row">
								<div class="col-md-12 mb-3">
									<label class="form-label" for="nombre">Nombre *</label>
									<input type="text" class="form-control" id="nombre" name="nombre" required>
									<div class="invalid-feedback d-block" data-error-for="nombre"></div>
								</div>
								<div class="col-md-12 mb-3">
									<label class="form-label" for="empresa">Empresa</label>
									<input type="text" class="form-control" id="empresa" name="empresa">
									<div class="invalid-feedback d-block" data-error-for="empresa"></div>
								</div>
								<div class="col-md-6 mb-3">
									<label class="form-label" for="email">Email</label>
									<input type="email" class="form-control" id="email" name="email">
									<div class="invalid-feedback d-block" data-error-for="email"></div>
								</div>
								<div class="col-md-6 mb-3">
									<label class="form-label" for="telefono">Teléfono</label>
									<input type="text" class="form-control" id="telefono" name="telefono">
									<div class="invalid-feedback d-block" data-error-for="telefono"></div>
								</div>
								<small class="form-text text-muted mb-3">Debes indicar al menos un email o un teléfono.</small>
								<div class="col-md-12 mb-3">
									<label class="form-label" for="asignado_a">Asignar a</label>
									<select class="form-select" id="asignado_a" name="asignado_a">
										<option value="">Según regla de asignación vigente</option>
										@foreach ($comerciales as $comercial)
											<option value="{{ $comercial->id }}">{{ $comercial->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-12">
									<label class="form-label" for="notas">Notas</label>
									<textarea class="form-control" id="notas" name="notas" rows="2"></textarea>
								</div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
							<button type="submit" class="btn btn-primary" data-loading-text="Guardando...">Guardar</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	{{-- Ver ficha: modal alimentado por AJAX (GET /leads/{lead}) — no hay página de detalle
	     propia, todo lo que antes vivía en leads/show se resuelve acá (datos, oportunidades,
	     actividad con alta de notas, convertir en cliente). --}}
	<div class="modal fade" id="leadFichaModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">
						<span id="ficha-nombre"></span>
						<span class="badge bg-primary-light ms-2" id="ficha-estado"></span>
					</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="row gy-2 mb-3">
						<div class="col-md-6">
							<small class="text-muted d-block">Empresa</small>
							<span id="ficha-empresa">—</span>
						</div>
						<div class="col-md-6">
							<small class="text-muted d-block">Origen</small>
							<span id="ficha-origen">—</span>
						</div>
						<div class="col-md-6">
							<small class="text-muted d-block">Email</small>
							<span id="ficha-email">—</span>
						</div>
						<div class="col-md-6">
							<small class="text-muted d-block">Teléfono</small>
							<span id="ficha-telefono">—</span>
						</div>
						<div class="col-md-6">
							<small class="text-muted d-block">Asignado a</small>
							<span id="ficha-asignado">—</span>
						</div>
						<div class="col-md-6 d-none" id="ficha-motivo-wrap">
							<small class="text-muted d-block">Motivo descarte</small>
							<span id="ficha-motivo"></span>
						</div>
					</div>

					<div class="mb-3 d-none" id="ficha-oportunidades-wrap">
						<h6 class="mb-2">Oportunidades</h6>
						<ul class="list-unstyled mb-0" id="ficha-oportunidades-list"></ul>
					</div>

					<hr>

					<h6 class="mb-2">Actividad</h6>
					<form id="ficha-nota-form" class="mb-3">
						<div class="row g-2">
							<div class="col-md-3">
								<select class="form-select" id="ficha-nota-tipo">
									<option value="nota">Nota</option>
									<option value="llamada">Llamada</option>
									<option value="email">Email</option>
									<option value="reunion">Reunión</option>
								</select>
							</div>
							<div class="col-md-7">
								<input type="text" class="form-control" id="ficha-nota-contenido" placeholder="Describe la actividad..." maxlength="500">
							</div>
							<div class="col-md-2">
								<button type="submit" class="btn btn-primary w-100" data-loading-text="...">Añadir</button>
							</div>
						</div>
					</form>

					<ul class="list-group list-group-flush" id="ficha-notas-list" style="max-height: 240px; overflow-y: auto;"></ul>
				</div>
				<div class="modal-footer">
					<a href="#" class="btn btn-outline-primary" id="ficha-nueva-oportunidad">+ Nueva oportunidad</a>
					<button type="button" class="btn btn-primary d-none" id="ficha-btn-convertir" data-bs-toggle="modal" data-bs-target="#convertirModal">Convertir en cliente</button>
					<a href="{{ route('clientes.index') }}" class="btn btn-outline-primary d-none" id="ficha-link-cliente">Ver cliente convertido</a>
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="convertirModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<form method="POST" id="convertir-form" class="modal-content">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Convertir en cliente</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p>Se creará un cliente nuevo a partir de los datos de este lead. Puedes completar datos fiscales adicionales:</p>
					<div class="mb-3">
						<label class="form-label" for="conv_nif">NIF (opcional)</label>
						<input type="text" class="form-control" id="conv_nif" name="nif">
					</div>
					<div class="mb-3">
						<label class="form-label" for="conv_direccion">Dirección (opcional)</label>
						<input type="text" class="form-control" id="conv_direccion" name="direccion">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">Convertir</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		window.leadFormState = {
			storeUrl: @json(route('leads.store')),
			indexUrl: @json(route('leads.index')),
		};
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/leads-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/leads-modal.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/leads-ficha.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Leads')
@section('ayuda')
	@include('ayuda.leads')
@endsection
