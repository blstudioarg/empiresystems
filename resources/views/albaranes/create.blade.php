@php
	$modoBloqueado = (bool) $albaran;
	$modoInicial = $albaran
		? ($albaran->presupuesto_id ? 'presupuesto' : 'directo')
		: ($presupuesto ? 'presupuesto' : ($clientePreseleccionado ? 'directo' : 'presupuesto'));
@endphp
@extends('layouts.app')

@section('title', $albaran ? 'Editar albarán' : 'Nuevo albarán')

@push('styles')
	<style>
		/* Mismo patrón "documento" que presupuestos/create.blade.php (docs/04-front-guidelines.md). */
		.albaran-documento {
			background: #fdfcfa;
			border: 1px solid #ecebe7;
		}

		.albaran-membrete {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			flex-wrap: wrap;
			gap: 1rem;
			padding-bottom: 1.25rem;
			margin-bottom: 1.25rem;
			border-bottom: 2px solid #222;
		}

		.albaran-identificador {
			font-size: 1.1rem;
			font-weight: 700;
			letter-spacing: 0.02em;
		}

		.albaran-modo-toggle {
			display: flex;
			gap: 0.5rem;
			margin-bottom: 1.25rem;
		}

		.albaran-modo-toggle .btn {
			border-radius: 2rem;
		}

		.albaran-modo-toggle .btn.active {
			background: var(--primary, #1D69D6);
			border-color: var(--primary, #1D69D6);
			color: #fff;
		}

		.albaran-meta-campo label {
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #8a8a8a;
			margin-bottom: 0.2rem;
		}

		#lineas-table-presupuesto th, #lineas-table-directo th {
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #8a8a8a;
			border-bottom-width: 2px;
			border-color: #222;
		}

		#lineas-table-presupuesto td, #lineas-table-directo td {
			vertical-align: middle;
			border-color: #ecebe7;
		}

		.linea-row input[type="number"],
		.linea-base,
		.totales-documento td {
			font-variant-numeric: tabular-nums;
		}

		.linea-pendiente-hint {
			font-size: 0.7rem;
			color: #8a8a8a;
			white-space: nowrap;
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

		.albaran-action-bar {
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

		.albaran-action-bar .total-pill {
			margin-right: auto;
			font-size: 1rem;
		}

		.albaran-action-bar .total-pill strong {
			font-size: 1.35rem;
			font-variant-numeric: tabular-nums;
		}

		.albaran-catalogo {
			border: 1px solid #ecebe7;
			border-radius: var(--bs-border-radius);
			padding: 0.75rem;
			height: 100%;
			display: flex;
			flex-direction: column;
		}

		.albaran-catalogo-buscador-wrap { position: relative; }
		.albaran-catalogo-buscador-wrap .albaran-catalogo-buscador-icon {
			position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%);
			color: #b3b0a8; font-size: 0.78rem; pointer-events: none;
		}
		.albaran-catalogo-buscador-wrap .form-control { padding-left: 1.85rem; }

		.albaran-catalogo-lista {
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
			border-left: 3px solid var(--albaran-accent, transparent);
			transition: background .12s ease, transform .08s ease;
		}

		.catalogo-item:hover {
			background: #f6f5f2;
			transform: translateX(1px);
		}

		.catalogo-item .tipo-icon {
			flex: none;
			width: 16px;
			text-align: center;
			color: var(--albaran-accent, #8a8a8a);
			font-size: 0.72rem;
			opacity: .85;
		}

		.catalogo-item .nombre-wrap { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
		.catalogo-item .nombre { font-weight: 600; font-size: 0.8125rem; line-height: 1.2; }
		.catalogo-item .meta { font-size: 0.72rem; color: #8a8a8a; }
		.catalogo-item .precio { font-variant-numeric: tabular-nums; font-size: 0.8125rem; white-space: nowrap; color: #555; }

		.catalogo-item-libre {
			border: 1px dashed #c9c5bd;
			border-left: 1px dashed #c9c5bd;
			color: #555;
			margin-bottom: 0.35rem;
			font-size: 0.8125rem;
			font-weight: 600;
			gap: 0.4rem;
		}

		.catalogo-vacio {
			font-size: 0.78rem;
			color: #8a8a8a;
			padding: 0.75rem 0.5rem;
			text-align: center;
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

			<form id="albaran-form" method="POST"
				action="{{ $albaran ? route('albaranes.update', $albaran) : route('albaranes.store') }}">
				@csrf
				@if ($albaran)
					@method('PUT')
				@endif

				<div class="card albaran-documento">
					<div class="card-body">

						<div class="albaran-membrete">
							<div>
								<strong>{{ tenant()->nombre_comercial }}</strong><br>
								<small class="text-muted">{{ tenant()->nif }}</small>
							</div>
							<div class="text-md-end">
								<div class="albaran-identificador">{{ $albaran?->numero ?? 'Borrador' }}</div>
								<span class="badge badge-light-secondary text-uppercase">{{ $albaran?->estado?->label() ?? 'Borrador' }}</span>
							</div>
						</div>

						@unless ($modoBloqueado)
							<div class="albaran-modo-toggle" role="group">
								<button type="button" class="btn btn-outline-secondary btn-sm {{ $modoInicial === 'presupuesto' ? 'active' : '' }}" data-modo="presupuesto">Desde presupuesto</button>
								<button type="button" class="btn btn-outline-secondary btn-sm {{ $modoInicial === 'directo' ? 'active' : '' }}" data-modo="directo">Directo a cliente</button>
							</div>
						@else
							<p class="text-muted mb-3" style="font-size: 0.8125rem;">
								{{ $modoInicial === 'presupuesto' ? 'Albarán generado desde un presupuesto aceptado.' : 'Albarán directo a cliente, sin presupuesto de origen.' }}
							</p>
						@endunless

						<input type="hidden" name="modo" id="modo" value="{{ $modoInicial }}">

						{{-- Panel: desde presupuesto --}}
						<div id="panel-presupuesto" class="{{ $modoInicial === 'presupuesto' ? '' : 'd-none' }}">
							<div class="row gy-3 mb-4">
								<div class="col-md-6 albaran-meta-campo">
									<label for="presupuesto_id" class="form-label d-block">Presupuesto de origen</label>
									@if ($modoBloqueado)
										<input type="text" class="form-control" value="{{ $presupuesto?->numero }} — {{ $presupuesto?->receptor_nombre }}" disabled>
										<input type="hidden" name="presupuesto_id" id="presupuesto_id" value="{{ $presupuesto?->id }}">
									@else
										<select name="presupuesto_id" id="presupuesto_id" class="form-control">
											<option value="">— Elegí un presupuesto aceptado —</option>
											@foreach ($presupuestos as $p)
												<option value="{{ $p['id'] }}" @selected(optional($presupuesto)->id == $p['id'])>{{ $p['numero'] }} — {{ $p['receptor'] }}</option>
											@endforeach
										</select>
									@endif
									<div class="invalid-feedback" data-error-for="presupuesto_id"></div>
									<small class="form-text text-muted">Solo se listan presupuestos aceptados con líneas pendientes de entrega.</small>
								</div>
							</div>

							<h5 class="mb-2">Líneas pendientes de entrega</h5>
							<div class="table-responsive">
								<table class="table" id="lineas-table-presupuesto">
									<thead>
										<tr>
											<th>Concepto</th>
											<th class="text-end">Pendiente</th>
											<th class="text-end">A entregar ahora</th>
											<th class="text-end">Precio</th>
											<th class="text-end">Base</th>
										</tr>
									</thead>
									<tbody id="lineas-body-presupuesto"></tbody>
								</table>
							</div>
							<div class="invalid-feedback d-block" data-error-for="lineas"></div>
						</div>

						{{-- Panel: directo a cliente --}}
						<div id="panel-directo" class="{{ $modoInicial === 'directo' ? '' : 'd-none' }}">
							<div class="row gy-3 mb-4">
								<div class="col-md-6 albaran-meta-campo">
									<label for="cliente_id" class="form-label d-block">Cliente</label>
									<select name="cliente_id" id="cliente_id" class="form-control" {{ $modoBloqueado ? 'disabled' : '' }}>
										<option value="">— Ninguno —</option>
										@foreach ($clientes as $cliente)
											<option value="{{ $cliente->id }}"
												data-recargo="{{ $cliente->aplica_recargo_equivalencia ? 1 : 0 }}"
												@selected(old('cliente_id', optional($clientePreseleccionado)->id) == $cliente->id)>
												{{ $cliente->razon_social ?: $cliente->nombre }}
											</option>
										@endforeach
									</select>
									@if ($modoBloqueado)
										<input type="hidden" name="cliente_id" value="{{ $clientePreseleccionado?->id }}">
									@endif
									<div class="invalid-feedback" data-error-for="cliente_id"></div>
								</div>
							</div>

							<h5 class="mb-2">Líneas</h5>
							<div class="row gx-3">
								<div class="col-lg-3 mb-3 mb-lg-0">
									<div class="albaran-catalogo">
										<label for="catalogo-buscador" class="form-label mb-1">Añadir artículo</label>
										<div class="albaran-catalogo-buscador-wrap">
											<i class="fas fa-search albaran-catalogo-buscador-icon"></i>
											<input type="search" id="catalogo-buscador" class="form-control form-control-sm" placeholder="Buscar por nombre o SKU…">
										</div>
										<div id="catalogo-lista" class="albaran-catalogo-lista"></div>
									</div>
								</div>

								<div class="col-lg-9">
									<div class="table-responsive">
										<table class="table" id="lineas-table-directo">
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
											<tbody id="lineas-body-directo"></tbody>
										</table>
									</div>
								</div>
							</div>
						</div>

						<div class="row mt-4">
							<div class="col-md-7">
								<label for="notas" class="form-label">Notas</label>
								<textarea name="notas" id="notas" rows="3" class="form-control">{{ old('notas', $albaran?->notas) }}</textarea>
							</div>
							<div class="col-md-5">
								<table class="totales-documento">
									<tbody id="preview-desglose"></tbody>
									<tr class="total-final">
										<td>Total</td>
										<td class="text-end" id="preview-total">0,00 €</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>

				<div class="albaran-action-bar">
					<div class="total-pill text-muted">
						Total albarán <strong id="preview-total-bar">0,00 €</strong>
					</div>
					<a href="{{ $albaran ? route('albaranes.show', $albaran) : route('albaranes.index') }}" class="btn btn-light">Cancelar</a>
					<button type="submit" class="btn btn-primary" data-loading-text="Guardando...">Guardar albarán</button>
				</div>
			</form>
		</div>
	</div>

	<template id="linea-template-presupuesto">
		<tr class="linea-row">
			<td class="linea-concepto-texto"></td>
			<td class="text-end linea-pendiente-hint"></td>
			<td>
				<input type="number" step="0.0001" min="0" class="form-control text-end linea-cantidad" name="" value="0">
			</td>
			<td class="text-end linea-precio-texto"></td>
			<td class="linea-base">0,00 €</td>
		</tr>
	</template>

	<template id="linea-template-directo">
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
				<input type="number" step="0.01" min="0" max="100" class="form-control text-end linea-tipo" name="" value="{{ $regimen['tipoPorDefecto'] }}">
			</td>
			<td class="linea-base">0,00 €</td>
			<td>
				<button type="button" class="btn btn-danger light btn-sm btn-remove-linea">✕</button>
			</td>
		</tr>
	</template>
@endsection

@section('ayuda-titulo', 'Albaranes')
@section('ayuda')
	@include('ayuda.albaranes')
@endsection

@push('scripts')
	<script>
		window.albaranFormState = {
			articulosUrl: @json(route('articulos.index')),
			presupuestos: @json($presupuestos->keyBy('id')),
			presupuestoPreseleccionado: @json($presupuesto?->id),
			lineasIniciales: @json($lineasIniciales),
			erroresValidacion: @json($errors->getMessages()),
			regimen: @json($regimen),
			modoInicial: @json($modoInicial),
			modoBloqueado: @json($modoBloqueado),
		};
	</script>
	<script src="{{ asset('js/albaranes-form.js') }}"></script>
@endpush
