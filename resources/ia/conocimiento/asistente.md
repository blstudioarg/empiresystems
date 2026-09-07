# Asistente IA

El asistente es un panel lateral que se abre desde el ícono de chat de la barra superior (topbar),
disponible en todas las pantallas salvo la de fichar. Responde dudas de
funcionamiento, consulta datos con las tools disponibles según los permisos del usuario, y propone
crear o editar clientes, artículos, presupuestos y facturas en borrador (siempre con confirmación).
La conversación se conserva mientras dure la sesión y se puede reiniciar con "conversación nueva".
Lo activa un administrador desde Configuración → Asistente IA pegando la clave de API de OpenAI.

Mientras el asistente trabaja, el panel muestra un indicador de progreso ("Enviando…",
"Pensando…") y el botón de enviar queda deshabilitado hasta que termina el turno. Cuando el
asistente propone crear o editar algo, el turno se detiene ahí a la espera de que el usuario
confirme o cancele en la tarjeta: no vuelve a preguntar lo mismo por su cuenta.
