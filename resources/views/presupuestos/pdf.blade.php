<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $presupuesto->numero }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
		.header { display: table; width: 100%; margin-bottom: 20px; }
		.header .col { display: table-cell; vertical-align: top; width: 50%; }
		.receptor-info { margin-top: 60px; }
		h1 { font-size: 20px; margin: 0 0 5px; }
		table { width: 100%; border-collapse: collapse; margin-top: 15px; }
		th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
		th { background: #f4f4f4; }
		.text-right { text-align: right; }
		.totales { width: 300px; margin-left: auto; margin-top: 15px; }
		.totales td { border: none; padding: 3px 8px; }
		.totales .total-final { font-size: 15px; font-weight: bold; border-top: 2px solid #222; }
		.logo { display: block; max-height: 50px; max-width: 220px; width: auto; height: auto; margin-bottom: 10px; }
	</style>
</head>
<body>
	<div class="header">
		<div class="col">
			@php
				$__logo = $presupuesto->tenant->logo_facturacion_path
					? public_path('storage/'.$presupuesto->tenant->logo_facturacion_path)
					: public_path('images/logardo.png');
			@endphp
			<img class="logo" src="{{ $__logo }}" alt="Logo">
			<strong>{{ $presupuesto->tenant->nombre_comercial }}</strong><br>
			NIF: {{ $presupuesto->tenant->nif }}<br>
			{{ $presupuesto->tenant->email }}
		</div>
		<div class="col">
			<div class="receptor-info">
				<strong>Para</strong><br>
				{{ $presupuesto->receptor_nombre }}<br>
				@if ($presupuesto->receptor_nif)
					NIF: {{ $presupuesto->receptor_nif }}<br>
				@endif
				@if ($presupuesto->receptor_direccion)
					{{ $presupuesto->receptor_direccion }}<br>
				@endif
				{{ trim($presupuesto->receptor_cp.' '.$presupuesto->receptor_ciudad) }}
			</div>
		</div>
	</div>

	<h1>Presupuesto {{ $presupuesto->numero }}</h1>
	<table>
		<thead>
			<tr>
				<th>Fecha de emisión</th>
				<th>Válido hasta</th>
				<th>Estado</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>{{ $presupuesto->fecha_emision->format('d/m/Y') }}</td>
				<td>{{ $presupuesto->fecha_validez?->format('d/m/Y') ?? '-' }}</td>
				<td>{{ $presupuesto->estado->label() }}</td>
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
				<th>Impuesto</th>
				<th class="text-right">Base</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($presupuesto->lineas as $linea)
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
			<td class="text-right">{{ number_format((float) $presupuesto->base_total, 2, ',', '.') }} €</td>
		</tr>
		<tr>
			<td>{{ strtoupper($presupuesto->regimen_impositivo->value) }}</td>
			<td class="text-right">{{ number_format((float) $presupuesto->cuota_impuesto_total, 2, ',', '.') }} €</td>
		</tr>
		@if ((float) $presupuesto->cuota_recargo_total > 0)
			<tr>
				<td>Recargo de equivalencia</td>
				<td class="text-right">{{ number_format((float) $presupuesto->cuota_recargo_total, 2, ',', '.') }} €</td>
			</tr>
		@endif
		@if ((float) $presupuesto->irpf_cuota > 0)
			<tr>
				<td>IRPF {{ $presupuesto->irpf_porcentaje }}%</td>
				<td class="text-right">-{{ number_format((float) $presupuesto->irpf_cuota, 2, ',', '.') }} €</td>
			</tr>
		@endif
		<tr class="total-final">
			<td>Total</td>
			<td class="text-right">{{ number_format((float) $presupuesto->total, 2, ',', '.') }} €</td>
		</tr>
	</table>

	@if ($presupuesto->notas)
		<div style="margin-top:20px">
			<strong>Notas</strong><br>
			{{ $presupuesto->notas }}
		</div>
	@endif

	<p style="margin-top: 25px; color: #888;">Este documento es una oferta comercial, no una factura.</p>
</body>
</html>
