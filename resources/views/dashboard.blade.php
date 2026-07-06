@extends('layouts.app')

@section('title', 'Dashboard')

@php
	$rango = $datos['rango'];
	$datosGraficos = [
		'serie_facturacion' => $datos['serie_facturacion'],
		'comparativo' => $datos['comparativo'],
		'distribucion_estados' => $datos['distribucion_estados'],
	];
@endphp

@push('styles')
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-daterangepicker/daterangepicker.css') }}">
	<style>
		/* style.css define `label { margin-bottom: 0.5rem }` global; en el btn-group de presets
		   los <label> son los propios botones (patrón .btn-check + label.btn), no campos de
		   formulario, así que ese margen deja un hueco debajo del grupo. */
		#dashboard-filtro-form label.btn {
			margin-bottom: 0;
		}

		#dashboard-contenido.dashboard-cargando {
			opacity: .5;
			pointer-events: none;
			transition: opacity 150ms ease-out;
		}
	</style>
@endpush

@push('scripts')
	<script src="{{ asset('vendor/raphael/raphael.min.js') }}"></script>
	<script src="{{ asset('vendor/morris/morris.min.js') }}"></script>
	<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
	<script src="{{ asset('vendor/moment/moment.min.js') }}"></script>
	<script src="{{ asset('vendor/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
	<script>
		window.dashboardData = @json($datosGraficos);
	</script>
	<script src="{{ asset('js/plugins-init/dashboard-charts.init.js') }}"></script>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			{{-- Filtro de rango --}}
			<div class="row">
				<div class="col-12">
					<div class="card same-card">
						<div class="card-body py-2">
							<form id="dashboard-filtro-form" method="GET" class="d-flex flex-wrap align-items-center gap-2">
								<div class="btn-group" role="group" aria-label="Rango de fechas">
									<input type="radio" class="btn-check" name="preset" id="preset-mes" value="mes" autocomplete="off" {{ $rango['preset'] === 'mes' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="preset-mes">Mes</label>

									<input type="radio" class="btn-check" name="preset" id="preset-trimestre" value="trimestre" autocomplete="off" {{ $rango['preset'] === 'trimestre' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="preset-trimestre">Trimestre</label>

									<input type="radio" class="btn-check" name="preset" id="preset-anio" value="anio" autocomplete="off" {{ $rango['preset'] === 'anio' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="preset-anio">Año</label>

									<input type="radio" class="btn-check" name="preset" id="preset-personalizado" value="personalizado" autocomplete="off" {{ $rango['preset'] === 'personalizado' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="preset-personalizado">Personalizado</label>
								</div>

								<div id="dashboard-rango-personalizado" class="{{ $rango['preset'] === 'personalizado' ? '' : 'd-none' }}">
									<input type="text" id="dashboard-rango-input" class="form-control" style="min-width: 220px;" readonly
										value="{{ \Illuminate\Support\Carbon::parse($rango['desde'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($rango['hasta'])->format('d/m/Y') }}">
									<input type="hidden" name="desde" id="dashboard-rango-desde" value="{{ $rango['desde'] }}">
									<input type="hidden" name="hasta" id="dashboard-rango-hasta" value="{{ $rango['hasta'] }}">
								</div>

								<span class="text-muted ms-auto" id="dashboard-rango-mostrando">
									<i class="fas fa-calendar-alt me-1"></i>Mostrando: {{ \Illuminate\Support\Carbon::parse($rango['desde'])->format('d/m/Y') }}
									– {{ \Illuminate\Support\Carbon::parse($rango['hasta'])->format('d/m/Y') }}
								</span>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div id="dashboard-contenido">
				@include('partials.dashboard-contenido', ['datos' => $datos])
			</div>

		</div>
	</div>
@endsection
