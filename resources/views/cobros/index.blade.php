@extends('layouts.app')

@section('title', 'Cobros')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-daterangepicker/daterangepicker.css') }}">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto se rompe en
		   vertical (memoria feedback_datatable_pagination_css). */
		#cobros-facturas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#cobros-facturas-table_wrapper .dataTables_paginate .paginate_button.next,
		#cobros-table_wrapper .dataTables_paginate .paginate_button.previous,
		#cobros-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}

		/* Etiqueta de criterio de fecha de cada indicador (FR-008, guía L1212), mismo patrón que
		   informes-comerciales/index.blade.php. */
		.criterio-badge {
			display: inline-block;
			font-size: .6875rem;
			font-weight: 600;
			letter-spacing: .02em;
			text-transform: uppercase;
			padding: .1rem .45rem;
			border-radius: .3rem;
			margin-left: .4rem;
			vertical-align: middle;
		}

		.criterio-evento {
			background: rgba(139, 92, 246, .14);
			color: #7C3AED;
		}

		.criterio-instantanea {
			background: rgba(245, 158, 11, .16);
			color: #B45309;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="cobros-cards" class="row">
				<div class="col-xl-3 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Pendiente de cobro<span class="criterio-badge criterio-instantanea">Hoy</span></h6>
									<h4 class="mb-0" data-metric="pendiente_total">0,00 €</h4>
								</div>
								<div>
									<x-lordicon icon="euro" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-3 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Cobrado en el periodo<span class="criterio-badge criterio-evento">Cobro</span></h6>
									<h4 class="mb-0 text-success" data-metric="cobrado_periodo">0,00 €</h4>
								</div>
								<div>
									<x-lordicon icon="wired-outline-2115-refund-hover-pinch" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-3 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Vencido<span class="criterio-badge criterio-instantanea">Hoy</span></h6>
									<h4 class="mb-0 text-danger" data-metric="vencido_total">0,00 €</h4>
								</div>
								<div>
									<x-lordicon icon="wired-outline-457-shield-security-hover-pinch" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-xl-3 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Facturas pendientes<span class="criterio-badge criterio-instantanea">Hoy</span></h6>
									<h4 class="mb-0" data-metric="facturas_pendientes">0</h4>
								</div>
								<div>
									<x-lordicon icon="invoice" size="38" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-12">
					<div class="card same-card">
						<div class="card-body py-2">
							@include('cobros._filtros')
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">Cobros</h4>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="cobros-facturas-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Nº</th>
											<th>Cliente</th>
											<th>Fecha</th>
											<th>Vencimiento</th>
											<th>Total a cobrar</th>
											<th>Cobrado</th>
											<th>Saldo</th>
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

	@include('partials._cobros_modal')
@endsection

@section('ayuda-titulo', 'Cobros')
@section('ayuda')
	@include('ayuda.cobros')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/moment/moment.min.js') }}"></script>
	<script src="{{ asset('vendor/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
	<script>
		window.cobrosUrls = {
			listado: @json(route('cobros.index')),
			resumen: @json(route('cobros.resumen')),
		};
	</script>
	<script src="@assetv('js/plugins-init/cobros-modal.js')"></script>
	<script src="@assetv('js/plugins-init/cobros-datatable.init.js')"></script>
@endpush
