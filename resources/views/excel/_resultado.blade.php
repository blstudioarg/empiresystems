@if ($tipo === 'previsualizacion')
	<div class="card">
		<div class="card-header">
			<h5 class="card-title mb-0">Previsualización</h5>
		</div>
		<div class="card-body">
			<p class="mb-3">
				<strong>{{ $datos->totalFilas }}</strong> filas leídas,
				<strong class="text-success">{{ $datos->validas }}</strong> válidas,
				<strong class="text-danger">{{ count($datos->rechazadas) }}</strong> rechazadas.
			</p>

			@if (count($datos->rechazadas) > 0)
				<p class="text-danger mb-2">Filas rechazadas:</p>
				<ul class="list-group list-group-flush mb-3" style="max-height: 220px; overflow-y: auto;">
					@foreach ($datos->rechazadas as $rechazo)
						<li class="list-group-item px-0">Fila {{ $rechazo->fila }}: {{ $rechazo->motivo }}</li>
					@endforeach
				</ul>
			@endif

			@if (count($datos->muestra) > 0)
				<p class="mb-2">Muestra de filas válidas:</p>
				<div class="table-responsive mb-3">
					<table class="table table-sm">
						<thead>
							<tr>
								@foreach (array_keys($datos->muestra[0]) as $clave)
									<th>{{ $clave }}</th>
								@endforeach
							</tr>
						</thead>
						<tbody>
							@foreach (array_slice($datos->muestra, 0, 10) as $fila)
								<tr>
									@foreach ($fila as $valor)
										<td>{{ is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor }}</td>
									@endforeach
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif

			@if ($datos->validas > 0)
				<form method="POST" action="{{ route($modulo.'.importar.confirmar', ['modulo' => $modulo]) }}">
					@csrf
					<input type="hidden" name="token" value="{{ $datos->token }}">
					<button type="submit" class="btn btn-success" data-loading-text="Importando...">
						Confirmar e importar {{ $datos->validas }}
					</button>
				</form>
			@else
				<p class="text-muted mb-0">No hay ninguna fila válida para importar.</p>
			@endif
		</div>
	</div>
@else
	<div class="card">
		<div class="card-header">
			<h5 class="card-title mb-0">Resultado de la importación</h5>
		</div>
		<div class="card-body">
			<p class="mb-3"><strong>{{ $datos['importados'] }}</strong> registros importados correctamente.</p>

			@if (count($datos['rechazadas']) > 0)
				<p class="text-danger mb-2"><strong>{{ count($datos['rechazadas']) }}</strong> filas rechazadas:</p>
				<ul class="list-group list-group-flush mb-3" style="max-height: 220px; overflow-y: auto;">
					@foreach ($datos['rechazadas'] as $rechazo)
						<li class="list-group-item px-0">Fila {{ $rechazo['fila'] }}: {{ $rechazo['motivo'] }}</li>
					@endforeach
				</ul>
				<a href="{{ route($modulo.'.importar.rechazos', ['modulo' => $modulo, 'token' => $datos['token']]) }}" class="btn btn-outline-danger btn-sm">
					Descargar detalle de rechazos
				</a>
			@else
				<p class="text-success mb-0">Sin filas rechazadas.</p>
			@endif
		</div>
	</div>
@endif
