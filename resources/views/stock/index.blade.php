@extends('layouts.app')

@section('title', 'Stock')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#kardex-table_wrapper .dataTables_paginate .paginate_button.previous,
		#kardex-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="stock-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Artículos con gestión de stock</h6>
									<h3 class="mb-0" data-metric="articulos_gestionados">0</h3>
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
									<h6 class="mb-1">Movimientos registrados</h6>
									<h3 class="mb-0" data-metric="movimientos">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-153-bar-chart" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Alertas de stock mínimo</h6>
									<h3 class="mb-0" data-metric="alertas">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-50-minus-circle" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row" id="alertas-stock-row" style="display: none;">
				<div class="col-xl-12">
					<div class="card border-warning">
						<div class="card-body">
							<h6 class="mb-2">Artículos a reponer (stock en o bajo el mínimo)</h6>
							<div class="d-flex flex-wrap gap-2" id="alertas-stock-body"></div>
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">
								Kardex de movimientos
								@isset($articuloFiltrado)
									<small class="text-muted">— {{ $articuloFiltrado->nombre }}</small>
								@endisset
							</h4>
							<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajusteStockModal">
								+ Ajuste manual
							</button>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="kardex-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Fecha</th>
											<th>Artículo</th>
											<th>Tipo</th>
											<th>Cantidad</th>
											<th>Stock resultante</th>
											<th>Origen</th>
											<th>Motivo</th>
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

		@include('stock._ajuste')
	</div>
@endsection

@section('ayuda-titulo', 'Kardex de stock')
@section('ayuda')
	@include('ayuda.stock')
@endsection

@push('scripts')
	<script>
		window.stockIndexUrl = @json(isset($articuloFiltrado) ? route('stock.show', $articuloFiltrado) : route('stock.index'));
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/stock-kardex.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/stock-ajuste.init.js') }}"></script>
@endpush
