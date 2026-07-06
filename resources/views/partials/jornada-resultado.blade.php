@if (! $miembroSeleccionado)
	<div class="alert alert-info mb-0">Selecciona un miembro para ver su registro de jornada.</div>
@else
	<div class="d-flex justify-content-between align-items-center mb-2">
		<div class="text-muted">Periodo: {{ $rango->etiqueta() }}</div>
		<button type="button" class="btn btn-outline-primary btn-sm btn-ver-pdf-jornada"
			data-pdf-url="{{ route('jornada.exportar', ['miembro_id' => $miembroSeleccionado->id, 'preset' => 'personalizado', 'desde' => $rango->desde->toDateString(), 'hasta' => $rango->hasta->toDateString()]) }}">
			Exportar PDF
		</button>
	</div>

	<div class="table-responsive">
		<table class="table table-sm">
			<thead>
				<tr>
					<th>Fecha y hora</th>
					<th>Tipo</th>
					<th>Ubicación</th>
					<th>Corrección</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				@forelse ($datos['eventos'] as $evento)
					<tr class="{{ $evento->corrige_fichaje_id ? 'table-warning' : '' }}">
						<td>{{ $evento->ocurrido_at->enZonaTenant()->format('d/m/Y H:i') }}</td>
						<td>{{ $evento->tipo->label() }}</td>
						<td>{{ $evento->resultado_ubicacion?->label() ?? '—' }}</td>
						<td>
							@if ($evento->corrige_fichaje_id)
								Corrige fichaje #{{ $evento->corrige_fichaje_id }}: {{ $evento->motivo }}
							@else
								—
							@endif
						</td>
						<td>
							@if (! $evento->corrige_fichaje_id)
								<button type="button" class="btn btn-outline-secondary btn-sm btn-corregir-fichaje"
									data-url="{{ route('fichajes.corregir', $evento) }}"
									data-tipo="{{ $evento->tipo->value }}"
									data-ocurrido-at="{{ $evento->ocurrido_at->enZonaTenant()->format('Y-m-d\TH:i') }}">
									Corregir
								</button>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="text-muted">Sin fichajes en el periodo seleccionado.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>

	<div class="text-end fw-bold mb-3">
		Total horas efectivas: {{ number_format($datos['total_horas'], 2, ',', '.') }} h
	</div>

	<hr>
	<h6>Cumplimiento previsto vs. real</h6>

	@php
		$badges = [
			'libre' => 'badge-secondary',
			'ausencia' => 'badge-danger',
			'retraso' => 'badge-warning',
			'parcial' => 'badge-warning',
			'cumplido' => 'badge-success',
			'exceso' => 'badge-info',
		];
	@endphp

	<div class="table-responsive">
		<table class="table table-sm">
			<thead>
				<tr>
					<th>Fecha</th>
					<th>Previstas</th>
					<th>Trabajadas</th>
					<th>Dentro / fuera de horario</th>
					<th>Veredicto</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($cumplimiento as $dia)
					<tr>
						<td>{{ $dia->fecha->translatedFormat('d/m/Y') }}</td>
						<td>{{ number_format($dia->horasPrevistas, 2, ',', '.') }} h</td>
						<td>{{ number_format($dia->horasTrabajadas, 2, ',', '.') }} h</td>
						<td>
							<span class="text-success">{{ number_format($dia->horasDentroHorario, 2, ',', '.') }} h</span>
							@if ($dia->horasFueraHorario > 0)
								/ <span class="text-muted">{{ number_format($dia->horasFueraHorario, 2, ',', '.') }} h fuera</span>
							@endif
						</td>
						<td>
							<span class="badge light {{ $badges[$dia->veredicto->value] }}">{{ $dia->veredicto->label() }}</span>
							@if ($dia->incidencia)
								<span class="badge light badge-dark">Incidencia</span>
							@endif
						</td>
					</tr>
				@empty
					<tr><td colspan="5" class="text-muted">Sin días en el periodo seleccionado.</td></tr>
				@endforelse
			</tbody>
		</table>
	</div>
@endif
