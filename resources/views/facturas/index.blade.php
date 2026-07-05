@extends('layouts.app')

@section('title', 'Facturas')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto
		   ("Anterior"/"Siguiente") se rompe en vertical. Dejamos que el ancho
		   se ajuste al texto en una sola línea (mismo fix que clientes/articulos). */
		#facturas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#facturas-table_wrapper .dataTables_paginate .paginate_button.next,
		#cobros-table_wrapper .dataTables_paginate .paginate_button.previous,
		#cobros-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="facturas-cards" class="row">
				<div class="col-xl-6 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de facturas</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="invoice" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Importe total</h6>
									<h3 class="mb-0" data-metric="importe_total">0,00 €</h3>
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
							<h4 class="card-title mb-0">Facturas</h4>
							<a href="{{ route('facturas.create') }}" class="btn btn-primary">
								+ Nueva factura
							</a>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="facturas-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nº</th>
											<th>Cliente</th>
											<th>Fecha</th>
											<th>Total</th>
											<th>Estado</th>
											<th>Cobro</th>
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

	<div class="modal fade" id="facturaPdfModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vista previa de la factura</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="facturaPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="cobrosModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Cobros de la factura</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<p class="mb-2">Saldo pendiente: <strong id="cobroSaldoPendiente">0,00</strong> €</p>

					<div class="table-responsive mb-3">
						<table id="cobros-table" class="table table-sm display responsive nowrap w-100">
							<thead>
								<tr>
									<th>Fecha</th>
									<th>Método</th>
									<th>Referencia</th>
									<th class="text-end">Importe</th>
									<th>Estado</th>
									<th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<form id="registrarCobroForm">
						@csrf
						<h6 class="mb-2">Registrar nuevo cobro</h6>
						<div class="mb-3">
							<label for="cobroFecha" class="form-label">Fecha</label>
							<input type="date" id="cobroFecha" name="fecha" class="form-control" required>
						</div>
						<div class="mb-3">
							<label for="cobroImporte" class="form-label">Importe</label>
							<div class="input-group">
								<input type="number" id="cobroImporte" name="importe" class="form-control" step="0.01" min="0.01" required>
								<button type="button" id="cobroPagarRestante" class="btn btn-outline-secondary">Pagar restante</button>
							</div>
						</div>
						<div class="mb-3">
							<label for="cobroMetodo" class="form-label">Método</label>
							<select id="cobroMetodo" name="metodo" class="form-control" required>
								<option value="transferencia">Transferencia</option>
								<option value="tarjeta">Tarjeta</option>
								<option value="efectivo">Efectivo</option>
								<option value="domiciliacion">Domiciliación</option>
							</select>
						</div>
						<div class="mb-3">
							<label for="cobroReferencia" class="form-label">Referencia</label>
							<input type="text" id="cobroReferencia" name="referencia" class="form-control" maxlength="100">
						</div>
						<button type="submit" class="btn btn-primary">Registrar cobro</button>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="enviarFacturaModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form id="enviarFacturaForm" method="POST" class="modal-content">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Enviar factura por email</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="enviarDestinatario" class="form-label">Destinatario</label>
						<input type="email" id="enviarDestinatario" name="destinatario" class="form-control" required>
						<div class="invalid-feedback d-block" data-error-for="destinatario"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="enviarFacturaSubmit" data-loading-text="Enviando...">Enviar</button>
				</div>
			</form>
		</div>
	</div>

	<div class="modal fade" id="rectificarFacturaModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form id="rectificarFacturaForm" method="POST" class="modal-content">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title">Rectificar factura</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label for="rectificarTipo" class="form-label">Modalidad</label>
						<select id="rectificarTipo" name="tipo_rectificacion" class="form-control" required>
							<option value="sustitucion">Por sustitución</option>
							<option value="diferencias">Por diferencias</option>
						</select>
					</div>
					<div class="mb-3">
						<label for="rectificarMotivo" class="form-label">Motivo</label>
						<textarea id="rectificarMotivo" name="motivo_rectificacion" class="form-control" rows="3" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary">Rectificar</button>
				</div>
			</form>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/facturas-datatable.init.js') }}"></script>
@endpush
