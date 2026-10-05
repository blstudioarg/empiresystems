<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ __('Cierre de caja :numero', ['numero' => $informe['numero']]) }}</title>
	<style>
		/* Rollo de 80 mm, misma técnica que facturas/ticket-80mm. Monoespaciada: es la voz del
		   papel térmico, y alinea las cifras en columna sin depender de tabular-nums. */
		@page { margin: 6px 8px; }
		body { font-family: DejaVu Sans Mono, monospace; font-size: 9px; color: #000; }
		.cab { text-align: center; }
		.grande { font-size: 11px; }
		.titulo { font-size: 11px; font-weight: bold; margin-top: 4px; letter-spacing: .5px; }
		.center { text-align: center; }
		.right { text-align: right; white-space: nowrap; }
		.bold { font-weight: bold; }
		.muted { color: #333; }
		.sec { font-weight: bold; margin-bottom: 2px; letter-spacing: .5px; }
		.sep { border-top: 1px dashed #000; margin: 5px 0; }
		table { width: 100%; border-collapse: collapse; }
		td { padding: 1px 0; vertical-align: top; }
		.total td { font-size: 11px; font-weight: bold; }
	</style>@if ($fuenteCjk ?? false){!! \App\Traduccion\Bilingue::estiloFuenteCjk() !!}@endif
</head>
<body>
	@include('caja._informe-contenido')
</body>
</html>
