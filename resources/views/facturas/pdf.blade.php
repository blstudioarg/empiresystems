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
		.logo { height: 50px; margin-bottom: 10px; }
		.badge { display: inline-block; padding: 2px 8px; background: #eee; border-radius: 4px; }
	</style>
</head>
<body>
	<div class="header">
		<div class="col">
			@if ($factura->tenant->logo_path)
				<img class="logo" src="{{ public_path('storage/'.$factura->tenant->logo_path) }}" alt="Logo">
			@endif
			<strong>{{ $factura->tenant->nombre_comercial }}</strong><br>
			{{ $factura->tenant->razon_social }}<br>
			NIF: {{ $factura->tenant->nif }}<br>
			{{ $factura->tenant->email }}
		</div>
		<div class="col right">
			<h1>{{ $factura->numero_completo ?? 'Factura (borrador)' }}</h1>
			<span class="badge">{{ ucfirst($factura->estado->value) }}</span><br><br>
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

	@if ($factura->notas)
		<div style="margin-top:20px">
			<strong>Notas</strong><br>
			{{ $factura->notas }}
		</div>
	@endif
</body>
</html>
