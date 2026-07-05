# Contrato de UI: Mi perfil

Esta feature no expone APIs nuevas. Reutiliza rutas ya existentes. Se documenta el contrato de
la vista y de los endpoints que consume.

## Rutas (existentes, sin cambios)

### `GET /perfil` → `profile.show`

- **Auth**: requerida (middleware de sesión + tenant activo).
- **Controller**: `ProfileController@show` — devuelve `view('profile.show', ['user' => auth()->user()])`.
- **Entrada**: ninguna (sin parámetros; el usuario se toma de la sesión).
- **Salida**: HTML de la vista de perfil dentro del layout con sidebar.
- **Contrato de la vista** (`profile.show`):
  - Recibe `$user` (usuario autenticado).
  - Muestra: avatar (con badge de estado online decorativo), `name`, `rol->label()`, `email`,
    nombre de empresa (o "Sin empresa"), badge de estado (`estado->label()`), fecha de alta.
  - Incluye un formulario de cambio de foto que apunta a `profile.avatar.update`.
  - No muestra contenido demo del template.

### `POST /perfil/avatar` → `profile.avatar.update`

- **Auth**: requerida.
- **Controller**: `ProfileController@updateAvatar` (existente).
- **Entrada**: `multipart/form-data` con campo `avatar` (imagen, `max:2048` KB). `@csrf` obligatorio.
- **Validación** (ya implementada): `required|image|max:2048`.
- **Salida (no-AJAX)**: redirect a `profile.show` con flash `success` → toast de éxito.
- **Salida (AJAX/JSON, opcional)**: `{ message, avatar_url }`.
- **Errores**: validación → redirect back con errores / mensaje; el avatar previo no cambia.

## Contrato de notificaciones

- Éxito y error se comunican vía **toastr** (`partials/flash-toastr` a partir de los flash de
  sesión, o `window.showToast(type, message)` si se hace AJAX). Prohibido introducir alerts
  Bootstrap ad-hoc (regla del proyecto).
