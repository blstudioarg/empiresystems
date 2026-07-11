@extends('layouts.app')

@section('title', $albaran->numero)

@section('content')
	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-5">
					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h4 class="card-title mb-0">{{ $albaran->numero }}</h4>
							<span class="badge bg-primary-light" id="albaran-estado-badge">{{ $albaran->estado->label() }}</span>
						</div>
						<div class="card-body">
							<dl class="row mb-0">
								<dt class="col-5">Cliente</dt>
								<dd class="col-7">{{ $albaran->receptor_nombre }}</dd>
								<dt class="col-5">Fecha de entrega</dt>
								<dd class="col-7">{{ $albaran->fecha_entrega?->format('d/m/Y') ?? '—' }}</dd>
								<dt class="col-5">Total</dt>
								<dd class="col-7">{{ number_format((float) $albaran->total, 2, ',', '.') }} €</dd>
								<dt class="col-5">Presupuesto de origen</dt>
								<dd class="col-7">
									@if ($albaran->presupuesto)
										<a href="{{ route('presupuestos.pdf', $albaran->presupuesto) }}" target="_blank">{{ $albaran->presupuesto->numero }}</a>
									@else
										Venta directa (sin presupuesto)
									@endif
								</dd>
								<dt class="col-5">Factura</dt>
								<dd class="col-7">
									@if ($albaran->facturaConvertida)
										<a href="{{ route('facturas.edit', $albaran->facturaConvertida) }}">{{ $albaran->facturaConvertida->numero_completo ?? 'Borrador #'.$albaran->facturaConvertida->id }}</a>
									@else
										Sin facturar
									@endif
								</dd>
							</dl>
						</div>
						<div class="card-footer d-flex flex-column gap-2">
							@if ($albaran->estado->esEditable())
								<a href="{{ route('albaranes.edit', $albaran) }}" class="btn btn-outline-primary w-100">Editar</a>
								<button type="button" class="btn btn-success w-100" id="btn-entregar" data-loading-text="...">Marcar como entregado</button>
							@endif
							@if ($albaran->estado->value === 'entregado')
								<button type="button" class="btn btn-outline-danger w-100" id="btn-anular" data-loading-text="...">Anular albarán</button>
							@endif
						</div>
					</div>
				</div>

				<div class="col-lg-7">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0">Líneas</h5>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table class="table">
									<thead>
										<tr>
											<th>Concepto</th>
											<th class="text-end">Cantidad</th>
											<th class="text-end">Precio</th>
											<th class="text-end">Base</th>
										</tr>
									</thead>
									<tbody>
										@foreach ($albaran->lineas as $linea)
											<tr>
												<td>{{ $linea->concepto }}</td>
												<td class="text-end">{{ \App\Support\Formato::cantidad($linea->cantidad) }}{{ $linea->unidad ? ' '.$linea->unidad : '' }}</td>
												<td class="text-end">{{ \App\Support\Formato::moneda($linea->precio_unitario) }} €</td>
												<td class="text-end">{{ \App\Support\Formato::moneda($linea->base) }} €</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script>
		window.albaranShowState = {
			estadoUrl: @json(route('albaranes.estado', $albaran)),
		};
	</script>
@endsection

@push('scripts')
	<script src="{{ asset('js/plugins-init/albaranes-show.init.js') }}"></script>
@endpush

@section('ayuda-titulo', 'Albaranes')
@section('ayuda')
	@include('ayuda.albaranes')
@endsection
