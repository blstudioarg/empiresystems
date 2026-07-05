@extends('layouts.app')

@section('title', 'Compra')

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xl-12">
					<div class="card">
						<div class="card-header flex-wrap">
							<h4 class="card-title mb-0">
								Compra {{ $compra->numero_documento ?? '#'.$compra->id }}
								<span class="badge bg-secondary" id="compra-estado-badge">{{ ucfirst($compra->estado->value) }}</span>
							</h4>
							<div class="d-flex gap-2" id="compra-acciones">
								@if ($compra->estado->value === 'borrador')
									<a href="{{ route('compras.edit', $compra) }}" class="btn btn-outline-primary">Editar</a>
									<button type="button" class="btn btn-primary" id="btn-confirmar-compra">Confirmar (repone stock)</button>
								@endif
								@if ($compra->estado->value === 'confirmada')
									<button type="button" class="btn btn-danger" id="btn-anular-compra">Anular (revierte stock)</button>
								@endif
							</div>
						</div>
						<div class="card-body">
							<p><strong>Proveedor:</strong> {{ $compra->proveedor->razon_social ?: $compra->proveedor->nombre }}</p>
							<p><strong>Fecha:</strong> {{ $compra->fecha->toDateString() }}</p>

							<table class="table table-sm">
								<thead>
									<tr>
										<th>Concepto</th>
										<th>Cantidad</th>
										<th>Precio</th>
										<th>% Impuesto</th>
										<th>Base</th>
										<th>Cuota</th>
									</tr>
								</thead>
								<tbody>
									@foreach ($compra->lineas as $linea)
										<tr>
											<td>{{ $linea->concepto }} @if ($linea->articulo) <span class="badge bg-light text-dark">gestiona stock: {{ $linea->articulo->gestion_stock ? 'sí' : 'no' }}</span> @endif</td>
											<td>{{ \App\Support\Formato::cantidad($linea->cantidad) }} {{ $linea->unidad }}</td>
											<td>{{ \App\Support\Formato::moneda($linea->precio_unitario) }} €</td>
											<td>{{ \App\Support\Formato::porcentaje($linea->tipo_impositivo) }}%</td>
											<td>{{ \App\Support\Formato::moneda($linea->base) }} €</td>
											<td>{{ \App\Support\Formato::moneda($linea->cuota_impuesto) }} €</td>
										</tr>
									@endforeach
								</tbody>
							</table>

							<p><strong>Base total:</strong> {{ \App\Support\Formato::moneda($compra->base_total) }} € — <strong>Cuota impuesto:</strong> {{ \App\Support\Formato::moneda($compra->cuota_impuesto_total) }} € — <strong>Total:</strong> {{ \App\Support\Formato::moneda($compra->total) }} €</p>

							<div id="compra-movimientos">
								@if ($compra->movimientos->isNotEmpty())
									<h6>Movimientos de stock generados</h6>
									<ul>
										@foreach ($compra->movimientos as $movimiento)
											<li>{{ ucfirst($movimiento->tipo->value) }} de {{ \App\Support\Formato::cantidad($movimiento->cantidad) }} — stock resultante {{ \App\Support\Formato::cantidad($movimiento->stock_resultante) }}</li>
										@endforeach
									</ul>
								@endif
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('scripts')
	<script>
		window.compraEstadoState = {
			confirmarUrl: @json(route('compras.confirmar', $compra)),
			anularUrl: @json(route('compras.anular', $compra)),
			editUrl: @json(route('compras.edit', $compra)),
		};
	</script>
	<script src="{{ asset('js/plugins-init/compras-show.init.js') }}"></script>
@endpush
