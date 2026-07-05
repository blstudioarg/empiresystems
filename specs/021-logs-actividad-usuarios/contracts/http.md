# HTTP Contracts: Logs de actividad de usuarios

Ruta bajo el grupo `['tenant.context', 'auth']` de `routes/web.php` (sin restricción de rol —
Assumptions de la spec: visible para `usuario`, `admin` y `super_admin` operando en su tenant).

## GET `/logs` → `LogActividadController@index`

Vista HTML (US1) o, con `Accept: application/json` / parámetros de DataTables, respuesta JSON
paginada en servidor (D1).

**Nombre de ruta**: `logs.index`

**Request (JSON, protocolo DataTables server-side)**:

| Parámetro | Tipo | Notas |
|---|---|---|
| `draw` | int | Se devuelve tal cual, para que DataTables descarte respuestas fuera de orden. |
| `start` | int | Offset (equivalente a `skip`). |
| `length` | int | Tamaño de página (equivalente a `take`); `-1` no se soporta (sin "ver todos"). |
| `search[value]` | string, opcional | Coincidencia parcial (case-insensitive) contra `usuario_nombre`, `descripcion` y el label de `accion`. |
| `order[0][column]` + `columns[i][data]` | int/string | Columnas ordenables: `ocurrido_at` (default, desc), `usuario_nombre`, `accion`. `descripcion` no es ordenable (igual criterio que la columna "Acciones" en `facturas-datatable.init.js`). |

**Respuesta 200 (JSON)**:

```json
{
  "draw": 3,
  "recordsTotal": 482,
  "recordsFiltered": 12,
  "data": [
    {
      "fecha": "04/07/2026 10:32",
      "usuario_nombre": "Federico Gundel",
      "accion": "modificacion",
      "accion_label": "Modificación",
      "resultado": "exito",
      "resultado_label": "Éxito",
      "ip_origen": "203.0.113.10",
      "entidad_tipo": "cliente",
      "entidad_label": "Cliente",
      "descripcion": "Modificó el cliente Acme S.L."
    }
  ]
}
```

`resultado`/`resultado_label` distinguen acceso autorizado (`exito`) de denegado (`fallo`) —
registro de accesos RGPD/LOPDGDD. `ip_origen` es la IP capturada en el servidor al registrar el
evento (nunca la envía el cliente). Un intento de login fallido aparece con `accion: "login"`,
`resultado: "fallo"` y `usuario_nombre` = email intentado (sin usuario asociado).

- `recordsTotal`: total de filas del tenant sin filtrar (`logs_actividad` acotado por
  `tenant_id` del usuario autenticado, nunca de otro tenant — Principio I / US2).
- `recordsFiltered`: total tras aplicar `search[value]`.
- El orden por defecto (sin `order` explícito) es `ocurrido_at` descendente.
- Ninguna combinación de `start`/`length`/`search`/`order` puede exponer filas de otro tenant: el
  filtro por tenant se aplica **antes** de paginar/buscar/ordenar, no como post-filtro.

**Request (HTML, primera carga de la vista)**: sin parámetros — solo renderiza el layout con la
tabla vacía; los datos se piden vía AJAX al inicializar la datatable (mismo patrón que
`facturas.index`/`usuarios.index`).

**Respuestas de error**:

| Situación | HTML | JSON |
|---|---|---|
| Sin sesión activa | 302 → `/login` | 302 → `/login` (middleware `auth`) |
| Tenant inactivo / dominio no resuelto | Ver `SetTenantContext` (404 o redirect a login) | ídem |

No hay endpoints de escritura: `logs_actividad` no expone `store`/`update`/`destroy` (FR-007). La
escritura ocurre exclusivamente desde `RegistradorActividad`, invocado internamente por otros
controladores y por `LogAuthenticationActivity` (ver `data-model.md`).
