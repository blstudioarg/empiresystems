# Feature Specification: Envío de facturas por email (SMTP por tenant)

**Feature Branch**: `017-envio-facturas-email`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Envío de facturas por email vía SMTP propio de cada tenant."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configurar el correo del tenant (Priority: P1)

Un usuario del tenant abre la pantalla de Configuración, entra en la pestaña **Email** y
carga los datos de su cuenta SMTP (servidor, puerto, cifrado, usuario, contraseña, remitente,
nombre del remitente y, opcionalmente, una dirección de respuesta). Guarda y el sistema conserva
esos datos de forma segura (la contraseña nunca se muestra de vuelta en claro). Antes de usarla en
una factura real, pulsa **"Enviar email de prueba"** y recibe en su propio buzón un correo de test
que confirma que los datos son correctos.

**Why this priority**: Sin una cuenta de correo configurada y verificada, ninguna factura puede
enviarse. Es el cimiento del resto de la feature y aporta valor por sí solo (deja al tenant listo
para operar y le da certeza de que sus credenciales funcionan).

**Independent Test**: Se puede probar de forma aislada configurando el SMTP de un tenant, guardando,
recargando la pantalla (la contraseña aparece enmascarada, no en claro) y disparando el email de
prueba con credenciales válidas e inválidas para comprobar el mensaje de éxito/error.

**Acceptance Scenarios**:

1. **Given** un tenant sin correo configurado, **When** el usuario completa y guarda la pestaña
   Email con datos válidos, **Then** el sistema persiste la configuración y la contraseña queda
   almacenada cifrada, no legible en claro.
2. **Given** una configuración de email ya guardada, **When** el usuario vuelve a abrir la pestaña
   Email, **Then** todos los campos se muestran rellenos salvo la contraseña, que aparece
   enmascarada; guardar sin re-escribir la contraseña conserva la contraseña anterior.
3. **Given** una configuración de email guardada con credenciales válidas, **When** el usuario pulsa
   "Enviar email de prueba", **Then** recibe un correo de prueba en su propia dirección y ve una
   notificación de éxito.
4. **Given** una configuración con credenciales o servidor incorrectos, **When** el usuario pulsa
   "Enviar email de prueba", **Then** el sistema muestra un mensaje de error claro y no se cae con
   una excepción cruda.

---

### User Story 2 - Enviar una factura emitida por email (Priority: P1)

Desde una factura ya **emitida**, el usuario pulsa **"Enviar por email"**. El sistema propone como
destinatario el email del cliente que quedó guardado en la factura, que el usuario puede editar o
completar antes de confirmar. Al confirmar, el cliente recibe un correo enviado desde la propia
cuenta del tenant con el PDF de la factura adjunto. El sistema deja registro de que esa factura fue
enviada (a quién y cuándo) y lo refleja en el listado de facturas.

**Why this priority**: Es el objetivo de negocio de la feature — que el cliente reciba su factura
por correo desde la identidad de la empresa. Depende de la US1 (necesita correo configurado).

**Independent Test**: Con un tenant que ya tiene SMTP configurado, abrir una factura emitida,
enviarla a una dirección de prueba y verificar que el correo sale con el PDF adjunto, que queda
registrado el envío y que el listado marca la factura como enviada.

**Acceptance Scenarios**:

1. **Given** una factura emitida de un tenant con correo configurado y un cliente con email, **When**
   el usuario confirma el envío, **Then** se envía un correo desde la cuenta del tenant con el PDF de
   la factura adjunto al email indicado.
2. **Given** el formulario de envío, **When** se abre, **Then** el destinatario aparece precargado con
   el email guardado en la factura y es editable antes de confirmar.
3. **Given** un envío realizado con éxito, **When** el usuario vuelve al listado de facturas, **Then**
   la factura aparece marcada como "enviada" y existe un registro del envío (destinatario, fecha,
   resultado).
4. **Given** un tenant **sin** correo configurado, **When** el usuario intenta enviar una factura,
   **Then** el sistema no realiza el envío y avisa claramente de que debe configurar primero su correo
   (error controlado, sin excepción cruda).
5. **Given** una factura en estado **borrador**, **When** el usuario mira las acciones disponibles,
   **Then** la opción de enviar por email no está disponible.
6. **Given** una factura cuyo destinatario no tiene email y el usuario no informa ninguno, **When**
   intenta confirmar el envío, **Then** el sistema lo bloquea con un mensaje pidiendo una dirección de
   destino válida.

---

### Edge Cases

- **Contraseña en re-guardado**: guardar la pestaña Email dejando el campo de contraseña vacío NO debe
  borrar ni sobrescribir la contraseña previamente guardada.
- **Factura simplificada sin cliente**: puede no tener email de cliente; el envío exige que el usuario
  informe una dirección válida manualmente.
- **Fallo del servidor SMTP durante el envío** (host caído, credenciales revocadas, rechazo del
  destinatario): el envío falla de forma controlada, se informa al usuario y no se marca la factura
  como enviada; conviene dejar traza del intento fallido.
- **Aislamiento entre tenants**: un tenant nunca debe poder enviar usando las credenciales de otro; el
  correo siempre sale con la cuenta del tenant activo.
- **Reenvío**: una factura ya enviada puede volver a enviarse (p. ej. corregir el destinatario); cada
  envío queda registrado.
- **Email de dirección de respuesta vacío**: si no se informa, no se fuerza una dirección de respuesta.

## Requirements *(mandatory)*

### Functional Requirements

**Configuración del correo del tenant**

- **FR-001**: El sistema MUST permitir a cada tenant configurar su propia cuenta de envío de correo:
  servidor, puerto, tipo de cifrado, usuario, contraseña, dirección remitente, nombre del remitente y
  dirección de respuesta opcional.
- **FR-002**: El sistema MUST almacenar la contraseña de correo de forma cifrada y NUNCA devolverla ni
  mostrarla en claro en la interfaz.
- **FR-003**: El sistema MUST conservar la contraseña previamente guardada si el usuario guarda la
  configuración sin introducir una nueva (campo de contraseña vacío = sin cambio).
- **FR-004**: El sistema MUST ofrecer una acción de "enviar email de prueba" que use la configuración
  guardada para mandar un correo a la propia dirección del usuario/tenant y confirmar visualmente si
  las credenciales funcionan.
- **FR-005**: El sistema MUST tratar cualquier fallo al enviar el email de prueba como un error
  controlado con mensaje claro, sin exponer una excepción cruda.

**Envío de facturas**

- **FR-006**: El sistema MUST permitir enviar por email una factura que esté en estado **emitida** (o
  posteriores), y MUST NOT ofrecer el envío para facturas en **borrador**.
- **FR-007**: El sistema MUST enviar el correo de la factura usando exclusivamente la cuenta de correo
  del **tenant activo**, con el remitente y la dirección de respuesta configurados.
- **FR-008**: El sistema MUST adjuntar el PDF de la factura al correo, generado con el mismo formato que
  el PDF descargable de la factura.
- **FR-009**: El sistema MUST proponer como destinatario por defecto el email del cliente guardado en la
  factura y MUST permitir editarlo o informarlo antes de confirmar el envío.
- **FR-010**: El sistema MUST validar que existe una dirección de destino válida antes de enviar; si no
  la hay, MUST bloquear el envío con un mensaje explicativo.
- **FR-011**: El sistema MUST impedir el envío cuando el tenant no tiene el correo configurado,
  avisándolo con un error controlado que indique que debe configurarlo primero.
- **FR-012**: El sistema MUST registrar cada envío de factura (destinatario, fecha/hora y resultado)
  como un evento asociado a la factura, sin crear un almacén de datos nuevo para ello.
- **FR-013**: El sistema MUST reflejar en el listado de facturas si una factura ya ha sido enviada por
  email.
- **FR-014**: El sistema MUST permitir reenviar una factura ya enviada, registrando cada envío por
  separado.

**Aislamiento y seguridad (multi-tenant)**

- **FR-015**: Toda la configuración de correo y todo envío MUST estar aislados por tenant; ningún tenant
  puede leer ni usar las credenciales de otro.
- **FR-016**: La resolución de la factura a enviar MUST respetar el aislamiento por tenant (una factura
  de otro tenant no debe ser accesible ni enviable).

### Key Entities *(include if feature involves data)*

- **Configuración de correo del tenant**: conjunto de parámetros de envío por tenant (servidor, puerto,
  cifrado, usuario, contraseña cifrada, remitente, nombre del remitente, dirección de respuesta).
  Reutiliza el almacén de configuración clave-valor existente del tenant, grupo "email".
- **Evento de envío de factura**: registro de que una factura fue enviada, con destinatario, momento y
  resultado. Reutiliza el registro de eventos de factura existente.
- **Factura**: entidad existente; se le añade la capacidad de ser enviada por correo y de reflejar su
  estado de envío. No cambia su inmutabilidad fiscal ni su estado fiscal por el hecho de enviarse.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un tenant puede pasar de "sin correo configurado" a "email de prueba recibido
  correctamente" en un solo paso de configuración, sin intervención de soporte.
- **SC-002**: El 100% de las facturas enviadas llegan con el PDF de la factura adjunto y con el
  remitente del propio tenant (no de la plataforma).
- **SC-003**: El 100% de los intentos de envío sin correo configurado o sin destinatario válido se
  resuelven con un mensaje claro para el usuario y ningún error no controlado.
- **SC-004**: Para cualquier factura, un usuario puede verificar en el listado y en el historial de la
  factura si fue enviada, a quién y cuándo, sin salir de la aplicación.
- **SC-005**: En pruebas con dos o más tenants, ningún envío usa las credenciales o el remitente de un
  tenant distinto al activo (cero fugas de credenciales entre tenants).

## Assumptions

- **Envío síncrono (deuda técnica asumida)**: el correo se envía dentro de la misma petición del
  usuario; no se implementan colas ni procesos en segundo plano en esta versión. Se migrará a envío en
  cola cuando el volumen lo exija.
- **Una sola cuenta SMTP por tenant**: cada tenant configura una única cuenta de envío; no hay múltiples
  perfiles de remitente.
- **Un único destinatario por envío**: no se contemplan CC/BCC ni listas de destinatarios en esta
  versión.
- **Plantilla de correo fija**: el cuerpo del email de factura es estándar del sistema; no es
  personalizable por el tenant en esta versión.
- **Reutilización de infraestructura existente**: se apoya en el almacén de configuración por tenant, en
  el registro de eventos de factura y en la generación de PDF de factura ya existentes.
- **Fuera de alcance v1**: SMTP global/de plataforma; emails transaccionales del sistema (verificación
  de cuenta, restablecimiento de contraseña, aprobación de usuarios); colas/workers; CC/BCC/listas de
  destinatarios; plantillas de email personalizables; recordatorios automáticos de vencimiento o cobro.
- **Cumplimiento de la constitución**: la feature respeta el aislamiento multi-tenant (Principio I) y no
  altera la inmutabilidad fiscal de las facturas emitidas (Principio II): enviar una factura no cambia
  su estado fiscal ni su contenido.
