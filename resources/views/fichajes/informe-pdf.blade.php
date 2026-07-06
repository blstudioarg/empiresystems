<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Jornada {{ $miembro->user->name }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
		h1 { font-size: 18px; margin: 0 0 5px; }
		.meta { margin-bottom: 15px; color: #444; }
		table { width: 100%; border-collapse: collapse; margin-top: 15px; }
		th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
		th { background: #f4f4f4; }
		.correccion { color: #a15c00; }
		.totales { width: 300px; margin-left: auto; margin-top: 15px; }
		.totales td { border: none; padding: 3px 8px; }
		.totales .total-final { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
	</style>
</head>
<body>
	<h1>Registro de jornada</h1>
	<div class="meta">
		<strong>{{ $miembro->user->name }}</strong>@if ($miembro->puesto) — {{ $miembro->puesto }}@endif<br>
		Periodo: {{ $rango->etiqueta() }}
	</div>

	<table>
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
				<tr class="{{ $evento->corrige_fichaje_id ? 'correccion' : '' }}">
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
				<tr><td colspan="4">Sin fichajes en el periodo seleccionado.</td></tr>
			@endforelse
		</tbody>
	</table>

	<table class="totales">
		<tr class="total-final">
			<td>Total horas efectivas</td>
			<td>{{ number_format($datos['total_horas'], 2, ',', '.') }} h</td>
		</tr>
	</table>

	@isset($cumplimiento)
		<h1 style="margin-top: 25px;">Cumplimiento previsto vs. real</h1>
		<table>
			<thead>
				<tr>
					<th>Fecha</th>
					<th>Previstas</th>
					<th>Trabajadas</th>
					<th>Dentro horario</th>
					<th>Fuera horario</th>
					<th>Veredicto</th>
				</tr>
			</thead>
			<tbody>
				@forelse ($cumplimiento as $dia)
					<tr>
						<td>{{ $dia->fecha->translatedFormat('d/m/Y') }}</td>
						<td>{{ number_format($dia->horasPrevistas, 2, ',', '.') }} h</td>
						<td>{{ number_format($dia->horasTrabajadas, 2, ',', '.') }} h</td>
						<td>{{ number_format($dia->horasDentroHorario, 2, ',', '.') }} h</td>
						<td>{{ number_format($dia->horasFueraHorario, 2, ',', '.') }} h</td>
						<td>{{ $dia->veredicto->label() }}@if ($dia->incidencia) (incidencia) @endif</td>
					</tr>
				@empty
					<tr><td colspan="6">Sin días en el periodo seleccionado.</td></tr>
				@endforelse
			</tbody>
		</table>
	@endisset
</body>
</html>
