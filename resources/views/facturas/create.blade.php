@extends('layouts.app')

@section('title', $factura ? 'Editar factura' : 'Nueva factura')

@push('styles')
	<style>
		/* El "documento" imita el objeto final (misma lógica de bloques que facturas/pdf.blade.php)
		   para que crear/editar se sienta como trabajar sobre la factura real, no sobre un
		   formulario administrativo genérico. Fondo "papel" apenas distinto del blanco de las
		   demás cards del panel, para diferenciar visualmente "esto es el documento". */
		.factura-documento {
			background: #fdfcfa;
			border: 1px solid #ecebe7;
		}

		.factura-membrete {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			flex-wrap: wrap;
			gap: 1rem;
			padding-bottom: 1.25rem;
			margin-bottom: 1.25rem;
			border-bottom: 2px solid #222;
		}

		.factura-membrete .emisor {
			display: flex;
			align-items: center;
			gap: 0.75rem;
		}

		.factura-membrete .emisor img {
			height: 44px;
		}

		.factura-identificador {
			font-size: 1.1rem;
			font-weight: 700;
			letter-spacing: 0.02em;
		}

		.factura-meta-campo label {
			font-size: 0.72rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #8a8a8a;
			margin-bottom: 0.2rem;
		}

		/* Números tabulares: cantidad/precio/dto/IVA/base deben alinear en columna como en una
		   factura real, no "bailar" según la cantidad de dígitos (comportamiento por defecto de
		   la mayoría de las tipografías, incluida la del template). */
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

		/* Barra de acción inferior sticky: reemplaza a la card lateral de "Resumen" — deja que
		   el documento sea lo único que ocupa el ancho, y el total+acciones quedan siempre a
		   mano sin competir visualmente con la factura mientras se scrollea. */
		.factura-action-bar {
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

		.factura-action-bar .total-pill {
			margin-right: auto;
			font-size: 1rem;
		}

		.factura-action-bar .total-pill strong {
			font-size: 1.35rem;
			font-variant-numeric: tabular-nums;
		}

		/* Panel de catálogo a la izquierda del detalle: buscar/filtrar/elegir un artículo (o una
		   línea libre) es la acción, no un <select> escondido en el header de la tabla. */
		.factura-catalogo {
			border: 1px solid #ecebe7;
			border-radius: var(--bs-border-radius);
			padding: 0.75rem;
			height: 100%;
			display: flex;
			flex-direction: column;
		}

		/* Buscador del catálogo con icono, mismo lenguaje visual introducido en POS (pos-form). */
		.factura-catalogo-buscador-wrap { position: relative; }
		.factura-catalogo-buscador-wrap .factura-catalogo-buscador-icon {
			position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%);
			color: #b3b0a8; font-size: 0.78rem; pointer-events: none;
		}
		.factura-catalogo-buscador-wrap .form-control { padding-left: 1.85rem; }

		.factura-catalogo-filtros {
			display: flex;
			gap: 0.35rem;
			margin-top: 0.5rem;
		}

		.factura-catalogo-filtros .btn {
			flex: 1;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 0.3rem;
			border-radius: 2rem;
		}

		.factura-catalogo-filtros .btn .filtro-count {
			background: rgba(0, 0, 0, 0.08);
			border-radius: 1rem;
			padding: 0.02rem 0.4rem;
			font-size: 0.68rem;
		}

		/* Activo en azul de marca (coherente con el resto de la app), no el gris/negro por
		   defecto de outline-secondary.active de Bootstrap. */
		.factura-catalogo-filtros .btn.active {
			background: var(--primary, #1D69D6);
			border-color: var(--primary, #1D69D6);
			color: #fff;
		}

		.factura-catalogo-filtros .btn.active .filtro-count {
			background: rgba(255, 255, 255, 0.25);
		}

		.factura-catalogo-lista {
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
			border-left: 3px solid var(--factura-accent, transparent);
			transition: background .12s ease, transform .08s ease;
		}

		.catalogo-item:hover {
			background: #f6f5f2;
			transform: translateX(1px);
		}

		.catalogo-item:active {
			transform: scale(.98);
		}

		/* Destello breve al añadir la línea, para confirmar la acción sin interrumpir el flujo
		   (mismo patrón que ".just-added" en pos-form.js). */
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
			color: var(--factura-accent, #8a8a8a);
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

		/* Línea recién añadida a la tabla: mismo destello, para que quede claro qué fila es
		   nueva cuando el catálogo está a la izquierda y la tabla a la derecha. */
		.linea-row.just-added td {
			animation: catalogo-flash .6s ease;
		}

		/* Botón de quitar línea: discreto por defecto, visible al pasar por la fila (no compite
		   visualmente con el documento salvo que el usuario lo necesite). */
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

			<form id="factura-form" method="POST"
				action="{{ $factura ? route('facturas.update', $factura) : route('facturas.store') }}">
				@csrf
				@if ($factura)
					@method('PUT')
				@endif

				<div class="card factura-documento">
					<div class="card-body">

						<div class="factura-membrete">
							<div class="emisor">
								{{-- Mismo logo que se imprime en el PDF de la factura (logo de facturación
								     con fallback al logo por defecto), no el logo general de marca. --}}
								<img src="{{ tenant()->logo_facturacion_path ? asset('storage/'.tenant()->logo_facturacion_path) : asset('images/logardo.png') }}" alt="Logo de facturación">
								<div>
									<strong>{{ tenant()->nombre_comercial }}</strong><br>
									<small class="text-muted">{{ tenant()->nif }}</small>
								</div>
							</div>
							<div class="text-md-end">
								<div class="factura-identificador">{{ $factura?->numero_completo ?? 'Borrador' }}</div>
								<span class="badge badge-light-secondary text-uppercase">{{ $factura?->estado->value ?? 'borrador' }}</span>
							</div>
						</div>

						<div class="row gy-3 mb-4">
							<div class="col-md-4 factura-meta-campo">
								<label for="cliente_id" class="form-label d-block">Cliente</label>
								<select name="cliente_id" id="cliente_id" class="form-control">
									<option value="">Selecciona un cliente</option>
									@foreach ($clientes as $cliente)
										<option value="{{ $cliente->id }}"
											data-recargo="{{ $cliente->aplica_recargo_equivalencia ? 1 : 0 }}"
											data-nombre="{{ $cliente->nombre }}"
											data-razon-social="{{ $cliente->razon_social }}"
											data-nif="{{ $cliente->nif }}"
											data-direccion="{{ $cliente->direccion }}"
											data-cp="{{ $cliente->cp }}"
											data-ciudad="{{ $cliente->ciudad }}"
											data-provincia="{{ $cliente->provincia }}"
											data-pais="{{ $cliente->pais }}"
											@selected(old('cliente_id', $factura?->cliente_id) == $cliente->id)>
											{{ $cliente->razon_social ?: $cliente->nombre }}
										</option>
									@endforeach
								</select>
								<div class="invalid-feedback" data-error-for="cliente_id"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="fecha_expedicion" class="form-label d-block">Expedición</label>
								<input type="date" name="fecha_expedicion" id="fecha_expedicion" class="form-control"
									value="{{ old('fecha_expedicion', $factura?->fecha_expedicion?->toDateString() ?? now()->toDateString()) }}">
								<div class="invalid-feedback" data-error-for="fecha_expedicion"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="fecha_vencimiento" class="form-label d-block">Vencimiento</label>
								<input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control"
									value="{{ old('fecha_vencimiento', $factura?->fecha_vencimiento?->toDateString() ?? now()->addDays($diasVencimiento)->toDateString()) }}">
								<div class="invalid-feedback" data-error-for="fecha_vencimiento"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="forma_pago" class="form-label d-block">Forma de pago</label>
								<select name="forma_pago" id="forma_pago" class="form-control">
									@foreach (['transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'efectivo' => 'Efectivo', 'domiciliacion' => 'Domiciliación'] as $valor => $etiqueta)
										<option value="{{ $valor }}" @selected(old('forma_pago', $factura?->forma_pago?->value) === $valor)>{{ $etiqueta }}</option>
									@endforeach
								</select>
								<div class="invalid-feedback" data-error-for="forma_pago"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="irpf_porcentaje" class="form-label d-block">IRPF %</label>
								<input type="number" step="0.01" min="0" max="100" name="irpf_porcentaje" id="irpf_porcentaje"
									class="form-control" value="{{ old('irpf_porcentaje', $factura?->irpf_porcentaje) }}">
								<div class="invalid-feedback" data-error-for="irpf_porcentaje"></div>
							</div>
						</div>

						@php
							$formaPagoActual = old('forma_pago', $factura?->forma_pago?->value);
							$cuentaSeleccionada = old('cuenta_bancaria_id', $factura?->cuenta_bancaria_id);
						@endphp
						<div class="row gy-3 mb-4" id="cuenta-bancaria-wrapper" style="{{ $formaPagoActual === 'transferencia' ? '' : 'display:none;' }}">
							<div class="col-md-6 factura-meta-campo">
								<label for="cuenta_bancaria_id" class="form-label d-block">Cuenta bancaria de cobro</label>
								<select name="cuenta_bancaria_id" id="cuenta_bancaria_id" class="form-control">
									<option value="">Sin cuenta / indicar aparte</option>
									@foreach ($cuentasBancarias as $cuenta)
										<option value="{{ $cuenta->id }}" @selected((string) $cuentaSeleccionada === (string) $cuenta->id)>
											{{ $cuenta->alias }} — {{ $cuenta->banco?->nombre }} ({{ $cuenta->iban }})
										</option>
									@endforeach
								</select>
								<div class="invalid-feedback" data-error-for="cuenta_bancaria_id"></div>
								<small class="form-text text-muted">Solo se muestra en facturas con forma de pago «Transferencia». Se copia congelada al PDF.</small>
							</div>
						</div>

						<h5 class="mb-2">Datos del cliente en esta factura</h5>
						<p class="text-muted" style="font-size: 0.8125rem; margin-top: -0.5rem;">
							Se precargan al elegir el cliente, pero son propios de esta factura: se pueden editar sin que
							afecte a la ficha del cliente.
						</p>
						<div class="row gy-3 mb-4">
							<div class="col-md-4 factura-meta-campo">
								<label for="cliente_nombre" class="form-label d-block">Nombre</label>
								<input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control"
									value="{{ old('cliente_nombre', $factura?->cliente_nombre) }}">
								<div class="invalid-feedback" data-error-for="cliente_nombre"></div>
							</div>
							<div class="col-md-4 factura-meta-campo">
								<label for="cliente_razon_social" class="form-label d-block">Razón social</label>
								<input type="text" name="cliente_razon_social" id="cliente_razon_social" class="form-control"
									value="{{ old('cliente_razon_social', $factura?->cliente_razon_social) }}">
								<div class="invalid-feedback" data-error-for="cliente_razon_social"></div>
							</div>
							<div class="col-md-4 factura-meta-campo">
								<label for="cliente_nif" class="form-label d-block">NIF</label>
								<input type="text" name="cliente_nif" id="cliente_nif" class="form-control"
									value="{{ old('cliente_nif', $factura?->cliente_nif) }}">
								<div class="invalid-feedback" data-error-for="cliente_nif"></div>
							</div>
							<div class="col-md-6 factura-meta-campo">
								<label for="cliente_direccion" class="form-label d-block">Dirección</label>
								<input type="text" name="cliente_direccion" id="cliente_direccion" class="form-control"
									value="{{ old('cliente_direccion', $factura?->cliente_direccion) }}">
								<div class="invalid-feedback" data-error-for="cliente_direccion"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="cliente_cp" class="form-label d-block">Código postal</label>
								<input type="text" name="cliente_cp" id="cliente_cp" class="form-control"
									value="{{ old('cliente_cp', $factura?->cliente_cp) }}">
								<div class="invalid-feedback" data-error-for="cliente_cp"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="cliente_ciudad" class="form-label d-block">Ciudad</label>
								<input type="text" name="cliente_ciudad" id="cliente_ciudad" class="form-control"
									value="{{ old('cliente_ciudad', $factura?->cliente_ciudad) }}">
								<div class="invalid-feedback" data-error-for="cliente_ciudad"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="cliente_provincia" class="form-label d-block">Provincia</label>
								<input type="text" name="cliente_provincia" id="cliente_provincia" class="form-control"
									value="{{ old('cliente_provincia', $factura?->cliente_provincia) }}">
								<div class="invalid-feedback" data-error-for="cliente_provincia"></div>
							</div>
							<div class="col-md-2 factura-meta-campo">
								<label for="cliente_pais" class="form-label d-block">País</label>
								<input type="text" name="cliente_pais" id="cliente_pais" maxlength="2" class="form-control"
									value="{{ old('cliente_pais', $factura?->cliente_pais ?? 'ES') }}">
								<div class="invalid-feedback" data-error-for="cliente_pais"></div>
							</div>
						</div>

						<h5 class="mb-2">Líneas</h5>
						<div class="row gx-3">
							<div class="col-lg-3 mb-3 mb-lg-0">
								<div class="factura-catalogo">
									<label for="catalogo-buscador" class="form-label mb-1">Añadir artículo</label>
									<div class="factura-catalogo-buscador-wrap">
										<i class="fas fa-search factura-catalogo-buscador-icon"></i>
										<input type="search" id="catalogo-buscador" class="form-control form-control-sm"
											placeholder="Buscar por nombre o SKU…">
									</div>

									<div class="factura-catalogo-filtros" role="group">
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

									<div id="catalogo-lista" class="factura-catalogo-lista"></div>
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
								<textarea name="notas" id="notas" rows="3" class="form-control">{{ old('notas', $factura?->notas) }}</textarea>
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

				<div class="factura-action-bar">
					<div class="total-pill text-muted">
						Total factura <strong id="preview-total-bar">0,00 €</strong>
					</div>
					<a href="{{ route('facturas.index') }}" class="btn btn-light">Cancelar</a>
					<button type="submit" class="btn btn-primary">Guardar borrador</button>
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

@push('scripts')
	<script>
		window.facturaFormState = {
			articulosUrl: @json(route('articulos.index')),
			lineasIniciales: @json($lineasIniciales),
			aplicaRecargoCliente: {{ $factura?->cliente?->aplica_recargo_equivalencia ? 'true' : 'false' }},
			erroresValidacion: @json($errors->getMessages()),
			regimen: @json($regimen),
		};
	</script>
	<script src="{{ asset('js/facturas-form.js') }}"></script>
	<script>
		(function () {
			const formaPago = document.getElementById('forma_pago');
			const wrapper = document.getElementById('cuenta-bancaria-wrapper');
			const select = document.getElementById('cuenta_bancaria_id');
			if (!formaPago || !wrapper) return;

			function toggle() {
				const esTransferencia = formaPago.value === 'transferencia';
				wrapper.style.display = esTransferencia ? '' : 'none';
				if (!esTransferencia && select) select.value = '';
			}

			formaPago.addEventListener('change', toggle);
			toggle();
		})();
	</script>
@endpush
