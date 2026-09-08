@extends('layouts.app')

@section('title', 'Panel de Super Admin')

@push('scripts')
	<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
	<script>
		window.panelSuperAdminState = @json(['serie_altas' => $datos['serie_altas'] ?? []]);
	</script>
	<script src="@assetv('js/plugins-init/super-admin-panel.init.js')"></script>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			@if (($datos['totales']['tenants'] ?? 0) === 0)
				<div class="row">
					<div class="col-12">
						<div class="card same-card">
							<div class="card-body text-center py-5">
								<h4 class="mb-2">Todavía no hay tenants dados de alta</h4>
								<p class="text-muted mb-4">Cuando crees el primero, vas a ver acá el estado del conjunto:
									altas, usuarios y actividad.</p>
								<a href="{{ route('super_admin.tenants.index') }}" class="btn btn-primary">Crear el primer tenant</a>
							</div>
						</div>
					</div>
				</div>
			@else
				{{-- Tarjetas de métricas --}}
				<div class="row">
					<div class="col-xl-4 col-sm-6">
						<div class="card same-card">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<h6 class="mb-1">Tenants totales</h6>
										<h4 class="mb-0" data-metric="tenants">{{ $datos['totales']['tenants'] }}</h4>
									</div>
									<div>
										<x-lordicon icon="empresa" size="38" trigger="hover" target=".card" />
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
										<h6 class="mb-1">Activos</h6>
										<h4 class="mb-0 text-success" data-metric="activos">{{ $datos['totales']['activos'] }}</h4>
									</div>
									<div>
										<x-lordicon icon="wired-outline-267-like-thumb-up-hover-up" size="38" trigger="hover" target=".card" />
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
										<h6 class="mb-1">Inactivos</h6>
										<h4 class="mb-0 {{ $datos['totales']['inactivos'] > 0 ? 'text-danger' : '' }}" data-metric="inactivos">{{ $datos['totales']['inactivos'] }}</h4>
									</div>
									<div>
										<x-lordicon icon="wired-outline-50-minus-circle" size="38" trigger="hover" target=".card" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-xl-6 col-sm-6">
						<div class="card same-card">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<h6 class="mb-1">Altas este mes</h6>
										<h4 class="mb-0" data-metric="altas_mes">{{ $datos['totales']['altas_mes'] }}</h4>
									</div>
									<div>
										<x-lordicon icon="wired-outline-49-plus-circle" size="38" trigger="hover" target=".card" />
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-xl-6 col-sm-6">
						<div class="card same-card">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-center">
									<div>
										<h6 class="mb-1">Usuarios totales</h6>
										<h4 class="mb-0" data-metric="usuarios">{{ $datos['totales']['usuarios'] }}</h4>
									</div>
									<div>
										<x-lordicon icon="people" size="38" trigger="hover" target=".card" />
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				{{-- Evolución de altas --}}
				<div class="row">
					<div class="col-xl-12">
						<div class="card same-card">
							<div class="card-header">
								<h4 class="card-title">Evolución de altas de tenants (últimos 12 meses)</h4>
							</div>
							<div class="card-body">
								<canvas id="chart-altas-tenants" height="90"></canvas>
							</div>
						</div>
					</div>
				</div>

				{{-- Últimos tenants creados y ranking por tamaño --}}
				<div class="row">
					<div class="col-xl-6">
						<div class="card same-card">
							<div class="card-header">
								<h4 class="card-title">Últimos tenants creados</h4>
								<a href="{{ route('super_admin.tenants.index') }}" class="btn btn-primary light btn-sm">Gestión de tenants</a>
							</div>
							<div class="card-body">
								@if (empty($datos['ultimos_tenants']))
									<p class="text-muted mb-0">Todavía no hay tenants para mostrar.</p>
								@else
									<div class="table-responsive">
										<table class="table table-borderless mb-0">
											<thead>
												<tr>
													<th>Nombre</th>
													<th>Dominio</th>
													<th>Estado</th>
													<th>Alta</th>
												</tr>
											</thead>
											<tbody>
												@foreach ($datos['ultimos_tenants'] as $tenant)
													<tr class="panel-super-admin-fila" data-href="{{ $tenant['gestion_url'] }}" style="cursor: pointer;">
														<td>{{ $tenant['nombre'] }}</td>
														<td>{{ $tenant['dominio'] ?? '—' }}</td>
														<td>
															<span class="badge {{ $tenant['activo'] ? 'badge-success' : 'badge-danger' }}">
																{{ $tenant['activo'] ? 'Activo' : 'Inactivo' }}
															</span>
														</td>
														<td>{{ $tenant['alta'] }}</td>
													</tr>
												@endforeach
											</tbody>
										</table>
									</div>
								@endif
							</div>
						</div>
					</div>
					<div class="col-xl-6">
						<div class="card same-card">
							<div class="card-header">
								<h4 class="card-title">Ranking por tamaño</h4>
							</div>
							<div class="card-body">
								@if (empty($datos['ranking_tamano']))
									<p class="text-muted mb-0">Todavía no hay datos suficientes para un ranking.</p>
								@else
									<div class="table-responsive">
										<table class="table table-borderless mb-0">
											<thead>
												<tr>
													<th>Tenant</th>
													<th class="text-end">Usuarios</th>
													<th class="text-end">Documentos emitidos</th>
												</tr>
											</thead>
											<tbody>
												@foreach ($datos['ranking_tamano'] as $tenant)
													<tr>
														<td>{{ $tenant['nombre'] }}</td>
														<td class="text-end">{{ $tenant['usuarios'] }}</td>
														<td class="text-end">{{ $tenant['documentos'] }}</td>
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

				{{-- Tenants que requieren atención --}}
				<div class="row">
					<div class="col-xl-12">
						<div class="card same-card">
							<div class="card-header">
								<h4 class="card-title">Requieren atención</h4>
							</div>
							<div class="card-body">
								@if (empty($datos['atencion']))
									<p class="text-success mb-0">Todos los tenants están en orden: sin desactivados, sin
										usuarios bloqueados y con actividad reciente.</p>
								@else
									<div class="table-responsive">
										<table class="table table-borderless mb-0">
											<thead>
												<tr>
													<th>Tenant</th>
													<th>Motivos</th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												@foreach ($datos['atencion'] as $tenant)
													<tr>
														<td>{{ $tenant['nombre'] }}</td>
														<td>
															@foreach ($tenant['motivos'] as $motivo)
																<span class="badge badge-warning me-1">
																	{{ match ($motivo) {
																		'desactivado' => 'Desactivado',
																		'sin_usuarios' => 'Sin usuarios que puedan entrar',
																		'sin_actividad' => 'Sin actividad en 30 días',
																		default => $motivo,
																	} }}
																</span>
															@endforeach
														</td>
														<td class="text-end">
															<a href="{{ $tenant['gestion_url'] }}" class="btn btn-primary light btn-sm">Gestionar</a>
														</td>
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
			@endif

		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Panel de Super Admin')
@section('ayuda')
	@include('ayuda.super-admin-panel')
@endsection
