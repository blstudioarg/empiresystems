# Data Model: Traducción del POS al chino

## Ajuste nuevo (sin tabla): `pos.idioma` en `configuraciones`

Clave `pos.idioma` del grupo `pos` (patrón `ConfigPos`, `docs/01-arquitectura.md` Decisión 10).
Valores: `es` (por defecto, también por **ausencia** de fila) y `zh` (chino simplificado). No
depende de `pos.hosteleria_activo` (FR-002). Lectura: `ConfigPos::idioma(int $tenantId): string`.
Escritura: `ConfigPos::guardar(...)` con la clave `idioma`, validada contra la lista
`config('traduccion.idiomas')`.

## Tabla nueva (central): `traducciones`

Memoria de traducción automática de los textos **de la aplicación**. Sin `tenant_id`: no contiene
datos de ningún tenant (research D11).

| Columna | Tipo | Nulos | Notas |
|---|---|---|---|
| `id` | BIGINT autoincrement | no | |
| `idioma` | VARCHAR(10) | no | idioma de destino (`zh`) |
| `hash` | CHAR(64) | no | SHA-256 de `texto`; clave de búsqueda |
| `texto` | TEXT | no | texto original en español, tal cual aparece en `__()` (con `:variables`) |
| `ambito` | VARCHAR(40) | no | ámbito donde se encontró (`pos`); informativo, para extender a otros módulos |
| `es_html` | BOOLEAN | no | el texto lleva marcado HTML (guías de ayuda) → se traduce con `tag_handling=html` |
| `traduccion` | TEXT | sí | `null` mientras está pendiente o falló |
| `estado` | VARCHAR(12) | no | `pendiente` / `traducida` / `error` |
| `intentos` | SMALLINT UNSIGNED | no | se incrementa en cada intento fallido; tras 5 deja de reintentarse solo |
| `ultimo_error` | VARCHAR(255) | sí | causa del último fallo (sin datos personales) |
| `traducida_en` | TIMESTAMP | sí | |
| `vista_en` | TIMESTAMP | sí | último momento en que la extracción o un request la encontró (para detectar textos obsoletos) |
| `created_at` / `updated_at` | TIMESTAMP | | |

Índices: `UNIQUE (idioma, hash)`; `(idioma, estado)`.

**Reglas**:
- Se inserta como `pendiente` (insert-ignore por `idioma, hash`) desde la extracción del deploy o
  desde el respaldo de "clave no encontrada" (research D4).
- Pasa a `traducida` solo si la traducción contiene todas las variables `:x` del texto (D8); si no,
  `error`.
- Las filas no se editan desde la app: la corrección de un tenant va a otra tabla (FR-017).
- Un texto que ya no aparece en el código no se borra automáticamente (puede volver); `vista_en`
  permite limpiar a mano en el futuro.

## Tabla nueva (tenant): `traduccion_correcciones`

Corrección manual de un tenant sobre la traducción de un texto (FR-016/017/018).

| Columna | Tipo | Nulos | Notas |
|---|---|---|---|
| `id` | BIGINT autoincrement | no | |
| `tenant_id` | BIGINT, índice | no | `BelongsToTenant` (Principio I) |
| `idioma` | VARCHAR(10) | no | |
| `hash` | CHAR(64) | no | hash del texto en español que corrige |
| `texto` | TEXT | no | copia del texto en español (para listar correcciones antiguas aunque el texto haya cambiado) |
| `traduccion` | TEXT | no | traducción escrita por el tenant; saneada si el texto es HTML (D8) |
| `corregida_por` | FK → `users.id` | sí | `nullOnDelete` |
| `created_at` / `updated_at` | TIMESTAMP | | |

Índices: `UNIQUE (tenant_id, idioma, hash)`; `tenant_id`.

**Reglas**:
- Prevalece sobre `traducciones` solo para su tenant (`TranslationLoader`, D2).
- Debe conservar todas las variables `:x` del texto original (validación al guardar).
- Se puede borrar ("Restaurar automática"): es configuración del tenant, no un registro fiscal.

## Glosario (sin tabla): `config/traduccion.php`

`glosario.<idioma>`: mapa término español → término traducido (research D7). `no_traducir`: lista de
siglas protegidas. Versionado en el repo; su hash nombra el glosario remoto.

## Ámbitos de extracción (sin tabla): `config/traduccion.php`

`ambitos.pos.rutas`: archivos y carpetas a escanear; `ambitos.pos.claves_extra`: clases que aportan
claves no extraíbles por expresión regular (etiquetas del grupo POS de `CatalogoMenu`) (research D5).

## Payloads y vistas afectados

- **Vistas del POS**: `window.posI18n` (o equivalente global) con el diccionario `texto → traducción`
  efectivo del ámbito `pos` para el tenant, inyectado solo si el idioma del POS no es español; los
  JS lo usan con `__t()`.
- **Configuración → POS**: selector de idioma + sección «Traducciones del POS» (contrato en
  `contracts/traducciones.md`).
