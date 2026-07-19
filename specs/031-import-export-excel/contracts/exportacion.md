# Contrato: Exportación

**Feature**: 031-import-export-excel

## `POST /exportar/{modulo}`

Genera y descarga un `.xlsx` con las filas indicadas.

**Por qué `POST` y no `GET`**: la petición transporta la lista de IDs visibles en el listado
(decisión D1). Con 10.000 filas la query string superaría con creces el límite práctico de longitud
de URL. Además, mantener la exportación fuera de un `GET` evita que una URL con datos de negocio
quede en el historial del navegador y en los logs de acceso del servidor.

**Módulos válidos**: `clientes`, `articulos`, `facturas`, `albaranes`, `leads`.

**Autorización**: la ruta vive dentro del grupo `can:ver-{modulo}` ya existente en
`routes/web.php`. Sin permiso → `403` (FR-004). No se crean permisos nuevos.

### Petición

```json
{
  "ids": [12, 45, 46, 91]
}
```

| Campo | Tipo | Reglas |
|---|---|---|
| `ids` | `int[]` | `required`, `array`, `min:1`. Cada elemento `integer`. |

`ids` son los identificadores de las filas que sobreviven a la búsqueda, los filtros y el orden
activos en el DataTable, obtenidos en el cliente con
`table.rows({ search: 'applied' }).data()`.

### Respuesta — 200

Descarga binaria.

| Cabecera | Valor |
|---|---|
| `Content-Type` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| `Content-Disposition` | `attachment; filename="clientes-2026-07-18.xlsx"` (FR-007) |

Contenido: una fila de cabecera con las etiquetas en español de la definición del módulo, seguida
de una fila por registro, en el mismo orden en que llegaron los IDs (que es el orden que el
usuario ve en pantalla).

### Respuestas de error

| Código | Caso |
|---|---|
| `403` | El usuario carece del permiso `ver-{modulo}` (FR-004). |
| `404` | `{modulo}` no corresponde a ninguna definición registrada. |
| `422` | `ids` ausente, vacío o con elementos no enteros. |

### Caso: ninguna fila coincide

Si tras aplicar el scope de tenant no queda ningún registro, la respuesta **sigue siendo `200`**
con un fichero válido que contiene solo la fila de cabecera, y el cliente muestra un toast de
aviso (FR/edge case: "listado sin filas"). No es un error: el usuario pidió exportar lo que estaba
viendo, y lo que estaba viendo era nada.

---

## Garantía de aislamiento multi-tenant (Principio I, FR-003)

**Esta es la parte crítica del contrato.**

El backend resuelve los IDs recibidos así:

```
Modelo::whereIn('id', $ids)   →  pasa por el TenantScope de BaseModel
```

Un cliente que manipule el payload e incluya IDs pertenecientes a otro tenant **no recibe esas
filas**: el `TenantScope` las elimina de la consulta antes de que lleguen al fichero. El cliente
propone qué filas quiere; el servidor decide cuáles puede ver.

**Prohibido explícitamente** en la implementación de este endpoint:

- `withoutGlobalScopes()` / `withoutGlobalScope(TenantScope::class)`
- `DB::table(...)` o cualquier consulta cruda que no pase por el modelo Eloquent
- Cualquier `where('tenant_id', $request->input(...))` que tome el tenant del cliente

Si alguien "optimiza" esta consulta saltándose el scope, el endpoint se convierte en una fuga de
datos entre tenants a petición del atacante. El test de aislamiento
(`ExportacionExcelTest::test_no_exporta_filas_de_otro_tenant_aunque_se_manden_sus_ids`) existe
específicamente para atrapar esa regresión, y se escribe **antes** de la implementación
(Principio IV).

---

## Registro de actividad (FR-008)

Cada exportación con resultado `200` registra, vía `RegistradorActividad`:

| Campo | Valor |
|---|---|
| Acción | `AccionLogActividad::Exportacion` (caso nuevo, ver D6) |
| Entidad | La `entidadLog()` de la definición del módulo |
| ID entidad | `null` (la exportación es sobre un conjunto, no sobre un registro) |
| Descripción | `"Exportó {N} {módulo} a Excel"` |

El recuento `N` es el de filas **efectivamente escritas** en el fichero, no el de IDs recibidos.
La diferencia entre ambos es exactamente la señal de un intento de acceso cruzado entre tenants,
y por eso se registra el número real.
