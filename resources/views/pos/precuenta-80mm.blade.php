<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Precuenta</title>
	{{-- Precuenta (feature 049): documento NO fiscal. Plantilla propia a propósito (research D6):
	     si fuera un @if dentro de ticket-80mm, un cambio futuro del ticket podría colar en la
	     precuenta el QR, el número o la serie. Aquí no hay nada de eso: ni número, ni serie, ni
	     QR, ni NIF del emisor. Todo sale de la FOTO guardada en la fila, nunca de la cuenta viva. --}}
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
		.titulo { font-size: 15px; font-weight: bold; letter-spacing: 2px; text-align: center; margin: 2px 0; }
		.leyenda { text-align: center; font-weight: bold; font-size: 9px; border: 1px solid #000; padding: 3px; margin: 4px 0; }
		.reimpresion { text-align: center; font-size: 9px; font-weight: bold; margin-top: 2px; }
		.opcion { font-size: 9px; color: #333; padding-left: 8px; }
		.total { font-size: 13px; font-weight: bold; }
		.muted { color: #333; }
	</style>
</head>
<body>
	@php
		$__tenant = $precuenta->tenant;
		$__logo = $__tenant?->logo_facturacion_path
			? public_path('storage/'.$__tenant->logo_facturacion_path)
			: public_path('images/logardo.png');
		$__impuesto = \App\Enums\RegimenImpositivo::tryFrom($precuenta->regimen_impositivo)?->label() ?? 'IVA';
		$__dinero = fn ($v) => number_format((float) $v, 2, ',', '.');
		$__sumaLineas = collect($precuenta->lineas)->sum(fn ($l) => (float) $l['importe']);
		$__importeSuplemento = round((float) $precuenta->total - $__sumaLineas, 2);
	@endphp

	<div class="emisor">
		<img class="logo" src="{{ $__logo }}" alt="Logo"><br>
		<span class="bold">{{ $__tenant?->nombre_comercial }}</span>
	</div>

	<div class="titulo">PRECUENTA</div>
	<div class="leyenda">Documento no válido como factura</div>
	@if ($precuenta->reimpresion)
		<div class="reimpresion">Reimpresión</div>
	@endif

	<div class="sep"></div>

	<table>
		<tr>
			<td class="bold">
				@if ($precuenta->mesa_nombre)
					{{ $precuenta->mesa_nombre }}@if ($precuenta->zona_nombre) · {{ $precuenta->zona_nombre }}@endif
				@else
					Sin mesa
				@endif
			</td>
			{{-- Guardada en UTC; se muestra en la zona horaria del tenant (la hora que ve el cliente). --}}
			<td class="right">{{ $precuenta->emitida_en->enZonaTenant()->format('d/m/Y H:i') }}</td>
		</tr>
		@if ($precuenta->comensales)
			<tr><td colspan="2" class="muted">Comensales: {{ $precuenta->comensales }}</td></tr>
		@endif
		@if ($precuenta->usuario)
			<tr><td colspan="2" class="muted">Atendido por: {{ $precuenta->usuario->name }}</td></tr>
		@endif
	</table>

	<div class="sep"></div>

	<table>
		@foreach ($precuenta->lineas as $linea)
			<tr>
				<td colspan="2">{{ $linea['concepto'] }}</td>
			</tr>
			@foreach ($linea['opciones'] ?? [] as $opcion)
				<tr><td colspan="2" class="opcion">+ {{ $opcion }}</td></tr>
			@endforeach
			<tr>
				<td class="muted">{{ \App\Support\Formato::cantidad($linea['cantidad']) }} x {{ $__dinero($linea['precio_unitario']) }} €</td>
				<td class="right">{{ $__dinero($linea['importe']) }} €</td>
			</tr>
		@endforeach
		@if ((float) $precuenta->suplemento_zona > 0)
			{{-- Nunca un aumento silencioso: el suplemento de zona va como concepto visible. --}}
			<tr>
				<td>Suplemento {{ $precuenta->zona_nombre ?? 'zona' }} ({{ \App\Support\Formato::porcentaje($precuenta->suplemento_zona) }}%)</td>
				<td class="right">{{ $__dinero($__importeSuplemento) }} €</td>
			</tr>
		@endif
	</table>

	<div class="sep"></div>

	<table class="total">
		<tr>
			<td>TOTAL</td>
			<td class="right">{{ $__dinero($precuenta->total) }} €</td>
		</tr>
	</table>
	<div class="center muted">{{ $__impuesto }} incluido</div>

	<div class="sep"></div>
	<div class="leyenda">Documento no válido como factura</div>
	<div style="margin-top:6px" class="center muted">El ticket se entrega al pagar</div>
</body>
</html>
