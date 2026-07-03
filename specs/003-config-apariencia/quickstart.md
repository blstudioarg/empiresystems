# Quickstart — Configuración del tenant (Apariencia / Marca)

Guía de validación end-to-end de la feature. No contiene implementación; ver `tasks.md`.

## Prerrequisitos

- App corriendo (`php artisan serve`) y BD migrada.
- Symlink de storage creado (necesario para servir el logo):
  ```
  php artisan storage:link
  ```
- Migraciones nuevas aplicadas:
  ```
  php artisan migrate
  ```
- Al menos **2 tenants** con un usuario cada uno (usar seeders/factories existentes).

## Escenario 1 — Cambiar colores de marca (US1 / P1)

1. Iniciar sesión como usuario del **Tenant A**.
2. En la topbar, abrir el dropdown de la foto de perfil → **Configuración**.
3. Verificar que abre la tab **"Apariencia / Marca"** por defecto.
4. Cambiar color primario, secundario y de fondo de topbar con los color pickers.
5. **Esperado**: cada color se guarda automáticamente por AJAX al elegirlo (sin botón "Guardar"),
   con un toast de confirmación; los botones/links/badges de la propia pantalla usan el nuevo
   primario de inmediato (variables CSS actualizadas en vivo), y la topbar el nuevo fondo, sin
   recargar la página.
6. Confirmar en BD: existen filas en `configuraciones` con `tenant_id` de A y claves
   `apariencia.color_primario|secundario|topbar`.

## Escenario 2 — Subir logo con vista previa (US2 / P2)

1. En la misma tab, seleccionar una imagen (PNG/JPG/WEBP ≤ 1 MB) en el campo **Logo**.
2. **Esperado**: aparece la **vista previa** de la imagen inmediatamente, y el archivo se sube por
   AJAX en el mismo evento `change` (sin botón "Guardar"); toast de confirmación y el logo del
   nav-header se actualiza sin recargar.
3. Recargar la página.
4. **Esperado**: el logo del menú (nav-header) sigue siendo la imagen subida; `tenants.logo_path`
   del Tenant A apunta al fichero en `storage/app/public/logos/{id}/...`.
5. Intentar subir un `.txt` o una imagen > 1 MB → **Esperado**: error de validación (toast rojo), el
   logo vigente no cambia.

## Escenario 3 — Aislamiento entre tenants (Principio I)

1. Iniciar sesión como usuario del **Tenant B**.
2. Abrir Configuración → **Apariencia / Marca**.
3. **Esperado**: NO se ven los colores ni el logo del Tenant A; se ven los de B (o los defaults si B no
   configuró nada).
4. Guardar algo en B y verificar que la configuración de A queda intacta.

## Escenario 4 — Restablecer a valores por defecto (FR-010)

1. En un tenant con colores/logo propios, usar la acción **Restablecer**.
2. **Esperado**: la interfaz vuelve al aspecto por defecto del template (colores originales, logo SVG);
   las claves `apariencia.*` del tenant desaparecen y `logo_path` queda `null`.

## Escenario 5 — Acceso solo autenticado

1. Cerrar sesión y navegar a `/configuracion`.
2. **Esperado**: redirección a `/login`.

## Tabs futuras (US3 / P3)

- En la pantalla de Configuración, comprobar que existe la barra de tabs con "Apariencia / Marca"
  funcional y otras secciones señalizadas como próximas (inertes, sin error).

## Cobertura de tests esperada (ver tasks.md)

- Feature: guardar colores (persistencia + valores efectivos), subir logo (ok), validación de logo
  (rechazo), validación de color (rechazo), restablecer, acceso solo autenticado.
- Aislamiento (test-first): ≥2 tenants, lectura y escritura no cruzan de tenant.
