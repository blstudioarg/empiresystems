@extends('layouts.app')

@section('title', 'Mi jornada')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title">Turno esperado</h4>
						</div>
						<div class="card-body">
							@if ($turnoSemana === null)
								<div class="alert alert-info mb-0">Sin horario asignado actualmente. Consultá con administración si esperabas tener uno.</div>
							@else
								@php
									$nombresDia = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
									$hoyIso = now()->dayOfWeekIso;
								@endphp
								<div class="mb-3">
									<strong>Hoy ({{ $nombresDia[$hoyIso] }}):</strong>
									@if (empty($turnoHoy))
										<span class="text-muted">Día libre</span>
									@else
										@foreach ($turnoHoy as $tramo)
											<span class="badge light badge-primary me-1">{{ $tramo['hora_inicio'] }} – {{ $tramo['hora_fin'] }}</span>
										@endforeach
									@endif
								</div>
								<div class="table-responsive">
									<table class="table table-sm mb-0">
										<thead>
											<tr>
												@foreach ($nombresDia as $nombreDia)
													<th>{{ $nombreDia }}</th>
												@endforeach
											</tr>
										</thead>
										<tbody>
											<tr>
												@foreach ($turnoSemana as $dia)
													<td class="{{ $dia['dia_semana'] === $hoyIso ? 'table-active' : '' }}">
														@if (empty($dia['tramos']))
															<span class="text-muted">Libre</span>
														@else
															@foreach ($dia['tramos'] as $tramo)
																<div>{{ $tramo['hora_inicio'] }}–{{ $tramo['hora_fin'] }}</div>
															@endforeach
														@endif
													</td>
												@endforeach
											</tr>
										</tbody>
									</table>
								</div>
							@endif
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h4 class="card-title">Mi jornada</h4>
						</div>
						<div class="card-body">
							<form id="mi-jornada-filtro-form" method="GET" action="{{ route('mi-jornada.index') }}" class="row g-2 align-items-end mb-3">
								<input type="hidden" name="preset" value="personalizado">
								<div class="col-md-4">
									<label class="form-label" for="desde">Desde</label>
									<input type="date" name="desde" id="desde" class="form-control" value="{{ $rango->desde->toDateString() }}">
								</div>
								<div class="col-md-4">
									<label class="form-label" for="hasta">Hasta</label>
									<input type="date" name="hasta" id="hasta" class="form-control" value="{{ $rango->hasta->toDateString() }}">
								</div>
								<div class="col-md-4">
									<button type="submit" class="btn btn-primary w-100">Consultar</button>
								</div>
							</form>

							<div id="mi-jornada-resultado">
								@include('partials.mi-jornada-resultado')
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="miJornadaPdfModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vista previa de mi jornada</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body p-0" style="height: 80vh;">
					<iframe id="miJornadaPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script src="{{ asset('js/plugins-init/mi-jornada-filtro.init.js') }}"></script>
@endpush
