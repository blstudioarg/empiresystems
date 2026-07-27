# Data Model: Perfil del cliente

No se agregan tablas ni columnas nuevas. Esta feature solo declara relaciones Eloquent inversas
que hoy faltan, sobre columnas `cliente_id` ya existentes, y define agregados calculados
(no persistidos) para el resumen financiero y la línea de tiempo.

## Entidades existentes afectadas

### Cliente (`app/Models/Cliente.php`)

Sin cambios de esquema. Se agregan las siguientes relaciones (no existían relaciones inversas
hacia ninguna de estas cuatro entidades):

| Relación | Tipo | Modelo relacionado | Clave foránea | Notas |
|---|---|---|---|---|
| `facturas()` | `HasMany` | `Factura` | `cliente_id` | Excluye facturas `tipo = simplificada` en el uso del perfil (mismo criterio que `FacturaController::index`), aplicado en el controller, no en la relación (la relación expone todas; el filtro de "no simplificadas" es una decisión de presentación del perfil, no una regla del dato). |
| `presupuestos()` | `HasMany` | `Presupuesto` | `cliente_id` | Sin filtro adicional. |
| `albaranes()` | `HasMany` | `Albaran` | `cliente_id` | Sin filtro adicional. |
| `oportunidades()` | `HasMany` | `Oportunidad` | `cliente_id` | Sin filtro adicional. |

Todas heredan `TenantScope` vía `BelongsToTenant` de cada modelo relacionado — no requieren
`where('tenant_id', ...)` manual (Principio I).

### Factura, Presupuesto, Albaran, Oportunidad

Sin cambios de esquema ni de relaciones propias (ya tienen `cliente()` `BelongsTo` definida). Se
reutilizan sus campos existentes para poblar las pestañas:

- **Factura**: `numero`, `fecha_expedicion`, `total`, `estadoCobro()`, `saldoPendiente()`,
  `fecha_vencimiento`.
- **Presupuesto**: `numero`, `fecha_emision`, `total`, `estado` (`EstadoPresupuesto`).
- **Albaran**: `numero`, `fecha_entrega`, `total`, `estado` (`EstadoAlbaran`).
- **Oportunidad**: `titulo`, `etapa` (`EtapaOportunidad`), `importe_estimado`, `created_at`.

## Agregados calculados (no persistidos)

### Resumen financiero del cliente

Calculado en `ClienteController::show()` a partir de `Cliente->facturas` (no simplificadas):

| Campo | Cálculo |
|---|---|
| `total_facturado` | Suma de `totalCobrable()` de todas las facturas no anuladas del cliente. |
| `pendiente_cobro` | Suma de `saldoPendiente()` de todas las facturas con saldo pendiente > 0. |
| `facturas_vencidas_cantidad` / `facturas_vencidas_importe` | Cantidad e importe (`saldoPendiente()`) de facturas con saldo pendiente > 0 y `fecha_vencimiento` anterior a hoy. |
| `ticket_medio` | `total_facturado / cantidad de facturas no anuladas` (0 si no hay facturas). |

### Línea de tiempo de actividad (Timeline)

Colección en memoria (no query única), construida combinando hasta 10 elementos más recientes de
cada una de las 4 relaciones ya cargadas (ver `research.md` §2), normalizados a:

```text
{
  fecha: Carbon,
  tipo: 'factura' | 'presupuesto' | 'albaran' | 'oportunidad',
  etiqueta: string,   // p.ej. "Factura F-2026-014 emitida"
  url: string,        // link al detalle del documento
}
```

Ordenada por `fecha` descendente, cortada a los 10 elementos más recientes del conjunto
combinado. Solo incluye tipos para los que el usuario tiene el permiso `ver-*` correspondiente.

## Validaciones y reglas de visibilidad

- El perfil solo es accesible si el cliente pertenece al tenant activo (heredado de
  `BelongsToTenant` + resolución manual `Cliente::findOrFail($id)` dentro del controller, mismo
  patrón que `update`/`destroy` de `ClienteController`).
- Cada pestaña de documento (facturas/presupuestos/albaranes/oportunidades) solo se puebla si
  `Auth::user()->can('ver-<modulo>')`; si no, el paginador correspondiente es `null` y la vista no
  renderiza esa pestaña ni consulta esos datos.
- Cada acceso rápido de creación solo se muestra si `Auth::user()->can('ver-<modulo>-crear')` (o
  el permiso `ver-<modulo>` cuando el módulo no distingue un permiso de creación separado, ver
  `research.md` §3).
