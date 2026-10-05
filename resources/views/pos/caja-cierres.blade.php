@extends('layouts.app')

@section('title', __('POS · Historial de cierres'))

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link href="@assetv('css/pos-caja.css')" rel="stylesheet">
	<style>
		#cierres-table_wrapper .dataTables_paginate .paginate_button.previous,
		#cierres-table_wrapper .dataTables_paginate .paginate_button.next {
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
									<h6 class="mb-1">{{ __('Cierres este mes') }}</h6>
									<h4 class="mb-0" data-metric="cierres">0</h4>
								</div>
								<div><x-lordicon icon="wired-outline-2510-money-safety-hover-pinch" size="38" trigger="hover" target=".card" /></div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">{{ __('Facturado este mes') }}</h6>
									<h4 class="mb-0" data-metric="facturado">0,00 €</h4>
								</div>
								<div><x-lordicon icon="euro" size="38" trigger="hover" target=".card" /></div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-4 col-sm-12">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">{{ __('Diferencia de efectivo del mes') }}</h6>
									<h4 class="mb-0" data-metric="descuadre" id="cierres-descuadre">0,00 €</h4>
								</div>
								<div><x-lordicon icon="wired-outline-2115-refund-hover-pinch" size="38" trigger="hover" target=".card" /></div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">{{ __('Historial de cierres de caja') }}</h4>
							<div class="d-flex gap-2">
								<a href="{{ route('pos.caja') }}" class="btn btn-primary">
									<i class="fa-solid fa-cash-register me-1" aria-hidden="true"></i> {{ __('Ir a la caja') }}
								</a>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="cierres-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>{{ __('Cierre') }}</th>
											<th>{{ __('Abrió') }}</th>
											<th>{{ __('Cerró') }}</th>
											<th>{{ __('Tickets') }}</th>
											<th>{{ __('Facturado') }}</th>
											<th>{{ __('Esperado') }}</th>
											<th>{{ __('Contado') }}</th>
											<th>{{ __('Diferencia') }}</th>
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

	<div class="modal fade" id="cajaInformeModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ __('Informe de cierre de caja') }}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="cajaInformeFrame" src="" title="{{ __('Informe de cierre de caja') }}" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', __('Historial de cierres'))
@section('ayuda')
	@include('ayuda.pos-caja-cierres')
@endsection

@push('scripts')
	<script>
		window.cajaCierresState = { url: @json(route('pos.caja.cierres')) };
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="@assetv('js/plugins-init/pos-caja-cierres.init.js')"></script>
@endpush
