@extends('layouts.app')

@section('title', 'Calendario de fichajes')

@push('styles')
	<link href="{{ asset('vendor/fullcalendar/main.min.css') }}" rel="stylesheet">
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			@unless ($miembros->isEmpty())
				{{-- Cards informativas del rango visible (feature 026). Mismo patrón que las métricas
				     de clientes/dashboard (x-lordicon size=50, sin clases en el contenedor del ícono,
				     ver docs/04-front-guidelines.md). Los valores los rellena calendario.init.js con
				     el feed /calendario/resumen al cambiar de mes o de miembro. --}}
				<div id="cal-metricas" class="row">
					<div class="col-xl-3 col-sm-6">
						<div class="card same-card">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<h6 class="mb-1">Cumplimiento</h6>
										<h3 class="mb-0" data-kpi="cumplimiento">—</h3>
										<small class="text-muted" data-kpi="cumplimiento-detalle">&nbsp;</small>
									</div>
									<div>
										<x-lordicon icon="wired-outline-2764-reliable-alt-hover-pinch" size="50" trigger="hover" target=".card" />
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
										<h6 class="mb-1">Horas trabajadas</h6>
										<h3 class="mb-0"><span data-kpi="horas-trabajadas">—</span> <small class="text-muted fs-6">/ <span data-kpi="horas-previstas">—</span> h</small></h3>
										<small data-kpi="horas-diferencia">&nbsp;</small>
									</div>
									<div>
										<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="50" trigger="hover" target=".card" />
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
										<h6 class="mb-1">Retrasos</h6>
										<h3 class="mb-0" data-kpi="retrasos">—</h3>
										<small class="text-muted" data-kpi="retrasos-detalle">&nbsp;</small>
									</div>
									<div>
										<x-lordicon icon="wired-outline-3097-pause-circle-hover-pinch" size="50" trigger="hover" target=".card" />
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
										<h6 class="mb-1">Ausencias</h6>
										<h3 class="mb-0" data-kpi="ausencias">—</h3>
										<small class="text-muted" data-kpi="ausencias-detalle">&nbsp;</small>
									</div>
									<div>
										<x-lordicon icon="wired-outline-309-avatar-icon-cross-hover-click" size="50" trigger="hover" target=".card" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			@endunless
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header flex-wrap gap-2">
							<h4 class="card-title mb-0">Calendario de fichajes</h4>
							<div class="d-flex align-items-center gap-2">
								<label class="form-label mb-0" for="calendario-miembro">Miembro</label>
								<select id="calendario-miembro" class="form-select" style="min-width: 220px;">
									@if ($miembros->count() > 1)
										<option value="">Todo el equipo</option>
									@endif
									@foreach ($miembros as $m)
										<option value="{{ $m->id }}" {{ $miembros->count() === 1 ? 'selected' : '' }}>
											{{ $m->user->name }}
										</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="card-body">
							@if ($miembros->isEmpty())
								<div class="text-center text-muted py-5">
									<p class="mb-1 fw-bold">Aún no hay miembros de equipo activos.</p>
									<p class="mb-3">Añade miembros para ver su cumplimiento en el calendario.</p>
									<a href="{{ route('miembros-equipo.index') }}" class="btn btn-primary">Ir a Miembros</a>
								</div>
							@else
								{{-- Leyenda de veredictos: mismo mapa de clases que emite el backend (D6). --}}
								<div class="cal-leyenda mb-3" aria-label="Leyenda de veredictos">
									@foreach (\App\Enums\VeredictoCumplimiento::cases() as $veredicto)
										<span class="cal-leyenda-item {{ $veredicto->clase() }}">{{ $veredicto->label() }}</span>
									@endforeach
									<span class="cal-leyenda-item cal-veredicto-incidencia">Incidencia</span>
									<span class="cal-leyenda-item cal-previsto">Previsto</span>
									<span class="cal-leyenda-item cal-real">Trabajado</span>
								</div>
								<div id="calendar" class="app-fullcalendar"></div>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	@include('calendario._modales')
@endsection

@push('scripts')
	<script src="{{ asset('vendor/fullcalendar/main.min.js') }}"></script>
	<script src="{{ asset('vendor/fullcalendar/locales/es.js') }}"></script>
	<script>
		window.calendarioConfig = {
			eventosUrl: @json(route('calendario.eventos')),
			resumenUrl: @json(route('calendario.resumen')),
			csrf: @json(csrf_token()),
			horariosUrlTemplate: @json(route('asignaciones-horario.index', ['miembro' => '__ID__'])),
			asignarUrlTemplate: @json(route('asignaciones-horario.store', ['miembro' => '__ID__'])),
			horarios: @json($horarios->map(fn ($h) => ['id' => $h->id, 'nombre' => $h->nombre])->values()),
		};
	</script>
	<script src="{{ asset('js/plugins-init/calendario.init.js') }}"></script>
@endpush
