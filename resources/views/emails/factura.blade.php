<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $factura->numero_completo ?? 'Factura' }}</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222;">
	<p>Hola{{ $factura->cliente ? ' '.($factura->cliente->razon_social ?: $factura->cliente->nombre) : '' }},</p>

	<p>
		Adjuntamos la factura <strong>{{ $factura->numero_completo }}</strong>,
		de fecha {{ $factura->fecha_expedicion->format('d/m/Y') }},
		por un importe total de <strong>{{ number_format((float) $factura->total, 2, ',', '.') }} €</strong>.
	</p>

	<p>Encontrarás el PDF de la factura adjunto a este correo.</p>

	<p>
		Un saludo,<br>
		{{ $factura->tenant->nombre_comercial }}
	</p>
</body>
</html>
