# Feature Specification: Cierre de caja del POS

**Feature Branch**: `048-cierre-caja-pos`

**Created**: 2026-10-03

**Status**: Draft

**Input**: User description: "Cierre de caja del POS (TPV), pensado para tablet y como un POS real. El negocio quiere saber todo lo que factura en el día. Flujo de caja: apertura con fondo inicial, entradas/salidas manuales de efectivo durante el turno, y cierre con arqueo (efectivo esperado vs contado, por denominaciones, con teclado táctil propio) y descuadre. Informe de cierre tipo «informe Z» (tickets, total, desglose por método de pago y por tipo impositivo, anulados, movimientos, esperado vs contado, descuadre, quién abrió/cerró y cuándo), imprimible en 80 mm o A4 con vista previa en modal. Histórico de cierres en un listado. Cierre inmutable. Interfaz tablet-first, estética de POS real, coherente con Crear ticket y con docs/04-front-guidelines.md."

## Contexto y encuadre

Hoy el POS emite tickets (facturas simplificadas) y guarda **cómo se cobró cada uno** (efectivo,
tarjeta, transferencia, domiciliación, incluso repartido entre varios métodos), pero **no existe el
concepto de caja**: no hay apertura, ni fondo de cambio, ni arqueo, ni un informe de fin de jornada.
El cliente lo reportó como "el cerrar caja no me anda", y la investigación confirmó que la función
nunca existió (ni pantalla, ni lógica, ni datos).

La pregunta de negocio que esta feature responde es la de cualquier comercio u hostelería al bajar
la persiana: *"¿cuánto vendí hoy, cómo me lo pagaron, y el dinero que hay en el cajón cuadra con lo
que dice el sistema?"*.

Lo que esta feature **no** toca: la emisión de tickets, su numeración, su cálculo de impuestos ni su
registro Verifactu. El cierre de caja **lee** lo que el POS ya registra; no altera ningún documento
fiscal. Tampoco es un requisito normativo (el "informe Z" no es un documento exigido por la AEAT para
un sistema Verifactu): es una herramienta de **control interno** del negocio.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Abrir la caja al empezar la jornada (Priority: P1)

Como encargado o cajero, al empezar el turno abro la caja desde la tablet indicando con cuánto
efectivo arranca el cajón (el fondo de cambio). Lo tecleo con un teclado numérico grande en pantalla
—o, si prefiero, contando billete a billete—, confirmo, y a partir de ese momento todo lo que se
cobre en el POS queda asociado a esta caja.

**Why this priority**: sin apertura no hay sesión de caja a la que atribuir las ventas ni fondo
inicial contra el que calcular el efectivo esperado. Es el cimiento de todo lo demás.

**Independent Test**: abrir caja con un fondo de 150,00 €, verificar que la pantalla de caja pasa a
estado "abierta" mostrando el fondo, quién la abrió y a qué hora, y que un ticket emitido a
continuación figura como venta de esa sesión.

**Acceptance Scenarios**:

1. **Given** no hay ninguna caja abierta, **When** el usuario entra en la pantalla de caja, **Then**
   ve un estado "Caja cerrada" con una acción principal grande "Abrir caja".
2. **Given** el usuario está abriendo caja, **When** teclea el fondo con el teclado propio de la
   pantalla, **Then** el teclado del sistema operativo no aparece y el importe se ve en grande en
   todo momento.
3. **Given** el usuario confirma la apertura con un fondo de 150,00 €, **When** la operación termina,
   **Then** la caja queda abierta, registrando el fondo, el usuario que la abrió y la fecha/hora.
4. **Given** ya hay una caja abierta, **When** otro usuario intenta abrir caja, **Then** el sistema
   no crea una segunda sesión y le muestra la sesión abierta existente.
5. **Given** el usuario indica un fondo de 0,00 €, **When** confirma, **Then** la caja se abre igual
   (arrancar sin cambio es válido).

---

### User Story 2 - Cerrar la caja con arqueo e informe Z (Priority: P1)

Al terminar la jornada, cierro la caja. El sistema me pide que cuente el efectivo del cajón: puedo
contar por denominaciones (cuántos billetes de 50, de 20, cuántas monedas de 2 €…) tocando cada una
y tecleando la cantidad, o introducir directamente el total contado. **Mientras cuento no veo cuánto
"debería" haber** (arqueo ciego, para que el conteo sea honesto). Al confirmar el conteo, el sistema
me muestra el resultado: efectivo esperado, efectivo contado y la diferencia (sobrante o faltante),
y genera el informe de cierre (informe Z) con todo lo facturado en la sesión. Puedo verlo en
pantalla e imprimirlo en la impresora de tickets (80 mm) o en A4.

**Why this priority**: es exactamente lo que pidió el cliente ("saber todo lo que facturan en el
día") y la razón de ser de la feature. Junto con la apertura forma el MVP.

**Independent Test**: con una caja abierta con fondo 100,00 € y tres tickets (40,00 € en efectivo,
25,00 € con tarjeta, y 30,00 € repartido 10,00 € efectivo + 20,00 € tarjeta), cerrar contando
148,00 € en efectivo y verificar: esperado 150,00 €, contado 148,00 €, faltante 2,00 €; total
facturado 95,00 €; efectivo 50,00 €, tarjeta 45,00 €; 3 tickets.

**Acceptance Scenarios**:

1. **Given** una caja abierta, **When** el usuario inicia el cierre, **Then** ve la pantalla de
   conteo con todas las denominaciones de euro (billetes de 500 a 5 €, monedas de 2 € a 0,01 €) en
   botones grandes, y un total contado que se actualiza en vivo.
2. **Given** el usuario está contando, **When** mira la pantalla, **Then** no se muestra en ningún
   lugar el efectivo esperado ni el descuadre hasta que confirma el conteo.
3. **Given** el usuario prefiere no contar por denominaciones, **When** elige introducir el total
   directamente, **Then** puede teclear el importe contado con el teclado propio.
4. **Given** el usuario confirma el conteo, **When** el sistema cierra la sesión, **Then** calcula el
   efectivo esperado como fondo inicial + cobros en efectivo de los tickets de la sesión + entradas
   manuales − salidas manuales, y registra el descuadre (contado − esperado).
5. **Given** el cierre se completó, **When** el usuario ve el resultado, **Then** el descuadre se
   presenta con un código visual inequívoco: cuadra (0,00 €), sobrante (positivo) o faltante
   (negativo), siempre acompañado de texto, nunca solo color.
6. **Given** el cierre se completó, **When** el usuario pide el informe, **Then** se abre una vista
   previa dentro de la app (sin salir de la pantalla) elegible en formato rollo 80 mm o A4.
7. **Given** un cierre ya realizado, **When** cualquiera intenta modificar sus cifras, el conteo o
   sus movimientos, **Then** el sistema lo impide: un cierre es inmutable.
8. **Given** el descuadre supera un umbral relevante, **When** el usuario confirma el conteo,
   **Then** el sistema le pide una observación obligatoria explicando la diferencia antes de cerrar.

---

### User Story 3 - Registrar entradas y salidas de efectivo durante el turno (Priority: P2)

Durante el turno a veces saco dinero del cajón (pago en efectivo al repartidor, retiro billetes
grandes para guardarlos en la caja fuerte) o meto dinero (cambio que traigo del banco). Lo registro
desde la pantalla de caja con un importe, un tipo (entrada o salida) y un motivo, para que el arqueo
final cuadre.

**Why this priority**: sin esto, cualquier movimiento de efectivo ajeno a las ventas aparece como
descuadre en el cierre y el informe pierde credibilidad. Pero el cierre ya aporta valor sin él.

**Independent Test**: con una caja abierta (fondo 100,00 €) sin ventas, registrar una salida de
30,00 € ("Pago proveedor pan") y una entrada de 50,00 € ("Cambio del banco"); cerrar contando
120,00 € y verificar que cuadra (esperado 120,00 €).

**Acceptance Scenarios**:

1. **Given** una caja abierta, **When** el usuario registra una salida con importe y motivo, **Then**
   el movimiento queda en la sesión con importe, tipo, motivo, usuario y hora.
2. **Given** el usuario intenta registrar un movimiento sin motivo o con importe 0 o negativo,
   **When** confirma, **Then** el sistema lo rechaza con un mensaje claro.
3. **Given** no hay caja abierta, **When** el usuario intenta registrar un movimiento, **Then** el
   sistema no lo permite.
4. **Given** un movimiento registrado por error, **When** el usuario lo quiere corregir, **Then** lo
   hace registrando un movimiento inverso (no se borra ni se edita el original), y ambos constan en
   el informe.

---

### User Story 4 - Seguir la caja en vivo durante el turno (Priority: P2)

Con la caja abierta, la pantalla de caja me muestra en todo momento cómo va la jornada: cuánto se
lleva vendido, cuántos tickets, cuánto por cada método de pago, el fondo inicial y los movimientos
del turno — como el "informe X" de una caja registradora (consulta sin cerrar).

**Why this priority**: es la consulta que el dueño hace varias veces al día ("¿cómo vamos?"). Aporta
valor y reutiliza los mismos cálculos que el cierre, pero no es imprescindible para cerrar.

**Independent Test**: con una caja abierta, emitir un ticket en el POS y volver a la pantalla de
caja: el total y el conteo de tickets reflejan la nueva venta.

**Acceptance Scenarios**:

1. **Given** una caja abierta, **When** el usuario entra en la pantalla de caja, **Then** ve el total
   vendido, el número de tickets, el desglose por método de pago, el fondo y la lista de movimientos
   del turno.
2. **Given** la vista en vivo, **When** se muestra, **Then** incluye lo vendido por cada método
   (también efectivo), pero **no** muestra el "efectivo esperado en cajón" (fondo + efectivo +
   movimientos) como cifra única: esa cifra se reserva para después del conteo del cierre, para no
   vaciar de sentido el arqueo ciego.

---

### User Story 5 - Consultar el histórico de cierres (Priority: P3)

Como dueño, consulto el listado de todos los cierres de caja: fecha, quién abrió y cerró, total
facturado, efectivo esperado y contado, y descuadre. Desde cada cierre puedo ver o reimprimir su
informe Z.

**Why this priority**: el dato vale también hacia atrás (comparar días, detectar descuadres
recurrentes), pero el valor inmediato está en cerrar el día.

**Independent Test**: tras realizar dos cierres, el listado muestra ambos con sus cifras, y la
acción "Ver informe" abre la vista previa del informe correspondiente.

**Acceptance Scenarios**:

1. **Given** existen cierres anteriores, **When** el usuario entra al histórico, **Then** ve un
   listado buscable, paginado y ordenable con una fila por sesión de caja.
2. **Given** un cierre en el listado, **When** el usuario elige "Ver informe", **Then** se abre la
   vista previa del informe Z en un modal dentro de la app, en 80 mm o A4.
3. **Given** un cierre con descuadre, **When** aparece en el listado, **Then** su estado
   (cuadra / sobrante / faltante) se distingue con un indicador con texto.

---

### Edge Cases

- **Intento de cobrar sin caja abierta** (FR-020): el cobro se rechaza en el servidor aunque la
  pantalla de venta se haya quedado abierta desde antes del cierre (otra tablet cerró la caja); el
  ticket armado no se pierde y la pantalla ofrece abrir caja para reintentar.
- **Caja que queda abierta de un día para otro** (olvido de cierre): la sesión sigue abierta hasta
  que alguien la cierre; el informe abarca desde la apertura hasta el cierre, aunque cruce la
  medianoche, y la pantalla de caja avisa visiblemente de que la caja lleva abierta desde un día
  anterior.
- **Dos dispositivos cerrando la misma caja a la vez**: solo el primer cierre prospera; el segundo
  recibe un aviso de que la caja ya fue cerrada y ve el informe del cierre hecho.
- **Ticket emitido mientras se está contando el cierre**: el cierre incluye todo lo emitido hasta el
  instante en que se confirma; el informe y el esperado se calculan en ese instante, no al abrir la
  pantalla de conteo.
- **Ticket anulado** dentro de la sesión: no suma al total facturado ni al efectivo esperado, y
  figura en una sección propia del informe ("Anulados") con su número e importe.
- **Cuenta de mesa (hostelería) cobrada en varias partes**: cada cobro es un ticket independiente y
  se atribuye a la sesión abierta en el momento de cobrarse; una cuenta abierta sin cobrar no cuenta
  como venta.
- **Sesión sin ninguna venta**: se puede cerrar igual; el informe muestra 0 tickets y el arqueo solo
  contra fondo y movimientos.
- **Salida mayor que el efectivo disponible**: se permite registrarla (el sistema no conoce el
  efectivo real del cajón), pero el esperado puede quedar negativo y se mostrará como tal.
- **Ticket cualificado (con NIF del receptor)**: cuenta igual que cualquier otro ticket.
- **Tenant con régimen IGIC o IPSI**: el desglose por tipo impositivo usa el impuesto del régimen del
  tenant, nunca asume IVA.
- **Usuario sin permiso de caja**: no ve la entrada de menú y cualquier acceso directo es rechazado.

## Requirements *(mandatory)*

### Functional Requirements

**Sesión de caja (apertura)**

- **FR-001**: El sistema MUST permitir abrir una sesión de caja indicando un fondo inicial en euros
  (≥ 0,00 €, con céntimos), registrando quién la abre y cuándo.
- **FR-002**: El sistema MUST garantizar como máximo **una** sesión de caja abierta a la vez por
  negocio (una caja por tenant, compartida por todos los dispositivos), también ante dos aperturas
  simultáneas desde dispositivos distintos.
- **FR-003**: El fondo inicial MUST poder introducirse tanto como importe total como contando por
  denominaciones, con la misma pantalla de conteo que el cierre.

**Atribución de ventas**

- **FR-004**: Todo ticket emitido mientras hay una sesión abierta MUST quedar atribuido a esa sesión
  de forma permanente, con independencia de cuándo se cierre la sesión o se consulte el informe.
- **FR-005**: Los cobros de cuentas de mesa (módulo de hostelería) MUST atribuirse igual que
  cualquier ticket, porque cada cobro emite su propio ticket.
- **FR-006**: El cierre de caja MUST NOT modificar ningún ticket, su numeración, sus importes ni su
  registro fiscal; solo los lee.

**Movimientos manuales**

- **FR-007**: Con una sesión abierta, el sistema MUST permitir registrar entradas y salidas de
  efectivo con importe (> 0), tipo (entrada/salida), motivo obligatorio, usuario y fecha/hora.
- **FR-008**: Los movimientos MUST ser de solo alta: no se editan ni se borran; las correcciones se
  hacen con un movimiento inverso.

**Cierre y arqueo**

- **FR-009**: El sistema MUST permitir cerrar la sesión abierta registrando el efectivo contado,
  bien por denominaciones (cantidad de cada billete/moneda de euro: 500, 200, 100, 50, 20, 10, 5 €;
  2, 1 €; 0,50, 0,20, 0,10, 0,05, 0,02, 0,01 €), bien como importe total.
- **FR-010**: Durante el conteo del cierre, el sistema MUST NOT mostrar el efectivo esperado ni el
  descuadre (arqueo ciego); se revelan solo tras confirmar el conteo.
- **FR-011**: El sistema MUST calcular, en el momento de confirmar el cierre y en el servidor, el
  efectivo esperado = fondo inicial + suma de cobros en efectivo de los tickets no anulados de la
  sesión + entradas − salidas, y el descuadre = contado − esperado. El cliente nunca es la fuente de
  ninguna de estas cifras.
- **FR-012**: Si el valor absoluto del descuadre supera un umbral (por defecto 5,00 €, configurable
  por el negocio), el sistema MUST exigir una observación antes de completar el cierre.
- **FR-013**: Un cierre completado MUST ser inmutable: ni sus cifras, ni el conteo, ni los
  movimientos de la sesión, ni la atribución de tickets pueden cambiar después.
- **FR-014**: Si dos dispositivos intentan cerrar la misma sesión, el sistema MUST aceptar solo el
  primero e informar al segundo.

**Informe de cierre (informe Z)**

- **FR-015**: El informe de una sesión cerrada MUST incluir: identificación de la sesión, apertura
  (usuario y fecha/hora) y cierre (usuario y fecha/hora); número de tickets y total facturado
  (impuestos incluidos); desglose por método de pago (efectivo, tarjeta, transferencia,
  domiciliación); desglose por tipo impositivo con base imponible y cuota según el régimen del
  tenant (incluido el recargo de equivalencia si lo hubiera); primer y último número de ticket de la
  sesión; tickets anulados (número e importe); movimientos manuales; fondo inicial; efectivo
  esperado, contado y descuadre; conteo por denominaciones si se hizo; observación si la hay.
- **FR-016**: El informe MUST poder visualizarse en una vista previa dentro de la aplicación (sin
  abrir otra pestaña) y generarse en formato rollo de 80 mm y en A4.
- **FR-017**: El informe de un cierre MUST mostrar siempre las mismas cifras, se consulte cuando se
  consulte (las cifras del cierre se congelan al cerrar).

**Consulta en vivo e histórico**

- **FR-018**: Con una sesión abierta, la pantalla de caja MUST mostrar el resumen en vivo de la
  sesión (informe X): total vendido, nº de tickets, desglose por método de pago, fondo inicial y
  movimientos.
- **FR-019**: El sistema MUST ofrecer un listado histórico de sesiones de caja (buscable, paginado y
  ordenable) con fecha, usuarios de apertura y cierre, total facturado, esperado, contado, descuadre
  y estado, con la acción de ver/reimprimir el informe.

**Reglas de operación**

- **FR-020**: Emitir un ticket o cobrar una cuenta cuando no hay caja abierta MUST bloquearse,
  como en un POS real: el servidor rechaza la emisión y la pantalla de venta ofrece abrir la caja
  ahí mismo (sin salir del TPV) para continuar. Armar el ticket, guardar cuentas de mesa y consultar
  siguen permitidos sin caja; lo que exige caja es **cobrar**.
- **FR-021**: El informe Z MUST cubrir lo vendido en el POS (tickets emitidos en la sesión). Las
  facturas ordinarias y sus cobros no pasan por la caja y ya tienen sus propios módulos (Facturas,
  Cobros); no forman parte del arqueo.
- **FR-022**: El acceso a la caja (pantalla, movimientos, cerrar, histórico) MUST estar protegido por
  un permiso propio del catálogo de permisos, con su entrada de menú en el grupo POS. **Abrir** la
  caja lo puede hacer además quien tiene permiso para crear tickets, para que un cajero pueda abrirla
  desde el TPV (FR-020) sin acceso al resto de la caja.
- **FR-023**: Toda sesión, movimiento y cierre MUST pertenecer al negocio (tenant) activo y nunca ser
  visible ni operable desde otro.

**Experiencia táctil (tablet-first)**

- **FR-024**: Todas las pantallas de caja (apertura, movimientos, conteo, resultado) MUST ser
  operables con el dedo en una tablet en horizontal: objetivos táctiles amplios, importes grandes y
  legibles, y entrada numérica con teclado propio de la app (nunca el teclado del sistema).
- **FR-025**: La pantalla de caja MUST comunicar en todo momento, de un vistazo, si la caja está
  abierta o cerrada, desde cuándo y quién la abrió, y avisar si lleva abierta desde un día anterior.
- **FR-026**: Las acciones de caja MUST dar feedback de carga mientras se procesan y notificar el
  resultado con el sistema de notificaciones de la app.

### Key Entities

- **Sesión de caja**: periodo entre una apertura y un cierre. Atributos: fondo inicial, estado
  (abierta/cerrada), usuario y momento de apertura, usuario y momento de cierre, y —al cerrar— las
  cifras congeladas del informe (totales, desgloses, esperado, contado, descuadre, observación).
- **Movimiento de caja**: entrada o salida manual de efectivo dentro de una sesión. Atributos:
  tipo, importe, motivo, usuario, momento. Solo alta.
- **Conteo de efectivo**: detalle por denominación (valor y cantidad) del efectivo contado al cerrar
  (y opcionalmente al abrir).
- **Ticket** (existente): factura simplificada del POS con su desglose de cobro por métodos; gana
  la referencia a la sesión de caja en la que se emitió.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario abre caja en menos de 20 segundos desde que entra en la pantalla de caja.
- **SC-002**: Un usuario completa el cierre (contando por denominaciones un cajón con hasta 8
  denominaciones distintas) e imprime el informe en menos de 2 minutos.
- **SC-003**: El total facturado y el desglose por método de pago del informe Z coinciden al céntimo
  con la suma de los tickets de la sesión en el 100 % de los casos.
- **SC-004**: El efectivo esperado coincide al céntimo con fondo + efectivo de tickets + entradas −
  salidas en el 100 % de los casos, incluidos tickets con pago repartido entre métodos.
- **SC-005**: Ningún cierre completado cambia sus cifras al volver a consultarlo, aunque después se
  emitan, anulen o modifiquen otros documentos.
- **SC-006**: Todas las acciones de caja son realizables en una tablet de 10" en horizontal sin que
  aparezca el teclado del sistema y sin scroll para llegar a la acción principal.
- **SC-007**: Cero fugas de datos de caja entre negocios distintos.

## Assumptions

- **Arqueo ciego**: quien cuenta no ve el esperado hasta confirmar. Es la práctica estándar de
  control de efectivo en TPV.
- **Una caja por negocio**: varias cajas/terminales con sesiones independientes quedan fuera de
  alcance (Principio V); todos los dispositivos venden contra la misma sesión.
- **Tenants que ya usan el POS**: desde el despliegue, cobrar exige caja
  abierta (FR-020). Es un cambio de comportamiento visible para quien ya usa el POS; la guía in-app y
  la apertura inline desde el TPV lo hacen de un toque.
- **Moneda**: euros, con las denominaciones vigentes del euro (incluidas monedas de 1 y 2 céntimos,
  que siguen siendo de curso legal).
- **Umbral de descuadre**: 5,00 € por defecto, ajustable desde la configuración del POS.
- **Solo el efectivo se arquea**: tarjeta, transferencia y domiciliación se informan como total
  cobrado por ese método, sin conciliación contra el datáfono ni el banco (fuera de alcance).
- **Atribución por sesión, no por usuario**: el informe es de la caja, no de cada camarero/cajero.
  Un cierre "por empleado" queda fuera de alcance.
- **Tickets anteriores a la feature**: los tickets emitidos antes de que exista la caja no se
  atribuyen a ninguna sesión y no se reconstruyen sesiones históricas.
- **Reabrir un cierre** queda fuera de alcance (contradice la inmutabilidad); un error de conteo se
  documenta en la observación.
- **Fuera de alcance**: cajón portamonedas físico y su apertura automática, impresión directa sin
  diálogo (se imprime desde la vista previa del PDF), datáfono integrado, y envío del informe por
  email.
- La zona horaria de las fechas/horas mostradas es la configurada para el negocio.
