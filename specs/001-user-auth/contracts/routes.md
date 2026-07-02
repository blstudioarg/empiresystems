# Contrato de rutas HTTP: Autenticación

**Feature**: 001-user-auth · **Date**: 2026-07-02

## Rutas públicas (middleware `guest`)

| Método | URI | Nombre | Acción | Respuesta |
|---|---|---|---|---|
| GET | `/login` | `login` | Muestra el formulario de login | 200, vista `auth.login` |
| POST | `/login` | `login.attempt` | Intenta autenticar | ver casos abajo |

### POST /login — casos

**Request body** (`application/x-www-form-urlencoded`):

| Campo | Reglas de validación |
|---|---|
| email | requerido, formato email |
| password | requerido, string |
| remember | opcional, boolean (checkbox) |

| Caso | Resultado |
|---|---|
| Credenciales válidas, user activo, tenant activo o null | 302 → `/` (o URL intended); sesión regenerada; si `remember`, cookie recordable emitida |
| Credenciales inválidas / user inactivo / tenant inactivo | 302 back a `/login` con error genérico en `email`: "Estas credenciales no coinciden con nuestros registros." + `old('email')` |
| Validación fallida (email vacío/mal formado, password vacía) | 302 back con errores de validación en español; no cuenta para throttle |
| 6º intento fallido dentro de la ventana (email+IP) | 302 back con error "Demasiados intentos. Inténtalo de nuevo en :seconds segundos."; no se procesa el intento |
| Usuario ya autenticado hace GET /login | 302 → `/` (comportamiento estándar del middleware guest) |

## Rutas autenticadas (middleware `auth` + `SetTenantContext`)

| Método | URI | Nombre | Acción | Respuesta |
|---|---|---|---|---|
| POST | `/logout` | `logout` | Cierra sesión (invalida sesión + regenera token CSRF) | 302 → `/login` |
| GET | `/` | `dashboard` | Dashboard (existente) | 200 solo autenticado |

**Regla general (FR-003)**: toda ruta interna presente y futura vive dentro del grupo `auth` +
`SetTenantContext`. Un guest que pida cualquiera recibe 302 → `/login`.

## Comportamiento del middleware `SetTenantContext`

| Estado del usuario | Efecto |
|---|---|
| Autenticado con `tenant_id` y tenant activo | `tenancy()->initialize(tenant)` — `tenant()` disponible, `tenancy()->initialized === true` |
| Autenticado super_admin (`tenant_id` null) | No inicializa — contexto central, `tenancy()->initialized === false` |
| Autenticado con tenant **inactivo** (desactivado a mitad de sesión) | Logout forzado + 302 → `/login` |
| Guest | No aplica (el middleware `auth` corta antes) |

## Logging (FR-014)

| Evento | Nivel | Datos |
|---|---|---|
| Login OK | info | user id, email, IP |
| Logout | info | user id, email |
| Intento fallido | warning | email intentado, IP |
| Lockout (throttle) | warning | email, IP |

Nunca se registran contraseñas.
