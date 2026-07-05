@extends('layouts.app')

@section('title', 'Dashboard')

@php
	$datosGraficos = [
		'serie_facturacion_12_meses' => $datos['serie_facturacion_12_meses'],
		'comparativo_6_meses' => $datos['comparativo_6_meses'],
		'distribucion_estados' => $datos['distribucion_estados'],
	];
@endphp

@push('scripts')
	<script src="{{ asset('vendor/raphael/raphael.min.js') }}"></script>
	<script src="{{ asset('vendor/morris/morris.min.js') }}"></script>
	<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
	<script>
		window.dashboardData = @json($datosGraficos);
	</script>
	<script src="{{ asset('js/plugins-init/dashboard-charts.init.js') }}"></script>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			{{-- KPIs del mes en curso --}}
			<div class="row">
				<div class="col-xl-3 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Facturado este mes</h6>
									<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['facturado_mes']['valor']) }} €</h3>
									@if ($datos['kpis']['facturado_mes']['variacion_pct'] === null)
										<small class="text-muted">Sin datos previos</small>
									@else
										<small class="{{ $datos['kpis']['facturado_mes']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
											<i class="fas fa-arrow-{{ $datos['kpis']['facturado_mes']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
											{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['facturado_mes']['variacion_pct'])) }}% vs mes anterior
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
									<h6 class="mb-1">Cobrado este mes</h6>
									<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['cobrado_mes']['valor']) }} €</h3>
									@if ($datos['kpis']['cobrado_mes']['variacion_pct'] === null)
										<small class="text-muted">Sin datos previos</small>
									@else
										<small class="{{ $datos['kpis']['cobrado_mes']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
											<i class="fas fa-arrow-{{ $datos['kpis']['cobrado_mes']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
											{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['cobrado_mes']['variacion_pct'])) }}% vs mes anterior
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
									<h6 class="mb-1">Pendiente de cobro</h6>
									<h3 class="mb-0">{{ \App\Support\Formato::moneda($datos['kpis']['pendiente_cobro']['valor']) }} €</h3>
									<small class="text-muted">Total a día de hoy</small>
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
									<h6 class="mb-1">Facturas emitidas este mes</h6>
									<h3 class="mb-0">{{ $datos['kpis']['num_facturas_mes']['valor'] }}</h3>
									@if ($datos['kpis']['num_facturas_mes']['variacion_pct'] === null)
										<small class="text-muted">Sin datos previos</small>
									@else
										<small class="{{ $datos['kpis']['num_facturas_mes']['variacion_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
											<i class="fas fa-arrow-{{ $datos['kpis']['num_facturas_mes']['variacion_pct'] >= 0 ? 'up' : 'down' }}"></i>
											{{ \App\Support\Formato::porcentaje(abs($datos['kpis']['num_facturas_mes']['variacion_pct'])) }}% vs mes anterior
										</small>
									@endif
								</div>
								<div>
									<x-lordicon icon="ticket" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			{{-- Tendencia de facturación y brecha de cobro --}}
			<div class="row">
				<div class="col-xl-8">
					<div class="card same-card">
						<div class="card-header">
							<h4 class="card-title">Evolución de facturación (12 meses)</h4>
						</div>
						<div class="card-body p-0">
							@if (collect($datos['serie_facturacion_12_meses'])->sum('facturado') <= 0)
								<p class="text-muted mb-0 p-3">Todavía no hay facturación registrada para mostrar una tendencia.</p>
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
								<p class="text-muted mb-0">Todavía no hay facturas registradas.</p>
							@else
								<div style="height: 360px;">
									<canvas id="chart-distribucion-estados"></canvas>
								</div>
							@endif
						</div>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-8">
					<div class="card same-card">
						<div class="card-header">
							<h4 class="card-title">Facturado vs. cobrado (6 meses)</h4>
						</div>
						<div class="card-body">
							@if (collect($datos['comparativo_6_meses'])->sum('facturado') <= 0 && collect($datos['comparativo_6_meses'])->sum('cobrado') <= 0)
								<p class="text-muted mb-0">Todavía no hay actividad para comparar facturación y cobro.</p>
							@else
								<canvas id="chart-comparativo" height="90"></canvas>
							@endif
						</div>
					</div>
				</div>
				<div class="col-xl-4">
					<div class="card same-card">
						<div class="card-header">
							<h4 class="card-title">Brecha de cobro (mes actual)</h4>
						</div>
						<div class="card-body">
							@php
								$ultimoMes = collect($datos['comparativo_6_meses'])->last();
								$brecha = $ultimoMes ? $ultimoMes['facturado'] - $ultimoMes['cobrado'] : 0;
							@endphp
							@if ($brecha <= 0)
								<p class="text-success mb-0">Sin brecha: lo cobrado cubre lo facturado este mes.</p>
							@else
								<h3 class="mb-1">{{ \App\Support\Formato::moneda($brecha) }} €</h3>
								<p class="text-muted mb-0">Todavía sin cobrar del total facturado este mes.</p>
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
								<p class="text-muted mb-0">Todavía no hay facturas asignadas a clientes.</p>
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
							<h4 class="card-title">Alertas de stock</h4>
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
								<p class="text-muted mb-0">Todavía no hay facturas emitidas.</p>
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

		</div>
	</div>

	<script>
		document.querySelectorAll('.factura-reciente-fila').forEach(function (fila) {
			fila.addEventListener('click', function () {
				window.location.href = fila.dataset.href;
			});
		});
	</script>
@endsection
