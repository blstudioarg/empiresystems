# Quickstart: Alta de usuario administrador al crear un tenant

## Prerrequisitos

- Entorno local levantado (`php artisan serve` o equivalente) con base de datos migrada.
- Un usuario `super_admin` existente (o creado vía `User::factory()->superAdmin()->create()` en
  tinker/seeder) para poder acceder a `/super_admin/tenants`.

## Validar por UI (manual)

1. Iniciar sesión como super admin en el dominio central.
2. Ir a `Tenants` → `+ Agregar tenant`.
3. Completar los datos fiscales, el dominio, y los nuevos campos **Email del administrador** y
   **Contraseña del administrador**.
4. Guardar. Verificar el toast de éxito y que el tenant aparece en el listado.
5. Abrir el dominio del tenant recién creado en una pestaña nueva (o `curl -H "Host: <dominio>"`)
   e iniciar sesión con el email/contraseña indicados.
6. **Resultado esperado**: acceso inmediato al CRM del tenant, sin mensaje de "pendiente de
   aprobación", con permisos de administrador (ej. acceso a `Configuración`, `Usuarios`).

## Validar casos de error (manual)

- Dejar vacío el email o la contraseña del admin → el formulario muestra error de validación y
  no se crea el tenant (verificar que no aparece en el listado ni en `tenants` en BD).
- Escribir un email con formato inválido → mismo resultado.
- Escribir una contraseña de menos de 8 caracteres → mismo resultado.

## Validar por tests automatizados

```bash
php artisan test --filter=TenantCrudTest
```

Casos cubiertos (ver tasks.md para el desglose):

- Alta de tenant crea también el usuario administrador (rol, estado, activo correctos).
- El administrador creado puede iniciar sesión inmediatamente en el dominio del tenant.
- Validación: email/contraseña vacíos, email inválido, contraseña corta → no crea tenant.
- Aislamiento: el administrador de un tenant no es visible/autenticable en otro tenant.

## Fuera de alcance de esta validación

- Edición de un tenant existente no debe pedir ni tocar credenciales de administrador (sin
  cambios de comportamiento respecto a hoy — no requiere prueba nueva más allá de que
  `UpdateTenantRequest` siga aceptando los mismos campos que antes).
- Envío de credenciales por email al cliente: fuera de alcance (ver Assumptions en spec.md).
