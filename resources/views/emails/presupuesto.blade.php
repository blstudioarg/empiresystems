<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $presupuesto->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222;">
	<p>Hola{{ $presupuesto->receptor_nombre ? ' '.$presupuesto->receptor_nombre : '' }},</p>

	<p>
		Adjuntamos el presupuesto <strong>{{ $presupuesto->numero }}</strong>,
		de fecha {{ $presupuesto->fecha_emision->format('d/m/Y') }},
		por un importe total de <strong>{{ number_format((float) $presupuesto->total, 2, ',', '.') }} €</strong>.
		@if ($presupuesto->fecha_validez)
			Válido hasta el {{ $presupuesto->fecha_validez->format('d/m/Y') }}.
		@endif
	</p>

	<p>Encontrarás el PDF del presupuesto adjunto a este correo.</p>

	<p>
		Un saludo,<br>
		{{ $presupuesto->tenant->nombre_comercial }}
	</p>
</body>
</html>
