# Contrato HTTP — Cuentas bancarias

Rutas dentro del grupo autenticado + `tenant.context` (mismo grupo que `clientes`, `articulos`,
`configuracion`). Todas operan sobre el tenant activo; ninguna expone cuentas de otro tenant.

## Rutas

| Método | URI | Nombre | Acción | Descripción |
|--------|-----|--------|--------|-------------|
| GET | `/configuracion` | `configuracion.show` | `ConfiguracionController@show` | Tab "Cuentas bancarias" lista las cuentas del tenant (incl. inactivas) |
| GET | `/cuentas-bancarias` | `cuentas-bancarias.index` | `CuentaBancariaController@index` | (opcional) JSON/datatable de cuentas del tenant |
| POST | `/cuentas-bancarias` | `cuentas-bancarias.store` | `CuentaBancariaController@store` | Alta de cuenta |
| PUT/PATCH | `/cuentas-bancarias/{id}` | `cuentas-bancarias.update` | `CuentaBancariaController@update` | Edición de cuenta |
| DELETE | `/cuentas-bancarias/{id}` | `cuentas-bancarias.destroy` | `CuentaBancariaController@destroy` | Baja lógica (`activa=false` + soft delete) |
| POST | `/cuentas-bancarias/{id}/restaurar` | `cuentas-bancarias.restore` | `CuentaBancariaController@restore` | Reactivar (`restore()` + `activa=true`) |

> `{id}` se resuelve manualmente con `CuentaBancaria::findOrFail($id)` en el cuerpo del método
> (no type-hint), por el pitfall de route-model binding tenant-scoped ([[project-tenant-route-binding]]).

## Payloads

### POST `/cuentas-bancarias` (store)

Request (form):

| Campo | Regla |
|-------|-------|
| `banco_id` | required, exists:bancos,id |
| `alias` | required, string, max:255 |
| `iban` | required, string, `IbanValido` (ISO 13616 + mod-97) |
| `titular` | required, string, max:255 |

Respuestas:
- **Éxito (redirect)**: `redirect()->route('configuracion.show')->with('success', 'Cuenta bancaria creada correctamente.')` (o JSON `{ "message": ... }` si `wantsJson`).
- **Validación fallida**: 422 con errores por campo (`iban` inválido → mensaje "El IBAN no es válido.").

### PUT/PATCH `/cuentas-bancarias/{id}` (update)

Mismos campos que store. `activa` puede incluirse (boolean) si se permite alternar estado desde el
formulario de edición; alternativamente el toggle activa/inactiva se hace vía destroy/restore.

Respuestas: análogas a store con mensaje "Cuenta bancaria actualizada correctamente."

### DELETE `/cuentas-bancarias/{id}` (destroy)

Sin body. Marca `activa=false` y hace soft delete. Respuesta: flash `success` "Cuenta bancaria
desactivada."

### POST `/cuentas-bancarias/{id}/restaurar` (restore)

Sin body. `restore()` + `activa=true`. Respuesta: flash `success` "Cuenta bancaria reactivada."

## Integración en facturas (US3)

- `GET /facturas/crear` y `GET /facturas/{factura}/editar`: la vista recibe
  `$cuentasBancarias` = cuentas del tenant `where('activa', true)` (no soft-deleted). El selector se
  muestra/oculta según `forma_pago`.
- `POST /facturas` y `PUT/PATCH /facturas/{factura}`: aceptan `cuenta_bancaria_id` (nullable,
  `exists` en cuentas del tenant). El backend compone el snapshot `cuenta_bancaria_banco/iban/
  titular` SOLO si `forma_pago = transferencia` y hay `cuenta_bancaria_id`; en caso contrario deja
  los cuatro campos `null`. `StoreFacturaRequest`/`UpdateFacturaRequest` añaden la regla del campo.
- El PDF (`facturas/pdf.blade.php`) renderiza el bloque bancario si el snapshot está presente.

## Aislamiento (aplicable a todas las rutas)

- Toda consulta de `cuentas_bancarias` pasa por el global scope de tenant (`BelongsToTenant`).
- `banco_id` referencia el catálogo central `bancos` (compartido; no filtra por tenant).
- Un id de cuenta de otro tenant → `findOrFail` lanza 404 (el scope lo excluye del resultado).
