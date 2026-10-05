# Contrato: idioma del POS, traducciones y comando de sincronización

Todas las rutas web viven en el grupo de Configuración existente, con `can:ver-configuracion` y
**sin** `modulo.hosteleria` (el idioma aplica al POS aunque el módulo de hostelería esté apagado,
FR-002). Resolución manual del modelo bajo `TenantScope` (memoria `project_tenant_route_binding`).

## `PUT|PATCH /configuracion/pos` → `configuracion.pos.update` (existente, ampliado)

Nuevo campo opcional:

```json
{ "idioma": "zh" }
```

`idioma`: `sometimes`, uno de `config('traduccion.idiomas')` (`es`, `zh`). Sin el campo, el idioma
no cambia (los formularios actuales siguen funcionando). La respuesta JSON (`config`) incluye
`idioma`. Con `idioma` distinto de `es`, la respuesta indica si hay textos del POS todavía sin
traducir (`traducciones_pendientes: int`), para que la pantalla lo avise.

## `GET /configuracion/pos/traducciones` → `configuracion.pos.traducciones.index`

Listado para la DataTable de «Traducciones del POS» (JSON, client-side: unos cientos de filas).

**200**:

```json
{
  "idioma": "zh",
  "data": [
    {
      "hash": "9f1c…",
      "texto": "Cobrar",
      "traduccion": "收款",
      "origen": "automatica",
      "estado": "traducida",
      "es_html": false,
      "corregida_por": null,
      "corregida_en": null
    }
  ]
}
```

`origen`: `automatica` | `corregida` | `pendiente` (sin traducción todavía). `traduccion` es la
**efectiva** para el tenant (corrección si existe). Solo textos del ámbito `pos` y del idioma del
tenant. Si el idioma del tenant es `es`: `data: []`. (Hoy `pos` es el único ámbito; cuando haya más,
el filtro por ámbito se revisa porque `traducciones.ambito` guarda el primero donde se encontró.)

## `PUT /configuracion/pos/traducciones/{hash}` → `configuracion.pos.traducciones.update`

Crea o actualiza la corrección del tenant.

**Request**: `{ "traduccion": "结账" }` — `required|string|max:5000`.

**Validación** (422 con mensaje legible):
- el `hash` debe existir en `traducciones` para el idioma del tenant (404 si no);
- la traducción debe contener todas las variables `:x` del texto original;
- si el texto es HTML, se sanea (solo `strong`, `em`, `br`) antes de guardar.

**200**: la fila del listado actualizada (`origen: "corregida"`).

## `DELETE /configuracion/pos/traducciones/{hash}` → `configuracion.pos.traducciones.destroy`

«Restaurar automática»: borra la corrección del tenant para ese texto. **200** con la fila
(`origen: "automatica"` o `"pendiente"`). 404 si no había corrección del tenant.

## Comando `php artisan traducciones:sincronizar {--ambito=pos} {--idioma=zh} {--solo-extraer}`

1. Extrae las claves del ámbito (research D5) y las inserta como `pendiente` si no existen; actualiza
   `vista_en` de las que sí.
2. Sincroniza el glosario del idioma con DeepL (research D7).
3. Traduce las pendientes (y las `error` con menos de 5 intentos) en lotes de ≤ 50.
4. Imprime un resumen: nuevas, traducidas, con error, pendientes, caracteres usados.

**Nunca devuelve código de error por un fallo de la API ni por cupo agotado** (el deploy sigue):
lo informa en el resumen. Devuelve error solo si falta la configuración (`DEEPL_API_KEY`) y se
pidió traducir. `--solo-extraer` no llama a la API. Sin textos por traducir no hace ninguna llamada
(ni siquiera el glosario). `--retraducir` vuelve a dejar pendiente todo el ámbito (tras cambiar el
glosario); las correcciones de los tenants no se tocan.

## Comando `php artisan pos:preparar-fuentes`

Genera la caché de métricas de Noto Sans SC en `storage/fonts` (paso del deploy, research D9).
Idempotente.

## Global JS `__t(texto, params)`

Disponible en las vistas del POS. Busca `texto` en el diccionario inyectado (`window.posI18n`) y
sustituye `:param`; si no está, devuelve `texto` (español). Mismo contrato que `__()` de Laravel.

## Helper `bilingue(string $texto, array $params = []): string`

Para las plantillas PDF (research D9): `texto` si el idioma del POS del tenant es `es`; si no,
`texto / traducción` (o solo `texto` si no hay traducción guardada). No depende del locale del
request.
