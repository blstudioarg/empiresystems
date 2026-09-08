# Asistente IA

El asistente es un panel lateral que se abre desde el ícono de destello (✦, el símbolo habitual de
IA) de la barra superior (topbar),
disponible en todas las pantallas salvo la de fichar. Responde dudas de
funcionamiento, consulta datos con las tools disponibles según los permisos del usuario, y propone
crear o editar clientes, artículos, presupuestos y facturas en borrador (siempre con confirmación).
Las conversaciones se guardan: sobreviven al cierre de sesión y quedan en un historial propio de
cada usuario, al que se llega desde el icono de reloj de la cabecera del panel. Desde ahí se puede
reabrir cualquier conversación anterior para continuarla, o borrarla. "Conversación nueva" abre un
hilo vacío sin perder el anterior.

Las conversaciones se conservan **90 días desde la última vez que se usaron** y luego se eliminan
automáticamente junto con sus mensajes; un administrador puede cambiar ese plazo en
Configuración → Asistente IA. Es un requisito de protección de datos, no una limitación técnica.

Cuando una conversación se hace muy larga, la parte más antigua se resume automáticamente para no
perder el hilo: el panel avisa mientras lo hace y marca la parte resumida. El asistente sigue
sabiendo lo que se habló antes, aunque no lo conserve palabra por palabra.
Lo activa un administrador desde Configuración → Asistente IA pegando la clave de API de OpenAI.

Mientras el asistente trabaja, el panel muestra un indicador de progreso ("Enviando…",
"Pensando…") y el botón de enviar queda deshabilitado hasta que termina el turno. Cuando el
asistente propone crear o editar algo, el turno se detiene ahí a la espera de que el usuario
confirme o cancele en la tarjeta: no vuelve a preguntar lo mismo por su cuenta.

Cuando le pedís varias altas a la vez (por ejemplo, "creá 10 clientes de prueba"), el asistente
prepara todas juntas y te las muestra en **una sola tarjeta** con la lista completa: las revisás y
confirmás una vez, no diez. El máximo por tarjeta es 20. Si alguna no se puede completar (datos que
faltan, un NIF repetido), las demás se crean igual y el asistente te dice cuáles quedaron fuera y
por qué: nunca se pierde el lote entero por un error en un elemento.

## Importar ficheros conversando

El asistente puede recibir material para importar (clientes, artículos o proveedores): un Excel, un
CSV, un PDF, una foto o un texto. Lo analiza, dice qué falta, corrige contigo lo que haga falta y lo
importa cuando lo confirmás, sin pasar por la pantalla de importación. El clip para adjuntar aparece
en el panel **solo cuando la conversación va de importar algo**; no sirve para adjuntar ficheros a
cualquier otra cosa. El detalle completo del flujo, los formatos admitidos y sus límites están en la
guía de importación y exportación.

## Sugerencias del panel vacío

Cuando no hay conversación empezada, el panel ofrece sugerencias agrupadas por categoría (Importar,
Consultar, Crear, Aprender). Solo se ofrece lo que la persona puede hacer con sus permisos, así que
dos personas distintas ven listas distintas. Pulsar una la envía como mensaje, y desaparecen en
cuanto la conversación arranca.
