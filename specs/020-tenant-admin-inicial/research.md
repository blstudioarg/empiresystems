# Research: Alta de usuario administrador al crear un tenant

No hay incógnitas técnicas (`NEEDS CLARIFICATION`) pendientes: la spec fue clarificada por el
usuario (opción A: contraseña manual) y el código existente ya resuelve todos los patrones
necesarios. Este documento deja constancia de las decisiones tomadas y las alternativas
descartadas.

## D1 — Dónde crear el usuario administrador

- **Decisión**: crear el `User` dentro del mismo `DB::transaction()` que ya usa
  `TenantController::store()` para crear el `Tenant` y su `Domain`.
- **Rationale**: FR-005 exige atomicidad entre tenant, dominio y usuario. El controller ya tiene
  la transacción abierta; añadir la creación del `User` ahí es el cambio mínimo y reutiliza el
  mecanismo de rollback de Laravel/MySQL sin introducir un service nuevo (Principio V).
- **Alternativas consideradas**: un `TenantAdminCreator` service dedicado — descartado por
  YAGNI: no hay más de un caller hoy, y `Tenant::booted()` ya demuestra que la lógica de alta
  vive bien en el propio flujo de creación sin capas extra.

## D2 — Rol, estado y flag `activo` del usuario creado

- **Decisión**: `rol = UserRole::Admin`, `estado = EstadoUsuario::Aprobado`, `activo = true`.
- **Rationale**: son los enums ya existentes (`app/Enums/UserRole.php`,
  `app/Enums/EstadoUsuario.php`); `Admin` da acceso de administración del tenant sin ser
  `SuperAdmin` (FR-009), y `Aprobado` + `activo=true` son exactamente los valores que hoy permite
  iniciar sesión sin pasar por el flujo de aprobación de `RegisterController` (FR-003).
- **Alternativas consideradas**: introducir un estado nuevo tipo `auto_aprobado` — descartado,
  la spec (Assumptions) dice explícitamente que se reutilizan los estados existentes.

## D3 — Password: sin campo de confirmación

- **Decisión**: un único input `admin_password`, validado con `required`, `string`, `min:8`
  (misma política mínima que `RegisterRequest`), sin campo de confirmación.
- **Rationale**: la spec no pide confirmación de contraseña; es un formulario interno operado
  por el super admin (no un formulario de autoservicio), y la Constitución pide simplicidad
  (Principio V) — no añadir validación no exigida por la spec.
- **Alternativas consideradas**: replicar `confirmed` como en `RegisterRequest` — descartado,
  añadiría un campo y una regla no solicitados por ninguna acceptance scenario.

## D4 — Unicidad del email del administrador

- **Decisión**: no se añade una regla `unique` de base de datos sobre `admin_email`; solo
  `required` + `email`. La unicidad "dentro del tenant" es trivialmente cierta porque el tenant
  se crea en la misma operación (no puede haber usuarios previos que colisionen), tal como
  documenta la spec en "Edge Cases".
- **Rationale**: `users.email` no tiene un unique constraint global en este proyecto (el
  registro público solo valida `unique:users,email` a nivel de aplicación, no de columna); igual
  email puede repetirse entre tenants distintos. Añadir una regla `unique` aquí sería
  redundante e incluso incorrecto (bloquearía reusar el mismo email de admin en dos tenants
  distintos, cosa que la spec permite explícitamente).
- **Alternativas consideradas**: `Rule::unique('users')->where('tenant_id', ...)` — no aplica
  porque el tenant aún no existe al validar; se descarta por innecesario dado D4's rationale.

## D5 — Dónde se muestran/ocultan los campos del admin en el formulario

- **Decisión**: los inputs `admin_email` / `admin_password` viven siempre en
  `_form.blade.php`, pero el JS de `super-admin-tenants-modal.init.js` los oculta y les quita
  `required`/limpia su valor cuando el modal entra en modo edición (`fillForm`), y los muestra y
  vuelve a poner `required` en modo alta (`resetForm`).
- **Rationale**: la spec dice que la edición de un tenant no toca su administrador (no hay
  ningún requisito de edición de admin en esta feature); ocultarlos evita que
  `UpdateTenantRequest` reciba/valide campos que no le corresponden y evita que el usuario
  intente "cambiar la contraseña del admin" editando el tenant, que no es el flujo soportado.
- **Alternativas consideradas**: dos modales separados (uno de alta, uno de edición) —
  descartado por Principio V, duplicaría markup y JS ya existente sin necesidad.
