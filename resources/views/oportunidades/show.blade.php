@extends('layouts.app')

@section('title', $oportunidad->titulo)

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-5">
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h4 class="card-title mb-0">{{ $oportunidad->titulo }}</h4>
							<span class="badge bg-primary-light" id="oportunidad-etapa-badge">{{ $oportunidad->etapa->label() }}</span>
						</div>
						<div class="card-body">
							<dl class="row mb-0">
								<dt class="col-5">Receptor</dt>
								<dd class="col-7">{{ $oportunidad->cliente?->nombre ?? $oportunidad->lead?->nombre }}</dd>
								<dt class="col-5">Importe estimado</dt>
								<dd class="col-7">{{ $oportunidad->importe_estimado ? number_format((float) $oportunidad->importe_estimado, 2, ',', '.').' €' : '—' }}</dd>
								<dt class="col-5">Responsable</dt>
								<dd class="col-7">{{ $oportunidad->asignadoA?->name ?? 'Sin asignar' }}</dd>
								<dt class="col-5" id="oportunidad-motivo-dt" style="{{ $oportunidad->motivo_perdida ? '' : 'display:none' }}">Motivo de pérdida</dt>
								<dd class="col-7" id="oportunidad-motivo-valor" style="{{ $oportunidad->motivo_perdida ? '' : 'display:none' }}">{{ $oportunidad->motivo_perdida }}</dd>
							</dl>
						</div>
						<div class="card-footer" id="oportunidad-acciones" style="{{ $oportunidad->etapa->esTerminal() ? 'display:none' : '' }}">
							<a href="{{ route('oportunidades.index', ['editar' => $oportunidad->id]) }}" class="btn btn-outline-primary w-100 mb-2">Editar</a>

							<div class="mb-2" id="oportunidad-btn-negociacion-wrap" style="{{ $oportunidad->etapa->value === 'nueva' ? '' : 'display:none' }}">
								<button type="button" class="btn btn-outline-primary w-100" id="btn-en-negociacion" data-loading-text="...">Pasar a negociación</button>
							</div>

							<button type="button" class="btn btn-success w-100 mb-2" id="btn-ganar" data-loading-text="...">Marcar como ganada</button>

							<div class="mb-2">
								<input type="text" class="form-control" id="oportunidad-motivo-perdida-input" placeholder="Motivo de la pérdida" maxlength="255">
							</div>
							<button type="button" class="btn btn-outline-danger w-100" id="btn-perder" data-loading-text="...">Marcar como perdida</button>
						</div>
					</div>
				</div>

				<div class="col-lg-7">
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h5 class="card-title mb-0">Presupuestos</h5>
							<a href="{{ route('presupuestos.create', ['oportunidad_id' => $oportunidad->id]) }}"
								class="btn btn-sm btn-outline-primary" id="oportunidad-nuevo-presupuesto"
								style="{{ $oportunidad->etapa->value === 'perdida' ? 'display:none' : '' }}">+ Nuevo presupuesto</a>
						</div>
						<div class="card-body">
							<ul class="list-group list-group-flush">
								@forelse ($oportunidad->presupuestos as $presupuesto)
									<li class="list-group-item d-flex justify-content-between px-0">
										<a href="{{ route('presupuestos.pdf', $presupuesto) }}" target="_blank">{{ $presupuesto->numero }}</a>
										<span>{{ $presupuesto->estado->label() }} — {{ number_format((float) $presupuesto->total, 2, ',', '.') }} €</span>
									</li>
								@empty
									<li class="list-group-item px-0 text-muted">Todavía no hay presupuestos.</li>
								@endforelse
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script>
		window.oportunidadShowState = {
			etapaUrl: @json(route('oportunidades.etapa', $oportunidad)),
		};
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('js/plugins-init/oportunidades-show.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Oportunidades')
@section('ayuda')
	@include('ayuda.oportunidades')
@endsection
