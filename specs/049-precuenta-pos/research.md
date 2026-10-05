# Research: Precuenta en el POS de hostelería

Decisiones técnicas tomadas antes del diseño. Cada una resuelve un punto que la spec deja abierto
o un riesgo detectado al leer el código existente.

## D1 — La precuenta no es un registro de facturación

- **Decision**: la precuenta no pasa por `RegistroTicket`, no crea `facturas`, no toca series ni
  `ServicioVerifactu`. Es un registro interno (`pos_precuentas`).
- **Rationale**: RD 1007/2023 y la Orden HAC/1177/2024 regulan los registros de las **facturas
  expedidas** (`docs/02-facturacion-espana.md` §1). Una precuenta es un documento informativo
  equivalente a una proforma, que no genera registro Verifactu
  ([Holded — tipos de facturas y Verifactu](https://www.holded.com/es/blog/tipos-facturas-verifactu)).
  El riesgo que sí hay que cubrir es la **confusión con una factura** y el uso de la precuenta para
  cobrar sin facturar: se mitiga con el diseño del documento (D6) y con el registro append-only de
  emisiones (D3).
- **Alternatives considered**: emitir la precuenta como factura en borrador (descartado: un borrador
  es una factura, y la cuenta abierta se creó en la 038 precisamente para no usar borradores como
  cuentas); no registrar nada (descartado: pierde la trazabilidad "cuenta con precuenta que acabó
  anulada").
- **Follow-up**: añadir una subsección breve "Precuenta (documento no fiscal)" en
  `docs/02-facturacion-espana.md` con esta fuente, antes del código (Principio II: los cambios
  normativos van primero a la doc). **Antes de producción**, reconfirmar contra las FAQ oficiales de
  la AEAT sobre Verifactu (la fuente citada es secundaria).

## D2 — Un único cálculo del importe para precuenta y cobro

- **Decision**: extraer de `CobradorCuenta::cobrar()` la construcción de las líneas a cobrar
  (unidades pendientes × precio efectivo con suplemento de zona, concepto con opciones) a un método
  público reutilizable, y calcular el total de la precuenta con **la misma** `CalculadoraFactura`,
  el mismo régimen del tenant y el mismo recargo de equivalencia que aplicaría `RegistroTicket`
  (según el cliente receptor de la cuenta).
- **Rationale**: SC-002 exige que el total de la precuenta coincida **al céntimo** con el ticket. Si
  la precuenta usara `PosCuentaLinea::brutoDe()` (redondeo de cuota por línea) y el ticket
  `CalculadoraFactura` (mismo redondeo, pero con posible recargo), cualquier divergencia futura entre
  ambos caminos rompería la promesa sin que nadie lo note. Un solo camino lo hace imposible.
- **Alternatives considered**: sumar `brutoPendiente()` con suplemento (descartado: ignora el
  recargo de equivalencia y duplica la regla de redondeo); simular un `RegistroTicket` en transacción
  y hacer rollback (descartado: toca numeración bajo lock y Verifactu, aunque se deshaga).

## D3 — Registro append-only con foto de las líneas

- **Decision**: tabla `pos_precuentas`, una fila por emisión, con una columna JSON `lineas` que
  guarda la foto impresa (concepto, opciones, cantidad, precio, importe) y los totales. El PDF se
  regenera siempre desde esa fila, nunca desde la cuenta viva. El modelo bloquea `update` y `delete`
  (lanza excepción), igual que los ledgers append-only del proyecto.
- **Rationale**: FR-013 (append-only) y "volver a abrir una precuenta muestra lo entregado". Si el
  PDF se generara desde la cuenta, una precuenta antigua mostraría consumos que el cliente nunca vio.
- **Alternatives considered**: guardar el PDF en `storage/` (descartado: archivos sin ciclo de vida,
  peso en hosting compartido, Principio V); tabla hija `pos_precuenta_lineas` (descartado: nunca se
  consulta por línea; el JSON basta y es inmutable).

## D4 — "Vigente" se decide con una huella del consumo, no con la versión

- **Decision**: cada precuenta guarda `huella_consumo` = SHA-256 de una representación canónica del
  consumo de la cuenta: por cada línea (ordenadas por contenido, no por id), `articulo_id`,
  `concepto`, `cantidad`, `precio_unitario`, `suplemento_opciones`, `tipo_impositivo` y los
  `opcion_id` ordenados; más el porcentaje de suplemento de zona efectivo. **No** incluye
  `cantidad_saldada`, notas, comensales ni receptor. La precuenta más reciente de una cuenta abierta
  es **vigente** si su huella coincide con la huella actual; si no, **desactualizada**.
- **Rationale**: `pos_cuentas.version` sube con cualquier guardado (también al cambiar notas o
  comensales, y el TPV guarda antes de cobrar), así que compararla daría falsos "desactualizada"
  (contra FR-017). La huella refleja exactamente lo que cambia el importe: añadir/quitar/modificar
  líneas, unir cuentas, o transferir a una zona con otro suplemento (FR-016), y deja fuera el cobro
  parcial, que solo mueve `cantidad_saldada` (FR-017). `sincronizarLineas` puede recrear líneas con
  ids nuevos: por eso se ordena por contenido y no por id.
- **Alternatives considered**: flag `precuenta_vigente` en `pos_cuentas` puesto a `false` en cada
  punto que toca líneas (descartado: hay que acordarse en `update`, `unir`, `transferir` y cualquier
  camino futuro; el día que alguien añada uno nuevo, el estado miente). La huella se deriva, no se
  mantiene.
- **Reimpresión (FR-025)**: una precuenta nueva es "reimpresión" si la última precuenta de la cuenta
  tiene la misma huella **y** el mismo pendiente por línea (es decir, nada cambió, ni siquiera un
  cobro parcial intermedio). Se resuelve comparando también la huella del pendiente (misma
  representación + `cantidad_saldada`), guardada como `huella_pendiente`.

## D5 — Estado de mesa "precuenta" decidido en servidor

- **Decision**: `SalaController::estado()` añade a cada mesa ocupada `precuenta_pedida` (bool) y
  `precuenta_hace_min` (int|null). Cuando `precuenta_pedida` es `true`, `olvidada` se fuerza a
  `false` (FR-018). En cliente, `PosPlanoDibujo.claseEstado()` —el único sitio que decide la clase
  visual, § "Dos vistas de los mismos datos"— devuelve `'precuenta'` antes de mirar `olvidada`. La
  tira de métricas gana la categoría "Precuenta".
- **Rationale**: § "Tarjeta de mesa y sus tres estados": el servidor decide, la vista no hace
  aritmética de fechas. Mantener `estado: 'ocupada'` y añadir un booleano no rompe a ningún consumidor
  actual del payload.
- **Consulta**: una sola consulta extra para las precuentas de todas las cuentas abiertas
  (`whereIn(cuenta_id)`, ordenadas por id desc, agrupadas en PHP), y se carga `lineas.opciones` y
  `mesa.zona` para la huella. Sin N+1.
- **Color**: violeta (`--pos-precuenta: #7c3aed`, fondo `#f6f2ff`), distinto del gris/verde/ámbar ya
  reservados y del rojo del rechazo (§ "Feedback de bloqueo…"); no usa el primario del tenant porque
  puede coincidir con el verde de "ocupada".

## D6 — Documento: plantilla propia de 80 mm, sin nada fiscal

- **Decision**: `resources/views/pos/precuenta-80mm.blade.php`, renderizada con dompdf con el mismo
  papel que el ticket (`226.77pt` de ancho, alto según líneas). Cabecera con logo y nombre comercial
  (sin NIF ni dirección fiscal), título "PRECUENTA", leyenda "Documento no válido como factura"
  arriba y abajo, mesa/zona, fecha y hora, comensales, quién la emitió, "Reimpresión" si aplica,
  líneas, suplemento de zona como línea visible, total y "<impuesto> incluido" con el nombre del
  impuesto del régimen congelado en la precuenta (IVA/IGIC/IPSI, nunca IVA fijo). **No** incluye el
  partial `verifactu-qr`, ni número, ni serie.
- **Rationale**: FR-007/FR-008, SC-004. Una plantilla aparte (en vez de un `@if` dentro de
  `ticket-80mm`) garantiza que el QR y la numeración no puedan "colarse" en la precuenta por un
  cambio futuro del ticket.
- **Alternatives considered**: reutilizar `facturas/ticket-80mm` con una bandera (descartado por lo
  anterior).

## D7 — Front: botón en el contexto de cuenta y modal propio de vista previa

- **Decision**: botón "Precuenta" dentro del chip de mesa de la cabecera del ticket
  (`#pos-mesa-chip`), junto a ⇄ y ×, visible solo con cuenta y pendiente > 0 (§ "Franja central de la
  botonera…": la botonera no cambia). Un indicador en el chip muestra "Precuenta" (vigente) o
  "Precuenta desactualizada" (aviso), con el estado que manda el servidor. Modal propio
  `#posPrecuentaModal` (centrado, iframe, botón "Imprimir" que imprime el iframe, botón "Listo"); al
  cerrarse llama a `vaciarPantalla()` del módulo cuenta (FR-011). El módulo nuevo
  `public/js/pos-precuenta.js` se registra con `PosApp.registrar('precuenta', …)` (§ "Partición de un
  archivo JS grande…").
- **Tamaño del modal**: `modal-lg` como el modal "Ver ticket" ya existente del TPV, no `modal-xl`:
  el documento es un rollo de 80 mm y en `modal-xl` queda una tira estrecha en un lienzo enorme. Es la
  misma desviación que ya hace el ticket; se anota en la guía de front.
- **Guardar antes de generar (FR-003)**: el módulo llama a `PosApp.modulos.cuenta.guardar()` y solo
  si responde ok pide la precuenta con la `version` resultante, igual que `cobrarCuenta()`.
- **Aviso de desactualizada (FR-022)**: el módulo cuenta compara `precuenta.estado` antes y después
  de cada guardado; si pasa de `vigente` a `desactualizada`, `showToast('warning', …)`.
- **Cobro (FR-024)**: el modal de cobro muestra una franja de aviso con "Precuenta entregada: X € ·
  Total actual: Y €" cuando `precuenta.estado === 'desactualizada'`.

## D8 — Sin permiso nuevo ni interruptor

- **Decision**: rutas dentro del grupo existente `can:ver-pos-sala` + `modulo.hosteleria`.
- **Rationale**: no es una entrada de menú (§ "Nueva entrada de menú ⇒ nuevo permiso" no aplica);
  quien opera cuentas opera precuentas. Sin interruptor en `ConfigPos` (Principio V).

## D9 — Precuenta parcial fuera de alcance

- **Decision**: no se implementa. La spec lo documenta en Assumptions.
- **Rationale**: el modal de cobro del TPV no envía `lineas` a `/pos/cuentas/{id}/cobrar`
  (`public/js/pos-cobro.js`, `cobrarCuenta()` solo manda `version` y `pagos`), aunque la tarea T094
  de la 038 figura como hecha y la guía `ayuda/pos-crear.blade.php` describe el cobro por partes. No
  hay selector de líneas que reutilizar. Es un hueco preexistente que se reporta aparte; no se
  arregla dentro de esta feature.

## D10 — Datos personales

- **Decision**: `pos_precuentas.usuario_id` (FK nullable a `users`, `nullOnDelete`) es la única
  referencia personal, del mismo tipo que `pos_cuentas.abierta_por`. Sin IP ni user-agent. Mismo
  ciclo de vida que las cuentas y los cobros: registro operativo del negocio, sin purga propia
  (constitución, Additional Constraints: la purga aplica a tablas de actividad/accesos). Se anota en
  `docs/03-modelo-datos.md`.
