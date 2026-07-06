{{-- KPIs financieros del periodo --}}
<div class="row">
	<div class="col-xl-3 col-sm-6">
		<div class="card same-card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h6 class="mb-1">Facturado</h6>
						<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['facturado']['valor']) }} €</h3>
						@if ($datos['kpis']['facturado']['variacion_pct'] === null)
							<small class="text-muted">Sin datos previos</small>
						@else
							<small class="{{ $datos['kpis']['facturado']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
								<i class="fas fa-arrow-{{ $datos['kpis']['facturado']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
								{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['facturado']['variacion_pct'])) }}% vs periodo anterior
							</small>
						@endif
					</div>
					<div>
						<x-lordicon icon="invoice" size="50" trigger="hover" target=".card" />
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
						<h6 class="mb-1">Cobrado</h6>
						<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['cobrado']['valor']) }} €</h3>
						@if ($datos['kpis']['cobrado']['variacion_pct'] === null)
							<small class="text-muted">Sin datos previos</small>
						@else
							<small class="{{ $datos['kpis']['cobrado']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
								<i class="fas fa-arrow-{{ $datos['kpis']['cobrado']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
								{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['cobrado']['variacion_pct'])) }}% vs periodo anterior
							</small>
						@endif
					</div>
					<div>
						<x-lordicon icon="euro" size="50" trigger="hover" target=".card" />
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
						<h6 class="mb-1">Gastos</h6>
						<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['gastos']['valor']) }} €</h3>
						<small class="text-muted">Compras confirmadas del periodo</small>
					</div>
					<div>
						<x-lordicon icon="wired-outline-153-bar-chart" size="50" trigger="hover" target=".card" />
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
						<h6 class="mb-1">Resultado</h6>
						<h3 class="mb-0 {{ $datos['kpis']['resultado']['valor'] >= 0 ? 'text-success' : 'text-danger' }}">
							{{ \App\Support\Formato::moneda($datos['kpis']['resultado']['valor']) }} €
						</h3>
						<small class="text-muted">Facturado − gastos</small>
					</div>
					<div>
						<x-lordicon icon="ticket" size="50" trigger="hover" target=".card" />
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- KPIs operativos: a día de hoy y ventas POS --}}
<div class="row">
	<div class="col-xl-4 col-sm-6">
		<div class="card same-card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h6 class="mb-1">Pendiente de cobro</h6>
						<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['pendiente_cobro']['valor']) }} €</h3>
						<small class="text-muted"><i class="fas fa-clock me-1"></i>A día de hoy, no depende del periodo</small>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-4 col-sm-6">
		<div class="card same-card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h6 class="mb-1">Facturas emitidas</h6>
						<h3 class="mb-0">{{ $datos['kpis']['num_facturas']['valor'] }}</h3>
						@if ($datos['kpis']['num_facturas']['variacion_pct'] === null)
							<small class="text-muted">Sin datos previos</small>
						@else
							<small class="{{ $datos['kpis']['num_facturas']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
								<i class="fas fa-arrow-{{ $datos['kpis']['num_facturas']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
								{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['num_facturas']['variacion_pct'])) }}% vs periodo anterior
							</small>
						@endif
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-4 col-sm-6">
		<div class="card same-card">
			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center">
					<div>
						<h6 class="mb-1">Ventas POS</h6>
						<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['ventas_pos']['valor']) }} €</h3>
						<small class="text-muted">Tickets simplificados, aparte del facturado</small>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- Tendencia de facturación y distribución --}}
<div class="row">
	<div class="col-xl-8">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Evolución de facturación</h4>
			</div>
			<div class="card-body p-0">
				@if (collect($datos['serie_facturacion'])->sum('facturado') <= 0)
					<p class="text-muted mb-0 p-3">Todavía no hay facturación registrada en este periodo para mostrar una tendencia.</p>
				@else
					<div id="morris-serie-facturacion" style="height: 360px;"></div>
				@endif
			</div>
		</div>
	</div>
	<div class="col-xl-4">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Distribución por estado</h4>
			</div>
			<div class="card-body">
				@if (collect($datos['distribucion_estados'])->sum('cantidad') <= 0)
					<p class="text-muted mb-0">Todavía no hay facturas registradas en este periodo.</p>
				@else
					<div style="height: 360px;">
						<canvas id="chart-distribucion-estados"></canvas>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>

{{-- Facturado vs. cobrado e IVA --}}
<div class="row">
	<div class="col-xl-8">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Facturado vs. cobrado</h4>
			</div>
			<div class="card-body">
				@if (collect($datos['comparativo'])->sum('facturado') <= 0 && collect($datos['comparativo'])->sum('cobrado') <= 0)
					<p class="text-muted mb-0">Todavía no hay actividad en este periodo para comparar facturación y cobro.</p>
				@else
					<canvas id="chart-comparativo" height="90"></canvas>
				@endif
			</div>
		</div>
	</div>
	<div class="col-xl-4">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">{{ $datos['impuestos']['etiqueta'] }} repercutido vs. soportado</h4>
			</div>
			<div class="card-body">
				@if ($datos['impuestos']['repercutido'] <= 0 && $datos['impuestos']['soportado'] <= 0)
					<p class="text-muted mb-0">Todavía no hay actividad en este periodo para calcular impuestos.</p>
				@else
					@php
						$aLiquidar = $datos['impuestos']['repercutido'] - $datos['impuestos']['soportado'];
					@endphp
					<ul class="list-group list-group-flush">
						<li class="list-group-item d-flex justify-content-between align-items-center px-0">
							<span>Repercutido</span>
							<strong>{{ \App\Support\Formato::moneda($datos['impuestos']['repercutido']) }} €</strong>
						</li>
						<li class="list-group-item d-flex justify-content-between align-items-center px-0">
							<span>Soportado</span>
							<strong>{{ \App\Support\Formato::moneda($datos['impuestos']['soportado']) }} €</strong>
						</li>
						<li class="list-group-item d-flex justify-content-between align-items-center px-0">
							<span>{{ $aLiquidar >= 0 ? 'A liquidar' : 'A compensar' }}</span>
							<strong class="{{ $aLiquidar >= 0 ? 'text-danger' : 'text-success' }}">
								{{ \App\Support\Formato::moneda(abs($aLiquidar)) }} €
							</strong>
						</li>
					</ul>
				@endif
			</div>
		</div>
	</div>
</div>

{{-- Composición de cartera y accesos rápidos --}}
<div class="row">
	<div class="col-xl-6">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Top 5 clientes</h4>
				<a href="{{ route('clientes.index') }}" class="btn btn-primary light btn-sm">Ver clientes</a>
			</div>
			<div class="card-body">
				@if (empty($datos['top_clientes']))
					<p class="text-muted mb-0">Todavía no hay facturas asignadas a clientes en este periodo.</p>
				@else
					<ul class="list-group list-group-flush">
						@foreach ($datos['top_clientes'] as $cliente)
							<li class="list-group-item d-flex justify-content-between align-items-center px-0">
								<span>{{ $cliente['nombre'] }}</span>
								<strong>{{ \App\Support\Formato::moneda($cliente['total_facturado']) }} €</strong>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
		</div>
	</div>
	<div class="col-xl-6">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">
					Alertas de stock
					<small class="d-block text-muted fw-normal"><i class="fas fa-clock me-1"></i>A día de hoy</small>
				</h4>
				@if ($datos['alertas_stock']['gestiona_stock'])
					<a href="{{ route('stock.index') }}" class="btn btn-primary light btn-sm">Ver stock</a>
				@endif
			</div>
			<div class="card-body">
				@if (! $datos['alertas_stock']['gestiona_stock'])
					<p class="text-muted mb-0">Este tenant no gestiona stock de artículos.</p>
				@elseif (empty($datos['alertas_stock']['items']))
					<p class="text-success mb-0">Sin alertas: todo el stock está por encima de su mínimo.</p>
				@else
					<ul class="list-group list-group-flush">
						@foreach ($datos['alertas_stock']['items'] as $item)
							<li class="list-group-item d-flex justify-content-between align-items-center px-0">
								<span>{{ $item['nombre'] }}</span>
								<span class="text-danger">
									{{ \App\Support\Formato::cantidad($item['stock_actual']) }}
									@if ($item['stock_minimo'] !== null)
										/ mín. {{ \App\Support\Formato::cantidad($item['stock_minimo']) }}
									@endif
								</span>
							</li>
						@endforeach
					</ul>
				@endif
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-xl-12">
		<div class="card same-card">
			<div class="card-header">
				<h4 class="card-title">Últimas facturas emitidas</h4>
				<a href="{{ route('facturas.index') }}" class="btn btn-primary light btn-sm">Ver todas</a>
			</div>
			<div class="card-body">
				@if (empty($datos['facturas_recientes']))
					<p class="text-muted mb-0">Todavía no hay facturas emitidas en este periodo.</p>
				@else
					<div class="table-responsive">
						<table class="table table-borderless mb-0">
							<thead>
								<tr>
									<th>Número</th>
									<th>Cliente</th>
									<th>Estado</th>
									<th>Fecha</th>
									<th class="text-end">Total</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($datos['facturas_recientes'] as $factura)
									<tr class="factura-reciente-fila" data-href="{{ route('facturas.pdf', $factura['id']) }}" style="cursor: pointer;">
										<td>{{ $factura['numero_completo'] }}</td>
										<td>{{ $factura['cliente_nombre'] }}</td>
										<td>{{ ucfirst($factura['estado']) }}</td>
										<td>{{ \Illuminate\Support\Carbon::parse($factura['fecha_expedicion'])->format('d/m/Y') }}</td>
										<td class="text-end">{{ \App\Support\Formato::moneda($factura['total']) }} €</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
			</div>
		</div>
	</div>
</div>
