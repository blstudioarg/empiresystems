@extends('layouts.app')

@section('title', $presupuesto ? 'Editar presupuesto' : 'Nuevo presupuesto')

@push('styles')
	<style>
		/* Mismo patrón "documento" que facturas/create.blade.php (docs/04-front-guidelines.md):
		   el presupuesto reutiliza el motor de cálculo de facturas, así que la experiencia de
		   crearlo/editarlo debe sentirse igual — catálogo a la izquierda, líneas a la derecha,
		   fondo "papel" y barra de acción inferior sticky. */
		.presupuesto-documento {
			background: #fdfcfa;
			border: 1px solid #ecebe7;
		}

		.presupuesto-membrete {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			flex-wrap: wrap;
			gap: 1rem;
			padding-bottom: 1.25rem;
			margin-bottom: 1.25rem;
			border-bottom: 2px solid #222;
		}

		.presupuesto-identificador {
			font-size: 1.1rem;
			font-weight: 700;
			letter-spacing: 0.02em;
		}

		.presupuesto-meta-campo label {
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #8a8a8a;
			margin-bottom: 0.2rem;
		}

		.linea-row input[type="number"],
		.linea-base,
		.totales-documento td {
			font-variant-numeric: tabular-nums;
		}

		#lineas-table {
			margin-bottom: 0;
		}

		#lineas-table th {
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #8a8a8a;
			border-bottom-width: 2px;
			border-color: #222;
		}

		#lineas-table td {
			vertical-align: middle;
			border-color: #ecebe7;
		}

		.linea-row .form-control {
			border-color: transparent;
			background: transparent;
			padding-left: 0.35rem;
			padding-right: 0.35rem;
		}

		.linea-row .form-control:hover,
		.linea-row .form-control:focus {
			border-color: var(--bs-border-color, #ced4da);
			background: #fff;
		}

		.linea-base {
			text-align: right;
			font-weight: 600;
			white-space: nowrap;
		}

		.totales-documento {
			width: 320px;
			margin-left: auto;
		}

		.totales-documento td {
			padding: 0.2rem 0;
		}

		.totales-documento .total-final td {
			padding-top: 0.5rem;
			border-top: 2px solid #222;
			font-size: 1.15rem;
			font-weight: 700;
		}

		.presupuesto-action-bar {
			position: sticky;
			bottom: 0;
			z-index: 5;
			background: #fff;
			border-top: 1px solid #ecebe7;
			box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.04);
			padding: 0.85rem 1.25rem;
			margin: 1rem -1rem -1rem;
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 1rem;
		}

		.presupuesto-action-bar .total-pill {
			margin-right: auto;
			font-size: 1rem;
		}

		.presupuesto-action-bar .total-pill strong {
			font-size: 1.35rem;
			font-variant-numeric: tabular-nums;
		}

		.presupuesto-catalogo {
			border: 1px solid #ecebe7;
			border-radius: var(--bs-border-radius);
			padding: 0.75rem;
			height: 100%;
			display: flex;
			flex-direction: column;
		}

		.presupuesto-catalogo-buscador-wrap { position: relative; }
		.presupuesto-catalogo-buscador-wrap .presupuesto-catalogo-buscador-icon {
			position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%);
			color: #b3b0a8; font-size: 0.78rem; pointer-events: none;
		}
		.presupuesto-catalogo-buscador-wrap .form-control { padding-left: 1.85rem; }

		.presupuesto-catalogo-filtros {
			display: flex;
			gap: 0.35rem;
			margin-top: 0.5rem;
		}

		.presupuesto-catalogo-filtros .btn {
			flex: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 0.3rem;
			border-radius: 2rem;
		}

		.presupuesto-catalogo-filtros .btn .filtro-count {
			background: rgba(0, 0, 0, 0.08);
			border-radius: 1rem;
			padding: 0.02rem 0.4rem;
			font-size: 0.68rem;
		}

		.presupuesto-catalogo-filtros .btn.active {
			background: var(--primary, #1D69D6);
			border-color: var(--primary, #1D69D6);
			color: #fff;
		}

		.presupuesto-catalogo-filtros .btn.active .filtro-count {
			background: rgba(255, 255, 255, 0.25);
		}

		.presupuesto-catalogo-lista {
			margin-top: 0.75rem;
			overflow-y: auto;
			max-height: 420px;
			flex: 1;
		}

		.catalogo-item {
			position: relative;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 0.5rem;
			padding: 0.5rem 0.6rem 0.5rem 0.75rem;
			border-radius: var(--bs-border-radius-sm);
			cursor: pointer;
			border-left: 3px solid var(--presupuesto-accent, transparent);
			transition: background .12s ease, transform .08s ease;
		}

		.catalogo-item:hover {
			background: #f6f5f2;
			transform: translateX(1px);
		}

		.catalogo-item:active {
			transform: scale(.98);
		}

		.catalogo-item.just-added {
			animation: catalogo-flash .5s ease;
		}

		@keyframes catalogo-flash {
			0% { background: rgba(29, 105, 214, .16); }
			100% { background: transparent; }
		}

		.catalogo-item .tipo-icon {
			flex: none;
			width: 16px;
			text-align: center;
			color: var(--presupuesto-accent, #8a8a8a);
			font-size: 0.72rem;
			opacity: .85;
		}

		.catalogo-item .nombre-wrap { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }

		.catalogo-item .nombre {
			font-weight: 600;
			font-size: 0.8125rem;
			line-height: 1.2;
		}

		.catalogo-item .meta {
			font-size: 0.72rem;
			color: #8a8a8a;
		}

		.catalogo-item .precio {
			font-variant-numeric: tabular-nums;
			font-size: 0.8125rem;
			white-space: nowrap;
			color: #555;
		}

		.catalogo-item-libre {
			border: 1px dashed #c9c5bd;
			border-left: 1px dashed #c9c5bd;
			color: #555;
			margin-bottom: 0.35rem;
			font-size: 0.8125rem;
			font-weight: 600;
			gap: 0.4rem;
		}

		.catalogo-item-libre:hover {
			border-color: var(--primary, #1D69D6);
			color: var(--primary, #1D69D6);
			background: transparent;
			transform: none;
		}

		.catalogo-item-libre i { font-size: 0.7rem; }

		.catalogo-vacio {
			font-size: 0.78rem;
			color: #8a8a8a;
			padding: 0.75rem 0.5rem;
			text-align: center;
		}

		.linea-row.just-added td {
			animation: catalogo-flash .6s ease;
		}

		.linea-row .btn-remove-linea {
			opacity: 0.35;
			transition: opacity .12s ease;
			border: none;
			background: transparent;
			color: #c0392b;
			font-size: 0.85rem;
			padding: 0.2rem 0.4rem;
		}

		.linea-row:hover .btn-remove-linea,
		.linea-row .btn-remove-linea:focus-visible {
			opacity: 1;
			background: transparent;
		}
	</style>
@endpush

@section('content')
	<div class="content-body">
		<div class="container-fluid">

			<form id="presupuesto-form" method="POST"
				action="{{ $presupuesto ? route('presupuestos.update', $presupuesto) : route('presupuestos.store') }}">
				@csrf
				@if ($presupuesto)
					@method('PUT')
				@endif

				<div class="card presupuesto-documento">
					<div class="card-body">

						<div class="presupuesto-membrete">
							<div>
								<strong>{{ tenant()->nombre_comercial }}</strong><br>
								<small class="text-muted">{{ tenant()->nif }}</small>
							</div>
							<div class="text-md-end">
								<div class="presupuesto-identificador">{{ $presupuesto?->numero ?? 'Borrador' }}</div>
								<span class="badge badge-light-secondary text-uppercase">{{ $presupuesto?->estado?->label() ?? 'Borrador' }}</span>
							</div>
						</div>

						<div class="row gy-3 mb-4">
							<div class="col-md-3 presupuesto-meta-campo">
								<label for="cliente_id" class="form-label d-block">Cliente</label>
								<select name="cliente_id" id="cliente_id" class="form-control">
									<option value="">— Ninguno —</option>
									@foreach ($clientes as $cliente)
										<option value="{{ $cliente->id }}"
											data-recargo="{{ $cliente->aplica_recargo_equivalencia ? 1 : 0 }}"
											@selected(old('cliente_id', optional($clientePreseleccionado)->id) == $cliente->id)>
											{{ $cliente->razon_social ?: $cliente->nombre }}
										</option>
									@endforeach
								</select>
								<div class="invalid-feedback" data-error-for="cliente_id"></div>
							</div>
							<div class="col-md-3 presupuesto-meta-campo">
								<label for="lead_id" class="form-label d-block">o Lead</label>
								<select name="lead_id" id="lead_id" class="form-control">
									<option value="">— Ninguno —</option>
									@foreach ($leads as $lead)
										<option value="{{ $lead->id }}" @selected(old('lead_id', optional($leadPreseleccionado)->id) == $lead->id)>
											{{ $lead->nombre }}
										</option>
									@endforeach
								</select>
								<small class="form-text text-muted">Indica un cliente o un lead como receptor.</small>
							</div>
							<div class="col-md-2 presupuesto-meta-campo">
								<label for="fecha_emision" class="form-label d-block">Emisión</label>
								<input type="date" name="fecha_emision" id="fecha_emision" class="form-control"
									value="{{ old('fecha_emision', $presupuesto?->fecha_emision?->toDateString() ?? now()->toDateString()) }}">
								<div class="invalid-feedback" data-error-for="fecha_emision"></div>
							</div>
							<div class="col-md-2 presupuesto-meta-campo">
								<label for="fecha_validez" class="form-label d-block">Válido hasta</label>
								<input type="date" name="fecha_validez" id="fecha_validez" class="form-control"
									value="{{ old('fecha_validez', $presupuesto?->fecha_validez?->toDateString() ?? now()->addDays($diasValidez)->toDateString()) }}">
								<div class="invalid-feedback" data-error-for="fecha_validez"></div>
							</div>
							<div class="col-md-2 presupuesto-meta-campo">
								<label for="irpf_porcentaje" class="form-label d-block">IRPF %</label>
								<input type="number" step="0.01" min="0" max="100" name="irpf_porcentaje" id="irpf_porcentaje"
									class="form-control" value="{{ old('irpf_porcentaje', $presupuesto?->irpf_porcentaje) }}">
								<div class="invalid-feedback" data-error-for="irpf_porcentaje"></div>
							</div>
						</div>

						<input type="hidden" name="oportunidad_id" id="oportunidad_id" value="{{ old('oportunidad_id', optional($oportunidadPreseleccionada)->id) }}">
						@if ($oportunidadPreseleccionada)
							<p class="text-muted mb-4" style="font-size: 0.8125rem; margin-top: -1rem;">
								Vinculado a la oportunidad <strong>{{ $oportunidadPreseleccionada->titulo }}</strong>.
							</p>
						@endif

						<h5 class="mb-2">Líneas</h5>
						<div class="row gx-3">
							<div class="col-lg-3 mb-3 mb-lg-0">
								<div class="presupuesto-catalogo">
									<label for="catalogo-buscador" class="form-label mb-1">Añadir artículo</label>
									<div class="presupuesto-catalogo-buscador-wrap">
										<i class="fas fa-search presupuesto-catalogo-buscador-icon"></i>
										<input type="search" id="catalogo-buscador" class="form-control form-control-sm"
											placeholder="Buscar por nombre o SKU…">
									</div>

									<div class="presupuesto-catalogo-filtros" role="group">
										<button type="button" class="btn btn-outline-secondary btn-sm active" data-filtro-tipo="todos">
											Todos <span class="filtro-count" data-count-tipo="todos">0</span>
										</button>
										<button type="button" class="btn btn-outline-secondary btn-sm" data-filtro-tipo="producto">
											Productos <span class="filtro-count" data-count-tipo="producto">0</span>
										</button>
										<button type="button" class="btn btn-outline-secondary btn-sm" data-filtro-tipo="servicio">
											Servicios <span class="filtro-count" data-count-tipo="servicio">0</span>
										</button>
									</div>

									<div id="catalogo-lista" class="presupuesto-catalogo-lista"></div>
								</div>
							</div>

							<div class="col-lg-9">
								<div class="table-responsive">
									<table class="table" id="lineas-table">
										<thead>
											<tr>
												<th>Concepto</th>
												<th>Unidad</th>
												<th class="text-end">Cantidad</th>
												<th class="text-end">Precio</th>
												<th class="text-end">Dto. %</th>
												<th class="text-end">{{ $regimen['label'] }} %</th>
												<th class="text-end">Base</th>
												<th></th>
											</tr>
										</thead>
										<tbody id="lineas-body"></tbody>
									</table>
								</div>
								<div class="invalid-feedback d-block" data-error-for="lineas"></div>
							</div>
						</div>

						<div class="row mt-4">
							<div class="col-md-7">
								<label for="notas" class="form-label">Notas</label>
								<textarea name="notas" id="notas" rows="3" class="form-control">{{ old('notas', $presupuesto?->notas) }}</textarea>
							</div>
							<div class="col-md-5">
								<table class="totales-documento">
									<tbody id="preview-desglose"></tbody>
									<tr>
										<td>IRPF</td>
										<td class="text-end" id="preview-irpf">-0,00 €</td>
									</tr>
									<tr class="total-final">
										<td>Total</td>
										<td class="text-end" id="preview-total">0,00 €</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>

				<div class="presupuesto-action-bar">
					<div class="total-pill text-muted">
						Total presupuesto <strong id="preview-total-bar">0,00 €</strong>
					</div>
					<a href="{{ route('presupuestos.index') }}" class="btn btn-light">Cancelar</a>
					<button type="submit" class="btn btn-primary" data-loading-text="Guardando...">Guardar presupuesto</button>
				</div>
			</form>
		</div>
	</div>

	<template id="linea-template">
		<tr class="linea-row">
			<td>
				<input type="text" class="form-control linea-concepto" name="" placeholder="Concepto">
			</td>
			<td>
				<input type="text" class="form-control linea-unidad" name="" placeholder="ud">
			</td>
			<td>
				<input type="number" step="0.0001" min="0" class="form-control text-end linea-cantidad" name="" value="1">
			</td>
			<td>
				<input type="number" step="0.01" min="0" class="form-control text-end linea-precio" name="" value="0">
			</td>
			<td>
				<input type="number" step="0.01" min="0" max="100" class="form-control text-end linea-descuento" name="" value="0">
			</td>
			<td>
				@if ($regimen['tiposValidos'] !== null)
					<select class="form-control linea-tipo" name="">
						@foreach ($regimen['tiposValidos'] as $tipoValido)
							<option value="{{ $tipoValido }}" @selected($tipoValido == $regimen['tipoPorDefecto'])>{{ $tipoValido }}</option>
						@endforeach
					</select>
				@else
					<input type="number" step="0.01" min="0" max="100" class="form-control text-end linea-tipo" name="" value="{{ $regimen['tipoPorDefecto'] }}">
				@endif
			</td>
			<td class="linea-base">0,00 €</td>
			<td>
				<button type="button" class="btn btn-danger light btn-sm btn-remove-linea">✕</button>
			</td>
		</tr>
	</template>
@endsection

@section('ayuda-titulo', 'Presupuestos')
@section('ayuda')
	@include('ayuda.presupuestos')
@endsection

@push('scripts')
	<script>
		window.presupuestoFormState = {
			articulosUrl: @json(route('articulos.index')),
			lineasIniciales: @json($lineasIniciales),
			erroresValidacion: @json($errors->getMessages()),
			regimen: @json($regimen),
		};
	</script>
	<script src="{{ asset('js/presupuestos-form.js') }}"></script>
@endpush
