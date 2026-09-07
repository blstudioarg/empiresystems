@extends('layouts.app')

@section('title', 'Perfil del cliente')

@push('styles')
	<link href="{{ asset('vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
	<link href="{{ asset('vendor/datatables/responsive/responsive.css') }}" rel="stylesheet">
	<style>
		/* El template estiliza previous/next como flechas de 24px; con texto
		   ("Anterior"/"Siguiente") se rompe en vertical. Una regla por tabla del perfil. */
		#facturas-cliente-table_wrapper .dataTables_paginate .paginate_button.previous,
		#facturas-cliente-table_wrapper .dataTables_paginate .paginate_button.next,
		#presupuestos-cliente-table_wrapper .dataTables_paginate .paginate_button.previous,
		#presupuestos-cliente-table_wrapper .dataTables_paginate .paginate_button.next,
		#albaranes-cliente-table_wrapper .dataTables_paginate .paginate_button.previous,
		#albaranes-cliente-table_wrapper .dataTables_paginate .paginate_button.next,
		#oportunidades-cliente-table_wrapper .dataTables_paginate .paginate_button.previous,
		#oportunidades-cliente-table_wrapper .dataTables_paginate .paginate_button.next,
		#actividad-cliente-table_wrapper .dataTables_paginate .paginate_button.previous,
		#actividad-cliente-table_wrapper .dataTables_paginate .paginate_button.next {
			width: auto;
			padding: 0 0.75rem;
			white-space: nowrap;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			{{-- Cabecera del cliente --}}
			<div class="card profile-overview">
				<div class="card-header border-0 flex-wrap">
					<div class="d-flex align-items-center gap-3">
						{{-- Placa con el ícono del tipo de cliente, mismo tratamiento que las cards de
						     métricas (ver docs/04-front-guidelines.md, "Placa de ícono"). --}}
						<div class="icono-placa">
							<x-lordicon icon="{{ $cliente->tipo === \App\Enums\TipoCliente::Empresa ? 'empresa' : 'person' }}"
								size="44" trigger="hover" target=".profile-overview" />
						</div>
						<div>
						<h4 class="card-title mb-1">{{ $cliente->razon_social ?: $cliente->nombre }}</h4>
						<ul class="d-flex flex-wrap fs-6 align-items-center mb-0">
							<li class="me-3 d-inline-flex align-items-center">
								<i class="las la-tag me-1 fs-18"></i>{{ $cliente->tipo === \App\Enums\TipoCliente::Empresa ? 'Empresa' : 'Particular' }}
							</li>
							@if ($cliente->nif)
								<li class="me-3 d-inline-flex align-items-center">
									<i class="las la-id-card me-1 fs-18"></i>{{ $cliente->nif }}
								</li>
							@endif
							@if ($cliente->email)
								<li class="me-3 d-inline-flex align-items-center">
									<i class="las la-envelope me-1 fs-18"></i>{{ $cliente->email }}
								</li>
							@endif
							@if ($cliente->telefono)
								<li class="me-3 d-inline-flex align-items-center">
									<i class="las la-phone me-1 fs-18"></i>{{ $cliente->telefono }}
								</li>
							@endif
						</ul>
						</div>
					</div>
					<div class="d-flex gap-2 align-items-center flex-wrap">
						<a href="{{ route('clientes.index') }}" class="btn btn-outline-secondary">Volver</a>
						@can('ver-facturas-crear')
							<a href="{{ route('facturas.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-outline-primary">+ Factura</a>
						@endcan
						@can('ver-presupuestos')
							<a href="{{ route('presupuestos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-outline-primary">+ Presupuesto</a>
						@endcan
						@can('ver-albaranes')
							<a href="{{ route('albaranes.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-outline-primary">+ Albarán</a>
						@endcan
						@can('ver-oportunidades')
							<a href="{{ route('oportunidades.index', ['cliente_id' => $cliente->id]) }}" class="btn btn-outline-primary">+ Oportunidad</a>
						@endcan
					</div>
				</div>
			</div>

			<div class="card">
				<div class="card-body">
					<ul class="nav nav-tabs" id="perfil-cliente-tabs" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active d-flex align-items-center" id="tab-datos-generales-btn" data-bs-toggle="tab"
								data-bs-target="#tab-datos-generales" type="button" role="tab"
								aria-controls="tab-datos-generales" aria-selected="true">
								<x-lordicon icon="person" size="22" trigger="hover" target=".nav-link" />
								<span class="ms-2">Datos generales</span>
							</button>
						</li>
						@can('ver-facturas')
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-resumen-financiero-btn" data-bs-toggle="tab"
									data-bs-target="#tab-resumen-financiero" type="button" role="tab"
									aria-controls="tab-resumen-financiero" aria-selected="false">
									<x-lordicon icon="euro" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Resumen financiero</span>
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-facturas-btn" data-bs-toggle="tab"
									data-bs-target="#tab-facturas" type="button" role="tab"
									aria-controls="tab-facturas" aria-selected="false">
									<x-lordicon icon="invoice" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Facturas</span>
								</button>
							</li>
						@endcan
						@can('ver-presupuestos')
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-presupuestos-btn" data-bs-toggle="tab"
									data-bs-target="#tab-presupuestos" type="button" role="tab"
									aria-controls="tab-presupuestos" aria-selected="false">
									<x-lordicon icon="wired-outline-979-project-estimate-hover-pinch" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Presupuestos</span>
								</button>
							</li>
						@endcan
						@can('ver-albaranes')
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-albaranes-btn" data-bs-toggle="tab"
									data-bs-target="#tab-albaranes" type="button" role="tab"
									aria-controls="tab-albaranes" aria-selected="false">
									<x-lordicon icon="wired-outline-56-document-hover-swipe" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Albaranes</span>
								</button>
							</li>
						@endcan
						@can('ver-oportunidades')
							<li class="nav-item" role="presentation">
								<button class="nav-link d-flex align-items-center" id="tab-oportunidades-btn" data-bs-toggle="tab"
									data-bs-target="#tab-oportunidades" type="button" role="tab"
									aria-controls="tab-oportunidades" aria-selected="false">
									<x-lordicon icon="wired-outline-456-handshake-deal-hover-pinch" size="22" trigger="hover" target=".nav-link" />
									<span class="ms-2">Oportunidades</span>
								</button>
							</li>
						@endcan
						<li class="nav-item" role="presentation">
							<button class="nav-link d-flex align-items-center" id="tab-actividad-btn" data-bs-toggle="tab"
								data-bs-target="#tab-actividad" type="button" role="tab"
								aria-controls="tab-actividad" aria-selected="false">
								<x-lordicon icon="wired-outline-153-bar-chart" size="22" trigger="hover" target=".nav-link" />
								<span class="ms-2">Actividad</span>
							</button>
						</li>
					</ul>

					<div class="tab-content pt-4" id="perfil-cliente-tabs-content">
						{{-- Datos generales: editables in-place (mismo patrón que profile/show.blade.php,
						     formularios AJAX dentro de la pestaña, no un modal aparte). --}}
						<div class="tab-pane fade show active" id="tab-datos-generales" role="tabpanel" aria-labelledby="tab-datos-generales-btn">
							<form id="cliente-perfil-form">
								@include('clientes._form')
								<div class="mt-3">
									<button type="submit" class="btn btn-primary" id="btn-guardar-cliente">Guardar cambios</button>
								</div>
							</form>
						</div>

						{{-- Resumen financiero: tarjetas + gráficos (mismo stack Chart.js del dashboard) --}}
						@can('ver-facturas')
							<div class="tab-pane fade" id="tab-resumen-financiero" role="tabpanel" aria-labelledby="tab-resumen-financiero-btn">
								@if ($resumen['cantidad_facturas'] === 0)
									<p class="mb-0 text-muted">Sin facturas registradas.</p>
								@else
									<div class="row">
										<div class="col-xl-3 col-sm-6">
											<div class="card same-card">
												<div class="card-body">
													<div class="d-flex justify-content-between align-items-center">
														<div>
															<h6 class="mb-1">Total facturado</h6>
															<h4 class="mb-0">{{ \App\Support\Formato::moneda($resumen['total_facturado']) }}</h4>
														</div>
														<x-lordicon icon="invoice" size="38" trigger="hover" target=".card" />
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
															<h4 class="mb-0">{{ \App\Support\Formato::moneda($resumen['pendiente_cobro']) }}</h4>
														</div>
														<x-lordicon icon="euro" size="38" trigger="hover" target=".card" />
													</div>
												</div>
											</div>
										</div>
										<div class="col-xl-3 col-sm-6">
											<div class="card same-card">
												<div class="card-body">
													<div class="d-flex justify-content-between align-items-center">
														<div>
															<h6 class="mb-1">Facturas vencidas</h6>
															<h4 class="mb-0">{{ $resumen['facturas_vencidas_cantidad'] }}</h4>
															<small class="text-muted">{{ \App\Support\Formato::moneda($resumen['facturas_vencidas_importe']) }}</small>
														</div>
														<x-lordicon icon="wired-outline-2115-refund-hover-pinch" size="38" trigger="hover" target=".card" />
													</div>
												</div>
											</div>
										</div>
										<div class="col-xl-3 col-sm-6">
											<div class="card same-card">
												<div class="card-body">
													<div class="d-flex justify-content-between align-items-center">
														<div>
															<h6 class="mb-1">Ticket medio</h6>
															<h4 class="mb-0">{{ \App\Support\Formato::moneda($resumen['ticket_medio']) }}</h4>
														</div>
														<x-lordicon icon="wired-outline-153-bar-chart" size="38" trigger="hover" target=".card" />
													</div>
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-xl-8">
											<div class="card same-card">
												<div class="card-header">
													<h4 class="card-title">Facturado vs. cobrado (últimos 6 meses)</h4>
												</div>
												<div class="card-body">
													<canvas id="chart-cliente-evolucion" height="90"></canvas>
												</div>
											</div>
										</div>
										<div class="col-xl-4">
											<div class="card same-card">
												<div class="card-header">
													<h4 class="card-title">Estado de cobro</h4>
												</div>
												<div class="card-body">
													<div style="height: 300px;">
														<canvas id="chart-cliente-cobro"></canvas>
													</div>
												</div>
											</div>
										</div>
									</div>
								@endif
							</div>

							{{-- Facturas --}}
							<div class="tab-pane fade" id="tab-facturas" role="tabpanel" aria-labelledby="tab-facturas-btn">
								<div class="table-responsive">
									<table id="facturas-cliente-table" class="display responsive nowrap w-100">
										<thead>
											<tr>
												<th>Número</th>
												<th>Fecha</th>
												<th>Importe</th>
												<th>Estado de cobro</th>
												<th>Acciones</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
							</div>
						@endcan

						{{-- Presupuestos --}}
						@can('ver-presupuestos')
							<div class="tab-pane fade" id="tab-presupuestos" role="tabpanel" aria-labelledby="tab-presupuestos-btn">
								<div class="table-responsive">
									<table id="presupuestos-cliente-table" class="display responsive nowrap w-100">
										<thead>
											<tr>
												<th>Número</th>
												<th>Fecha</th>
												<th>Importe</th>
												<th>Estado</th>
												<th>Acciones</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
							</div>
						@endcan

						{{-- Albaranes --}}
						@can('ver-albaranes')
							<div class="tab-pane fade" id="tab-albaranes" role="tabpanel" aria-labelledby="tab-albaranes-btn">
								<div class="table-responsive">
									<table id="albaranes-cliente-table" class="display responsive nowrap w-100">
										<thead>
											<tr>
												<th>Número</th>
												<th>Fecha</th>
												<th>Importe</th>
												<th>Estado</th>
												<th>Acciones</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
							</div>
						@endcan

						{{-- Oportunidades --}}
						@can('ver-oportunidades')
							<div class="tab-pane fade" id="tab-oportunidades" role="tabpanel" aria-labelledby="tab-oportunidades-btn">
								<div class="table-responsive">
									<table id="oportunidades-cliente-table" class="display responsive nowrap w-100">
										<thead>
											<tr>
												<th>Título</th>
												<th>Etapa</th>
												<th>Valor estimado</th>
												<th>Acciones</th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
								</div>
							</div>
						@endcan

						{{-- Actividad --}}
						<div class="tab-pane fade" id="tab-actividad" role="tabpanel" aria-labelledby="tab-actividad-btn">
							<div class="table-responsive">
								<table id="actividad-cliente-table" class="display responsive nowrap w-100">
									<thead>
										<tr>
											<th>Fecha</th>
											<th>Tipo</th>
											<th>Evento</th>
											<th>Acciones</th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		{{-- Vista previa de documentos: SIEMPRE en modal, nunca otra pestaña
		     (docs/04-front-guidelines.md → "Ver un documento"). --}}
		<div class="modal fade" id="documentoPdfModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="documentoPdfModalLabel">Vista previa del documento</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body p-0" style="height: 80vh;">
						<iframe id="documentoPdfFrame" src="" style="width: 100%; height: 100%; border: 0;"></iframe>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('ayuda-titulo', 'Perfil del cliente')
@section('ayuda')
	@include('ayuda.clientes')
@endsection

@push('scripts')
	@php
		// Se arma acá y no dentro del @json(): Blade no parsea bien una expresión con arrays
		// literales sumados dentro de una directiva.
		$clientePayload = $cliente->only([
			'id', 'tipo', 'nombre', 'razon_social', 'nif', 'direccion', 'cp',
			'ciudad', 'provincia', 'pais', 'email', 'telefono', 'notas',
		]);
		$clientePayload['tipo'] = $cliente->tipo->value;
		$clientePayload['aplica_recargo_equivalencia'] = (bool) $cliente->aplica_recargo_equivalencia;
	@endphp

	<script>
		window.clientePerfilState = {
			cliente: @json($clientePayload),
			updateUrl: @json(route('clientes.update', $cliente)),
			localidadesUrl: @json(route('localidades.index')),
			recursoUrl: @json(route('clientes.show', ['cliente' => $cliente->id])),
			graficos: @json($graficos),
		};
	</script>
	<script src="{{ asset('vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('vendor/datatables/responsive/responsive.js') }}"></script>
	<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
	<script src="@assetv('js/plugins-init/cliente-perfil.init.js')"></script>
@endpush
