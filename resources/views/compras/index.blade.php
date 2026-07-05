@extends('layouts.app')

@section('title', 'Compras')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#compras-table_wrapper .dataTables_paginate .paginate_button.previous,
		#compras-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="compras-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de compras</h6>
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
									<h6 class="mb-1">Confirmadas</h6>
									<h3 class="mb-0" data-metric="confirmadas">0</h3>
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
									<h6 class="mb-1">Importe confirmado</h6>
									<h3 class="mb-0" data-metric="importe_total">0</h3>
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
							<h4 class="card-title mb-0">Compras</h4>
							<a href="{{ route('compras.create') }}" class="btn btn-primary">+ Nueva compra</a>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="compras-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Proveedor</th>
											<th>Nº documento</th>
											<th>Fecha</th>
											<th>Estado</th>
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
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script>
		$(function () {
			$('#compras-table').DataTable({
				responsive: true,
				processing: true,
				ajax: {
					url: window.location.href,
					dataSrc: function (json) {
						if (json.totales) {
							$('[data-metric="total"]').text(json.totales.total);
							$('[data-metric="confirmadas"]').text(json.totales.confirmadas);
							$('[data-metric="importe_total"]').text(json.totales.importe_total);
						}

						return json.data;
					},
				},
				columns: [
					{ data: 'proveedor' },
					{ data: 'numero_documento' },
					{ data: 'fecha' },
					{ data: 'estado' },
					{ data: 'total' },
					{ data: null, orderable: false, render: function (data, type, row) {
						return '<a class="btn btn-primary light btn-sm" href="' + row.show_url + '">Ver</a>';
					} },
				],
				language: {
					search: 'Buscar:',
					lengthMenu: 'Mostrar _MENU_ registros',
					info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
					infoEmpty: 'Mostrando 0 a 0 de 0 registros',
					infoFiltered: '(filtrado de _MAX_ registros totales)',
					zeroRecords: 'No se encontraron compras',
					emptyTable: 'Todavía no hay compras',
					processing: 'Cargando...',
					paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
				},
			});
		});
	</script>
@endpush
