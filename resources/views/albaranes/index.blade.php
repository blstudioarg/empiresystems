@extends('layouts.app')

@section('title', 'Albaranes')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#albaranes-table_wrapper .dataTables_paginate .paginate_button.previous,
		#albaranes-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="albaranes-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de albaranes</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="box" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Entregados</h6>
									<h3 class="mb-0" data-metric="entregados">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-56-document-hover-swipe" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Pendientes de facturar</h6>
									<h3 class="mb-0" data-metric="pendientes_facturar">0</h3>
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
						<div class="card-header border-0 flex-wrap gap-2">
							<h4 class="card-title mb-0">Albaranes</h4>
							<div class="d-flex gap-2">
								<button type="button" class="btn btn-success" id="btn-convertir-albaranes" disabled>Convertir a factura</button>
								<a href="{{ route('albaranes.create') }}" class="btn btn-primary">+ Nuevo albarán</a>
							</div>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="albaranes-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th></th>
											<th>Número</th>
											<th>Receptor</th>
											<th>Estado</th>
											<th>Fecha entrega</th>
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

	{{-- Cambiar estado: un único modal cuyo contenido se arma en JS según el estado de la fila. --}}
	<div class="modal fade" id="estadoAlbaranModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Cambiar estado — <span id="estadoAlbaranNumero"></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body d-flex flex-column gap-2" id="estadoAlbaranBody">
					<button type="button" class="btn btn-success w-100" id="estadoBtnEntregado" data-loading-text="...">Marcar como entregado</button>
					<button type="button" class="btn btn-outline-danger w-100" id="estadoBtnAnulado" data-loading-text="...">Anular</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		window.albaranesIndexUrl = @json(route('albaranes.index'));
		window.albaranesConvertirUrl = @json(route('albaranes.convertir'));
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/albaranes-datatable.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Albaranes')
@section('ayuda')
	@include('ayuda.albaranes')
@endsection
