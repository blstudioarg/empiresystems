@extends('layouts.app')

@section('title', 'Alertas')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		#alertas-table_wrapper .dataTables_paginate .paginate_button.previous,
		#alertas-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div id="alertas-cards" class="row">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Total de alertas</h6>
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
									<h6 class="mb-1">Nuevas</h6>
									<h3 class="mb-0" data-metric="nuevas">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-49-plus-circle" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Resueltas</h6>
									<h3 class="mb-0" data-metric="resueltas">0</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-267-like-thumb-up-hover-up" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title">Alertas</h4>
						</div>
						<div class="card-body">
							<table id="alertas-table" class="display responsive nowrap w-100">
								<thead>
									<tr>
										<th>Miembro</th>
										<th>Tipo</th>
										<th>Detalle</th>
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
@endsection

@section('ayuda-titulo', 'Alertas de fichaje')
@section('ayuda')
	@include('ayuda.alertas')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('js/plugins-init/alertas-datatable.init.js') }}"></script>
@endpush
