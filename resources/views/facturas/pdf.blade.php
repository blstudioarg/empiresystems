<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $factura->numero_completo ?? 'Borrador' }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
		.header { display: table; width: 100%; margin-bottom: 20px; }
		.header .col { display: table-cell; vertical-align: top; width: 50%; }
		.header .col.right { text-align: right; }
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
			<h1>{{ $factura->numero_completo ?? 'Factura (borrador)' }}</h1>
			<span class="badge">{{ ucfirst($factura->estado->value) }}</span>
				@if ($factura->es_rectificativa)
					<span class="badge">Factura rectificativa</span>
				@endif
				<br><br>
			Fecha de expedición: {{ $factura->fecha_expedicion->format('d/m/Y') }}<br>
			@if ($factura->fecha_operacion && $factura->fecha_operacion->ne($factura->fecha_expedicion))
				Fecha de operación: {{ $factura->fecha_operacion->format('d/m/Y') }}<br>
			@endif
			@if ($factura->fecha_vencimiento)
				Fecha de vencimiento: {{ $factura->fecha_vencimiento->format('d/m/Y') }}<br>
			@endif
			Forma de pago: {{ ucfirst($factura->forma_pago->value) }}
		</div>
	</div>

	<div>
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
					<td>{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4, ',', '.'), '0'), ',') }} {{ $linea->unidad }}</td>
					<td>{{ number_format((float) $linea->precio_unitario, 2, ',', '.') }} €</td>
					<td>{{ $linea->descuento_porcentaje ? $linea->descuento_porcentaje.'%' : '-' }}</td>
					<td>{{ $linea->tipo_impositivo }}%</td>
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

	@if ($factura->notas)
		<div style="margin-top:20px">
			<strong>Notas</strong><br>
			{{ $factura->notas }}
		</div>
	@endif
</body>
</html>
