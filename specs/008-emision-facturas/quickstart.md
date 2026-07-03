# Quickstart — Validación de la emisión de facturas

Guía para validar la feature end-to-end. Detalles de esquema en `data-model.md`, contrato HTTP en
`contracts/emision.md`.

## Prerrequisitos

- Entorno de la app funcionando (feature 005 en marcha: facturas en borrador ya se crean).
- Migración de `factura_eventos` aplicada.
- Un tenant con su serie ordinaria por defecto (seed) y al menos un cliente con datos fiscales
  completos (NIF, nombre/razón social, domicilio).

## Comandos

```bash
php artisan migrate            # crea factura_eventos
php artisan test --filter=FacturaEmision
php artisan test --filter=FacturaNumeracion
php artisan test --filter=FacturaInmutabilidad
php artisan test --filter=FacturaEmisionTenantIsolation
```

## Escenarios de validación

1. **Emitir un borrador (US1)**: crear una factura borrador válida → `POST /facturas/{id}/emitir`.
   Esperado: `estado = emitida`, `numero = 1`, `numero_completo = F-2026-0001` (según formato),
   `fecha_expedicion = hoy`, y los totales **idénticos** a los del borrador.

2. **Correlativo sin huecos (US1/SC-001)**: emitir dos borradores de la misma serie. Esperado:
   `0001` y `0002`, consecutivos, sin huecos ni duplicados.

3. **Reinicio anual (Clarifications/FR-002a)**: con una factura emitida del año anterior en la
   serie, emitir la primera del año en curso. Esperado: `numero = 1` (no continúa la del año previo).

4. **Concurrencia (SC-001)**: emitir dos facturas de la misma serie en paralelo (test que simula
   dos transacciones). Esperado: números distintos y consecutivos; nunca el mismo número dos veces.

5. **Inmutabilidad (US2/SC-002)**: sobre una factura emitida, intentar `edit`/`update` (→ 403),
   `destroy` (→ 403) y re-`emitir` (→ 422 sin nuevo número). Esperado: la factura permanece intacta
   con su número original.

6. **Validación previa (FR-011)**: intentar emitir un borrador sin líneas o con cliente sin NIF.
   Esperado: rechazo (422/flash error), la factura sigue en borrador, sin consumir número.

7. **Visibilidad (US3/SC-004)**: tras emitir, comprobar que listado, detalle y PDF muestran
   `F-2026-0001` en lugar de "Borrador"; un borrador sigue mostrando "Borrador".

8. **Evento append-only (US3/FR-013/SC-006)**: tras emitir, existe exactamente 1 evento
   `tipo_evento = emitida` para esa factura, con su `ocurrido_at`.

9. **Aislamiento multi-tenant (SC-005)**: emitir en el tenant A no cambia el próximo número del
   tenant B ni expone/permite emitir facturas de B.

## Criterio de aceptación

Todos los tests de los cuatro archivos en verde y los 9 escenarios comprobados manualmente o por
test. Los tests de numeración, inmutabilidad y aislamiento se escriben **antes** de implementar
(Principio IV) y deben fallar primero.
