# HTTP Contract — Configuración (Apariencia / Marca)

Endpoints web (Blade + form POST). Todos bajo middleware `['auth','tenant.context']`. Un usuario
autenticado solo opera sobre la configuración de **su** tenant activo.

## GET `/configuracion` → `configuracion.show`

Muestra la pantalla de Configuración con tabs; la tab "Apariencia / Marca" activa por defecto.

- **Auth**: requerido. No autenticado ⇒ redirect a `/login`.
- **Vista**: `configuracion.index`, con la apariencia vigente del tenant (colores efectivos + logo).
- **200**: HTML con el formulario de apariencia precargado con los valores actuales del tenant (o los
  defaults del template si no hay configurados).

## PUT/PATCH `/configuracion/apariencia` → `configuracion.apariencia.update`

Guarda los valores de apariencia del tenant activo. `multipart/form-data` (por el logo).

### Parámetros

| Campo | Tipo | Reglas | Notas |
|-------|------|--------|-------|
| color_primario | string | `nullable`, regex `/^#[0-9A-Fa-f]{6}$/` | HEX; vacío ⇒ no cambia esa clave |
| color_secundario | string | `nullable`, regex HEX | |
| color_topbar | string | `nullable`, regex HEX | |
| logo | file | `nullable\|image\|mimes:png,jpg,jpeg,webp\|max:1024` | reemplaza el logo vigente |
| restablecer | boolean | `nullable` | si `true`, ignora los demás campos y vuelve a defaults |

### Respuestas

- **302 redirect** a `configuracion.show` con flash `success` (petición normal de formulario) — o
  **200 JSON** `{ "message": "..." }` si `Accept: application/json` (coherente con el patrón de
  `ClienteController`).
- **422** (o redirect back con errores de sesión): validación fallida (color inválido, logo no
  admitido o demasiado grande). El estado guardado del tenant **no cambia**.
- **Efecto**:
  - Upsert de las claves `apariencia.color_*` presentes en `configuraciones` para el `tenant_id`
    activo (`tipo=string`, `grupo=apariencia`).
  - Si viene `logo`: se almacena en disco `public` bajo `logos/{tenant_id}/`, se actualiza
    `tenants.logo_path`, y se elimina el fichero anterior si existía.
  - Si `restablecer=true`: se borran las 3 claves de color del tenant y `logo_path=null` (+ borrado del
    fichero).
  - Se invalida la caché de apariencia del tenant.

## Aislamiento (Principio I) — invariantes verificables

- Guardar como usuario del tenant A nunca crea/modifica `configuraciones` ni `logo_path` del tenant B.
- Cargar `/configuracion` como usuario del tenant A muestra solo los valores del tenant A.

## Aplicación en la interfaz (no es endpoint, es contrato de render)

- El layout `layouts/app.blade.php` incluye en `<head>` el partial `apariencia-tenant` que emite un
  `<style>` con: `--primary`, `--primary-hover`, `--rgba-primary-1..9` (derivadas del primario),
  `--secondary`, `--topbar-bg` — solo para las claves que el tenant tenga configuradas.
- `nav-header.blade.php` renderiza `<img src=logo_path>` cuando `tenant()->logo_path` no es nulo; si no,
  el logo SVG por defecto.
