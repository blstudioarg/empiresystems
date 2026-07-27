<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Confirmá tu nuevo correo</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222;">
	<p>Hola {{ $user->name }},</p>

	<p>
		Recibimos una solicitud para cambiar el correo de tu cuenta a
		<strong>{{ $user->pending_email }}</strong>. Para confirmarlo, hacé clic en el siguiente
		enlace (válido por 24 horas):
	</p>

	<p><a href="{{ $urlVerificacion }}">Confirmar cambio de correo</a></p>

	<p>Si no solicitaste este cambio, podés ignorar este correo: tu email actual no se modifica
		hasta que confirmes el enlace.</p>

	<p>
		Un saludo,<br>
		{{ $user->tenant?->nombre_comercial ?? $user->tenant?->name }}
	</p>
</body>
</html>
