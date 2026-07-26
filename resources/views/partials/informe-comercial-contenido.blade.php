@php
	$bloques = $datos['alcance']['bloques_visibles'];
	$ind = $datos['indicadores'];
	$rat = $datos['ratios'];
	$comparativa = $datos['comparativa'];

	$fmtRatio = function ($valor, string $sufijo = '%') {
		return $valor === null
			? '<span class="ratio-sin-datos">Sin datos</span>'
			: \App\Support\Formato::porcentaje($valor).$sufijo;
	};
@endphp

{{-- Embudo: Leads → Oportunidades → Presupuestos → Negocio cerrado --}}
<div class="row">
	@if (in_array('leads', $bloques, true))
		<div class="col-xl-3 col-sm-6 embudo-paso">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">
						Leads captados
						<span class="criterio-badge criterio-cohorte" title="Adscrito por fecha de captación del lead">Cohorte</span>
					</h6>
					<h3 class="mb-1">{{ $ind['leads_captados'] }}</h3>
					<small class="text-muted">{{ $ind['leads_convertidos'] }} convertidos en cliente</small>
				</div>
			</div>
			<i class="fas fa-arrow-right embudo-flecha"></i>
		</div>
	@endif

	@if (in_array('oportunidades', $bloques, true))
		<div class="col-xl-3 col-sm-6 embudo-paso">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">
						Oportunidades abiertas
						<span class="criterio-badge criterio-instantanea" title="Instantánea a la fecha de corte del periodo">Instantánea</span>
					</h6>
					<h3 class="mb-1">{{ $ind['oportunidades_abiertas'] }}</h3>
					<small class="text-muted">{{ \App\Support\Formato::moneda($ind['importe_pipeline']) }} € en pipeline</small>
				</div>
			</div>
			<i class="fas fa-arrow-right embudo-flecha"></i>
		</div>
	@endif

	@if (in_array('presupuestos', $bloques, true))
		<div class="col-xl-3 col-sm-6 embudo-paso">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">
						Presupuestos emitidos
						<span class="criterio-badge criterio-cohorte" title="Adscrito por fecha de emisión del presupuesto">Cohorte</span>
					</h6>
					<h3 class="mb-1">{{ $ind['presupuestos_emitidos'] }}</h3>
					<small class="text-muted">{{ \App\Support\Formato::moneda($ind['importe_presupuestado']) }} € presupuestado</small>
				</div>
			</div>
			<i class="fas fa-arrow-right embudo-flecha"></i>
		</div>

		<div class="col-xl-3 col-sm-6 embudo-paso">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">
						Negocio cerrado del embudo
						<span class="criterio-badge criterio-cohorte" title="Facturas originadas en un presupuesto del periodo (adscrito por fecha de emisión del presupuesto)">Cohorte</span>
					</h6>
					<h3 class="mb-1">{{ $ind['facturas_del_embudo'] }}</h3>
					<small class="text-muted">{{ \App\Support\Formato::moneda($ind['importe_facturado_embudo']) }} € facturados</small>
				</div>
			</div>
		</div>
	@endif
</div>

{{-- Ratios de eficiencia --}}
<div class="row">
	@if (in_array('leads', $bloques, true))
		<div class="col-xl-3 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Conversión lead → cliente</h6>
					<h4 class="mb-0">{!! $fmtRatio($rat['conversion_lead_cliente']) !!}</h4>
				</div>
			</div>
		</div>
	@endif
	@if (in_array('oportunidades', $bloques, true))
		<div class="col-xl-3 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">
						Oportunidades ganadas
						<span class="criterio-badge criterio-evento" title="Sobre oportunidades cerradas en el periodo (fecha de cierre)">Evento</span>
					</h6>
					<h4 class="mb-0">{!! $fmtRatio($rat['oportunidades_ganadas']) !!}</h4>
				</div>
			</div>
		</div>
	@endif
	@if (in_array('presupuestos', $bloques, true))
		<div class="col-xl-3 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Aceptación de presupuestos</h6>
					<h4 class="mb-0">{!! $fmtRatio($rat['aceptacion_presupuestos']) !!}</h4>
				</div>
			</div>
		</div>
		<div class="col-xl-3 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Conversión presupuesto → factura</h6>
					<h4 class="mb-0">{!! $fmtRatio($rat['conversion_presupuesto_factura']) !!}</h4>
				</div>
			</div>
		</div>
	@endif
</div>

<div class="row">
	@if (in_array('oportunidades', $bloques, true))
		<div class="col-xl-4 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Importe medio de oportunidad ganada</h6>
					<h4 class="mb-0">
						@if ($rat['importe_medio_ganada'] === null)
							<span class="ratio-sin-datos">Sin datos</span>
						@else
							{{ \App\Support\Formato::moneda($rat['importe_medio_ganada']) }} €
						@endif
					</h4>
				</div>
			</div>
		</div>
	@endif
	@if (in_array('leads', $bloques, true))
		<div class="col-xl-4 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Ciclo medio lead → cliente</h6>
					<h4 class="mb-0">
						@if ($rat['ciclo_medio_lead_dias'] === null)
							<span class="ratio-sin-datos">Sin datos</span>
						@else
							{{ \App\Support\Formato::cantidad($rat['ciclo_medio_lead_dias']) }} días
						@endif
					</h4>
				</div>
			</div>
		</div>
	@endif
	@if (in_array('oportunidades', $bloques, true))
		<div class="col-xl-4 col-sm-6">
			<div class="card same-card">
				<div class="card-body">
					<h6 class="mb-1">Ciclo medio de oportunidad (apertura → cierre)</h6>
					<h4 class="mb-0">
						@if ($rat['ciclo_medio_oportunidad_dias'] === null)
							<span class="ratio-sin-datos">Sin datos</span>
						@else
							{{ \App\Support\Formato::cantidad($rat['ciclo_medio_oportunidad_dias']) }} días
						@endif
					</h4>
				</div>
			</div>
		</div>
	@endif
</div>

{{-- Evolución temporal --}}
<div class="row">
	<div class="col-xl-12">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Evolución de la actividad comercial</h4>
			</div>
			<div class="card-body p-0">
				@if (collect($datos['evolucion'])->sum('leads') + collect($datos['evolucion'])->sum('oportunidades') + collect($datos['evolucion'])->sum('presupuestos') <= 0)
					<p class="text-muted mb-0 p-3">Todavía no hay actividad en este periodo para mostrar una tendencia.</p>
				@else
					<div id="morris-evolucion-comercial" style="height: 360px;"></div>
				@endif
			</div>
		</div>
	</div>
</div>

{{-- Desglose por fases --}}
<div class="row">
	@if (in_array('leads', $bloques, true))
		<div class="col-xl-4">
			<div class="card same-card">
				<div class="card-header">
					<h4 class="card-title">Leads por estado</h4>
				</div>
				<div class="card-body">
					@if (collect($datos['fases']['leads_por_estado'])->sum('cantidad') <= 0)
						<p class="text-muted mb-0">Sin leads en este periodo.</p>
					@else
						<div style="height: 300px;"><canvas id="chart-leads-por-estado"></canvas></div>
					@endif
				</div>
			</div>
		</div>
	@endif
	@if (in_array('oportunidades', $bloques, true))
		<div class="col-xl-4">
			<div class="card same-card">
				<div class="card-header">
					<h4 class="card-title">Oportunidades por etapa</h4>
				</div>
				<div class="card-body">
					@if (collect($datos['fases']['oportunidades_por_etapa'])->sum('cantidad') <= 0)
						<p class="text-muted mb-0">Sin oportunidades en este periodo.</p>
					@else
						<canvas id="chart-oportunidades-por-etapa" height="220"></canvas>
					@endif
				</div>
			</div>
		</div>
	@endif
	@if (in_array('presupuestos', $bloques, true))
		<div class="col-xl-4">
			<div class="card same-card">
				<div class="card-header">
					<h4 class="card-title">Presupuestos por estado</h4>
				</div>
				<div class="card-body">
					@if (collect($datos['fases']['presupuestos_por_estado'])->sum('cantidad') <= 0)
						<p class="text-muted mb-0">Sin presupuestos en este periodo.</p>
					@else
						<canvas id="chart-presupuestos-por-estado" height="220"></canvas>
					@endif
				</div>
			</div>
		</div>
	@endif
</div>

{{-- Top artículos más presupuestados --}}
@if (in_array('presupuestos', $bloques, true))
	<div class="row">
		<div class="col-xl-12">
			<div class="card same-card">
				<div class="card-header">
					<h4 class="card-title">Artículos más presupuestados</h4>
				</div>
				<div class="card-body">
					@if (empty($datos['top_articulos']))
						<p class="text-muted mb-0">Todavía no hay líneas de presupuesto con artículo en este periodo.</p>
					@else
						<ul class="list-group list-group-flush">
							@foreach ($datos['top_articulos'] as $articulo)
								<li class="list-group-item d-flex justify-content-between align-items-center px-0">
									<span>{{ $articulo['nombre'] }}</span>
									<span>
										<strong>{{ \App\Support\Formato::moneda($articulo['importe']) }} €</strong>
										<small class="text-muted ms-2">{{ \App\Support\Formato::cantidad($articulo['unidades']) }} uds.</small>
									</span>
								</li>
							@endforeach
						</ul>
					@endif
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Comparativa entre ejercicios --}}
@if ($comparativa !== null)
	<div class="row">
		<div class="col-xl-12">
			<div class="card same-card">
				<div class="card-header">
					<h4 class="card-title">
						Comparativa vs. {{ $comparativa['periodo']['etiqueta'] }}
					</h4>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-borderless mb-0">
							<thead>
								<tr>
									<th>Indicador</th>
									<th class="text-end">Actual</th>
									<th class="text-end">Ejercicio anterior</th>
									<th class="text-end">Variación</th>
								</tr>
							</thead>
							<tbody>
								@foreach ([
									'leads_captados' => 'Leads captados',
									'leads_convertidos' => 'Leads convertidos',
									'oportunidades_creadas' => 'Oportunidades creadas',
									'oportunidades_ganadas' => 'Oportunidades ganadas',
									'presupuestos_emitidos' => 'Presupuestos emitidos',
									'facturas_del_embudo' => 'Negocio cerrado del embudo',
								] as $clave => $etiqueta)
									<tr>
										<td>{{ $etiqueta }}</td>
										<td class="text-end">{{ $ind[$clave] }}</td>
										<td class="text-end">{{ $comparativa['indicadores'][$clave] }}</td>
										<td class="text-end">
											@php $variacion = $comparativa['variaciones']['indicadores'][$clave]; @endphp
											@if ($variacion === null)
												<span class="ratio-sin-datos">No calculable</span>
											@else
												<span class="{{ $variacion >= 0 ? 'text-success' : 'text-danger' }}">
													<i class="fas fa-arrow-{{ $variacion >= 0 ? 'up' : 'down' }}"></i>
													{{ \App\Support\Formato::porcentaje(abs($variacion)) }}%
												</span>
											@endif
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
@endif
