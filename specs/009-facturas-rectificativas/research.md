# Research: Facturas rectificativas

Fase 0 — decisiones técnicas y su justificación. No quedan marcadores NEEDS CLARIFICATION (las
ambigüedades de alcance se resolvieron en la sesión de clarificación de la spec).

## 1. Modalidad "por diferencias": cómo se representa el delta

- **Decisión**: el usuario introduce las **líneas corregidas completas** (igual que un borrador
  ordinario). Para **por sustitución**, los totales persistidos de la rectificativa son los totales
  corregidos (suma de líneas). Para **por diferencias**, los totales persistidos de **cabecera**
  (`base_total`, `cuota_impuesto_total`, `cuota_recargo_total`, `irpf_cuota`, `total`) son el
  **delta = corregido − original**, calculado en backend a nivel de totales y de desglose por tipo
  impositivo. Las **líneas** guardan el detalle corregido de referencia; el desglose `factura_impuestos`
  de la rectificativa por diferencias se persiste como delta por tipo.
- **Rationale**: el usuario eligió "delta auto-calculado". Calcular el delta a nivel de totales/impuestos
  (no un emparejamiento línea-a-línea, que exigiría casar líneas heterogéneas entre original y
  corrección) es determinista, auditable y suficiente para el documento fiscal, que declara la
  diferencia de bases/cuotas. `CalculadoraFactura` calcula los totales corregidos; el delta es una
  resta contra los totales ya persistidos de la original.
- **Alternativas consideradas**:
  - *Delta línea-a-línea*: rechazado — requiere identidad/emparejamiento de líneas entre original y
    corrección; ambigüo y frágil, sin valor fiscal adicional.
  - *Guardar solo cabecera sin líneas en diferencias*: rechazado — perderíamos el detalle corregido
    útil para el PDF y para futuras validaciones; reutilizar el almacén de líneas existente es más
    simple.
- **Delta cero**: admitido (corrección no monetaria, p. ej. un dato del receptor); el `motivo` lo
  documenta. Por eso la validación de emisión **no** puede exigir `base_total > 0` para rectificativas.
- **Negativos**: la migración/casts permiten `DECIMAL(12,2)` negativos (ya lo permiten); ninguna
  restricción `unsigned` en los importes.

## 2. Numeración en serie rectificativa separada

- **Decisión**: reutilizar `NumeradorFacturas::siguienteNumero(Serie, fecha)` **sin cambios**. Ya es
  genérico: deriva `MAX(numero)+1` de las facturas de esa serie en ese año bajo `lockForUpdate`. Basta
  con que la rectificativa tenga `serie_id` = serie rectificativa del tenant.
- **Rationale**: la lógica de correlativo, reinicio anual y concurrencia de 008 es agnóstica al tipo
  de serie; no hay razón para duplicarla. Cumple Principio II (serie separada) y III (atomicidad).
- **Selección de serie (gotcha)**: hoy `FacturaController::store()` hace
  `Serie::where('activa', true)->firstOrFail()`, que tomaría la primera serie activa arbitrariamente
  al existir >1 serie. **Decisión**: seleccionar la serie por `tipo` (ordinaria para `store`,
  rectificativa al crear la rectificativa). Encapsular en `Serie::porTipo(TipoFactura)` o un scope
  para evitar repetir el criterio.
- **Sembrado**: no hay CRUD de series (fuera de alcance), así que se siembra una serie rectificativa
  por defecto por tenant (`codigo 'R'`, `tipo rectificativa`, `formato '{serie}-{anio}-{numero:0000}'`).
  El `SerieSeeder` y el `TenantFactory`/seeders de test deben crearla junto a la ordinaria.

## 3. Reutilización de `EmisorFacturas`

- **Decisión**: extender `EmisorFacturas::emitir()`/`validar()` en vez de crear un emisor paralelo:
  - `validar()`: si la factura es rectificativa, **omitir** la comprobación `base_total > 0` (permite
    delta 0/negativo). El resto (estado borrador, datos fiscales del receptor) se mantiene.
  - `emitir()`: si `es_rectificativa`, dentro de la **misma transacción** que asigna número y congela,
    marcar la factura original (`factura_rectificada`) como `EstadoFactura::Rectificada` y guardar.
    Registrar el evento "emitida" de la rectificativa (ya existe) y, opcionalmente, un evento
    "rectificada" sobre la original (append-only) para trazabilidad.
- **Rationale**: mantiene una sola ruta de emisión (`POST /facturas/{factura}/emitir`) y un solo
  punto de numeración/atomicidad; menos superficie de bug y coherente con el Principio III (huella
  Verifactu futura en un único servicio).
- **Alternativa**: `EmisorRectificativas` separado — rechazado (duplicaría numeración/congelado/evento).

## 4. Creación de la rectificativa

- **Decisión**: nuevo servicio `GeneradorRectificativa::generar(Factura $original, modalidad, motivo)`
  que valida (original en estado `emitida`, no `rectificada`; mismo tenant) y crea una factura
  `borrador` con `tipo = rectificativa`, `es_rectificativa = true`, `factura_rectificada_id`,
  `motivo_rectificacion`, `tipo_rectificacion`, copiando el snapshot del receptor, `regimen_impositivo`,
  `aplica_recargo` y `serie_id` (serie rectificativa). Inicialmente **copia las líneas de la original**
  como punto de partida editable.
- **Rationale**: separar la creación (reglas de rectificabilidad) de la edición (reutiliza el editor de
  borrador de 005) y de la emisión (008). El controller expone `POST /facturas/{factura}/rectificar`
  que delega en el servicio y redirige al editor del borrador creado.
- **Unicidad (una sola vez)**: la validación rechaza rectificar si la original ya está `rectificada`.
  Se refuerza además con la relación `rectificativa()` (una original tiene como máximo una
  rectificativa vinculada).

## 5. Migración aditiva y modelo de datos

- **Decisión**: nueva migración que **añade** a `facturas`: `es_rectificativa` (bool, default false),
  `factura_rectificada_id` (fk nullable → facturas), `motivo_rectificacion` (text nullable),
  `tipo_rectificacion` (string nullable, enum `sustitucion|diferencias`). Coincide con
  `docs/03-modelo-datos.md` (que ya las documenta). No se recrea la tabla (datos existentes intactos).
- **Rationale**: el modelo de datos ya anticipa estas columnas; solo faltaban en la tabla. Migración
  aditiva = compatible con hosting compartido y con datos de 005/008 ya presentes.

## 6. Inmutabilidad y estados

- **Decisión**: la rectificativa emitida reutiliza los mismos guardas de inmutabilidad de 008
  (`edit`/`update`/`destroy` exigen `estado === Borrador`). La original `rectificada` queda cubierta
  por esos mismos guardas (no es borrador → no editable/borrable) y por la validación de unicidad de
  rectificación. No hay transición de `rectificada` de vuelta a `emitida`.
- **Rationale**: cero lógica nueva de inmutabilidad; se apoya en el candado ya probado por 008.
