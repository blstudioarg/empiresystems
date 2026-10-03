<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Cierre de caja {{ $informe['numero'] }}</title>
	<style>
		/* A4: el mismo contenido que el rollo, en una columna centrada de lectura cómoda. Se
		   mantiene la monoespaciada para que el informe se reconozca igual en los dos formatos. */
		@page { margin: 28mm 0; }
		body { font-family: DejaVu Sans Mono, monospace; font-size: 10.5px; color: #111; }
		.hoja { width: 105mm; margin: 0 auto; }
		.cab { text-align: center; }
		.grande { font-size: 14px; }
		.titulo { font-size: 13px; font-weight: bold; margin-top: 6px; letter-spacing: 1px; }
		.center { text-align: center; }
		.right { text-align: right; white-space: nowrap; }
		.bold { font-weight: bold; }
		.muted { color: #555; }
		.sec { font-weight: bold; margin-bottom: 3px; letter-spacing: 1px; }
		.sep { border-top: 1px dashed #777; margin: 8px 0; }
		table { width: 100%; border-collapse: collapse; }
		td { padding: 2px 0; vertical-align: top; }
		.total td { font-size: 13px; font-weight: bold; }
	</style>
</head>
<body>
	<div class="hoja">
		@include('caja._informe-contenido')
	</div>
</body>
</html>
