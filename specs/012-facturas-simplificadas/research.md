# Research: POS — Facturas simplificadas (tickets)

Fase 0. Decisiones técnicas resueltas antes del diseño. No quedan NEEDS CLARIFICATION (las decisiones
de producto se cerraron con el usuario durante `/speckit-specify`).

## 1. Reutilización del motor fiscal existente (008/009)

- **Decisión**: reutilizar `App\Services\CalculadoraFactura` (cálculo por tipo impositivo agnóstico al
  régimen), `App\Services\NumeradorFacturas` (correlativo por serie/año con `lockForUpdate`) y el
  registro de eventos append-only de `App\Services\EmisorFacturas`. El servicio nuevo `RegistroTicket`
  orquesta estos, no reimplementa cálculo ni numeración.
- **Rationale**: Principios III y IV ya cubiertos y probados; reimplementar sería duplicación y riesgo.
- **Alternativas**: reescribir un cálculo "ligero" para tickets — rechazado (viola YAGNI y duplica
  lógica fiscal crítica).

## 2. Validación de receptor condicionada al tipo

- **Hallazgo**: `EmisorFacturas::validar()` exige hoy `cliente_nif` + nombre/razón + `cliente_direccion`
  siempre (salvo rectificativa). Eso **bloquearía la simplificada simple** (sin receptor).
- **Decisión**: condicionar esa validación al `tipo`: para `simplificada` NO se exige receptor (simple)
  y, si viene informado, se acepta (cualificada). Para `ordinaria` el comportamiento actual no cambia.
  Alternativamente, el `RegistroTicket` emite el ticket por su propia ruta y sólo se toca `EmisorFacturas`
  para no exigir receptor cuando `tipo === Simplificada`. Se elige tocar `EmisorFacturas` mínimamente
  (una guarda por tipo) para mantener un único punto de emisión/inmutabilidad/eventos.
- **Rationale**: mantiene un solo camino de emisión (menos superficie, misma inmutabilidad y evento
  "emitida"); el cambio es aditivo y no altera el flujo ordinario (los tests de 008 siguen verdes).
- **Alternativas**: duplicar la lógica de emisión en un `EmisorTickets` separado — rechazado por
  duplicación del candado de inmutabilidad y del registro de eventos.

## 3. Serie propia "S"

- **Decisión**: sembrar una serie `tipo = simplificada`, `codigo = 'S'`, `formato =
  '{serie}-{anio}-{numero:0000}'`, `activa = true` en `SerieSeeder`, idéntico patrón a "F"/"R".
  Resolución vía `Serie::activaPorTipo(TipoFactura::Simplificada)`.
- **Rationale**: consistente con 008/009; `NumeradorFacturas` ya reinicia por año y bloquea por serie.
- **Alternativas**: compartir serie con la ordinaria — descartado por el usuario (quiere serie propia).

## 4. Tope de importe (bloqueo duro, config por tenant)

- **Decisión**: nueva clave `factura.simplificada_tope_ampliado` (grupo `facturacion`, tipo `boolean`,
  default `false`) en `configuraciones`. `App\Support\TopeSimplificada` resuelve: `false → 400.00`,
  `true → 3000.00`. El tope se compara contra el **importe bruto** del ticket
  (`base_total + cuota_impuesto_total + cuota_recargo_total`, impuestos incluidos), de forma **inclusiva**
  (`≤ tope`). Si se supera, `RegistroTicket` lanza `TicketFueraDeTopeException` y no crea/emite nada.
- **Rationale**: sigue el mecanismo de config documentado (`docs/03-modelo-datos.md` §configuraciones,
  "3 pasos"); importe validado server-side (Principio III). El bruto con impuestos es el "importe total
  (IVA incl.)" que la normativa usa para el tope.
- **Alternativas**: modelar la lista de sectores como datos — rechazado; el usuario decidió un simple
  flag por tenant. IRPF no se resta para el tope (una simplificada de mostrador no lleva retención;
  aun si la llevara, el tope legal se mide sobre el importe con impuestos, no sobre el neto).

## 5. Módulo POS separado y exclusión en el listado ordinario

- **Decisión**: rutas `pos.*` con `PosController` (index/create/store/pdf) y vistas en
  `resources/views/pos/`. `FacturaController::index()` añade `->where('tipo', '!=',
  TipoFactura::Simplificada->value)` (o `whereIn([ordinaria, rectificativa])`) tanto en la rama JSON
  como en la de conteo, sin más cambios.
- **Rationale**: cumple el requisito de no mezclar y de no tocar la lógica de creación/edición
  ordinaria; el índice sólo se filtra.
- **Alternativas**: un parámetro `?tipo=` compartido en el mismo controlador — rechazado; el usuario
  quiere vistas y menú separados (módulo POS), no un filtro dentro de Facturas.

## 6. PDF en 80 mm y A4

- **Decisión**: `PosController::pdf(Factura, ?formato)` con `formato ∈ {ticket, a4}` (default `ticket`).
  - `ticket` → nueva plantilla `facturas/ticket-80mm.blade.php`, DomPDF con papel personalizado de
    ancho 80 mm y alto amplio/variable (`Pdf::loadView(...)->setPaper([0,0,226.77, <alto>])`, 80 mm ≈
    226.77 pt) — se ajusta el alto al contenido.
  - `a4` → reutiliza `facturas/pdf.blade.php` (o una variante reducida) en A4.
  - Ambas plantillas muestran el contenido mínimo de la simplificada; si el ticket es cualificado,
    añaden NIF+domicilio del receptor y el desglose de la cuota.
- **Rationale**: DomPDF (ya en el proyecto) admite tamaño de papel arbitrario; no hace falta librería
  nueva (Principio V).
- **Alternativas**: generar ESC/POS para impresora térmica — fuera de alcance; el PDF 80 mm imprime
  bien en rollo desde el navegador/visor.

## 7. Vista "Crear ticket" tablet-first

- **Decisión**: Blade + JS sobre el layout `app.blade.php`, con captura rápida (búsqueda/lista de
  artículos, botones grandes táctiles, total en vivo recalculado en cliente sólo como *preview*, con la
  verdad en backend). El diseño visual se elabora con las skills de diseño a nivel usuario
  (`frontend-design` / `emil-design-eng`) y `docs/04-front-guidelines.md` durante la implementación.
- **Rationale**: la simplificada pide menos datos → formulario más corto, ideal para TPV táctil.
- **Alternativas**: SPA/framework JS dedicado — rechazado (YAGNI, Principio V; el template ya trae
  jQuery/DataTables).

## 8. Cobros y stock

- **Decisión**: fuera de alcance de esta feature salvo lo que herede el motor reutilizado; si se
  requiere "cobro inmediato" en el POS, se especifica aparte (feature 010 de pagos ya existe y podría
  enlazarse después).
- **Rationale**: mantener el alcance acotado (Principio V, YAGNI).
