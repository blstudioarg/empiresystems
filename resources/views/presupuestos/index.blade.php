@extends('layouts.app')

@section('title', 'Presupuestos')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#presupuestos-table_wrapper .dataTables_paginate .paginate_button.previous,
		#presupuestos-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="presupuestos-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de presupuestos</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-979-project-estimate-hover-pinch" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Pendientes de respuesta</h6>
									<h3 class="mb-0" data-metric="pendientes">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-177-envelope-send-hover-flying" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Aceptado, por facturar</h6>
									<h3 class="mb-0" data-metric="importe_aceptado">0,00 €</h3>
								</div>
								<div>
									<x-lordicon icon="euro" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Presupuestos</h4>
							<a href="{{ route('presupuestos.create') }}" class="btn btn-primary">+ Nuevo presupuesto</a>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="presupuestos-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Número</th>
											<th>Receptor</th>
											<th>Estado</th>
											<th>Fecha emisión</th>
											<th>Total</th>
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
	</div>

	{{-- Ver: mismo patrón que facturas (facturaPdfModal) — el presupuesto se visualiza siempre
	     como el PDF que va a recibir el cliente, no una página de detalle aparte. --}}
	<div class="modal fade" id="presupuestoPdfModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vista previa del presupuesto</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="presupuestoPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="enviarPresupuestoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form id="enviarPresupuestoForm" method="POST" class="modal-content">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Enviar presupuesto por email</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="enviarPresupuestoDestinatario" class="form-label">Destinatario</label>
						<input type="email" id="enviarPresupuestoDestinatario" name="destinatario" class="form-control" required>
						<div class="invalid-feedback d-block" data-error-for="destinatario"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="enviarPresupuestoSubmit" data-loading-text="Enviando...">Enviar</button>
				</div>
			</form>
		</div>
	</div>

	{{-- Cambiar estado: un único modal cuyo contenido (qué botones mostrar) se arma en JS según
	     el estado actual de la fila que lo disparó — evita repetir 3 modales casi iguales. --}}
	<div class="modal fade" id="estadoPresupuestoModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Cambiar estado — <span id="estadoPresupuestoNumero"></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body d-flex flex-column gap-2" id="estadoPresupuestoBody">
					<button type="button" class="btn btn-primary w-100" id="estadoBtnEnviado" data-loading-text="...">Marcar como enviado</button>
					<button type="button" class="btn btn-success w-100" id="estadoBtnAceptado" data-loading-text="...">Marcar como aceptado</button>
					<button type="button" class="btn btn-outline-danger w-100" id="estadoBtnRechazado" data-loading-text="...">Marcar como rechazado</button>
					<form method="POST" id="estadoFormConvertir">
						@csrf
						<button type="submit" class="btn btn-success w-100">Convertir a factura</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<script>
		window.presupuestosIndexUrl = @json(route('presupuestos.index'));
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/presupuestos-datatable.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Presupuestos')
@section('ayuda')
	@include('ayuda.presupuestos')
@endsection
