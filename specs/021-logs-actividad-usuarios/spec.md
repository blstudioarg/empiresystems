# Feature Specification: Logs de actividad de usuarios

**Feature Branch**: `021-logs-actividad-usuarios`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Añadir un ítem \"Logs\" (o similar) en el dropdown de usuario del header (resources/views/partials/header.blade.php, junto a Profile/Configuración/Cerrar sesión) que lleve a una vista nueva con una datatable de logs de actividad de usuarios del tenant actual (login/logout, altas, bajas y cambios relevantes sobre entidades del CRM: clientes, artículos, facturas, configuración, usuarios). Debe respetar aislamiento multi-tenant (solo logs del tenant del usuario autenticado) y seguir los patrones existentes del proyecto: layout NexaDash, datatable server-side como en usuarios/facturas, toastr para notificaciones si aplica."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar el historial de actividad del tenant (Priority: P1)

Cualquier usuario autenticado (rol `usuario`, `admin` o `super_admin` operando dentro de su tenant)
abre el dropdown de su avatar en el header y selecciona la nueva opción "Logs". Llega a una vista
con una tabla paginada y buscable que muestra, en orden cronológico descendente, quién hizo qué,
sobre qué elemento del CRM y cuándo.

**Why this priority**: Es el flujo completo mínimo que entrega valor: sin esto no existe la
feature. El resto de historias son refinamientos sobre esta base.

**Independent Test**: Con datos de actividad ya existentes en el tenant, entrar como cualquier
usuario, abrir el dropdown, hacer clic en "Logs" y verificar que se ve una tabla con eventos
reales del tenant (no vacía, no de otro tenant).

**Acceptance Scenarios**:

1. **Given** un usuario autenticado con sesión activa, **When** abre el dropdown de usuario en el
   header, **Then** ve un ítem "Logs" junto a Profile/Configuración/Cerrar sesión.
2. **Given** el usuario hace clic en "Logs", **When** la vista carga, **Then** se muestra una
   datatable server-side (paginación, búsqueda, ordenamiento) con los eventos de actividad del
   tenant, más recientes primero.
3. **Given** un evento de tipo alta/baja/modificación sobre un cliente, artículo, factura, dato de
   configuración o usuario, **When** se produce esa acción, **Then** aparece una fila nueva en el
   log con usuario responsable, acción, entidad afectada y fecha/hora.
4. **Given** un inicio o cierre de sesión de cualquier usuario del tenant, **When** ocurre,
   **Then** queda registrado como evento de log visible en la tabla.

---

### User Story 2 - Aislamiento multi-tenant del log (Priority: P1)

Un usuario de un tenant nunca debe poder ver actividad de otro tenant, sea cual sea su rol.

**Why this priority**: Es un requisito no negociable de la arquitectura (aislamiento
multi-tenant); un fallo aquí es una fuga de datos entre clientes del SaaS.

**Independent Test**: Con al menos dos tenants con actividad registrada, entrar como usuario del
tenant A y confirmar que la tabla de logs solo contiene eventos del tenant A, incluso variando
parámetros de búsqueda/orden/paginación de la datatable.

**Acceptance Scenarios**:

1. **Given** dos tenants con eventos de actividad propios, **When** un usuario del tenant A
   consulta "Logs", **Then** solo ve eventos generados dentro del tenant A.
2. **Given** un usuario intenta manipular parámetros de la petición de la datatable (filtros,
   orden, paginación), **When** la petición se procesa, **Then** el resultado sigue acotado al
   tenant del usuario autenticado.

---

### User Story 3 - Buscar y acotar el historial (Priority: P2)

Un usuario quiere encontrar rápidamente qué pasó con un registro concreto (p. ej. "quién modificó
esta factura" o "quién dio de baja a este cliente") usando la búsqueda de la datatable.

**Why this priority**: Sin capacidad de búsqueda/orden, un historial que crece con el tiempo deja
de ser usable, pero la vista ya aporta valor solo con el listado cronológico de la historia 1.

**Independent Test**: Con varios eventos de distintos tipos de entidad, escribir un término de
búsqueda (nombre de usuario, tipo de acción o nombre de entidad) y verificar que la tabla filtra
correctamente sin recargar la página completa.

**Acceptance Scenarios**:

1. **Given** la tabla de logs con múltiples eventos, **When** el usuario escribe un término en el
   buscador de la datatable, **Then** la tabla muestra solo las filas cuyo contenido visible
   coincide con el término.
2. **Given** la tabla de logs, **When** el usuario ordena por columna (p. ej. fecha o usuario),
   **Then** las filas se reordenan en consecuencia manteniendo el resto de filtros aplicados.

---

### Edge Cases

- El tenant no tiene ningún evento de actividad todavía (tenant recién creado): la datatable se
  muestra vacía con el mensaje estándar de "sin resultados", sin error.
- Un usuario que generó eventos es eliminado posteriormente: la fila del log conserva el nombre
  del usuario en el momento del evento en lugar de romperse o desaparecer.
- Un mismo registro (p. ej. una factura) tiene múltiples eventos a lo largo del tiempo: todos
  aparecen como filas independientes, no se agrupan ni se sobrescriben.
- Volumen alto de eventos con el tiempo: la vista sigue siendo utilizable gracias a la paginación
  server-side (no se cargan todos los eventos de golpe en el navegador).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE añadir un ítem "Logs" en el dropdown de usuario del header, visible
  para cualquier usuario autenticado, que enlace a la nueva vista de historial de actividad.
- **FR-002**: El sistema DEBE registrar como evento de actividad, como mínimo: inicio de sesión,
  cierre de sesión, alta, baja (o desactivación) y modificación relevante de clientes, artículos,
  facturas, configuración del tenant y usuarios.
- **FR-003**: Cada evento registrado DEBE incluir: usuario responsable (nombre visible aunque el
  usuario sea eliminado después), tipo de acción, entidad/registro afectado y fecha/hora del
  evento.
- **FR-004**: La vista de logs DEBE mostrar los eventos en una tabla con paginación, búsqueda y
  ordenamiento resueltos en el servidor (mismo patrón que las datatables existentes de usuarios y
  facturas), no cargando el histórico completo de una sola vez en el navegador.
- **FR-005**: El sistema DEBE mostrar únicamente eventos pertenecientes al tenant del usuario
  autenticado; ningún parámetro de la datatable (búsqueda, orden, paginación) puede exponer
  eventos de otro tenant.
- **FR-006**: Cualquier usuario autenticado del tenant, sin importar su rol (`usuario`, `admin`,
  `super_admin` operando en ese tenant), DEBE poder acceder a la vista y ver el historial completo
  de actividad de ese tenant (no limitado a sus propias acciones).
- **FR-007**: Los eventos de actividad son de solo lectura desde la interfaz: el sistema NO DEBE
  ofrecer edición ni borrado manual de entradas del log.
- **FR-008**: El registro de eventos DEBE ser append-only (ninguna ruta de la aplicación edita ni
  borra selectivamente una fila existente), siguiendo el mismo patrón ya usado para el log de
  eventos de facturación. Esto no impide la purga automática por política de retención (FR-011):
  append-only describe la inmutabilidad de cada fila durante su vida útil, no una prohibición de
  borrado por antigüedad.
- **FR-009** *(enmienda post-MVP — registro de accesos RGPD/LOPDGDD)*: cada evento DEBE incluir
  además el resultado (autorizado/denegado), la IP de origen y el user-agent de la petición,
  capturados siempre en el servidor.
- **FR-010** *(enmienda)*: el sistema DEBE registrar también los intentos de inicio de sesión
  fallidos (credenciales inválidas o email inexistente), con el email intentado en lugar del
  nombre de usuario y sin usuario asociado, para permitir detectar accesos no autorizados.
- **FR-011** *(enmienda)*: el sistema DEBE purgar automáticamente, de forma periódica, los eventos
  que superen un plazo de retención configurable por tenant (default 730 días), en cumplimiento
  del principio de minimización de datos de RGPD.

### Key Entities *(include if feature involves data)*

- **Evento de actividad (log de usuario)**: representa una acción relevante ocurrida dentro de un
  tenant. Atributos clave: tenant al que pertenece, usuario responsable (nombre conservado aunque
  el usuario se elimine; email intentado si es un login fallido sin usuario), tipo de acción
  (login, logout, alta, baja, modificación), resultado (éxito/fallo), IP de origen, user-agent,
  tipo de entidad afectada (cliente, artículo, factura, configuración, usuario), referencia al
  registro afectado cuando aplica, fecha/hora, y una descripción breve legible del evento.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Cualquier usuario del tenant encuentra y abre el historial de actividad en 2 clics
  o menos desde cualquier pantalla del CRM (dropdown de usuario → Logs).
- **SC-002**: El 100% de los inicios/cierres de sesión y de las altas/bajas/modificaciones sobre
  clientes, artículos, facturas, configuración y usuarios quedan reflejados como una fila en el
  historial.
- **SC-003**: En ninguna prueba de aislamiento multi-tenant aparece una fila perteneciente a un
  tenant distinto al del usuario autenticado.
- **SC-004**: Con cientos de eventos acumulados, la vista de historial sigue cargando y
  respondiendo a búsquedas/paginación con la misma fluidez que las datatables existentes de
  usuarios y facturas (sin degradación perceptible para el usuario).

## Assumptions

- El ítem "Logs" es visible para todos los roles (`usuario`, `admin`, `super_admin`); no hay
  restricción de permisos por rol para esta primera versión, según fue confirmado explícitamente.
- "Cambios relevantes" sobre clientes, artículos, facturas, configuración y usuarios se interpreta
  como las operaciones de alta, baja/desactivación y modificación de esas entidades ya existentes
  en el CRM; no incluye cada lectura/consulta.
- ~~El histórico no tiene fecha de expiración ni purga automática en esta primera versión~~ —
  **revertido por FR-011**: al investigar la normativa española se determinó que el registro de
  accesos (RGPD/LOPDGDD) exige minimización de datos, así que el histórico sí tiene un plazo de
  retención configurable (default 730 días) con purga automática periódica.
- La vista de Super Admin sobre tenants (gestión global) queda fuera de alcance: este historial es
  siempre por tenant, visto desde dentro del propio tenant.
- Se reutiliza el patrón visual y de interacción ya establecido (layout NexaDash, datatable
  server-side, toastr para notificaciones puntuales) sin introducir nuevos patrones de UI.
