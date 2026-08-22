# Contratos HTTP — Módulo de Cobros

Dos rutas nuevas. Tres rutas existentes que se reutilizan sin cambiar su forma (solo cambian de
grupo de permiso).

---

## Rutas nuevas

Ambas en `routes/web.php`, dentro de `Route::middleware('can:ver-cobros')->group(...)`.

### `GET /cobros` → `CobroController@index`

Doble modo, igual que `LogActividadController@index`:

- **Sin `draw`** en la query ⇒ devuelve la vista `cobros.index`.
- **Con `draw`** (petición del DataTable) ⇒ devuelve JSON.

**Query params** (todos opcionales; los aporta `ajax.data`):

| Param | Tipo | Notas |
|---|---|---|
| `draw`, `start`, `length` | int | Protocolo DataTables |
| `search[value]` | string | Busca en `numero_completo` y en el nombre/razón social del cliente (D9) |
| `order[0][column]`, `order[0][dir]` | int / `asc`\|`desc` | Solo se aceptan columnas de la lista blanca `COLUMNAS_ORDENABLES`; cualquier otra cae al defecto (`fecha_vencimiento` asc, nulos al final) |
| `estado_cobro` | `pendiente`\|`parcial`\|`cobrada` | Vacío = todas |
| `cliente_id` | int | |
| `serie_id` | int | |
| `solo_vencidas` | `0`\|`1` | Se combina con AND sobre `estado_cobro` (D10) |
| `preset` | `mes`\|`trimestre`\|`anio`\|`personalizado` | Resuelto por `RangoFechas::desdePeticion()` |
| `desde`, `hasta` | `Y-m-d` | Solo con `preset=personalizado`. Rango inválido ⇒ mes en curso, sin error (D5) |

**Respuesta 200** (`application/json`):

```json
{
  "draw": 3,
  "recordsTotal": 128,
  "recordsFiltered": 12,
  "data": [
    {
      "id": 91,
      "identificador": "FA2026/0042",
      "cliente": "Construcciones Pérez S.L.",
      "serie": "FA2026",
      "fecha_expedicion": "2026-06-01",
      "fecha_vencimiento": "2026-07-01",
      "total_cobrable": "1210.00",
      "monto_cobrado": "500.00",
      "saldo_pendiente": "710.00",
      "estado_cobro": "parcial",
      "vencida": true,
      "dias_retraso": 51,
      "es_rectificada": false,
      "total_nominal": "1210.00",
      "modalidad_rectificacion": null,
      "pago_url": "/facturas/91/pagos",
      "cobros_url": "/facturas/91/pagos",
      "pdf_url": "/facturas/91/pdf"
    }
  ]
}
```

Notas del contrato:

- Los importes viajan como **string de 2 decimales con punto** (`number_format($v, 2, '.', '')`),
  idéntico a lo que ya hace `PagoController`. Nunca un `decimal:2` de Eloquent crudo.
- `dias_retraso` es `null` cuando la factura no está vencida o no tiene vencimiento.
- `fecha_vencimiento` puede ser `null`.
- `pago_url` es `null` si `saldo_pendiente <= 0` (FR-021: sin acción "Registrar cobro").
- `pdf_url` es `null` si el usuario **no** tiene `ver-facturas` (D8); el JS omite ese `<li>`.
- `es_rectificada` / `total_nominal` / `modalidad_rectificacion` alimentan el aviso de contexto
  que ya existe en el modal (`#cobroContextoRectificada`), para que `total_cobrable ≠ total` no
  parezca un error.

**403** si el usuario no tiene `ver-cobros`.

---

### `GET /cobros/resumen` → `CobroController@resumen`

Siempre JSON. Acepta los **mismos** params de rango y filtros que el listado, para que las cards
y la tabla no puedan desincronizarse.

**Respuesta 200**:

```json
{
  "pendiente_total": "18430.55",
  "cobrado_periodo": "7250.00",
  "vencido_total": "3110.20",
  "facturas_pendientes": 14,
  "periodo": { "preset": "mes", "desde": "2026-08-01", "hasta": "2026-08-21", "etiqueta": "01/08/2026 - 21/08/2026" }
}
```

`pendiente_total`, `vencido_total` y `facturas_pendientes` son instantáneas a hoy e **ignoran** el
rango; `cobrado_periodo` sí lo respeta (D4/FR-009). El bloque `periodo` es lo que la vista pinta
junto a las cards para que el criterio quede explícito (FR-008).

**403** si el usuario no tiene `ver-cobros`.

---

## Rutas existentes reutilizadas (sin cambios de forma)

Cambian de `can:ver-facturas` a `can:ver-cobros` (research D7), con la migración de datos que
concede `ver-cobros` a todo rol personalizado que ya tuviera `ver-facturas`.

| Ruta | Nombre | Contrato |
|---|---|---|
| `GET /facturas/{factura}/pagos` | `facturas.pagos.index` | `{ saldo_pendiente, estado_cobro, data: [{ id, fecha, importe, metodo, referencia, vigente, anular_url }] }` |
| `POST /facturas/{factura}/pagos` | `facturas.pagos.store` | 201 `{ message, id, saldo_pendiente, estado_cobro }` · 422 `{ message }` ante importe inválido |
| `POST /pagos/{pago}/anular` | `pagos.anular` | 200 `{ message, saldo_pendiente, estado_cobro }` · 422 `{ message }` |

**No se toca ni una línea de `PagoController` ni de `RegistroPagos`.** Si al implementar aparece
la tentación de cambiarlos, es señal de que se está introduciendo lógica nueva y hay que parar
(FR-004).

`POST /pagos/{pago}/anular` no serializa formulario ⇒ el JS **debe** enviar el header
`X-CSRF-TOKEN` leído del `<meta name="csrf-token">`, o devuelve 419 "CSRF token mismatch"
(guía de front, sección "CSRF en peticiones AJAX sin formulario").

---

## Contrato de permisos y menú

| Elemento | Valor |
|---|---|
| Permiso | `ver-cobros`, etiqueta "Cobros", módulo "Facturas" (`CatalogoPermisos::PERMISOS`) |
| Entrada de menú | `['clave' => 'cobros', 'etiqueta' => 'Cobros', 'icono' => null, 'ruta' => 'cobros.index', 'permiso' => 'ver-cobros', 'hijos' => []]`, como tercer hijo del grupo `facturas` de `CatalogoMenu::CATALOGO` |
| Seeder | `php artisan db:seed --class=PermisosSeeder` tras el alta (siembra la clave y sincroniza el rol "Administrador" de cada tenant) |
| Migración de datos | Concede `ver-cobros` a los roles **personalizados** de cada tenant que ya tuvieran `ver-facturas` (fijando el team de Spatie con `setPermissionsTeamId()` y `forgetCachedPermissions()` al terminar) |
| Contabilidad de tests | `CatalogoPermisosTest` (`claves()` y `clavesUsuarioBase()` suben en 1) y `RutasPermisosTest::mapaRutas()` (alta de `/cobros`) |
