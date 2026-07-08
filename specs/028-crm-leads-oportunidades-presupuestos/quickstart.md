# Quickstart / Validación — Gestión de Clientes CRM (028)

Guía para validar de extremo a extremo el embudo Lead → Oportunidad → Presupuesto → Factura.
Presupone el proyecto corriendo con al menos un tenant activo y usuarios con el permiso adecuado.

## Prerrequisitos

- Migraciones aplicadas: `php artisan migrate` (crea `leads`, `lead_notas`, `oportunidades`,
  `presupuestos`, `presupuesto_lineas`).
- Seeder de permisos re-ejecutado para registrar `ver-leads`, `ver-oportunidades`,
  `ver-presupuestos` y asignarlos al rol Administrador (`ProvisionadorRoles` / seeder de permisos).
- Al menos 2 usuarios comerciales en el tenant (para probar el reparto round-robin).
- Catálogo de `articulos` con distintos tipos impositivos (para validar el cálculo).

## Escenario 1 — Leads: alta manual, importación y asignación (US1)

1. Alta manual: `/leads/crear` → crear un lead con nombre + email. **Esperado**: estado `nuevo`,
   asignado según la estrategia vigente; visible en `/leads`.
2. Duplicado: crear otro lead con el mismo email. **Esperado**: rechazo con error "duplicado"
   (FR-004), no se crea.
3. Importación: en `/leads/importar` subir un CSV de N filas (incluir 1 fila sin email/teléfono y 1
   duplicada). **Esperado**: resumen `importados = N-2`, `rechazadas` lista las 2 con su motivo; las
   válidas existen (FR-003).
4. Round-robin: con la estrategia `round_robin` y 3 comerciales, importar 9 leads. **Esperado**:
   ~3 por comercial (SC-002).
5. Sin asignar: quitar todos los comerciales de la regla e importar 1 lead. **Esperado**: queda en
   la bandeja "sin asignar" (`asignado_a = null`), no se pierde (FR-006).

## Escenario 2 — Oportunidades y pipeline (US2)

1. Desde un lead cualificado: `/oportunidades/crear` vinculando el lead. **Esperado**: etapa `nueva`,
   responsable heredado.
2. Mover por el pipeline: `PUT .../etapa` a `en_negociacion`, luego `ganada`. **Esperado**: al ganar,
   el lead pasa a `cliente` (FR-012), `cerrada_at` fijada; verificar `leads.convertido_a_cliente_id`.
3. Perder otra oportunidad con `motivo_perdida`. **Esperado**: cerrada como `perdida`, no admite
   nuevos presupuestos (FR-013).
4. Vista pipeline `/oportunidades`: recuento e importe estimado agregados por etapa (FR-014).

## Escenario 3 — Presupuestos y conversión a factura (US3, crítico)

1. Crear presupuesto en `/presupuestos/crear` para una oportunidad, con **varias líneas de distintos
   tipos impositivos**. **Esperado**: totales (base, impuestos por tipo, total) calculados en
   backend; estado `borrador`; `numero` de presupuesto propio (no de serie de factura).
2. **Validación clave (SC-003)**: crear una factura borrador con exactamente las mismas líneas y
   comparar totales. **Esperado**: iguales al céntimo, para IVA, IGIC e IPSI (ver test automatizado).
3. Editar el presupuesto en borrador (añadir línea). **Esperado**: totales recalculados; sigue
   editable.
4. `PUT .../estado` a `enviado`, luego `aceptado`. **Esperado**: al aceptar, la oportunidad asociada
   pasa a `ganada`; se habilita "convertir a factura".
5. `POST .../convertir`. **Esperado**: se crea una **factura borrador** con las mismas líneas e
   importes, enlazada al presupuesto; el presupuesto queda `facturado` (solo lectura); **no** se ha
   consumido numeración de serie ni Verifactu todavía.
6. Emitir esa factura por el flujo normal de facturas. **Esperado**: ahora sí consume numeración
   correlativa, genera Verifactu y mueve stock (motor existente).
7. **No doble facturación (SC-005)**: intentar convertir el presupuesto otra vez. **Esperado**:
   bloqueado (estado `facturado` terminal).

## Comandos de validación automatizada

```bash
# Tests de aislamiento multi-tenant (Principio I / SC-006)
php artisan test --filter=LeadTenantScope
php artisan test --filter=OportunidadTenantScope
php artisan test --filter=PresupuestoTenantScope

# Igualdad de totales presupuesto == factura para los 3 regímenes (SC-003)
php artisan test --filter=PresupuestoCalculoIgualFactura

# Conversión y guarda de doble facturación (SC-005)
php artisan test --filter=PresupuestoConversionFactura

# Importación de leads: válidas/rechazadas/duplicados (FR-003/FR-004)
php artisan test --filter=ImportadorLeads
```

**Criterio de aceptación global**: los tests anteriores en verde + los 3 escenarios manuales
completados cubren FR-001..FR-026 y SC-001..SC-007. La categoría "Gestión de Clientes" del Kit
Digital queda cubierta en sus requisitos funcionales mínimos (SC-007).
