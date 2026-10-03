# Quickstart: validar los gestos de adjuntar

**Feature**: 047-adjuntar-pegar-arrastrar

Guía de validación manual. La feature es solo front y no añade lógica de servidor, así que esta
guía **es** la verificación principal (ver "Estrategia de test" en [plan.md](plan.md)). Cada
escenario cita el requisito que comprueba.

## Prerrequisitos

```bash
php artisan serve
npm run build          # o `npm run dev` si se está iterando
```

Entrar con un usuario con permiso de importar clientes (ver `ACCESOS.local.md`). Hace falta el
asistente IA configurado con clave, si no el widget no se emite.

Tener a mano:

- Una imagen en el portapapeles (una captura de pantalla sirve).
- Un `.xlsx` o `.csv` de clientes.
- Un fichero de tipo no admitido (por ejemplo un `.zip`).
- Un fichero de más de 5 MB.

## Regresión previa (obligatoria)

Antes de tocar nada, y otra vez al terminar:

```bash
php artisan test --filter="Asistente|Material|Importacion"
```

Deben quedar en verde **sin modificar ningún test**. Si alguno hubo que tocarlo, la feature rompió
el camino de la 046 y eso es un fallo, no un ajuste.

---

## E1 — Pegar una captura recién abierto el chat (FR-001, FR-005, research D2)

El escenario que motiva la feature.

1. Abrir el asistente sin escribir nada.
2. Pegar la imagen con Ctrl+V en el campo de escribir.

**Esperado**: el asistente pregunta de qué módulo es (nombrando clientes, artículos y
proveedores). El material queda retenido, no se pierde.

3. Responder "clientes".

**Esperado**: el material retenido se sube solo, aparece en el indicador de adjunto con un nombre
que incluye fecha y hora, y el asistente puede analizarlo.

**Fallo crítico**: que al pegar no ocurra nada visible. Es exactamente lo que
[research D2](research.md) existe para evitar.

## E2 — Pegar texto sigue funcionando (FR-003, SC-004)

1. Copiar un texto cualquiera.
2. Pegarlo en el campo de escribir.

**Esperado**: el texto se inserta en el campo. No aparece ningún adjunto ni ninguna notificación.

**Fallo**: que el texto no se inserte, o que aparezca un aviso de material.

## E3 — Pegar fuera del chat no hace nada (FR-004)

1. Con una imagen en el portapapeles, ir a cualquier formulario de la app (por ejemplo alta de
   cliente) con el asistente cerrado.
2. Pegar en un campo de texto.

**Esperado**: comportamiento normal del campo. Nada relacionado con el asistente.

## E4 — Contenido mixto (edge case del spec)

1. Copiar de un documento un fragmento que incluya imagen y texto.
2. Pegar en el campo de escribir.

**Esperado**: la imagen se adjunta **y** el texto aparece en el campo. Ninguno de los dos se pierde
en silencio.

## E5 — Arrastrar y soltar (FR-002, FR-006, FR-007)

1. Escribir "quiero importar clientes" para que el contexto quede resuelto.
2. Arrastrar el `.xlsx` desde el explorador hacia el panel del chat, **sin soltar**.

**Esperado**: el panel se marca visualmente como zona de destino.

3. Mover el puntero por encima de varios elementos internos del panel (mensajes, campo, botones).

**Esperado**: la marca **no parpadea** ([research D5](research.md)).

4. Soltar.

**Esperado**: el fichero queda adjuntado y la marca desaparece.

## E6 — No perder la página al soltar (FR-008, SC-005)

El fallo más caro de la feature.

1. Escribir un mensaje largo en el campo **sin enviarlo**.
2. Arrastrar un fichero y soltarlo sobre el panel.

**Esperado**: el fichero se adjunta y **el texto escrito sigue ahí**.

3. Repetir soltando sobre una zona del chat que no sea el centro del panel.

**Esperado**: en ningún caso el navegador abre el fichero ni reemplaza la aplicación.

**Fallo crítico**: que la página se sustituya por el fichero. Significa que falta cancelar el
comportamiento por defecto en `dragover`.

## E7 — Soltar fuera del panel (P2, escenario 3)

1. Arrastrar un fichero y soltarlo sobre el contenido de la página, fuera del chat.

**Esperado**: no se adjunta nada. Tampoco se abre el fichero.

## E8 — Arrastrar algo que no es un fichero (edge case)

1. Seleccionar texto de otra página y arrastrarlo sobre el panel.

**Esperado**: no se adjunta nada, y la marca visual —si llegó a aparecer— desaparece sin rastro.

## E9 — Rechazos por tipo y por tamaño (FR-009, FR-010, SC-003)

1. Con el contexto resuelto, pegar o arrastrar el `.zip`.

**Esperado**: notificación en español explicando que ese tipo no se admite. Idéntica a la que da el
clip con el mismo fichero.

2. Repetir con el fichero de más de 5 MB.

**Esperado**: notificación indicando el límite.

En ambos casos: **no se adjunta nada** y el aviso llega como notificación del sistema, no como un
bloque de alerta dentro del chat.

## E10 — Varios ficheros de una vez (FR-011)

1. Seleccionar tres ficheros admitidos y arrastrarlos juntos al panel.

**Esperado**: se adjunta uno y aparece un aviso explícito de que el resto no se tomó.

**Fallo**: que los sobrantes desaparezcan sin mención.

## E11 — Equivalencia con el clip (SC-006, contrato G5)

1. Adjuntar un `.xlsx` con el clip y anotar el diagnóstico del asistente.
2. Empezar una conversación nueva y adjuntar **el mismo fichero** arrastrándolo.

**Esperado**: el mismo diagnóstico. El gesto de entrada no cambia el resultado.

## E12 — Acumulación y descarte (contrato G5, punto 3)

1. Adjuntar un fichero con el clip.
2. Pegar una imagen a continuación.

**Esperado**: el segundo material se suma a la misma importación, igual que si se hubieran
adjuntado los dos con el clip.

3. Descartar el adjunto con su botón de quitar.

**Esperado**: se descarta, con la misma notificación de siempre.

## E13 — Permisos (FR-009, FR-013)

1. Entrar con un usuario **sin** permiso de importar clientes.
2. Intentar adjuntar un fichero de clientes pegándolo y arrastrándolo.

**Esperado**: el mismo aviso de permiso que da el clip. El gesto nuevo no es una vía para
saltárselo.

---

## Cierre

- [ ] E1-E13 verificados
- [ ] Suite completa en verde (`php artisan test`)
- [ ] Guía in-app actualizada (`resources/views/ayuda/importar-exportar.blade.php`)
- [ ] Base de conocimiento actualizada (`resources/ia/conocimiento/asistente.md`) — hoy dice que
      el clip es la única vía; si no se actualiza, el asistente explica mal su propia función
