@extends('layouts.app')

@section('title', 'Oportunidades')

@push('styles')
	<style>
		.pipeline-col { min-height: 200px; }
		.pipeline-card { cursor: pointer; }
		.pipeline-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
		.pipeline-importe { font-variant-numeric: tabular-nums; }
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<h4 class="mb-0">Pipeline de oportunidades</h4>
				<button type="button" class="btn btn-primary btn-add-oportunidad" data-bs-toggle="modal" data-bs-target="#oportunidadModal">
					+ Nueva oportunidad
				</button>
			</div>

			<div class="row mb-3">
				<div class="col-xl-4 col-sm-6">
					<div class="card same-card">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-center">
								<div>
									<h6 class="mb-1">Oportunidades abiertas</h6>
									<h3 class="mb-0">{{ $totalesGenerales['abiertas'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-153-bar-chart" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Importe en pipeline</h6>
									<h3 class="mb-0">{{ number_format($totalesGenerales['importe_pipeline'], 2, ',', '.') }} €</h3>
								</div>
								<div>
									<x-lordicon icon="euro" size="50" trigger="hover" target=".card" />
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
									<h6 class="mb-1">Ganadas</h6>
									<h3 class="mb-0">{{ $totalesGenerales['ganadas'] }}</h3>
								</div>
								<div>
									<x-lordicon icon="wired-outline-456-handshake-deal-hover-pinch" size="50" trigger="hover" target=".card" />
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row" id="pipeline-columnas">
				@foreach (['nueva' => 'Nueva', 'en_negociacion' => 'En negociación', 'ganada' => 'Ganada', 'perdida' => 'Perdida'] as $etapa => $label)
					<div class="col-lg-3 col-md-6 mb-3">
						<div class="card pipeline-col">
							<div class="card-header d-flex justify-content-between align-items-center">
								<span>{{ $label }}</span>
								<span class="badge bg-secondary-light" data-etapa-total="{{ $etapa }}">
									{{ $resumenPorEtapa[$etapa]['total'] ?? 0 }}
								</span>
							</div>
							<div class="card-body">
								<p class="pipeline-importe text-muted mb-3">
									{{ number_format($resumenPorEtapa[$etapa]['importe_total'] ?? 0, 2, ',', '.') }} €
								</p>
								<div data-etapa-lista="{{ $etapa }}"></div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	{{-- Alta/edición: un único modal, igual que leads/clientes — no hay página de creación
	     propia. El bloque de receptor (lead/cliente) solo aplica al crear; al editar el
	     receptor ya quedó fijado y no se puede cambiar (ver UpdateOportunidadRequest). --}}
	<div class="modal fade" id="oportunidadModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content">
				<form id="oportunidad-form" method="POST" action="{{ route('oportunidades.store') }}">
					@csrf
					<input type="hidden" name="_method" id="oportunidad_method" value="POST">
					<div class="modal-header">
						<h5 class="modal-title" id="oportunidadModalLabel">Nueva oportunidad</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="mb-3">
							<label class="form-label" for="op_titulo">Título *</label>
							<input type="text" class="form-control" id="op_titulo" name="titulo" required>
							<div class="invalid-feedback d-block" data-error-for="titulo"></div>
						</div>

						<div id="oportunidad-receptor-wrap">
							<div class="mb-3">
								<label class="form-label" for="op_lead_id">Lead</label>
								<select class="form-select" id="op_lead_id" name="lead_id">
									<option value="">— Ninguno —</option>
									@foreach ($leads as $lead)
										<option value="{{ $lead->id }}">{{ $lead->nombre }}</option>
									@endforeach
								</select>
							</div>
							<div class="mb-3">
								<label class="form-label" for="op_cliente_id">o Cliente</label>
								<select class="form-select" id="op_cliente_id" name="cliente_id">
									<option value="">— Ninguno —</option>
									@foreach ($clientes as $cliente)
										<option value="{{ $cliente->id }}">{{ $cliente->razon_social ?: $cliente->nombre }}</option>
									@endforeach
								</select>
								<div class="invalid-feedback d-block" data-error-for="cliente_id"></div>
								<small class="form-text text-muted">Indica exactamente uno de los dos: lead o cliente.</small>
							</div>
						</div>

						<div class="mb-3">
							<label class="form-label" for="op_importe">Importe estimado</label>
							<input type="number" step="0.01" min="0" class="form-control" id="op_importe" name="importe_estimado">
						</div>
						<div class="mb-3">
							<label class="form-label" for="op_asignado">Responsable</label>
							<select class="form-select" id="op_asignado" name="asignado_a">
								<option value="">— Sin asignar —</option>
								@foreach ($comerciales as $comercial)
									<option value="{{ $comercial->id }}">{{ $comercial->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="mb-0">
							<label class="form-label" for="op_notas">Notas</label>
							<textarea class="form-control" id="op_notas" name="notas" rows="2"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary" data-loading-text="Guardando...">Guardar</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script>
		window.oportunidadesIndexUrl = @json(route('oportunidades.index'));
		window.oportunidadFormState = {
			storeUrl: @json(route('oportunidades.store')),
			queryLeadId: @json(request()->query('lead_id')),
			queryClienteId: @json(request()->query('cliente_id')),
			queryEditar: @json(request()->query('editar')),
		};
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('js/plugins-init/oportunidades-pipeline.init.js') }}"></script>
	<script src="{{ asset('js/plugins-init/oportunidades-modal.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Oportunidades')
@section('ayuda')
	@include('ayuda.oportunidades')
@endsection
