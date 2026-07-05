# Research: Formulario de factura adaptado al régimen impositivo

**Feature**: 013-facturas-regimen-impositivo | **Date**: 2026-07-04

No quedaban `NEEDS CLARIFICATION` en la spec. Esta investigación consolida las decisiones técnicas
sobre el código existente que la implementación reutiliza.

## Decisión 1 — Fuente de verdad de los tipos válidos por régimen

- **Decision**: Reutilizar `App\Support\TiposImpositivos::validosPara(RegimenImpositivo)` como única
  fuente de los tipos por régimen, tanto para renderizar el selector en Blade como para el default.
- **Rationale**: Ya existe y ya la usa `TipoImpositivoValido` (validación backend). Si el formulario
  leyera de otra lista, front y validación podrían divergir (FR-007). Una sola fuente evita eso.
- **Alternatives considered**: Hardcodear las listas en Blade/JS (rechazado: duplica y desincroniza);
  crear una tabla de tipos en BD (rechazado por Principio V/YAGNI — la lista es estática y normativa).

## Decisión 2 — Cómo llega el régimen al JS

- **Decision**: El controlador pasa a la vista un pequeño payload de régimen
  (`regimen`, `regimenLabel`, `tiposValidos` (array o `null` para IPSI), `aplicaRecargoRegimen`
  = `regimen === iva`). La vista lo inyecta en `window.facturaFormState` / `window.posState`
  vía `@json`, igual que ya hace con `articulosUrl`, `lineasIniciales`, etc.
- **Rationale**: Mantiene el patrón existente de inyección de estado (`create.blade.php:585`,
  `pos/create.blade.php:228`). El JS ya lee de `state`; solo se añaden campos.
- **Alternatives considered**: Exponer un endpoint AJAX de tipos (rechazado: innecesario, el dato es
  estático y ya disponible en el render); leer `data-*` del DOM (más frágil que el state central).

## Decisión 3 — Selector cerrado vs input libre por régimen

- **Decision**: Para IVA e IGIC (listas cerradas) el tipo por línea se renderiza como `<select>` con
  las opciones de `validosPara()`. Para IPSI (`validosPara()` devuelve `null`) se mantiene el
  `<input type="number">` libre (0–100) actual.
- **Rationale**: Coincide con la semántica ya modelada en `TiposImpositivos` (`null` = libre) y con
  la spec (US4). Un select evita que el usuario teclee un tipo inválido para IVA/IGIC.
- **Alternatives considered**: Siempre input libre con validación (rechazado: peor UX, permite error
  hasta el submit); siempre select incluso IPSI (rechazado: IPSI no tiene lista cerrada).

## Decisión 4 — Recargo de equivalencia condicionado al régimen en el preview

- **Decision**: En `facturas-form.js`, el bloque de recargo (`{21:5.2,10:1.4,4:0.5}`) solo se evalúa
  si `state.aplicaRecargoRegimen` (régimen IVA) **y** el cliente está en recargo. Reutiliza el mapa
  vía `TiposImpositivos::recargoParaTipoIva()` conceptualmente (en JS se mantiene el literal, que solo
  se usa bajo IVA). En IGIC/IPSI no se muestra fila de recargo.
- **Rationale**: Espeja exactamente `CalculadoraFactura` (`aplicaRecargoEfectivo = aplicaRecargo &&
  regimen === Iva`, `CalculadoraFactura.php:20`). El backend ya es correcto; se alinea el preview.
- **Alternatives considered**: Dejar el recargo en JS y filtrarlo solo en backend (rechazado: el
  usuario vería un importe de recargo que luego desaparece al emitir → confuso e incumple US2).

## Decisión 5 — Etiquetas dinámicas (cabecera "IVA %" y desglose)

- **Decision**: La cabecera de columna y las claves de desglose del preview usan `regimenLabel`
  (`IVA`/`IGIC`/`IPSI`, de `strtoupper($regimen->value)`) en vez del literal "IVA". La clave JS de
  desglose pasa de `'IVA/IGIC ' + tipo + '%'` a `regimenLabel + ' ' + tipo + '%'`.
- **Rationale**: El PDF ya hace `strtoupper($impuesto->tipo_impuesto->value)`; se unifica el criterio
  de etiqueta en el formulario para no mostrar "IVA" en un tenant de Canarias (SC-001).
- **Alternatives considered**: Traducciones más ricas ("Impuesto General Indirecto Canario")
  (rechazado por ahora: las siglas son las usadas en el PDF y en la práctica contable; YAGNI).

## Decisión 6 — POS/ticket

- **Decision**: El POS toma el tipo de cada línea del artículo (`data-tipo-impositivo`), no de un
  selector; por tanto el cambio en `pos-form.js`/`pos/create.blade.php` se limita a: (a) etiqueta de
  impuesto dinámica ("Total (IVA incl.)" → régimen), y (b) confirmar que no calcula recargo (los
  tickets simplificados no aplican recargo; hoy el POS no lo calcula). No se añade selector de tipo.
- **Rationale**: Menor superficie de cambio, coherente con cómo funciona el POS hoy. Cumple US3 sin
  reescribir el flujo del ticket.
- **Alternatives considered**: Añadir selector de tipo en POS (rechazado: fuera de alcance; el ticket
  hereda el tipo del artículo por diseño).

## Riesgos y mitigaciones

- **Repoblado `old()` con select dependiente del régimen**: al fallar validación, el tipo elegido
  debe seguir seleccionado. Mitigación: renderizar `@selected()` comparando contra `old()`/valor de
  línea, igual que ya se hace en otros selects de la vista.
- **Tenant legado sin régimen**: el alta lo exige requerido; si faltara, se asume IVA por defecto en
  la capa de vista para no romper. Cubierto como edge case en la spec.
