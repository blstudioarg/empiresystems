# Contrato: gestos de entrada de material

**Feature**: 047-adjuntar-pegar-arrastrar

Esta feature no expone endpoints nuevos. El único contrato de red es el que ya define la feature
046 (`POST /asistente/material`, `DELETE /asistente/material/{token}`), que se consume **sin
cambios**. Lo que sigue es el contrato de comportamiento de la interfaz: qué hace cada gesto, qué
garantiza y qué tiene prohibido hacer.

---

## G1 — Pegar en el campo de escribir

**Disparador**: evento de pegado sobre el campo de escribir del chat.

| | |
| --- | --- |
| **Entrada** | Contenido del portapapeles |
| **Condición** | El portapapeles trae al menos un fichero |
| **Efecto** | El primer fichero se adjunta a la conversación |
| **Garantía** | Si no hay ficheros, el evento no se altera y el texto se inserta normalmente |

**Prohibiciones**:

- No cancelar el comportamiento por defecto cuando el portapapeles solo trae texto (rompería
  pegar, SC-004).
- No escuchar fuera del panel del asistente (FR-004).
- No descartar en silencio: todo caso que no adjunte tiene que explicarse o ser un pegado de texto
  normal.

**Contenido mixto** (imagen + texto): se adjunta la imagen **y** se deja pasar el texto. Ninguno
de los dos se pierde.

---

## G2 — Soltar sobre el panel

**Disparador**: soltar uno o más ficheros sobre el panel del chat.

| | |
| --- | --- |
| **Entrada** | Ficheros arrastrados |
| **Condición** | El puntero está sobre el panel del asistente |
| **Efecto** | El primer fichero se adjunta a la conversación |
| **Garantía** | La página no se sustituye ni se pierde lo escrito sin enviar |

**Prohibiciones**:

- No permitir el comportamiento por defecto del navegador sobre la zona (abriría el fichero y
  descartaría la aplicación, FR-008/SC-005).
- No adjuntar nada si se suelta fuera del panel.
- No dejar la marca visual encendida tras soltar, salir o cancelar (FR-007).

**Estado visual**: mientras hay un arrastre sobre el panel, el panel indica que es zona de
destino. La marca se apaga en el primer `drop`, en la salida real de la zona y si el arrastre se
cancela. Los elementos hijos del panel no deben hacerla parpadear.

---

## G3 — Material sin módulo resuelto

Es el caso central de la feature ([research D2](../research.md)).

**Situación**: llega un fichero por G1 o G2 y todavía no se sabe a qué módulo pertenece la
importación.

| | |
| --- | --- |
| **Efecto** | El material se retiene, no se sube ni se descarta |
| **Salida** | Se pide el módulo en una frase, nombrando las opciones válidas |
| **Resolución** | Al conocerse el módulo, el material retenido se sube solo |

**Prohibiciones**:

- No inferir el módulo del contenido del fichero (gastaría una llamada al proveedor antes de
  validar permisos y contradice la 046).
- No adjuntar a un módulo que la persona no nombró.
- No quedarse en silencio: es el fallo que esta decisión existe para evitar.

**Descarte**: si la persona cambia de conversación o descarta el adjunto, el material retenido se
olvida. No sobrevive a un cambio de hilo.

---

## G4 — Varios ficheros en un gesto

| | |
| --- | --- |
| **Efecto** | Se adjunta el primero |
| **Garantía** | Se avisa explícitamente de que el resto no se adjuntó |

**Prohibición**: descartar los sobrantes sin decirlo.

---

## G5 — Invariantes comunes a los tres gestos

Valen para el clip, para pegar y para soltar. Son el motivo por el que los tres convergen en el
mismo camino de subida ([research D1](../research.md)):

1. **Mismas reglas**: tipo admitido, tamaño máximo y permiso de importación del módulo. Ningún
   gesto es una vía alternativa para saltarse una validación.
2. **Mismos mensajes**: los rechazos se explican en español con el texto que ya devuelve el
   servidor, mostrado como notificación del sistema.
3. **Mismo estado posterior**: mismo indicador de adjunto, misma forma de descartarlo, misma
   acumulación sobre una importación en curso.
4. **Mismo aislamiento**: el material pertenece a la conversación y al tenant de quien lo aporta
   (FR-013, Principio I de la constitución).

**Verificación del invariante 1**: no existe un segundo punto de subida en el cliente. Si
apareciera uno, este contrato queda roto aunque los mensajes coincidan.
