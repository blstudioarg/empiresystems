# Contract: Rutas del catálogo de artículos

Mismo patrón que `clientes` (recurso web con respuesta dual Blade/JSON según `Accept`/`wantsJson`).
Todas las rutas requieren sesión autenticada con tenant activo (middleware ya existente en el
grupo de rutas de `clientes`).

| Método | Ruta | Acción | Notas |
|--------|------|--------|-------|
| GET | `/articulos` | `ArticuloController@index` | HTML: vista `articulos.index`. JSON (`wantsJson`): `{ data: Articulo[], totales: { total, productos, servicios } }` para alimentar el DataTable, igual que `clientes`. |
| POST | `/articulos` | `ArticuloController@store` | Valida con `StoreArticuloRequest`. HTML: redirect con flash `success`. JSON: `201` con mensaje. |
| PUT/PATCH | `/articulos/{articulo}` | `ArticuloController@update` | `$articulo` recibido como `string`, resuelto manualmente con `Articulo::findOrFail()` dentro del método (no binding implícito — ver research.md #3). Valida con `UpdateArticuloRequest`. |
| DELETE | `/articulos/{articulo}` | `ArticuloController@destroy` | Soft delete. Mismo patrón de resolución manual. |

## Forma de `data[]` en la respuesta JSON de `index`

Cada elemento incluye, como mínimo, los campos necesarios para pintar la tabla y los formularios
de edición (igual que `clientes`): `id`, `tipo`, `tipo_label`, `sku`, `nombre`, `descripcion`,
`unidad`, `precio`, `tipo_impositivo`, `gestion_stock`, `stock_actual`, `stock_minimo`,
`irpf_defecto`, `aplica_recargo_equivalencia`, `activo`, `update_url`, `delete_url`.

## Forma de `totales`

```json
{ "total": 0, "productos": 0, "servicios": 0 }
```

## Validación de `tipo_impositivo` (StoreArticuloRequest / UpdateArticuloRequest)

- `required`, `numeric`, `between:0,100`.
- Regla adicional (custom rule o `Rule::in()` dinámico resuelto en `prepareForValidation`/`rules()`
  a partir de `tenant()->regimen_impositivo`): el valor MUST estar en
  `TiposImpositivos::validosPara($regimen)` cuando ese método no devuelva `null` (caso `iva`/
  `igic`); para `ipsi` basta la validación de rango. Mensaje de error: "El tipo impositivo indicado
  no es válido para el régimen fiscal de tu empresa (IVA/IGIC/IPSI)."
