# Data Model: POS — Facturas simplificadas (tickets)

Fase 1. **No hay cambios de esquema en `facturas`.** El módulo reutiliza tablas y modelos existentes;
sólo se añade una clave de configuración y una fila de serie sembrada.

## Entidades reutilizadas (sin migración)

### Factura (`facturas`) — nueva instancia con `tipo = simplificada`
- **Reutiliza** todas las columnas actuales. Relevantes para el ticket:
  - `tipo = simplificada` (enum ya existente en `App\Enums\TipoFactura`).
  - `serie_id` → serie "S".
  - `cliente_id`, `cliente_nombre`, `cliente_razon_social`, `cliente_nif`, `cliente_direccion`,
    `cliente_cp`, `cliente_ciudad`, `cliente_provincia`, `cliente_pais`: **nullable**.
    - **Simple**: todos vacíos/null.
    - **Cualificada**: informados (al menos NIF + domicilio), igual que una ordinaria, como snapshot
      congelado.
  - `regimen_impositivo`, `aplica_recargo`: congelados al emitir (copiados del tenant/cliente).
  - `base_total`, `cuota_impuesto_total`, `cuota_recargo_total`, `total`: calculados server-side.
  - `es_rectificativa = false`, `factura_rectificada_id = null` (rectificativa simplificada fuera de
    alcance).
  - `estado`: `borrador` → `emitida` (inmutable). En el flujo POS típico se crea y emite en la misma
    acción, pero el estado intermedio sigue siendo el mismo del motor de la 008.
- **Reglas de validación** (aplicadas en `StoreTicketRequest` + `RegistroTicket`):
  - Al menos 1 línea con `cantidad > 0` y `precio_unitario ≥ 0`, `base > 0` agregada.
  - Receptor **opcional**; si se informa parte del receptor para cualificada, exigir coherencia mínima
    (NIF + domicilio) según se defina en el request.
  - Importe bruto (`base_total + cuota_impuesto_total + cuota_recargo_total`) **≤ tope aplicable**
    (bloqueo duro).
- **Transiciones de estado**: idénticas a la ordinaria (motor 008). Emitida ⇒ inmutable (no edit, no
  delete, no re-emitir).

### Serie (`series`) — fila sembrada "S"
- `codigo = 'S'`, `tipo = simplificada`, `ejercicio = null`, `proximo_numero = 1`,
  `formato = '{serie}-{anio}-{numero:0000}'`, `activa = true`.
- Contador por (serie, año) con reinicio anual y bloqueo (reutiliza `NumeradorFacturas`).
- Aislada por `tenant_id` (`BelongsToTenant`).

### FacturaEvento (`factura_eventos`) — append-only
- Registra el evento `emitida` del ticket, igual que en la ordinaria. Sin cambios de esquema.

### Configuracion (`configuraciones`) — nueva clave
- **Clave**: `factura.simplificada_tope_ampliado`
- **Grupo**: `facturacion`
- **Tipo**: `boolean`
- **Default**: `false` (tope 400 €). `true` ⇒ tope 3.000 € (sector con tope ampliado).
- **Resolución**: tenant activo → fallback global, cacheable (patrón existente). Encapsulada en
  `App\Support\TopeSimplificada` (constantes `CLAVE`, `TOPE_BASE = 400.00`, `TOPE_AMPLIADO = 3000.00`).
- **Los 3 pasos** de alta de config (seeder + catálogo en `docs/03-modelo-datos.md` + input en
  `_tab_facturacion.blade.php`) son tareas de la feature.

## Reglas derivadas (no persistidas)

- **Tope aplicable** = `TopeSimplificada::topePara(tenant)` → 400.00 ó 3000.00.
- **Es cualificada** = el ticket tiene `cliente_nif` (o snapshot de receptor) informado; es una vista de
  presentación, no una columna nueva.
- **Importe para el tope** = `base_total + cuota_impuesto_total + cuota_recargo_total` (impuestos
  incluidos), comparación inclusiva `≤`.

## Aislamiento multi-tenant

Todas las entidades (`Factura`, `Serie`, `Configuracion`, `FacturaEvento`) llevan `tenant_id` y
`BelongsToTenant`/`TenantScope`. El listado POS, la numeración de "S" y la resolución del tope operan
siempre bajo el tenant activo. Tests de aislamiento con ≥2 tenants (Principio I).
