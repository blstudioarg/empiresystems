# Feature Specification: Alta de usuario administrador al crear un tenant

**Feature Branch**: `020-tenant-admin-inicial`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Alta de usuario administrador al crear un tenant desde el super admin. Al crear un tenant en el panel de super admin, además de los datos fiscales y el dominio, el super admin debe indicar el email y la contraseña inicial de un usuario administrador. Ese usuario se crea en la misma operación, asociado al tenant recién creado, con rol Admin, estado Activo/Aprobado y activo=true, para que el tenant sea accesible desde su nacimiento. La contraseña la escribe el super admin manualmente en el mismo formulario de alta (opción A). NO se siembran filas de configuración por defecto. Las series por defecto (F, R, S) se siguen creando automáticamente."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Crear un tenant con su administrador inicial (Priority: P1)

Como super admin, al dar de alta una empresa (tenant) desde el panel de super admin, indico
—junto a los datos fiscales y el dominio— el email y la contraseña de un usuario administrador.
Al guardar, la empresa queda creada y ese administrador puede iniciar sesión inmediatamente en
el dominio del tenant sin ningún paso intermedio de aprobación.

**Why this priority**: Es el objetivo central de la feature y resuelve un bloqueo real: hoy un
tenant recién creado nace inaccesible, porque el registro público solo crea usuarios pendientes
de aprobación y no existe ningún administrador que pueda aprobarlos (problema huevo-y-gallina).
Sin esto, ninguna empresa nueva puede empezar a usar el sistema.

**Independent Test**: Se puede probar de forma aislada creando un tenant desde el panel de super
admin con email + contraseña de administrador, y verificando que ese usuario puede autenticarse
correctamente en el dominio del tenant y acceder al CRM con permisos de administrador.

**Acceptance Scenarios**:

1. **Given** el super admin está en el formulario de alta de tenant, **When** completa los datos
   fiscales, el dominio, y el email y la contraseña del administrador, y guarda, **Then** el
   tenant se crea y también se crea un usuario con rol administrador, activo y aprobado, asociado
   a ese tenant.
2. **Given** un tenant recién creado con su administrador inicial, **When** ese administrador
   inicia sesión en el dominio del tenant con el email y contraseña indicados, **Then** accede al
   CRM sin requerir ninguna aprobación previa.
3. **Given** el super admin envía el formulario de alta, **When** falla la creación del usuario
   administrador (p. ej. por un error), **Then** no se crea ni el tenant ni el dominio ni el
   usuario (la operación completa se revierte) y se informa el error.

---

### User Story 2 - Validación de las credenciales del administrador (Priority: P2)

Como super admin, si introduzco un email inválido, un email ya usado en ese tenant, o una
contraseña que no cumple los requisitos mínimos, el sistema me lo advierte y no crea nada hasta
que corrija los datos.

**Why this priority**: Evita crear tenants con un administrador que no podrá entrar (email mal
escrito) o con credenciales débiles. Es soporte necesario para que la P1 sea fiable, pero no es
el flujo feliz en sí.

**Independent Test**: Enviar el formulario con un email malformado o una contraseña demasiado
corta y verificar que se muestran errores de validación y no se crea el tenant.

**Acceptance Scenarios**:

1. **Given** el formulario de alta, **When** el super admin deja vacío el email o la contraseña
   del administrador, **Then** el sistema muestra un error de validación y no crea el tenant.
2. **Given** el formulario de alta, **When** el super admin introduce un email con formato
   inválido, **Then** el sistema muestra un error de validación y no crea el tenant.
3. **Given** el formulario de alta, **When** la contraseña no cumple la longitud/complejidad
   mínima definida, **Then** el sistema muestra un error de validación y no crea el tenant.

---

### Edge Cases

- **Email duplicado dentro del mismo tenant**: la unicidad del email de usuario se evalúa dentro
  del ámbito del tenant. Como el tenant se está creando en la misma operación, no puede haber
  colisión con usuarios previos de ese tenant; el mismo email sí puede existir en otro tenant
  distinto sin conflicto.
- **Fallo parcial**: si se crea el tenant pero falla la creación del usuario (o viceversa), toda
  la operación se revierte para no dejar un tenant sin administrador ni un usuario huérfano.
- **Rol del administrador**: el usuario creado es administrador del tenant (no super admin); no
  obtiene acceso al panel de super admin.
- **Series por defecto**: se siguen creando automáticamente (F ordinaria, R rectificativa,
  S simplificada) como parte del alta del tenant, sin cambios respecto al comportamiento actual.
- **Configuración por defecto**: NO se crean filas de configuración en el alta; el tenant opera
  con los valores por defecto de fallback ya existentes hasta que un usuario los modifique.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El formulario de alta de tenant del panel de super admin DEBE incluir, además de
  los datos fiscales y el dominio ya existentes, dos campos nuevos: email del administrador
  inicial y contraseña del administrador inicial.
- **FR-002**: Al crear un tenant, el sistema DEBE crear en la misma operación un usuario asociado
  a ese tenant con: el email indicado, la contraseña indicada (almacenada de forma segura, nunca
  en texto plano), rol de administrador, estado aprobado/activo y habilitado para iniciar sesión.
- **FR-003**: El usuario administrador creado DEBE poder iniciar sesión en el dominio del tenant
  inmediatamente, sin ningún paso de aprobación adicional.
- **FR-004**: El sistema DEBE validar el email del administrador (obligatorio y con formato de
  email válido) y la contraseña (obligatoria y con una longitud/complejidad mínima) antes de
  crear nada; si la validación falla, no se crea el tenant, ni el dominio, ni el usuario.
- **FR-005**: La creación del tenant, su dominio y su usuario administrador DEBEN ser atómicas:
  si cualquiera de las tres falla, ninguna se persiste.
- **FR-006**: El usuario administrador creado DEBE quedar asociado exclusivamente al tenant recién
  creado y NO debe filtrarse ni ser visible desde otros tenants (aislamiento multi-tenant).
- **FR-007**: El sistema NO DEBE crear filas de configuración por defecto para el tenant nuevo;
  el comportamiento de fallback a valores por defecto existente se mantiene sin cambios.
- **FR-008**: El sistema DEBE seguir creando automáticamente las series por defecto del tenant
  (ordinaria, rectificativa, simplificada) en el alta, sin cambios respecto al comportamiento
  actual.
- **FR-009**: El usuario administrador creado NO DEBE recibir permisos de super admin; su ámbito
  de acceso se limita a la administración de su propio tenant.

### Key Entities *(include if feature involves data)*

- **Tenant**: la empresa cliente del SaaS. Se crea desde el panel de super admin con sus datos
  fiscales y un único dominio. Ahora, su alta incluye la creación de su administrador inicial.
- **Usuario administrador**: usuario perteneciente a un tenant, con rol de administrador, estado
  aprobado/activo, capaz de iniciar sesión y administrar el tenant. Se crea junto con el tenant.
  Atributos relevantes: email (único dentro del tenant), contraseña (almacenada de forma segura),
  rol, estado, indicador de activo, y la asociación al tenant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los tenants creados desde el panel de super admin quedan con al menos un
  usuario administrador capaz de iniciar sesión, sin pasos manuales posteriores.
- **SC-002**: Un administrador recién creado puede iniciar sesión en el dominio de su tenant en
  el primer intento con las credenciales indicadas por el super admin.
- **SC-003**: Ningún alta con datos de administrador inválidos (email/contraseña) genera un
  tenant persistido: 0 tenants creados en escenarios de validación fallida.
- **SC-004**: Ningún usuario administrador creado en un tenant es visible o accesible desde otro
  tenant (0 fugas entre tenants verificadas por prueba de aislamiento).

## Assumptions

- El super admin escribe la contraseña inicial manualmente en el mismo formulario de alta
  (opción A confirmada por el usuario). No se contempla, por ahora, contraseña autogenerada ni
  invitación por email con enlace para definir contraseña; esas variantes quedan fuera de alcance.
- La política de contraseñas (longitud/complejidad mínima) reutiliza la ya vigente en el resto
  del sistema para creación de usuarios; no se define una política nueva específica para este flujo.
- La unicidad del email de usuario es por tenant (un mismo email puede existir en tenants
  distintos), consistente con el modelo multi-tenant actual.
- Se crea exactamente un usuario administrador en el alta; gestionar administradores adicionales
  se hace después desde la administración de usuarios del propio tenant y queda fuera de alcance.
- El estado y los indicadores del usuario (aprobado, activo) usan los valores existentes que ya
  permiten el inicio de sesión sin aprobación; no se introducen estados nuevos.
- La comunicación de las credenciales al cliente (fuera del sistema) es responsabilidad del super
  admin; el envío automático de credenciales por email queda fuera de alcance.
