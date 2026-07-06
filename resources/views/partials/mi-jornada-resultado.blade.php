<div class="d-flex justify-content-between align-items-center mb-2">
	<div class="text-muted">Periodo: {{ $rango->etiqueta() }}</div>
	<button type="button" class="btn btn-outline-primary btn-sm btn-ver-pdf-mi-jornada"
		data-pdf-url="{{ route('mi-jornada.exportar', ['preset' => 'personalizado', 'desde' => $rango->desde->toDateString(), 'hasta' => $rango->hasta->toDateString()]) }}">
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
				</tr>
			@empty
				<tr><td colspan="4" class="text-muted">Sin fichajes en el periodo seleccionado.</td></tr>
			@endforelse
		</tbody>
	</table>
</div>

<div class="text-end fw-bold">
	Total horas efectivas: {{ number_format($datos['total_horas'], 2, ',', '.') }} h
</div>
