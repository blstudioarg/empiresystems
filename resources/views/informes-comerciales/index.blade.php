@extends('layouts.app')

@section('title', 'Informes comerciales')

@php
	$periodo = $datos['periodo'];
	$alcance = $datos['alcance'];
	$datosGraficos = [
		'evolucion' => $datos['evolucion'],
		'leads_por_estado' => $datos['fases']['leads_por_estado'],
		'oportunidades_por_etapa' => $datos['fases']['oportunidades_por_etapa'],
		'presupuestos_por_estado' => $datos['fases']['presupuestos_por_estado'],
		'comparativa' => $datos['comparativa'],
	];
@endphp

@push('styles')
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-daterangepicker/daterangepicker.css') }}">
	<style>
		#informe-comercial-filtro-form label.btn {
			margin-bottom: 0;
		}

		#informe-comercial-contenido.informe-comercial-cargando {
			opacity: .5;
			pointer-events: none;
			transition: opacity 150ms ease-out;
		}

		/* Distintivo visual frente al dashboard financiero: cada indicador declara su criterio
		   de adscripción de fecha (FR-006) como una etiqueta de color, en vez de un simple
		   número suelto — es la firma visual de esta sección. */
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

		.criterio-cohorte {
			background: rgba(29, 105, 214, .12);
			color: #1D69D6;
		}

		.criterio-evento {
			background: rgba(139, 92, 246, .14);
			color: #7C3AED;
		}

		.criterio-instantanea {
			background: rgba(245, 158, 11, .16);
			color: #B45309;
		}

		/* Bloques del embudo encadenados con una flecha entre ellos, para narrar el recorrido
		   Lead → Oportunidad → Presupuesto → Negocio cerrado en vez de una grilla suelta de cards. */
		.embudo-paso {
			position: relative;
		}

		.embudo-flecha {
			display: none;
			position: absolute;
			top: 50%;
			right: -.9rem;
			transform: translateY(-50%);
			color: var(--primary, #1D69D6);
			font-size: 1.1rem;
			z-index: 1;
		}

		@media (min-width: 1200px) {
			.embudo-paso:not(:last-child) .embudo-flecha {
				display: block;
			}
		}

		.ratio-sin-datos {
			color: var(--bs-secondary-color, #6c757d);
			font-style: italic;
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
		window.informeComercialData = @json($datosGraficos);
		window.informeComercialExportarUrl = @json(route('informes-comerciales.exportar'));
	</script>
	<script src="{{ asset('js/plugins-init/informe-comercial.init.js') }}"></script>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<div class="row">
				<div class="col-12">
					<div class="card same-card">
						<div class="card-body py-2">
							<form id="informe-comercial-filtro-form" method="GET" class="d-flex flex-wrap align-items-center gap-2">
								<div class="btn-group" role="group" aria-label="Rango de fechas">
									<input type="radio" class="btn-check" name="preset" id="ic-preset-mes" value="mes" autocomplete="off" {{ $periodo['preset'] === 'mes' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="ic-preset-mes">Mes</label>

									<input type="radio" class="btn-check" name="preset" id="ic-preset-trimestre" value="trimestre" autocomplete="off" {{ $periodo['preset'] === 'trimestre' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="ic-preset-trimestre">Trimestre</label>

									<input type="radio" class="btn-check" name="preset" id="ic-preset-anio" value="anio" autocomplete="off" {{ $periodo['preset'] === 'anio' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="ic-preset-anio">Año</label>

									<input type="radio" class="btn-check" name="preset" id="ic-preset-personalizado" value="personalizado" autocomplete="off" {{ $periodo['preset'] === 'personalizado' ? 'checked' : '' }}>
									<label class="btn btn-outline-primary" for="ic-preset-personalizado">Personalizado</label>
								</div>

								<div id="informe-comercial-rango-personalizado" class="{{ $periodo['preset'] === 'personalizado' ? '' : 'd-none' }}">
									<input type="text" id="informe-comercial-rango-input" class="form-control" style="min-width: 220px;" readonly
										value="{{ \Illuminate\Support\Carbon::parse($periodo['desde'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($periodo['hasta'])->format('d/m/Y') }}">
									<input type="hidden" name="desde" id="informe-comercial-rango-desde" value="{{ $periodo['desde'] }}">
									<input type="hidden" name="hasta" id="informe-comercial-rango-hasta" value="{{ $periodo['hasta'] }}">
								</div>

								<select name="canal_id" id="informe-comercial-canal" class="form-select" style="max-width: 200px;">
									<option value="">Todos los canales</option>
									<option value="sin_especificar">Sin especificar</option>
									@foreach ($canales as $canal)
										<option value="{{ $canal->id }}">{{ $canal->nombre }}</option>
									@endforeach
								</select>

								@if ($alcance['tipo'] === 'tenant')
									<select name="comercial_id" id="informe-comercial-comercial" class="form-select" style="max-width: 200px;">
										<option value="">Todos los comerciales</option>
										@foreach ($comerciales as $comercial)
											<option value="{{ $comercial->id }}">{{ $comercial->name }}</option>
										@endforeach
									</select>
								@endif

								<select name="fase" id="informe-comercial-fase" class="form-select" style="max-width: 220px;">
									<option value="">Todas las fases</option>
									<optgroup label="Estado de lead">
										@foreach (\App\Enums\EstadoLead::cases() as $estado)
											<option value="{{ $estado->value }}">{{ $estado->label() }}</option>
										@endforeach
									</optgroup>
									<optgroup label="Etapa de oportunidad">
										@foreach (\App\Enums\EtapaOportunidad::cases() as $etapa)
											<option value="{{ $etapa->value }}">{{ $etapa->label() }}</option>
										@endforeach
									</optgroup>
									<optgroup label="Estado de presupuesto">
										@foreach (\App\Enums\EstadoPresupuesto::cases() as $estado)
											<option value="{{ $estado->value }}">{{ $estado->label() }}</option>
										@endforeach
									</optgroup>
								</select>

								<div class="form-check form-switch ms-1">
									<input class="form-check-input" type="checkbox" name="comparar" value="1" id="informe-comercial-comparar">
									<label class="form-check-label" for="informe-comercial-comparar">Comparar con el ejercicio anterior</label>
								</div>

								<div class="ms-auto d-flex align-items-center gap-2">
									<span class="text-muted" id="informe-comercial-rango-mostrando">
										<i class="fas fa-calendar-alt me-1"></i>{{ $periodo['etiqueta'] }}
									</span>
									<button type="button" id="btn-exportar-informe-comercial" class="btn btn-outline-secondary">
										<i class="fas fa-file-excel me-1"></i>Exportar
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div id="informe-comercial-contenido">
				@include('partials.informe-comercial-contenido', ['datos' => $datos])
			</div>

		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Informes comerciales')
@section('ayuda')
	@include('ayuda.informes-comerciales')
@endsection
