# Research: Adjuntar material pegando o arrastrando

**Feature**: 047-adjuntar-pegar-arrastrar
**Fecha**: 2026-09-08

Todas las decisiones se tomaron leyendo el código que ya existe
(`public/js/asistente-chat.js`, `resources/views/partials/asistente-chat.blade.php`,
`app/Http/Controllers/AsistenteMaterialController.php`, `app/Support/MaterialImportable.php`) y
`docs/04-front-guidelines.md`, sección "Widget del asistente IA (feature 030)".

---

## D1 — El material ya viaja por un camino que no hay que tocar

**Decisión**: reutilizar `subirMaterial(fichero)`
([asistente-chat.js:274](../../public/js/asistente-chat.js#L274)) sin modificarla. Los gestos
nuevos solo tienen que conseguir un `File` y llamarla.

**Motivo**: la función ya arma el `FormData`, adjunta el módulo y el token de acumulación, muestra
el estado, y traduce todos los rechazos del contrato a `window.showToast`. Un `File` obtenido del
portapapeles o de un arrastre es indistinguible de uno salido del `<input type="file">`. Duplicar
esa lógica sería crear un segundo camino que se desincroniza al primer cambio.

**Consecuencia directa**: FR-009 y FR-010 (mismas reglas y mismos mensajes) se cumplen por
construcción, no por disciplina. No hay forma de que un gesto nuevo se salte una validación,
porque no existe otro punto de subida.

**Alternativas descartadas**: un endpoint o una función aparte para material pegado — más
superficie, ninguna ganancia.

---

## D2 — El obstáculo real: el clip está oculto hasta que se nombra el módulo

**Hallazgo**: `subirMaterial()` empieza con
`if (!urlMaterial || !moduloImportacion) return;` ([línea 275](../../public/js/asistente-chat.js#L275)),
y `moduloImportacion` solo se asigna en `detectarContextoImportacion()`
([línea 228](../../public/js/asistente-chat.js#L228)), que exige que el texto escrito nombre un
módulo (`clientes`, `artículos`, `proveedores`) **y** una intención de importar. Hasta entonces el
clip está `hidden` y `moduloImportacion` es `null`.

**El problema**: es exactamente el orden inverso al que propone la feature. Pegar una captura es
un gesto que la persona hace **antes** de explicar nada — ese es el punto entero de la historia
P1. Si los gestos nuevos heredan la guarda tal cual, pegar una captura recién abierto el chat
**no haría absolutamente nada, en silencio**: la peor variante posible, porque el usuario no
distingue "no soportado" de "roto".

**Decisión**: cuando llega material por un gesto nuevo y todavía no hay `moduloImportacion`, **no
descartarlo y no adivinar el módulo**. Se retiene el fichero y se pide el dato que falta en una
sola frase ("¿Esto es de clientes, artículos o proveedores?"). En cuanto se resuelve el módulo, el
material retenido sube solo.

**Motivo**: preserva la decisión de la 046 —no se importa a un módulo que nadie nombró (research
D5 de aquella feature, y FR-010 de su spec: la prohibición es cierta por construcción)— sin
convertir el gesto en un agujero negro. Inferir el módulo del contenido de la imagen exigiría
analizarla antes de saber si se puede, que es justo lo que la 046 evita.

**Alternativas descartadas**:
- *Aplicar la guarda y no hacer nada*: rompe P1 y viola SC-003 (todo rechazo se explica).
- *Adivinar el módulo por el contenido*: gasta una llamada al proveedor antes de validar permisos,
  y contradice la 046.
- *Habilitar el clip siempre*: cambia el alcance de la 046, que es una feature ya cerrada.

**Riesgo asumido**: es la parte con más lógica nueva de la feature. Se acota a un único punto
(la retención) y se cubre con casos explícitos en `quickstart.md`.

---

## D3 — Pegar: distinguir fichero de texto sin romper el pegado normal

**Decisión**: escuchar `paste` **en el campo de escribir**, no en `document`. Recorrer
`clipboardData.items` quedándose con los de `kind === 'file'`. Si no hay ninguno, no tocar el
evento y dejar que el navegador inserte el texto.

**Motivo**: FR-003 y FR-004. Un listener en `document` capturaría pegados de toda la aplicación
—incluido cualquier formulario— y es la clase de efecto colateral que aparece semanas después en
una pantalla sin relación. El campo del chat es la superficie donde la persona ya está escribiendo.

**`preventDefault` solo cuando hay fichero**: si se llama siempre, se rompe pegar texto (SC-004).

**Contenido mixto**: al copiar de un documento suele venir imagen *y* texto. Se adjunta la imagen y
**no** se llama a `preventDefault`, de modo que el texto acompañante entra en el campo por el
camino normal. Nada se pierde en silencio (edge case del spec).

---

## D4 — Nombre del material pegado

**Decisión**: `captura-YYYYMMDD-HHMMSS.<ext>`, con la extensión derivada del tipo del `File`.

**Motivo**: FR-005. Una captura llega con `name` vacío o un `image.png` genérico. Dos capturas
seguidas serían indistinguibles en el indicador de adjunto y en la conversación. El sello temporal
las ordena y las hace nombrables ("la de las 14:32").

**Detalle**: `AsistenteMaterialController` valida con `getClientOriginalExtension()`, así que el
nombre tiene que llevar extensión real o el fichero se rechazaría por tipo no admitido. Se
construye un `File` nuevo con el nombre correcto a partir del original.

---

## D5 — Arrastrar: zona de destino y el riesgo de perder la página

**Decisión**: `dragover`/`dragleave`/`drop` sobre el **panel** del chat, con `preventDefault()` en
`dragover` y en `drop`. Marca visual con una clase sobre el panel.

**Motivo**: FR-006, FR-007, FR-008. Sin `preventDefault` en `dragover`, el navegador aplica su
comportamiento por defecto: abre el fichero y **descarta la página**, con lo que se pierde lo que
la persona tuviera escrito sin enviar (SC-005). Es el fallo más caro de esta feature y el más
fácil de cometer.

**`dragleave` y elementos hijos**: `dragleave` dispara también al pasar sobre elementos hijos del
panel, lo que hace parpadear la marca. Se resuelve con un contador de entradas/salidas en vez de
limpiar en el primer `dragleave`.

**Alcance del listener**: el panel, no la ventana. Soltar un fichero fuera del chat no debe
adjuntar nada (acceptance scenario 3 de P2).

---

## D6 — Varios ficheros a la vez

**Decisión**: tomar el primero y avisar con un toast de que el resto no se adjuntó.

**Motivo**: FR-011 y la Assumption del spec. Es lo que ya hace el clip (`files[0]`,
[línea 256](../../public/js/asistente-chat.js#L256)). Aceptar varios de golpe multiplica los
estados intermedios (uno entra, otro se rechaza por tamaño, un tercero por tipo) y obligaría a
decidir qué mostrar cuando el resultado es mixto — sin resolver ningún caso que no se resuelva
adjuntando de a uno, que el endpoint ya soporta vía `token`.

**Lo que no se hace**: descartar en silencio. El aviso es obligatorio.

---

## D7 — Estilos y notificaciones

**Decisión**: CSS scoped bajo `.asistente-chat__*` en el `@push('styles')` del propio partial,
usando `var(--primary)` para la marca del tenant. Todo aviso por `window.showToast`.

**Motivo**: `docs/04-front-guidelines.md`, sección "Widget del asistente IA (feature 030)" —el CSS
del widget vive en su partial y respeta el color de marca— y la sección "Notificaciones" del
CLAUDE.md: siempre toastr, nunca alerts ad-hoc.

**Nota sobre el color**: la marca del tenant se declara también en `body` con `!important` (ver
`AparienciaTenant::variablesCss()`); usar `var(--primary)` hereda ese valor sin tocar nada.

---

## D8 — Táctil y navegadores sin soporte

**Decisión**: no detectar capacidades ni ocultar nada. Los listeners simplemente no se disparan
donde el gesto no existe, y el clip sigue siendo el camino.

**Motivo**: última Assumption del spec. Añadir detección de capacidades sería complejidad sin
beneficio: no hay nada que degradar porque el camino original nunca se retira.

---

## Resumen de riesgos

| Riesgo | Mitigación |
| --- | --- |
| Pegar una captura antes de nombrar el módulo no hace nada en silencio | D2: retener y preguntar |
| `preventDefault` de más rompe pegar texto | D3: solo cuando hay ficheros |
| Falta `preventDefault` en `dragover` → se pierde la página y lo escrito | D5: explícito, y caso en quickstart |
| Marca de arrastre parpadea sobre elementos hijos | D5: contador de entrada/salida |
| Dos capturas indistinguibles | D4: sello temporal |
| Listener de `paste` global captura toda la app | D3: acotado al campo del chat |
