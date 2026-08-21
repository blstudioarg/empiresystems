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

		/* Cards informativas que llevan a la vista que representan (ver
		   bindCardsInformativas en dashboard-charts.init.js). El propio icono
		   lordicon dentro de la card sigue reaccionando a su :hover normal. */
		.dashboard-card-clickable {
			cursor: pointer;
		}

		/* Prueba: entrada escalonada de las cards del dashboard al cargar/filtrar,
		   reutilizando el keyframe `metricCardIn` ya definido en app-overrides.css
		   para las cards de métricas (docs/04-front-guidelines.md). Delay por
		   columna dentro de cada fila, no por posición global en la página.

		   Gateada con la clase `.dashboard-anim` (añadida por JS, ver script más
		   abajo): si se dispara sola al pintar el DOM, para cuando `custom.js`
		   retira el preloader (800ms tras el evento `load`, ver handlePreloader
		   en public/js/custom.js) la animación ya terminó detrás del overlay y
		   nunca se llega a ver en la primera carga. En los refrescos por AJAX del
		   filtro no hay preloader de por medio, así que las cards nuevas animan
		   igual apenas se insertan (la clase ya quedó puesta desde la carga inicial). */
		@media (prefers-reduced-motion: no-preference) {
			#dashboard-contenido.dashboard-anim .card {
				animation: metricCardIn 420ms cubic-bezier(0.23, 1, 0.32, 1) both;
			}

			#dashboard-contenido.dashboard-anim .row > *:nth-child(1) .card { animation-delay: 0ms; }
			#dashboard-contenido.dashboard-anim .row > *:nth-child(2) .card { animation-delay: 60ms; }
			#dashboard-contenido.dashboard-anim .row > *:nth-child(3) .card { animation-delay: 120ms; }
			#dashboard-contenido.dashboard-anim .row > *:nth-child(4) .card { animation-delay: 180ms; }
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
	<script src="@assetv('js/plugins-init/dashboard-charts.init.js')"></script>
	<script>
		// Sincroniza la entrada animada de las cards con la desaparición del
		// preloader (mismo delay que handlePreloader en public/js/custom.js),
		// para que no corra oculta detrás del overlay. Una vez añadida, la
		// clase queda puesta y las cards que llegan por AJAX al filtrar
		// animan directo, sin este delay.
		jQuery(window).on('load', function () {
			setTimeout(function () {
				jQuery('#dashboard-contenido').addClass('dashboard-anim');
			}, 800);
		});
	</script>
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

@section('ayuda-titulo', 'Inicio')
@section('ayuda')
	@include('ayuda.dashboard')
@endsection
