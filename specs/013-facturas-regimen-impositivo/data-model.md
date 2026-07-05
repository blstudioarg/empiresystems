# Data Model: Formulario de factura adaptado al régimen impositivo

**Feature**: 013-facturas-regimen-impositivo | **Date**: 2026-07-04

> **Sin cambios de esquema.** Esta feature no crea ni altera tablas, columnas, índices ni
> migraciones. Solo expone datos ya existentes a la capa de presentación. Esta sección documenta
> las entidades implicadas (todas preexistentes) y el **payload de vista** derivado.

## Entidades existentes implicadas (sin cambios)

### Tenant
- `regimen_impositivo`: enum `iva` | `igic` | `ipsi` (cast a `App\Enums\RegimenImpositivo`).
  Determina los tipos ofrecidos, la etiqueta y si se muestra recargo. Asignado por el super admin.

### Factura
- `regimen_impositivo`: se **congela** al emitir copiándolo de `tenant()->regimen_impositivo`
  (comportamiento existente, `FacturaController.php:333`). Inmutable tras emisión.
- Desglose vía relación `impuestos` (`FacturaImpuesto`), con `tipo_impuesto` ∈
  {`iva`,`igic`,`ipsi`,`recargo`,`irpf`} — ya renderizado en el PDF.

### FacturaLinea
- `tipo_impositivo` (DECIMAL 5,2): el % elegido, que debe pertenecer al conjunto válido del régimen
  (IVA/IGIC) o ser libre (IPSI). Validado por `TipoImpositivoValido`.

## Payload de vista (derivado, no persistido)

Estructura que el controlador pasa a la Blade e inyecta en el estado JS. No es una tabla; es un
DTO de presentación calculado en cada render a partir de `tenant()->regimen_impositivo` y
`App\Support\TiposImpositivos`.

| Campo | Tipo | Origen | Uso |
|-------|------|--------|-----|
| `regimen` | string (`iva`/`igic`/`ipsi`) | `tenant()->regimen_impositivo->value` | lógica condicional en JS |
| `regimenLabel` | string (`IVA`/`IGIC`/`IPSI`) | `strtoupper($regimen)` | cabecera de columna y desglose |
| `tiposValidos` | array<float> \| null | `TiposImpositivos::validosPara($regimen)` | opciones del `<select>`; `null` ⇒ input libre (IPSI) |
| `tipoPorDefecto` | float | primer/tipo "general" del régimen (21 IVA, 7 IGIC, 0/libre IPSI) | valor inicial de línea nueva |
| `aplicaRecargoRegimen` | bool | `$regimen === iva` | habilita cálculo/columna de recargo en preview |

### Reglas de validación (ya existentes, reafirmadas)
- El `tipo_impositivo` de cada línea DEBE pasar `TipoImpositivoValido` (que consulta
  `TiposImpositivos::validosPara(tenant()->regimen_impositivo)`). El selector del formulario debe
  ofrecer exactamente ese conjunto para que front y backend no diverjan (FR-007).
- El recargo solo se calcula si el régimen es IVA (backend: `CalculadoraFactura`; front: preview).

### Estados / transiciones
- No hay estados nuevos. El régimen de la factura sigue el ciclo existente: **mutable mientras el
  tenant lo tenga configurado → congelado en la factura al emitir**.
