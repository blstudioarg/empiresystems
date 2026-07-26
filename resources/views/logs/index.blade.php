@extends('layouts.app')

@section('title', 'Logs de actividad')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto
		   ("Anterior"/"Siguiente") se rompe en vertical. Dejamos que el ancho
		   se ajuste al texto en una sola línea. */
		#logs-table_wrapper .dataTables_paginate .paginate_button.previous,
		#logs-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div id="logs-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de eventos</h6>
									<h3 class="mb-0" data-metric="total">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-457-shield-security-hover-pinch" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Eventos hoy</h6>
									<h3 class="mb-0" data-metric="hoy">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-69-eye-hover-blink" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Accesos fallidos</h6>
									<h3 class="mb-0" data-metric="fallidos">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-2604-2-factor-authentication-hover-pinch" size="50" trigger="hover" target=".card" />
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
							<h4 class="card-title mb-0">Historial de actividad</h4>
							<button type="button" id="btn-exportar-logs" class="btn btn-outline-secondary">Exportar</button>
						</div>
						<div class="card-body pt-0">
							<div class="table-responsive">
								<table id="logs-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Fecha</th>
											<th>Usuario</th>
											<th>Acción</th>
											<th>Resultado</th>
											<th>Detalle</th>
											<th>IP</th>
											<th>Navegador</th>
											<th>Ubicación</th>
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

@section('ayuda-titulo', 'Logs de actividad')
@section('ayuda')
	@include('ayuda.logs')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/logs-datatable.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/excel-export.init.js') }}"></script>
	<script>
		window.initExportacionExcel({
			boton: '#btn-exportar-logs',
			url: @json(route('logs.exportar', ['modulo' => 'logs'])),
			// Tabla server-side (docs/04-front-guidelines.md): el navegador solo tiene cargada
			// la página actual, así que en vez de leer filas de la tabla le pedimos al mismo
			// endpoint del listado TODAS las filas que matchean la búsqueda activa (sin paginar)
			// y de ahí sacamos los IDs a exportar.
			ids: function () {
				var table = $('#logs-table').DataTable();
				var params = new URLSearchParams({
					draw: 1,
					start: 0,
					length: 100000,
					'search[value]': table.search(),
				});

				return fetch(@json(route('logs.index')) + '?' + params.toString())
					.then(function (response) { return response.json(); })
					.then(function (json) {
						return json.data.map(function (fila) { return fila.id; });
					});
			},
		});
	</script>
@endpush
