<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $factura->numero_completo ?? 'Ticket' }}</title>
	<style>
		@page { margin: 6px 8px; }
		body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; }
		.center { text-align: center; }
		.right { text-align: right; }
		.bold { font-weight: bold; }
		.emisor { text-align: center; margin-bottom: 6px; }
		.emisor .logo { max-height: 40px; max-width: 180px; margin-bottom: 4px; }
		.sep { border-top: 1px dashed #000; margin: 6px 0; }
		table { width: 100%; border-collapse: collapse; }
		td { padding: 1px 0; vertical-align: top; }
		.lineas td { font-size: 10px; }
		.total { font-size: 13px; font-weight: bold; }
		.muted { color: #333; }
	</style>
</head>
<body>
	<div class="emisor">
		@php
			$__logoFacturacion = $factura->tenant->logo_facturacion_path
				? public_path('storage/'.$factura->tenant->logo_facturacion_path)
				: public_path('images/logardo.png');
		@endphp
		<img class="logo" src="{{ $__logoFacturacion }}" alt="Logo"><br>
		<span class="bold">{{ $factura->tenant->nombre_comercial }}</span><br>
		<span class="muted">NIF: {{ $factura->tenant->nif }}</span><br>
		@if ($factura->tenant->direccion)
			<span class="muted">{{ $factura->tenant->direccion }}</span><br>
		@endif
		@if ($factura->tenant->cp || $factura->tenant->ciudad)
			<span class="muted">{{ trim($factura->tenant->cp.' '.$factura->tenant->ciudad) }}</span>
		@endif
	</div>

	<div class="sep"></div>

	<table>
		<tr>
			<td class="bold">{{ $factura->numero_completo ?? 'Ticket' }}</td>
			<td class="right">{{ $factura->fecha_expedicion->format('d/m/Y') }}</td>
		</tr>
		<tr>
			<td colspan="2" class="muted">Factura simplificada</td>
		</tr>
	</table>

	@if ($factura->cliente_nif)
		<div class="sep"></div>
		<table>
			<tr><td class="bold" colspan="2">Receptor</td></tr>
			<tr><td colspan="2">{{ $factura->cliente_razon_social ?: $factura->cliente_nombre }}</td></tr>
			<tr><td colspan="2" class="muted">NIF: {{ $factura->cliente_nif }}</td></tr>
			@if ($factura->cliente_direccion)
				<tr><td colspan="2" class="muted">{{ $factura->cliente_direccion }}</td></tr>
			@endif
		</table>
	@endif

	<div class="sep"></div>

	<table class="lineas">
		@foreach ($factura->lineas as $linea)
			<tr>
				<td colspan="2">{{ $linea->concepto }}</td>
			</tr>
			<tr>
				<td class="muted">
					{{ \App\Support\Formato::cantidad($linea->cantidad) }} {{ $linea->unidad }}
					x {{ \App\Support\Formato::moneda($linea->precio_unitario) }} €
					@if ($linea->descuento_porcentaje) (-{{ \App\Support\Formato::porcentaje($linea->descuento_porcentaje) }}%) @endif
					· {{ \App\Support\Formato::porcentaje($linea->tipo_impositivo) }}%
				</td>
				<td class="right">{{ number_format((float) $linea->base, 2, ',', '.') }} €</td>
			</tr>
		@endforeach
	</table>

	<div class="sep"></div>

	<table>
		<tr>
			<td>Base imponible</td>
			<td class="right">{{ number_format((float) $factura->base_total, 2, ',', '.') }} €</td>
		</tr>
		@foreach ($factura->impuestos as $impuesto)
			<tr>
				<td>{{ strtoupper($impuesto->tipo_impuesto->value) }} {{ $impuesto->porcentaje }}%</td>
				<td class="right">{{ $impuesto->tipo_impuesto->value === 'irpf' ? '-' : '' }}{{ number_format((float) $impuesto->cuota, 2, ',', '.') }} €</td>
			</tr>
		@endforeach
	</table>

	<div class="sep"></div>

	<table class="total">
		<tr>
			<td>TOTAL</td>
			<td class="right">{{ number_format((float) $factura->total, 2, ',', '.') }} €</td>
		</tr>
	</table>

	<div class="sep"></div>
	<div class="center muted">Forma de pago: {{ ucfirst($factura->forma_pago->value) }}</div>
	@if ($factura->notas)
		<div class="sep"></div>
		<div class="muted">{{ $factura->notas }}</div>
	@endif
	<div style="margin-top:8px" class="center muted">Gracias por su visita</div>
</body>
</html>
