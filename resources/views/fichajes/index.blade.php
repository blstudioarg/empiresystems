@extends('layouts.app')

@section('title', 'Fichar')

@php
	$nombresDia = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
	$nombresMes = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
	$fechaHoy = $nombresDia[$ahoraLocal->dayOfWeekIso].', '.$ahoraLocal->day.' de '.$nombresMes[$ahoraLocal->month];
@endphp

@push('styles')
	<link href="{{ asset('vendor/leaflet/leaflet.css') }}" rel="stylesheet">
	<style>
		/* ---------- Layout general de la sección "app" de fichaje ---------- */
		.fichaje-app {
			padding-bottom: 0.5rem;
		}

		@media (max-width: 767.98px) {
			.fichaje-app {
				/* Deja sitio para el bottom nav fijo (altura barra + safe-area iOS + aire). */
				padding-bottom: calc(4.75rem + env(safe-area-inset-bottom));
			}
		}

		/* ---------- Hero: reloj, estado, turno y acción principal ---------- */
		.fichaje-hero {
			overflow: hidden;
			position: relative;
			/* Leaflet mete sus propios controles (zoom, atribución) en z-index:1000 dentro de
			   #fichaje-mapa. Sin esto, `position: relative` solo no crea contexto de apilamiento
			   propio, así que ese 1000 competía directamente contra el z-index del topbar/sidebar
			   del template (fuera de esta card) y ganaba. `isolation: isolate` aísla todo lo de
			   adentro (mapa incluido) en su propio contexto, sin tocar los z-index del layout. */
			isolation: isolate;
		}

		.fichaje-hero::before {
			content: '';
			position: absolute;
			inset: 0 0 auto 0;
			height: 4px;
			z-index: 2;
			background: var(--bs-secondary, #6c757d);
			transition: background-color 200ms ease;
		}

		.fichaje-hero[data-estado="abierta"]::before {
			background: var(--primary, #1D69D6);
		}

		.fichaje-hero[data-estado="en_pausa"]::before {
			background: #f2a600;
		}

		.fichaje-hero .card-body {
			text-align: center;
		}

		.fichaje-hero-top {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			flex-wrap: wrap;
			margin-bottom: 1rem;
		}

		.fichaje-estado-badge {
			font-size: 0.8125rem;
			font-weight: 600;
			padding: 0.35em 0.85em;
			transition: background-color 200ms ease, color 200ms ease;
		}

		.fichaje-turno-chip {
			font-size: 0.8125rem;
			color: var(--bs-secondary-color, #6c757d);
		}

		.fichaje-horario-badge {
			display: inline-flex;
			align-items: center;
			gap: 0.3125rem;
			font-size: 0.75rem;
			font-weight: 600;
		}

		.fichaje-horario-badge::before {
			content: '';
			width: 0.5rem;
			height: 0.5rem;
			border-radius: 50%;
			background: currentColor;
		}

		.fichaje-horario-badge--dentro {
			color: #12b76a;
		}

		.fichaje-horario-badge--fuera {
			color: var(--bs-secondary-color, #6c757d);
		}

		.fichaje-reloj {
			font-variant-numeric: tabular-nums;
			font-weight: 700;
			font-size: clamp(2.5rem, 9vw, 3.25rem);
			line-height: 1;
			letter-spacing: 0.01em;
		}

		.fichaje-fecha {
			text-transform: capitalize;
			color: var(--bs-secondary-color, #6c757d);
			margin-top: 0.25rem;
			font-size: 0.875rem;
		}

		.fichaje-acciones {
			display: flex;
			flex-direction: column;
			gap: 0.625rem;
			max-width: 22rem;
			margin: 1.5rem auto 0;
		}

		.fichaje-acciones button {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			transition: transform 160ms ease-out, background-color 150ms ease, border-color 150ms ease;
		}

		.fichaje-acciones button:active {
			transform: scale(0.97);
		}

		.btn-fichar-principal {
			height: 3.25rem;
			font-size: 1rem;
			font-weight: 600;
			border-radius: 0.75rem;
		}

		.fichaje-hint {
			font-size: 0.8125rem;
			color: var(--bs-secondary-color, #6c757d);
			margin-top: 0.75rem;
			min-height: 1.1em;
		}

		/* ---------- Card "Hoy": horas + timeline ---------- */
		.fichaje-horas-hoy {
			display: flex;
			align-items: baseline;
			gap: 0.5rem;
			font-variant-numeric: tabular-nums;
		}

		.fichaje-horas-hoy .valor {
			font-size: 2rem;
			font-weight: 700;
		}

		.fichaje-horas-hoy .etiqueta {
			font-size: 0.8125rem;
			color: var(--bs-secondary-color, #6c757d);
		}

		.fichaje-timeline {
			margin: 1rem 0 0;
			padding: 0;
			list-style: none;
		}

		.fichaje-timeline li {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.75rem;
			padding: 0.5rem 0;
			border-top: 1px solid rgba(0, 0, 0, 0.06);
			font-size: 0.875rem;
		}

		.fichaje-timeline li:first-child {
			border-top: none;
		}

		.fichaje-timeline .tipo-ubicacion {
			display: flex;
			flex-direction: column;
		}

		.fichaje-timeline .tipo {
			font-weight: 600;
		}

		.fichaje-timeline .ubicacion {
			font-size: 0.75rem;
			color: var(--bs-secondary-color, #6c757d);
		}

		.fichaje-timeline .hora {
			color: var(--bs-secondary-color, #6c757d);
			font-variant-numeric: tabular-nums;
		}

		.fichaje-timeline-vacio {
			color: var(--bs-secondary-color, #6c757d);
			font-size: 0.875rem;
			margin-top: 0.75rem;
		}

		/* ---------- Ubicación / mapa (cabecera de la card, de punta a punta) ---------- */
		#fichaje-mapa {
			height: 220px;
		}

		@media (min-width: 992px) {
			#fichaje-mapa {
				height: 280px;
			}
		}

		/* ---------- Bottom nav tipo app móvil ---------- */
		.fichaje-bottom-nav {
			position: fixed;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 1030;
			display: flex;
			align-items: flex-end;
			justify-content: space-around;
			background: #fff;
			border-top: 1px solid rgba(0, 0, 0, 0.08);
			padding: 0.375rem 0.5rem calc(0.375rem + env(safe-area-inset-bottom));
			box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
		}

		[data-theme-version="dark"] .fichaje-bottom-nav {
			background: #272627;
			border-top-color: rgba(255, 255, 255, 0.2);
		}

		.fichaje-bottom-nav-item {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 0.1875rem;
			padding: 0.375rem 0;
			font-size: 0.6875rem;
			font-weight: 600;
			color: var(--primary, #1D69D6);
			background: none;
			border: none;
			text-decoration: none;
			-webkit-tap-highlight-color: transparent;
			transition: opacity 150ms ease, transform 120ms ease-out;
		}

		.fichaje-bottom-nav-item:active {
			transform: scale(0.94);
		}

		.fichaje-bottom-nav-item:disabled {
			color: var(--bs-secondary-color, #6c757d);
			opacity: 0.4;
			pointer-events: none;
		}

		/* Botón central (Pausa/Reanudar): círculo elevado tipo FAB, igual que antes. */
		.fichaje-bottom-nav-item--main {
			position: relative;
		}

		.fichaje-bottom-nav-fab {
			width: 3rem;
			height: 3rem;
			margin-top: -1.5rem;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			background: var(--primary, #1D69D6);
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
			border: 3px solid #fff;
			transition: transform 150ms ease-out, background-color 150ms ease;
		}

		[data-theme-version="dark"] .fichaje-bottom-nav-fab {
			border-color: #272627;
		}

		/* Los dos <span> de ícono (pausa/reanudar) son inline por defecto: sin esto, el espacio de
		   línea propio del texto (line-height) los corre un par de px hacia abajo dentro del
		   círculo en vez de quedar centrados con el flex del padre. */
		.fichaje-bottom-nav-fab span {
			display: flex;
			align-items: center;
			justify-content: center;
			line-height: 0;
		}

		.fichaje-bottom-nav-item--main:active .fichaje-bottom-nav-fab {
			transform: scale(0.94);
		}

		.fichaje-bottom-nav-item--main:disabled .fichaje-bottom-nav-fab {
			background: var(--bs-secondary, #6c757d);
		}

		/* Mientras está en pausa, el botón pasa a "Reanudar": el círculo se pone verde (mismo
		   success que el indicador "Dentro de tu horario") para que se note que tocarlo retoma
		   la jornada, no para señalar alerta. */
		.fichaje-bottom-nav-fab--en-pausa {
			background: #12b76a;
		}

		/* En el card (desktop) el stack de botones va oculto en mobile: ahí las acciones viven en
		   el bottom nav de abajo. */
		@media (max-width: 767.98px) {
			.fichaje-acciones {
				display: none;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.fichaje-acciones button,
			.fichaje-bottom-nav-item {
				transition: none;
			}
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid fichaje-app">
			@if (! $miembro)
				<div class="row">
					<div class="col-12">
						<div class="card">
							<div class="card-body">
								<div class="alert alert-warning mb-0">
									Tu cuenta todavía no tiene un perfil de miembro de equipo asociado.
									Contacta con administración para poder fichar.
								</div>
							</div>
						</div>
					</div>
				</div>
			@else
				<div class="row justify-content-center">
					<div class="col-12 col-lg-8 col-xl-6">
						{{-- Una sola card "app": mapa arriba, estado+reloj+acción, resumen de hoy debajo --}}
						<div class="card fichaje-hero" data-estado="{{ $estado }}">
							<div id="fichaje-mapa"></div>

							<div class="card-body">
								<div class="fichaje-hero-top">
									<span id="fichaje-estado-badge" class="badge fichaje-estado-badge {{ ['cerrada' => 'bg-secondary', 'abierta' => 'bg-primary', 'en_pausa' => 'bg-warning text-dark'][$estado] }}" data-estado="{{ $estado }}">
										{{ ['cerrada' => 'Sin jornada abierta', 'abierta' => 'Jornada abierta', 'en_pausa' => 'En pausa'][$estado] }}
									</span>
									<span class="fichaje-turno-chip">
										@if ($turnoHoy === null)
											Sin horario asignado hoy
										@elseif (empty($turnoHoy))
											Hoy es tu día libre
										@else
											Turno hoy:
											@foreach ($turnoHoy as $i => $tramo)
												{{ $tramo['hora_inicio'] }}–{{ $tramo['hora_fin'] }}{{ $i < count($turnoHoy) - 1 ? ',' : '' }}
											@endforeach
										@endif
									</span>
									@if (! empty($turnoHoy))
										{{-- Se recalcula en vivo en JS (tickReloj) contra la hora local del navegador:
											indica si el instante actual cae dentro de alguno de los tramos de hoy. --}}
										<span id="fichaje-horario-badge" class="fichaje-horario-badge"></span>
									@endif
								</div>

								<div class="fichaje-reloj" id="fichaje-reloj" aria-live="off">--:--:--</div>
								<div class="fichaje-fecha">{{ $fechaHoy }}</div>

								<div class="fichaje-acciones" id="fichaje-botones" data-registrar-pausas="{{ $registrarPausas ? '1' : '0' }}">
									<button type="button" class="btn btn-primary btn-lg btn-fichar-principal" data-tipo="entrada">
										{{-- colors explícito (excepción documentada en docs/04-front-guidelines.md): el ícono
											va en blanco fijo sobre el fondo sólido --primary del botón, igual que el resto
											del contenido del botón (texto blanco por defecto de .btn-primary). --}}
										<x-lordicon icon="wired-outline-1846-employee-working-hover-working" size="26" trigger="hover" colors="primary:#ffffff,secondary:#ffffff" />
										<span>Fichar entrada</span>
									</button>
									<button type="button" class="btn btn-primary btn-lg btn-fichar-principal" data-tipo="fin_pausa">
										<span>Terminar pausa</span>
									</button>
									<button type="button" class="btn btn-outline-danger btn-fichar-principal" data-tipo="salida">
										<span>Fichar salida</span>
									</button>
									@if ($registrarPausas)
										<button type="button" class="btn btn-outline-secondary" data-tipo="inicio_pausa">
											Iniciar pausa
										</button>
									@endif
								</div>

								<div id="fichaje-precision-info" class="fichaje-hint"></div>

								<hr>

								<div class="fichaje-horas-hoy">
									<span class="valor" id="fichaje-horas-hoy">00:00:00</span>
									<span class="etiqueta">horas trabajadas hoy</span>
								</div>

								@if ($eventosHoy->isEmpty())
									<p class="fichaje-timeline-vacio" id="fichaje-timeline-vacio">Todavía no has fichado nada hoy.</p>
								@endif
								<ul class="fichaje-timeline" id="fichaje-timeline">
									@foreach ($eventosHoy as $evento)
										<li>
											<span class="tipo-ubicacion">
												<span class="tipo">{{ $evento->tipo->label() }}</span>
												<span class="ubicacion">{{ $evento->resultado_ubicacion?->label() ?? '—' }}</span>
											</span>
											<span class="hora">{{ $evento->ocurrido_at->enZonaTenant()->format('H:i') }}</span>
										</li>
									@endforeach
								</ul>

								<a href="{{ route('mi-jornada.index') }}" class="btn btn-outline-primary w-100 mt-3">
									Ver mi jornada completa
								</a>

								<hr>

								<p class="text-muted small mb-0">
									Al fichar se captura tu posición únicamente en el instante de la acción, para
									comprobar si estás dentro del perímetro autorizado de tu centro de trabajo. No se
									guarda un rastro continuo de tu ubicación ni tus coordenadas exactas: solo el
									resultado (dentro/fuera), la distancia calculada y la precisión reportada por tu
									dispositivo.
								</p>
							</div>
						</div>
					</div>
				</div>
			@endif
		</div>
	</div>

	@if ($miembro)
		{{-- Barra inferior tipo app móvil: en esta pantalla NO es navegación, son las 3 acciones de
			fichaje (entrada / pausa / salida). Los 3 slots están siempre visibles y se habilitan o
			deshabilitan según el estado (nunca desaparecen ni cambian de posición), para que la barra
			no "salte" — el JS (fichaje-app.init.js) controla disabled + el ícono central dinámico. --}}
		<nav class="fichaje-bottom-nav d-md-none" aria-label="Acciones de fichaje" id="fichaje-bottom-nav" data-registrar-pausas="{{ $registrarPausas ? '1' : '0' }}">
			<button type="button" class="fichaje-bottom-nav-item fichaje-nav-btn" data-rol="entrada" data-tipo="entrada">
				<x-lordicon icon="wired-outline-983-smart-lock-card-hover-pinch" size="26" trigger="hover" />
				<span>Entrada</span>
			</button>
			<button type="button" class="fichaje-bottom-nav-item fichaje-bottom-nav-item--main fichaje-nav-btn" data-rol="pausa" data-tipo="inicio_pausa">
				{{-- colors explícito (excepción documentada en docs/04-front-guidelines.md): los
					íconos van en blanco fijo sobre el círculo de fondo --primary. --}}
				<span class="fichaje-bottom-nav-fab">
					<span class="fichaje-nav-icono-pausa">
						<x-lordicon icon="wired-outline-3097-pause-circle-hover-pinch" size="26" trigger="hover" colors="primary:#ffffff,secondary:#ffffff" />
					</span>
					<span class="fichaje-nav-icono-reanudar d-none">
						<x-lordicon icon="wired-outline-29-play-pause-circle-hover-pinch" size="26" trigger="hover" colors="primary:#ffffff,secondary:#ffffff" />
					</span>
				</span>
				<span class="fichaje-nav-label-pausa">Pausa</span>
			</button>
			<button type="button" class="fichaje-bottom-nav-item fichaje-nav-btn" data-rol="salida" data-tipo="salida">
				<x-lordicon icon="wired-outline-2185-logout-hover-pinch" size="26" trigger="hover" />
				<span>Salida</span>
			</button>
		</nav>
	@endif
@endsection

@if ($miembro)
	@push('scripts')
		<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
		<script>
			window.fichajeState = {
				storeUrl: @json(route('fichajes.store')),
				tieneUbicacionTrabajo: @json($miembro->tieneUbicacionTrabajo()),
				trabajoLatitud: @json($miembro->tieneUbicacionTrabajo() ? (float) $miembro->trabajo_latitud : null),
				trabajoLongitud: @json($miembro->tieneUbicacionTrabajo() ? (float) $miembro->trabajo_longitud : null),
				distanciaMaxMetros: @json($miembro->distancia_max_metros),
				csrf: @json(csrf_token()),
				estado: @json($estado),
				registrarPausas: @json($registrarPausas),
				resumenHoy: @json($resumenHoy),
				turnoHoy: @json($turnoHoy),
			};
		</script>
		<script src="{{ asset('js/plugins-init/fichaje-app.init.js') }}"></script>
	@endpush
@endif
