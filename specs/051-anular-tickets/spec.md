# Feature Specification: Anular tickets desde el listado del POS

**Feature Branch**: `051-anular-tickets`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Poder eliminar los tickets desde la tabla de Facturas simplificadas del POS." Acordado con el usuario el 2026-10-05: un ticket emitido no se elimina (es una factura), se **anula**.

## Contexto y encuadre

En el listado del POS (Facturas simplificadas) hoy solo se puede ver o imprimir cada ticket. El
negocio necesita poder dejar sin efecto un ticket emitido por error (se cobró a la mesa
equivocada, se duplicó, el cliente se fue sin llevarse nada…).

### Encuadre normativo

Un ticket es una **factura simplificada** ya expedida: no se puede borrar. La numeración de una
serie debe ser correlativa y sin huecos, y la factura emitida es inmutable
(`docs/02-facturacion-espana.md` §7, constitución Principio II). Con el sistema Verifactu activo,
dejar sin efecto una factura expedida por error exige un **registro de anulación** encadenado
(`docs/02-facturacion-espana.md` §1). La aplicación ya implementa esa **anulación** para las
facturas ordinarias (motivo obligatorio, evento en el historial de la factura, registro de
anulación Verifactu y entrada en el registro de actividad); esta feature la lleva al POS. Lo que
el usuario pidió como "eliminar" se resuelve como **anular**: el ticket sigue existiendo con su
número, marcado como anulado.

Una **devolución** real (el cliente devuelve un producto que se llevó) no es una anulación: se
documenta con una factura rectificativa y queda fuera de esta feature.

### Convenciones de front que condicionan esta spec

Leídas antes de redactar (`docs/04-front-guidelines.md`, REGLA DE ORO de `CLAUDE.md`):

- § **"Columna Acciones de los listados"**: la acción va como un ítem más del dropdown «Acciones»,
  separada por un divisor por ser destructiva, y el backend decide si se muestra (solo en tickets
  que se pueden anular).
- § **"Confirmación de acciones irreversibles"**: modal genérico de confirmación, nunca
  `confirm()`; aquí además hay que escribir el motivo.
- § **"Modales: siempre centrados"** y § **"Estado de carga en botones"**.
- § **"Badges de estado"**: el estado «Anulado» se muestra con un badge de la familia existente.
- § **"Notificaciones"**: resultado con toast.
- § **"Textos traducibles"** (feature 050): todos los textos nuevos del POS se marcan para
  traducirse; el motivo que escribe la persona es un dato y no se traduce.

## Clarifications

### Session 2026-10-05

- Q: ¿«Eliminar» un ticket es borrarlo? → A: No: se **anula** (borrado físico descartado por normativa).
- Q: ¿Quién puede anular? → A: Un **permiso propio** del módulo POS, por defecto solo en el rol Administrador; el administrador lo da a otros roles.
- Q: ¿Qué pasa con el stock que descontó el ticket? → A: **Vuelve al stock** con un movimiento de entrada.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Anular un ticket emitido por error (Priority: P1)

La encargada ve en el listado de tickets uno que se emitió por error. Desde «Acciones» elige
«Anular», escribe el motivo («ticket duplicado») y confirma. El ticket queda marcado como anulado
en el listado, con su número intacto, y deja de contar en los totales de ventas.

**Why this priority**: es exactamente lo que pidió el usuario; sin ello un ticket erróneo queda
contando como venta para siempre.

**Independent Test**: emitir un ticket, anularlo desde el listado con un motivo y comprobar que
aparece como anulado, que no suma en los totales del listado ni en la caja abierta, y que su
número sigue ocupado.

**Acceptance Scenarios**:

1. **Given** un ticket emitido no anulado, **When** un usuario con permiso elige «Anular» en
   «Acciones», escribe un motivo y confirma, **Then** el ticket queda anulado, se muestra con el
   estado «Anulado» y aparece un aviso de éxito.
2. **Given** el modal de anulación abierto, **When** se intenta confirmar sin motivo, **Then** no
   se anula y se pide el motivo.
3. **Given** un ticket ya anulado, **When** se abre su menú «Acciones», **Then** la opción
   «Anular» no aparece; ver e imprimir el ticket siguen disponibles.
4. **Given** un ticket anulado, **When** se mira el listado, **Then** las tarjetas «Tickets
   emitidos» e «Importe total» no lo cuentan.
5. **Given** un ticket anulado, **When** se emite el siguiente ticket de la misma serie, **Then**
   recibe el número siguiente: el número del anulado no se reutiliza ni queda un hueco.

---

### User Story 2 - Que la anulación tenga efecto en caja y stock (Priority: P1)

Al anular un ticket del turno, el efectivo esperado de la caja baja en lo que se había cobrado en
efectivo, y las unidades que el ticket había descontado del inventario vuelven al stock.

**Why this priority**: si anular no corrige la caja y el stock, el arqueo descuadra y el
inventario queda mal; la anulación sería solo cosmética.

**Independent Test**: con la caja abierta, emitir un ticket en efectivo de un artículo con stock,
anularlo y comprobar el efectivo esperado y el stock antes y después.

**Acceptance Scenarios**:

1. **Given** una caja abierta y un ticket de ese turno cobrado en efectivo, **When** se anula,
   **Then** lo vendido y el efectivo esperado del turno dejan de incluirlo, y el ticket figura en
   la lista de anulados del informe de cierre.
2. **Given** un ticket de un artículo con control de stock, **When** se anula, **Then** se
   registra un movimiento de entrada que devuelve al stock las unidades que el ticket había
   descontado (también las de las opciones vinculadas a otro artículo), y el historial de
   movimientos conserva la salida original.
3. **Given** un ticket de un día cuya caja ya se cerró, **When** se anula, **Then** el cierre ya
   hecho no cambia (su informe sigue mostrando lo que había al cerrar).

---

### User Story 3 - Cumplimiento: Verifactu y trazabilidad (Priority: P1)

Cuando el negocio tiene Verifactu activo, anular un ticket genera el registro de anulación
correspondiente, igual que con una factura ordinaria. Siempre queda constancia de quién lo anuló,
cuándo y por qué.

**Why this priority**: es lo que hace que anular sea legal; sin ello la anulación sería una
manipulación del registro de facturación.

**Independent Test**: con Verifactu activo, anular un ticket y comprobar que existe su registro de
anulación encadenado; en cualquier caso, comprobar el evento con el motivo y el registro de
actividad.

**Acceptance Scenarios**:

1. **Given** un tenant con Verifactu activo y un ticket con registro de alta, **When** se anula,
   **Then** se genera su registro de anulación, encadenado al último registro del tenant.
2. **Given** cualquier tenant, **When** se anula un ticket, **Then** el historial del ticket
   guarda la anulación con el motivo y el registro de actividad guarda quién la hizo.

---

### User Story 4 - Solo quien tiene permiso puede anular (Priority: P2)

Anular un ticket corrige ventas y caja, así que no todo cajero puede hacerlo: hace falta un
permiso específico, que el administrador asigna a los roles que quiera.

**Why this priority**: anular ventas ya cobradas es la vía clásica de fraude en un TPV; separarlo de
"poder cobrar" es la práctica habitual.

**Independent Test**: con un usuario sin el permiso, comprobar que no ve la opción y que el
servidor rechaza la petición; con el permiso, que puede anular.

**Acceptance Scenarios**:

1. **Given** un usuario sin el permiso de anular tickets, **When** abre el listado, **Then** no ve
   «Anular» y, si intenta anular igualmente, el servidor lo rechaza.
2. **Given** un usuario con el permiso de anular tickets pero sin acceso a las facturas
   ordinarias, **When** anula un ticket, **Then** puede hacerlo.
3. **Given** dos tenants, **When** un usuario intenta anular un ticket del otro tenant, **Then** no
   lo encuentra y no se anula nada.

---

### Edge Cases

- **Ticket con un cobro registrado aparte** (un cobro de factura sobre el ticket, no el pago del
  propio ticket): no se anula; el aviso indica que debe hacerse con una rectificativa, igual que
  con las facturas ordinarias.
- **Ticket que ya tiene una rectificativa**: no se anula (ya está corregido por otra vía).
- **Ticket de una cuenta de mesa cobrada**: se anula como cualquier ticket; la cuenta de mesa
  sigue cerrada (no se reabre ni se recupera su consumo).
- **Dos personas anulan a la vez el mismo ticket**: solo una anulación tiene efecto; la otra
  recibe un aviso de que ya estaba anulado.
- **Ticket en borrador o que nunca se emitió**: no aparece la opción (el POS solo emite tickets
  ya emitidos).
- **POS en chino**: la acción, el modal, el badge y los avisos se ven traducidos; el motivo se
  guarda tal cual lo escribió la persona.

## Requirements *(mandatory)*

### Functional Requirements

**Acción y flujo**

- **FR-001**: El listado de tickets del POS DEBE ofrecer en «Acciones» la opción «Anular» en los
  tickets emitidos que se pueden anular, separada del resto por ser destructiva.
- **FR-002**: Anular DEBE exigir un motivo escrito (obligatorio, de hasta 500 caracteres) y una
  confirmación explícita antes de ejecutarse.
- **FR-003**: Un ticket anulado DEBE mostrarse en el listado con el estado «Anulado» y seguir
  pudiéndose ver e imprimir.
- **FR-004**: Las tarjetas de totales del listado («Tickets emitidos», «Importe total») NO DEBEN
  contar los tickets anulados.
- **FR-005**: NO DEBE poder anularse un ticket que ya esté anulado, que no esté emitido, que tenga
  cobros registrados aparte o que ya tenga una rectificativa; el aviso DEBE decir por qué.

**Efectos**

- **FR-006**: Anular NO DEBE borrar el ticket ni liberar su número: la numeración de la serie
  sigue correlativa y sin huecos.
- **FR-007**: Anular DEBE guardar en el historial del ticket la anulación con su motivo, y en el
  registro de actividad quién la hizo y cuándo.
- **FR-008**: Con Verifactu activo y un ticket con registro de alta, anular DEBE generar su
  registro de anulación encadenado, igual que con una factura ordinaria.
- **FR-009**: Anular DEBE devolver al stock, mediante movimientos de entrada, las unidades que el
  ticket había descontado (incluidas las de opciones vinculadas a otro artículo). Los movimientos
  originales no se modifican.
- **FR-010**: Un ticket anulado NO DEBE sumar en lo vendido ni en el efectivo esperado de la caja
  abierta, y DEBE figurar en la lista de anulados de su informe de cierre. Un cierre ya hecho NO
  DEBE cambiar.
- **FR-011**: Anular un ticket que salió del cobro de una cuenta de mesa NO DEBE reabrir ni
  modificar esa cuenta.

**Acceso**

- **FR-012**: Anular tickets DEBE requerir un permiso propio del módulo POS, independiente del
  acceso a las facturas ordinarias y del permiso de cobrar. La opción no se muestra sin él y el
  servidor rechaza la petición.
- **FR-013**: Solo se pueden anular tickets del propio tenant (Principio I).

**Idioma y documentación**

- **FR-014**: Los textos nuevos (acción, modal, badge, avisos y mensajes del servidor) DEBEN
  traducirse con el idioma del POS (feature 050). El motivo no se traduce.
- **FR-015**: La guía «Ayuda de esta pantalla» del listado de tickets y la base de conocimiento del
  asistente DEBEN explicar cómo anular un ticket y en qué se diferencia de una devolución.

### Key Entities

- **Ticket (factura simplificada)**: ya existente. Gana el estado «anulado» en el POS (el mismo
  estado que ya tienen las facturas); conserva número, importes y documento.
- **Evento de anulación**: entrada del historial del ticket con el motivo y quién lo anuló.
- **Registro de anulación Verifactu**: el ya existente para facturas, ahora también para tickets.
- **Movimiento de stock de devolución**: entrada que revierte la salida del ticket.
- **Permiso «Anular tickets»**: nuevo permiso del módulo POS.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Anular un ticket desde el listado lleva menos de 30 segundos (abrir acciones,
  escribir el motivo, confirmar).
- **SC-002**: El 100 % de los tickets anulados conserva su número, sin huecos en la serie.
- **SC-003**: Tras anular un ticket del turno, el efectivo esperado de la caja coincide al
  céntimo con el que habría si ese ticket no se hubiera emitido.
- **SC-004**: Tras anular un ticket, el stock de cada artículo afectado vuelve exactamente a su
  valor anterior a la venta (si no hubo otros movimientos entre medias).
- **SC-005**: El 100 % de las anulaciones con Verifactu activo genera su registro de anulación.
- **SC-006**: Ningún usuario sin el permiso consigue anular un ticket.

## Assumptions

- **"Eliminar" es anular**: confirmado con el usuario el 2026-10-05. El borrado físico queda
  descartado por normativa.
- **Anular ≠ devolver**: la anulación es para tickets emitidos por error. Una devolución de
  mercancía o de dinero de una venta real se documenta con una rectificativa, fuera de alcance.
  La guía de ayuda lo explica.
- **El cobro del ticket no impide anular**: los pagos con los que se cobró el ticket en caja forman
  parte del propio ticket; anularlo deja de contarlos en la caja. Solo bloquea un cobro registrado
  aparte sobre el ticket (como en las facturas ordinarias).
- **Permiso**: el permiso nuevo se asigna por defecto a los roles de administración, igual que el
  resto de permisos nuevos; el administrador decide a qué otros roles dárselo.
- **Sin ventana de tiempo**: se puede anular un ticket de cualquier fecha (la normativa no fija un
  plazo y el registro de anulación lleva su propia fecha); un cierre de caja pasado no se toca.
- **Las facturas ordinarias no cambian** en esta feature: su anulación sigue como está (que hoy no
  revierte el stock es una decisión aparte, fuera de alcance).
