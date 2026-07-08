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

			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header border-0 flex-wrap">
							<h4 class="card-title mb-0">Historial de actividad</h4>
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
@endpush
