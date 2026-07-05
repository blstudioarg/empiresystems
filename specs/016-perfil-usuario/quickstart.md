# Quickstart / Validación: Mi perfil

## Prerrequisitos

- Entorno del CRM levantado (`php artisan serve` o el entorno habitual) con la base sembrada
  (`php artisan migrate --seed`) — hay usuarios de prueba con tenant.
- Sesión iniciada como un usuario cualquiera del CRM.

## Escenario 1 — Ver mi perfil (P1)

1. Iniciar sesión.
2. Navegar a `/perfil` (o el enlace "Mi perfil" del menú de usuario).
3. **Esperado**: se muestra la cabecera con avatar, nombre real, rol en español, email, empresa,
   badge de estado (Aprobado/Pendiente/Rechazado) y fecha de alta. Sin contenido demo del template.
   Sin errores. El tema claro/oscuro coincide con el resto del CRM.

## Escenario 2 — Usuario sin foto / sin empresa (edge)

1. Con un usuario sin `avatar_path` y/o sin tenant, entrar a `/perfil`.
2. **Esperado**: avatar por defecto (no imagen rota) y "Sin empresa" en lugar de un campo vacío.

## Escenario 3 — Cambiar foto de perfil (P2)

1. En `/perfil`, seleccionar una imagen válida (< 2 MB) en el control de foto y enviar.
2. **Esperado**: la página vuelve a `/perfil`, el avatar mostrado es la nueva imagen y aparece un
   **toast de éxito**.
3. Repetir con un archivo no imagen o > 2 MB.
4. **Esperado**: mensaje de error (toast/validación) y el avatar **no** cambia.

## Escenario 4 — Aislamiento (P1)

1. Confirmar que la ruta `/perfil` no acepta identificador de usuario y siempre muestra al
   usuario autenticado.
2. **Esperado (test)**: `tests/Feature/ProfileTest.php` verifica que dos usuarios distintos ven
   cada uno sus propios datos y nunca los del otro.

## Comandos de test

```bash
php artisan test --filter=ProfileTest
```

**Esperado**: verde. Cubre acceso autenticado a `/perfil`, presencia de datos propios y ausencia
de datos de otro usuario (aislamiento), más los valores de reserva.
