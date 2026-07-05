<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $factura->numero_completo ?? 'Borrador' }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
		.header { display: table; width: 100%; margin-bottom: 20px; }
		.header .col { display: table-cell; vertical-align: top; width: 50%; }
		.header .col.right { text-align: left; }
		.cliente-info { margin-top: 60px; }
		h1 { font-size: 20px; margin: 0 0 5px; }
		table { width: 100%; border-collapse: collapse; margin-top: 15px; }
		th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
		th { background: #f4f4f4; }
		.text-right { text-align: right; }
		.totales { width: 300px; margin-left: auto; margin-top: 15px; }
		.totales td { border: none; padding: 3px 8px; }
		.totales .total-final { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
		.logo-wrap { display: block; clear: both; margin-bottom: 10px; }
		.logo { display: block; max-height: 50px; max-width: 220px; width: auto; height: auto; }
		.emisor-info { display: block; clear: both; }
		.badge { display: inline-block; padding: 2px 8px; background: #eee; border-radius: 4px; }
	</style>
</head>
<body>
	<div class="header">
		<div class="col">
			@php
				$__logoFacturacion = $factura->tenant->logo_facturacion_path
					? public_path('storage/'.$factura->tenant->logo_facturacion_path)
					: public_path('images/logardo.png');
			@endphp
			<div class="logo-wrap">
				<img class="logo" src="{{ $__logoFacturacion }}" alt="Logo">
			</div>
			<div class="emisor-info">
				<strong>{{ $factura->tenant->nombre_comercial }}</strong><br>
				{{ $factura->tenant->razon_social }}<br>
				NIF: {{ $factura->tenant->nif }}<br>
				@if ($factura->tenant->direccion)
					{{ $factura->tenant->direccion }}<br>
				@endif
				@if ($factura->tenant->cp || $factura->tenant->ciudad || $factura->tenant->provincia)
					{{ trim($factura->tenant->cp.' '.$factura->tenant->ciudad) }}@if ($factura->tenant->provincia) ({{ $factura->tenant->provincia }})@endif<br>
				@endif
				@if ($factura->tenant->pais)
					{{ $factura->tenant->pais }}<br>
				@endif
			{{ $factura->tenant->email }}
			</div>
		</div>
		<div class="col right">
			<div class="cliente-info">
			<strong>Cliente</strong><br>
			{{ $factura->cliente_razon_social ?: $factura->cliente_nombre }}<br>
			@if ($factura->cliente_nif)
				NIF: {{ $factura->cliente_nif }}<br>
			@endif
			@if ($factura->cliente_direccion)
				{{ $factura->cliente_direccion }}<br>
			@endif
			{{ trim($factura->cliente_cp.' '.$factura->cliente_ciudad) }}@if ($factura->cliente_provincia) ({{ $factura->cliente_provincia }})@endif<br>
			@if ($factura->cliente_pais)
				{{ $factura->cliente_pais }}
			@endif
			</div>
		</div>
	</div>

	<table>
		<thead>
			<tr>
				<th>Número</th>
				<th>Fecha de expedición</th>
				@if ($factura->fecha_operacion && $factura->fecha_operacion->ne($factura->fecha_expedicion))
					<th>Fecha de operación</th>
				@endif
				<th>Fecha de vencimiento</th>
				<th>Estado</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					{{ $factura->numero_completo ?? 'Borrador' }}
					@if ($factura->es_rectificativa)<br><span class="badge">Rectificativa</span>@endif
				</td>
				<td>{{ $factura->fecha_expedicion->format('d/m/Y') }}</td>
				@if ($factura->fecha_operacion && $factura->fecha_operacion->ne($factura->fecha_expedicion))
					<td>{{ $factura->fecha_operacion->format('d/m/Y') }}</td>
				@endif
				<td>{{ $factura->fecha_vencimiento ? $factura->fecha_vencimiento->format('d/m/Y') : '-' }}</td>
				<td>{{ ucfirst($factura->estado->value) }}</td>
			</tr>
		</tbody>
	</table>

	<table>
		<thead>
			<tr>
				<th>Concepto</th>
				<th>Cantidad</th>
				<th>Precio</th>
				<th>Dto.</th>
				<th>IVA</th>
				<th class="text-right">Base</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($factura->lineas as $linea)
				<tr>
					<td>{{ $linea->concepto }}</td>
					<td>{{ \App\Support\Formato::cantidad($linea->cantidad) }} {{ $linea->unidad }}</td>
					<td>{{ \App\Support\Formato::moneda($linea->precio_unitario) }} €</td>
					<td>{{ $linea->descuento_porcentaje ? \App\Support\Formato::porcentaje($linea->descuento_porcentaje).'%' : '-' }}</td>
					<td>{{ \App\Support\Formato::porcentaje($linea->tipo_impositivo) }}%</td>
					<td class="text-right">{{ number_format((float) $linea->base, 2, ',', '.') }} €</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	<table class="totales">
		<tr>
			<td>Base imponible</td>
			<td class="text-right">{{ number_format((float) $factura->base_total, 2, ',', '.') }} €</td>
		</tr>
		@foreach ($factura->impuestos as $impuesto)
			<tr>
				<td>{{ strtoupper($impuesto->tipo_impuesto->value) }} {{ $impuesto->porcentaje }}%</td>
				<td class="text-right">{{ $impuesto->tipo_impuesto->value === 'irpf' ? '-' : '' }}{{ number_format((float) $impuesto->cuota, 2, ',', '.') }} €</td>
			</tr>
		@endforeach
		<tr class="total-final">
			<td>Total</td>
			<td class="text-right">{{ number_format((float) $factura->total, 2, ',', '.') }} €</td>
		</tr>
	</table>

	<div style="clear: both;"></div>
	<p style="margin-top: 25px;"><strong>Forma de pago:</strong> {{ ucfirst($factura->forma_pago->value) }}</p>

	@if ($factura->cuenta_bancaria_iban)
		<table>
			<thead>
				<tr>
					<th colspan="2">Datos de cobro</th>
				</tr>
			</thead>
			<tbody>
				@if ($factura->cuenta_bancaria_banco)
					<tr>
						<td style="width: 160px;">Banco</td>
						<td>{{ $factura->cuenta_bancaria_banco }}</td>
					</tr>
				@endif
				<tr>
					<td style="width: 160px;">IBAN</td>
					<td>{{ $factura->cuenta_bancaria_iban }}</td>
				</tr>
				@if ($factura->cuenta_bancaria_titular)
					<tr>
						<td style="width: 160px;">Titular</td>
						<td>{{ $factura->cuenta_bancaria_titular }}</td>
					</tr>
				@endif
			</tbody>
		</table>
	@endif

	@if ($factura->es_rectificativa)
		<div style="margin-top:20px">
			<strong>Factura rectificativa</strong> ({{ $factura->tipo_rectificacion?->value === 'diferencias' ? 'por diferencias' : 'por sustitución' }})<br>
			Rectifica a la factura {{ $factura->facturaRectificada->numero_completo }}<br>
			Motivo: {{ $factura->motivo_rectificacion }}
		</div>
	@elseif ($factura->rectificativa)
		<div style="margin-top:20px">
			<strong>Rectificada</strong> por la factura {{ $factura->rectificativa->numero_completo }}
		</div>
	@endif

	@if ($factura->estado->value === 'emitida' && $factura->pagos->isNotEmpty())
		<div style="margin-top:20px">
			<strong>Historial de cobros</strong>
			<table>
				<thead>
					<tr>
						<th>Fecha</th>
						<th>Método</th>
						<th>Referencia</th>
						<th>Estado</th>
						<th class="text-right">Importe</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($factura->pagos as $pago)
						<tr>
							<td>{{ $pago->fecha->format('d/m/Y') }}</td>
							<td>{{ ucfirst($pago->metodo->value) }}</td>
							<td>{{ $pago->referencia ?: '-' }}</td>
							<td>{{ $pago->anulado_at ? 'Anulado' : 'Vigente' }}</td>
							<td class="text-right">{{ number_format((float) $pago->importe, 2, ',', '.') }} €</td>
						</tr>
					@endforeach
				</tbody>
			</table>
			<p>Saldo pendiente: {{ number_format($factura->saldoPendiente(), 2, ',', '.') }} €</p>
		</div>
	@endif

	@if ($factura->notas)
		<div style="margin-top:20px">
			<strong>Notas</strong><br>
			{{ $factura->notas }}
		</div>
	@endif
</body>
</html>
