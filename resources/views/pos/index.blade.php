@extends('layouts.app')

@section('title', __('POS · Facturas simplificadas'))

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#tickets-table_wrapper .dataTables_paginate .paginate_button.previous,
		#tickets-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="tickets-cards" class="row">
				<div class="col-xl-6 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">{{ __('Tickets emitidos') }}</h6>
									<h4 class="mb-0" data-metric="total">0</h4>
								</div>
								<div>
									<x-lordicon icon="invoice" size="38" trigger="hover" target=".card" />
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
									<h6 class="mb-1">{{ __('Importe total') }}</h6>
									<h4 class="mb-0" data-metric="importe_total">0,00 €</h4>
								</div>
								<div>
									<x-lordicon icon="euro" size="38" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">{{ __('Facturas simplificadas') }}</h4>
							@can('ver-pos-crear')
							<a href="{{ route('pos.create') }}" class="btn btn-primary">
								+ {{ __('Nuevo ticket') }}
							</a>
							@endcan
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="tickets-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>{{ __('Nº') }}</th>
											<th>{{ __('Receptor') }}</th>
											<th>{{ __('Fecha') }}</th>
											<th>{{ __('Total') }}</th>
											<th>{{ __('Pago') }}</th>
											<th>{{ __('Tipo') }}</th>
											<th>{{ __('Acciones') }}</th>
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

	<div class="modal fade" id="ticketPdfModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ __('Vista previa del ticket') }}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="ticketPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>

	{{-- Anular un ticket (feature 051): mismo patrón que «Anular factura» (facturas/index), con el
	     motivo obligatorio como confirmación explícita. --}}
	<div class="modal fade" id="ticketAnularModal" tabindex="-1" aria-labelledby="ticketAnularModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form id="ticketAnularForm" class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="ticketAnularModalLabel">{{ __('Anular ticket') }}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted">
						{{ __('El ticket queda anulado con su número, deja de contar en las ventas y en la caja, y lo vendido vuelve al stock. Es para tickets emitidos por error; una devolución se hace con una rectificativa.') }}
					</p>
					<p class="fw-bold mb-2" id="ticketAnularNumero"></p>
					<div class="mb-1">
						<label for="ticketAnularMotivo" class="form-label">{{ __('Motivo') }}</label>
						<textarea id="ticketAnularMotivo" name="motivo" class="form-control" rows="3" maxlength="500" required></textarea>
						<div class="invalid-feedback" data-error-for="motivo"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
					<button type="submit" class="btn btn-danger" id="ticketAnularConfirmar">{{ __('Anular') }}</button>
				</div>
			</form>
		</div>
	</div>
@endsection

@section('ayuda-titulo', __('Facturas simplificadas (POS)'))
@section('ayuda')
	@include('ayuda.pos')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="@assetv('js/plugins-init/pos-datatable.init.js')"></script>
@endpush
