# Contrato HTTP — Oportunidades

**Feature**: 028 | Rutas en `routes/web.php`, protegidas por permiso `ver-oportunidades` y global
scope de tenant.

| Método | Ruta | Nombre | Permiso | Descripción |
|--------|------|--------|---------|-------------|
| GET | `/oportunidades` | `oportunidades.index` | ver-oportunidades | Vista pipeline por etapa (recuento + importe estimado agregado) |
| GET | `/oportunidades/crear` | `oportunidades.create` | ver-oportunidades | Formulario (desde lead o cliente) |
| POST | `/oportunidades` | `oportunidades.store` | ver-oportunidades | Crea oportunidad (lead_id XOR cliente_id) |
| GET | `/oportunidades/{oportunidad}` | `oportunidades.show` | ver-oportunidades | Ficha + presupuestos asociados |
| GET | `/oportunidades/{oportunidad}/editar` | `oportunidades.edit` | ver-oportunidades | Edición |
| PUT | `/oportunidades/{oportunidad}` | `oportunidades.update` | ver-oportunidades | Actualiza datos/importe/responsable |
| PUT | `/oportunidades/{oportunidad}/etapa` | `oportunidades.etapa` | ver-oportunidades | Cambia etapa del pipeline |
| DELETE | `/oportunidades/{oportunidad}` | `oportunidades.destroy` | ver-oportunidades | Baja lógica |

## POST `/oportunidades` (`StoreOportunidadRequest`)

- `titulo` (requerido, ≤150)
- `lead_id` **XOR** `cliente_id` (exactamente uno; ambos validados contra el tenant)
- `importe_estimado` (nullable, decimal ≥0)
- `asignado_a` (nullable, exists users del tenant; por defecto hereda el del lead)
- `notas` (nullable)

**Regla**: si se informan ambos o ninguno de `lead_id`/`cliente_id`, error de validación.

## PUT `/oportunidades/{oportunidad}/etapa`

- `etapa` (requerido, in: `nueva`, `en_negociacion`, `ganada`, `perdida`)
- `motivo_perdida` (requerido **solo** si `etapa = perdida`)

**Efectos**:
- `ganada` → fija `cerrada_at`; si la oportunidad tiene `lead_id`, ejecuta `ConversorLeadCliente`
  (lead → cliente, FR-012).
- `perdida` → fija `cerrada_at` + `motivo_perdida`; bloquea nuevos presupuestos (FR-013).
- Transición a etapa terminal es irreversible desde la UI (auditado).
