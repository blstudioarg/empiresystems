# Research: Vista de perfil de usuario

## Decisión 1 — Fuente de la vista y del layout

- **Decisión**: Crear `resources/views/profile/show.blade.php` extendiendo `layouts/app.blade.php`
  (layout con sidebar del CRM), NO el `layouts/default` del template externo.
- **Rationale**: El controller ya apunta a `profile.show`; el proyecto ya migró al layout propio
  `layouts/app.blade.php`. El archivo del template (`overview-profile.blade.php`) extiende
  `layouts.default` (motor del template original) y solo se usa como banco de piezas visuales.
- **Alternativas**: Usar el layout del template → rechazado, rompe con la convención del proyecto
  y el theming/persistencia ya integrados.

## Decisión 2 — Qué piezas del template se trasplantan

- **Decisión**: Solo la **card de cabecera de perfil** (`.card.profile-overview`): avatar
  redondeado con badge de estado, nombre, y la lista de meta-datos en línea (`<ul>` con iconos
  `las la-*`). El resto (feed, comentarios, galerías, quotes, embeds, "Follow/Offer a Deal",
  redes sociales, stat cards de earnings, tabs de Projects/Campaigns/Documents/Followers/Activity,
  widgets de la columna derecha, to-dos, charts) se descarta.
- **Rationale**: FR-004 lo exige; el resto es contenido demo sin datos reales en el CRM.
- **Alternativas**: Conservar tabs/timeline → rechazado por YAGNI; no hay datos que alimentarlos
  y añadirían rutas/estado inexistentes.

## Decisión 3 — Mapeo de valores de enum a etiquetas en español

- **Decisión**: Añadir un método `label(): string` a `UserRole` y `EstadoUsuario` que devuelva
  la etiqueta en español (p. ej. Admin → "Administrador"; Aprobado → "Aprobado"). Para el estado,
  añadir también un helper de color/estilo de badge (`badgeClass()` o similar) para reflejar
  Pendiente/Aprobado/Rechazado con colores distinguibles.
- **Rationale**: Hoy las vistas pintan `->value` crudo (`super_admin`, `pendiente`), poco legible
  para el usuario. Centralizar la etiqueta en el enum es reutilizable en sidebar/header/registro y
  evita `match` duplicados en Blade. Es una mejora mínima y localizada.
- **Alternativas**: `match` inline en la vista → rechazado, duplica lógica de presentación que ya
  se necesita en varios sitios; array de traducción en config → sobredimensionado para 3+3 casos.

## Decisión 4 — Subida de avatar (UI)

- **Decisión**: Formulario `multipart/form-data` que apunta a `route('profile.avatar.update')`
  (`POST /perfil/avatar`) con `@csrf`, input `file` `avatar`. Confirmación/errores vía flash →
  `partials/flash-toastr` (el controller ya devuelve `->with('success', ...)`). Se acepta el flujo
  de recarga simple (redirect back) que el controller ya soporta; el modo AJAX/JSON queda como
  opcional y no bloqueante.
- **Rationale**: El endpoint ya existe y ya soporta redirect con flash; usar el flujo más simple
  cumple los criterios de éxito sin JS adicional. Toastr es el patrón obligatorio del proyecto.
- **Alternativas**: Subida AJAX con preview en vivo → válida pero no requerida por el MVP; se deja
  documentada como mejora opcional siguiendo el layout de preview de `docs/04-front-guidelines.md`.

## Decisión 5 — Valores de reserva (degradación elegante)

- **Decisión**: Empresa ausente → "Sin empresa"; sin foto → `avatarUrl()` ya devuelve un avatar
  por defecto; fecha de alta ausente → guion o "—". Aprobador (`aprobado_por`) es opcional y no se
  muestra como campo obligatorio.
- **Rationale**: FR-009 y edge cases del spec. `avatarUrl()` ya resuelve el fallback de imagen.
