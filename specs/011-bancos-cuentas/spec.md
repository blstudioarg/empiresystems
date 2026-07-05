# Feature Specification: Bancos y cuentas bancarias del tenant

**Feature Branch**: `011-bancos-cuentas`

**Created**: 2026-07-03

**Status**: Draft

**Input**: User description: "Gestión de bancos y cuentas bancarias del tenant: catálogo global de `bancos` (solo lectura, sembrado por el sistema) y CRUD de `cuentas_bancarias` propias del tenant (alias, banco, IBAN, titular, activa/inactiva con baja lógica) desde la pantalla de configuración. Estas cuentas se usan luego como selector en facturas con forma_pago=transferencia (esa integración con facturas puede ser parte de esta feature o quedar como siguiente paso, a decidir en la spec)."

## Clarifications

### Session 2026-07-03

- Q: ¿La integración con facturas (US3: selector de cuenta en factura por transferencia + snapshot en el PDF) entra en esta feature o queda para después? → A: Incluir US3 en esta feature (catálogo + CRUD de cuentas + selector en factura y snapshot en PDF).
- Q: ¿Cómo se define la lista inicial del catálogo global de bancos? → A: Lista curada de las principales entidades bancarias operando en España (Santander, BBVA, CaixaBank, Sabadell, Bankinter, ING, etc.), sembrada por seeder.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dar de alta cuentas bancarias propias (Priority: P1)

Un usuario del tenant, desde la pantalla de configuración, da de alta una cuenta bancaria propia
(elige el banco de un catálogo, introduce IBAN, titular y un alias identificativo) para poder
mostrarla más adelante en las facturas cobradas por transferencia.

**Why this priority**: Sin poder crear cuentas no hay nada que gestionar ni que seleccionar en
facturas; es la base de toda la feature.

**Independent Test**: Se puede probar completamente creando una cuenta desde configuración y
verificando que aparece listada con sus datos correctos, sin necesidad de que exista todavía la
integración con facturas.

**Acceptance Scenarios**:

1. **Given** un usuario en la pantalla de configuración de cuentas bancarias, **When** completa
   alias, selecciona un banco del catálogo, introduce un IBAN válido y un titular, y guarda,
   **Then** la cuenta queda creada, activa por defecto, y visible en el listado del tenant.
2. **Given** un formulario de alta de cuenta bancaria, **When** el usuario introduce un IBAN con
   formato inválido, **Then** el sistema rechaza el guardado y muestra un error indicando que el
   IBAN no es válido.
3. **Given** dos tenants distintos, **When** cada uno da de alta sus propias cuentas bancarias,
   **Then** ningún tenant puede ver ni listar las cuentas del otro.

---

### User Story 2 - Editar y desactivar cuentas bancarias (Priority: P2)

Un usuario del tenant edita los datos de una cuenta existente (alias, banco, IBAN, titular) o la
desactiva cuando deja de usarla, sin perder el histórico de facturas que ya la referencian.

**Why this priority**: Las cuentas bancarias cambian con el tiempo (se cierran, se renombran); es
necesario mantenerlas sin romper datos ya emitidos, pero es secundario frente a poder crearlas.

**Independent Test**: Se puede probar editando una cuenta existente y comprobando que los cambios
se reflejan, y desactivando otra y comprobando que deja de aparecer como seleccionable pero sigue
existiendo en el listado (marcada como inactiva).

**Acceptance Scenarios**:

1. **Given** una cuenta bancaria existente y activa, **When** el usuario edita su alias o titular
   y guarda, **Then** el listado refleja los nuevos datos.
2. **Given** una cuenta bancaria activa, **When** el usuario la desactiva (baja lógica), **Then**
   la cuenta pasa a estado inactivo, deja de ofrecerse como opción para nuevas selecciones, pero
   permanece visible en el listado de configuración con indicación de que está inactiva.
3. **Given** una cuenta bancaria inactiva, **When** el usuario la reactiva, **Then** vuelve a
   quedar disponible como opción seleccionable.

---

### User Story 3 - Elegir cuenta bancaria propia al emitir una factura por transferencia (Priority: P3)

Un usuario que está creando o editando una factura con forma de pago "transferencia" selecciona
una de sus cuentas bancarias activas para que sus datos (banco, IBAN, titular) queden reflejados
en la factura y en su PDF, quedando congelados aunque la cuenta se edite o desactive después.

**Why this priority**: Es el objetivo final de negocio (mostrar cuenta de cobro en la factura),
pero depende de que existan las dos historias anteriores. Está **incluida en el alcance de esta
feature** (ver Clarifications), como incremento posterior a US1/US2 dentro de la misma entrega.

**Independent Test**: Se puede probar creando una factura con forma de pago transferencia,
seleccionando una cuenta bancaria activa, y verificando que sus datos aparecen en la factura y en
el PDF generado, incluso después de editar o desactivar la cuenta original.

**Acceptance Scenarios**:

1. **Given** una factura en borrador con forma de pago "transferencia", **When** el usuario
   selecciona una de sus cuentas bancarias activas, **Then** los datos de esa cuenta (banco, IBAN,
   titular) quedan copiados en la factura y visibles en su PDF.
2. **Given** una factura ya creada con una cuenta bancaria seleccionada, **When** esa cuenta se
   edita o se desactiva posteriormente en configuración, **Then** los datos mostrados en la
   factura no cambian (quedan congelados con el valor original).
3. **Given** una factura con forma de pago distinta de "transferencia", **When** el usuario
   completa el formulario, **Then** no se le ofrece ni se le exige seleccionar cuenta bancaria.

### Edge Cases

- ¿Qué ocurre si un tenant intenta crear una factura por transferencia sin tener ninguna cuenta
  bancaria activa dada de alta? El sistema debe permitir continuar sin cuenta seleccionada
  (campo opcional) y avisar de que no hay cuentas activas disponibles.
- ¿Qué ocurre si se intenta desactivar la única cuenta activa del tenant? Se permite; no es
  obligatorio tener al menos una cuenta activa en todo momento.
- ¿Qué ocurre si se introduce un IBAN de un país no soportado por el formato ISO 13616 estándar?
  Se valida solo el formato genérico (checksum y estructura), no la pertenencia a un país concreto
  ni la existencia real de la cuenta.
- ¿Qué ocurre si dos cuentas del mismo tenant tienen el mismo IBAN? No hay restricción de
  unicidad de IBAN entre cuentas de un mismo tenant (puede darse de alta duplicada por error del
  usuario y luego desactivarse una).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema MUST ofrecer un catálogo de bancos, sembrado por el sistema, disponible
  para todos los tenants como lista de solo lectura (sin CRUD de bancos por parte del tenant).
- **FR-002**: El sistema MUST permitir a un usuario del tenant crear una cuenta bancaria propia
  indicando alias, banco (del catálogo), IBAN y titular.
- **FR-003**: El sistema MUST validar el IBAN introducido según el formato estándar ISO 13616
  (estructura y dígito de control) antes de permitir guardar la cuenta.
- **FR-004**: El sistema MUST listar únicamente las cuentas bancarias pertenecientes al tenant
  activo, sin exponer cuentas de otros tenants.
- **FR-005**: El sistema MUST permitir editar alias, banco, IBAN y titular de una cuenta bancaria
  existente.
- **FR-006**: El sistema MUST permitir activar/desactivar una cuenta bancaria (baja lógica),
  preservando el registro y su histórico en lugar de borrarlo físicamente.
- **FR-007**: El sistema MUST marcar toda cuenta bancaria nueva como activa por defecto.
- **FR-008**: El sistema MUST ofrecer únicamente cuentas bancarias activas como opciones
  seleccionables en el punto de selección de cuenta (p. ej. al emitir una factura por
  transferencia).
- **FR-009**: El sistema MUST permitir que una factura con forma de pago "transferencia" tenga
  asociada una cuenta bancaria del tenant, sin ser obligatorio.
- **FR-010**: Al seleccionar una cuenta bancaria en una factura, el sistema MUST copiar (snapshot)
  sus datos de banco, IBAN y titular en la propia factura, de forma que cambios posteriores en la
  cuenta original no alteren facturas ya creadas.
- **FR-011**: El sistema MUST notificar al usuario mediante mensajes claros (éxito/error) al crear,
  editar, activar o desactivar una cuenta bancaria.
- **FR-012**: El sistema MUST impedir que un usuario opere (ver, editar, seleccionar) cuentas
  bancarias de un tenant distinto al propio.

### Key Entities

- **Banco**: entidad bancaria del catálogo global compartido por todos los tenants (p. ej. BBVA,
  CaixaBank, Banco Santander). De solo lectura para los tenants; su nombre se usa para identificar
  la entidad en el selector de cuentas.
- **Cuenta bancaria**: cuenta propia de un tenant, usada para cobrar facturas por transferencia.
  Incluye alias identificativo, referencia al banco, IBAN, titular y estado (activa/inactiva).
  Pertenece a un único tenant y puede tener baja lógica sin eliminarse físicamente.
- **Factura (relación)**: al emitirse/crearse con forma de pago transferencia, puede referenciar
  opcionalmente una cuenta bancaria del tenant; conserva una copia congelada de sus datos
  independientemente de cambios futuros en la cuenta original.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede dar de alta una cuenta bancaria completa (alias, banco, IBAN,
  titular) en menos de 1 minuto.
- **SC-002**: El 100% de los IBAN con formato inválido son rechazados antes de guardarse, sin
  excepciones.
- **SC-003**: El 100% de las cuentas bancarias mostradas en el selector de una factura pertenecen
  al tenant activo y están activas.
- **SC-004**: Tras desactivar o editar una cuenta bancaria, el 100% de las facturas ya emitidas
  que la referenciaban conservan sin cambios los datos bancarios mostrados en su PDF.
- **SC-005**: Ningún usuario puede acceder, mediante la interfaz o manipulando peticiones directas,
  a cuentas bancarias de un tenant distinto al propio.

## Assumptions

- El catálogo de `bancos` se siembra mediante un seeder del sistema con una lista curada de las
  principales entidades bancarias operando en España (Santander, BBVA, CaixaBank, Sabadell,
  Bankinter, ING, etc.); el tenant no puede añadir bancos nuevos al catálogo en esta feature.
- No se valida el IBAN contra un servicio externo ni se comprueba que la cuenta exista realmente
  en la entidad bancaria; solo se valida el formato (ISO 13616).
- Queda fuera de alcance: IBAN/mandato SEPA del cliente para domiciliación bancaria, cuenta
  bancaria por defecto/predeterminada, múltiples divisas por cuenta, y conciliación o integración
  bancaria real (tal como ya documentado en `docs/03-modelo-datos.md`).
- La integración con facturas (User Story 3) reutiliza los campos ya previstos en el modelo de
  `facturas` (`cuenta_bancaria_id` + snapshot `cuenta_bancaria_banco/iban/titular`), documentados
  como "planeados" en `docs/03-modelo-datos.md`.
- Solo usuarios autenticados del tenant (roles `admin`/`usuario` habituales del panel) pueden
  gestionar cuentas bancarias; no se introduce un rol o permiso nuevo específico para esta feature.
