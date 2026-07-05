# UI Contract: Payload de régimen → vista de factura / POS

**Feature**: 013-facturas-regimen-impositivo

Contrato de la interfaz interna entre el controlador (Laravel) y la capa de presentación
(Blade + JS). No es una API pública; es el acuerdo de forma de datos que debe respetarse para que
el formulario refleje el régimen del tenant.

## 1. Datos que el controlador expone a la vista

`FacturaController@create`, `FacturaController@edit` y `PosController@create` DEBEN incluir en el
array de la vista un bloque `regimen` con esta forma:

```
regimen: {
  value: 'iva' | 'igic' | 'ipsi',   // tenant()->regimen_impositivo->value
  label: 'IVA' | 'IGIC' | 'IPSI',   // strtoupper(value)
  tiposValidos: number[] | null,    // TiposImpositivos::validosPara(...) ; null = entrada libre (IPSI)
  tipoPorDefecto: number,           // 21 (IVA) | 7 (IGIC) | 0 (IPSI)
  aplicaRecargo: boolean            // value === 'iva'
}
```

## 2. Inyección en el estado JS (Blade → window)

`facturas/create.blade.php` DEBE añadir a `window.facturaFormState`:

```
window.facturaFormState = {
  ...existente,
  regimen: @json($regimen)   // objeto del punto 1
};
```

`pos/create.blade.php` DEBE añadir el mismo objeto a `window.posState.regimen`.

## 3. Comportamiento observable exigido al JS

### `facturas-form.js`
- **Default de línea nueva**: usar `state.regimen.tipoPorDefecto` (no el literal `21`).
- **Selector de tipo**: si `state.regimen.tiposValidos` es un array, la fila usa un `<select>` con
  esas opciones; si es `null` (IPSI), usa input numérico libre.
- **Etiqueta de desglose**: clave = `state.regimen.label + ' ' + tipo + '%'` (no `'IVA/IGIC ...'`).
- **Recargo**: solo se calcula/muestra si `state.regimen.aplicaRecargo === true` **y** el cliente
  está en recargo. En IGIC/IPSI no aparece fila ni importe de recargo.

### `pos-form.js`
- **Etiqueta de impuesto** ("Total (IVA incl.)" y similares): usa `state.regimen.label`.
- No calcula recargo (sin cambios de comportamiento en ese punto).

## 4. Cabecera de tabla (Blade)

La `<th>IVA %</th>` de `facturas/create.blade.php` DEBE renderizar `{{ $regimen['label'] }} %`.

## 5. Invariantes / aserciones verificables

- **INV-1** (multi-tenant): el `regimen.value` inyectado SIEMPRE es el de `tenant()` activo; nunca
  el de otro tenant.
- **INV-2** (coherencia front/back): el conjunto ofrecido en el selector == el conjunto aceptado por
  `TipoImpositivoValido` para el mismo régimen.
- **INV-3** (recargo): para `regimen.value ∈ {igic, ipsi}` no existe ningún elemento de recargo en
  el preview ni en el desglose emitido.
- **INV-4** (server-side): el total emitido lo calcula el backend; el preview JS puede diferir por
  redondeo intermedio pero el valor persistido proviene de `CalculadoraFactura`/`RegistroTicket`.
