# Quickstart — Validar el menú personalizable por tenant

**Feature**: 036-menu-personalizable-tenant

Guía de verificación de punta a punta. No contiene implementación; para el diseño ver
[data-model.md](./data-model.md) y [contracts/configuracion-menu.md](./contracts/configuracion-menu.md).

## Prerrequisitos

- Entorno local en marcha (`php artisan serve` o el vhost habitual) y BD migrada.
- Credenciales de acceso: **`ACCESOS.local.md`** en la raíz del repo (gitignored). No resetear ni
  sembrar nada a ciegas; si falta un usuario, `php artisan db:seed --class=AccesoPersonalSeeder`
  (idempotente, nunca pisa contraseñas existentes).
- Sesión iniciada con el **admin del tenant "Empire Demo"** (tiene `ver-configuracion`).

> ⚠️ No ejecutar `migrate:fresh` / `migrate:refresh` / `db:wipe` para validar esta feature: no hace
> falta (no hay migración) y se perderían los datos de demo con imágenes.

## Verificación automática

```bash
php artisan test --filter=Menu
```

Debe pasar en verde:

| Test | Qué prueba | Requisito |
|---|---|---|
| `MenuAislamientoTenantTest` | Con 2 tenants, la personalización de A no se lee ni se altera desde B | FR-012, SC-004, Principio I |
| `MenuPersonalizadoTest` | Guardar, validar (vacío / >40 caracteres), restaurar, permisos (403 sin `ver-configuracion`), claves desconocidas descartadas, elemento del catálogo ausente colocado al final | FR-004…FR-018 |
| `MenuSidebarRenderTest` | El sidebar muestra los nombres y el orden personalizados; y para varios roles, el conjunto de entradas visibles es idéntico al de antes de personalizar | FR-009, FR-011, SC-003 |
| `MenuFusionTest` | Reglas de fusión catálogo + personalización: JSON vacío/corrupto, claves desconocidas, elemento ausente colocado al final, etiqueta en blanco | FR-007, FR-010, FR-017, FR-018 |
| `CatalogoMenuTest` | Invariantes del catálogo: claves únicas, rutas registradas, permisos existentes, dos niveles | INV-1…INV-4 |

## Verificación manual

### A. Renombrar (User Story 1)

1. Ir a **Configuración → Menú**. Se ve la lista jerárquica del menú con un input por elemento y su
   nombre por defecto como apoyo.
2. Cambiar `Clientes` → `Pacientes` y `Cartera de clientes` → `Mis pacientes`. Pulsar **Guardar**.
3. **Esperado**: toast de éxito, el botón muestra estado de carga mientras espera, y al navegar a
   cualquier pantalla el sidebar dice "Pacientes / Mis pacientes".
4. Volver a la tab: los inputs vienen precargados con los nombres personalizados.

### B. Validación

1. Vaciar el nombre de un elemento y pulsar **Guardar**.
2. **Esperado**: error junto a ese input, sin toast de éxito, y el menú sin cambios. Repetir con un
   nombre de más de 40 caracteres.

### C. Reordenar (User Story 2)

1. Arrastrar el grupo **Facturas** a la primera posición, y dentro de **Control de fichaje** subir
   **Alertas** al primer lugar. Guardar.
2. **Esperado**: el sidebar respeta ambos órdenes en la siguiente carga.
3. Intentar arrastrar una entrada fuera de su grupo → vuelve sola a su sitio (FR-008a).
4. Reordenar algo y **recargar sin guardar** → los cambios se descartan (FR-020b).

### D. Permisos intactos (FR-011, SC-003)

1. Entrar con un usuario de un rol acotado (p. ej. sin `ver-facturas`).
2. **Esperado**: no ve "Facturas" aunque esté renombrado y movido a la primera posición; el resto
   aparece en el orden personalizado, sin huecos.

### E. Aislamiento entre tenants (FR-012)

1. Entrar en otro tenant.
2. **Esperado**: menú con los nombres y el orden por defecto, sin rastro de la personalización.

### F. Super Admin (FR-014)

1. Entrar como Super Admin.
2. **Esperado**: su menú es el de siempre y **no** existe la tab "Menú" para él.

### G. Restaurar (User Story 3)

1. En la tab, pulsar **Restaurar valores por defecto** → aparece el modal de confirmación (centrado,
   con etiqueta "Restaurar", **no** con la papelera roja de borrado).
2. Cancelar → nada cambia. Volver a pulsar y confirmar.
3. **Esperado**: toast de éxito y menú idéntico al de un tenant que nunca personalizó nada.

### H. Registro de actividad (FR-022)

1. Ir a **Logs de actividad** tras guardar y tras restaurar.
2. **Esperado**: sendas entradas de modificación de configuración, con el usuario y la fecha.

## Comprobaciones de cierre (documentación al día, `CLAUDE.md`)

- [ ] `docs/04-front-guidelines.md`: anotada la **excepción a "Listados: SIEMPRE DataTable"** para
      el editor de menú, con su motivo, y el patrón de lista jerárquica arrastrable.
- [ ] `resources/views/ayuda/configuracion.blade.php`: la guía in-app menciona la tab Menú.
- [ ] `resources/ia/conocimiento/configuracion.md`: la base de conocimiento del asistente describe
      la personalización del menú (FR-013 de la feature 030).
- [ ] `docs/03-modelo-datos.md`: **solo si** se considera relevante documentar la clave
      `menu.personalizacion`; no hay tabla nueva que documentar.
</content>
