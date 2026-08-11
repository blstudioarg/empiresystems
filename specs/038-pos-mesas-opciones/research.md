# Phase 0 — Research: POS con mesas y opciones de artículo

Decisiones técnicas tomadas antes de diseñar. Cada una parte de código o documentación ya existente
en el repo, no de suposiciones.

---

## D1 — Dónde vive la configuración del módulo

**Decisión**: clave/valor en la tabla `configuraciones` ya existente, envuelto en un
`App\Support\ConfigPos` clonado de `App\Support\ConfigFichajes`.

Claves y valores por defecto:

| Clave | Tipo | Default | Qué controla |
|---|---|---|---|
| `pos.hosteleria_activo` | bool | `false` | Interruptor maestro (FR-002) |
| `pos.opciones_activo` | bool | `false` | Opciones de artículo (FR-004) |
| `pos.cobro_dividido_activo` | bool | `false` | Cobro por selección de líneas (FR-004) |
| `pos.suplemento_zona_activo` | bool | `false` | Suplemento por zona (FR-004) |
| `pos.mesa_olvidada_min` | int | `45` | Umbral de mesa "olvidada" (FR-014) |

**Rationale**: `ConfigFichajes` resuelve exactamente el mismo problema (flags booleanos + umbrales
enteros por tenant) y ya está probado. `configuraciones` tiene `tenant_id`, así que el aislamiento
viene dado. Los defaults viven en el `Support`, no en filas sembradas: un tenant sin ninguna fila
tiene el módulo apagado sin necesidad de migración de datos para los tenants existentes — que es
justo lo que exige FR-002 ("desactivado por defecto en todo tenant, existente o nuevo").

**Alternativas descartadas**:
- Columnas nuevas en `tenants`: obligaría a una migración por cada flag y `tenants` es tabla central,
  no de negocio; el resto de módulos ya evitó ese camino.
- Archivo de configuración: no es por tenant. Inviable en single-database multi-tenant.

---

## D2 — Prefijo `pos_` en los nombres de tabla

**Decisión**: las nueve tablas nuevas llevan prefijo `pos_` (`pos_zonas`, `pos_mesas`, `pos_cuentas`…).

**Rationale**: dos motivos concretos, no estética.
1. **Colisión semántica real**: ya existe `cuentas_bancarias`. Una tabla llamada `cuentas` a secas,
   en un proyecto de facturación, es una bomba de confusión en cada lectura de código futura.
2. **Es un módulo desactivable**: agrupar sus tablas bajo un prefijo hace evidente de un vistazo qué
   parte del esquema pertenece al módulo opcional, algo que importa al diagnosticar un tenant que lo
   tiene apagado.

**Desviación reconocida**: el resto del proyecto no usa prefijos (`facturas`, `compras`, `leads`).
Se acepta la inconsistencia por los dos motivos de arriba; queda registrada en
`docs/03-modelo-datos.md` al cerrar para que no se lea como descuido.

---

## D3 — Cómo se emite un cobro (total o parcial) reutilizando el motor existente

**Decisión**: `RegistroTicket::registrar(array $datos)` se usa **sin modificar su contrato**. Un
servicio nuevo `CobradorCuenta` traduce "estas unidades de estas líneas" al array `lineas[]` que
`RegistroTicket` ya espera, y lo invoca.

**Por qué funciona sin tocar nada**: `RegistroTicket` no sabe de dónde vienen las líneas — recibe
`['lineas' => [...], 'receptor' => [...], 'pagos' => [...]]` y se encarga de calcular
(`CalculadoraFactura`), validar el tope (`TopeSimplificada`), numerar con bloqueo y emitir
(`EmisorFacturas`) dentro de una transacción. Un cobro parcial es, para él, un ticket normal con
menos líneas. **Consecuencia importante**: la numeración correlativa, la inmutabilidad y el
encadenamiento Verifactu se heredan gratis y no hay un segundo camino de emisión que mantener
(exigencia del Principio III y del punto 3 del encargo).

**Verificado en el código**: `RegistroTicket::registrar()` construye la `Factura`, sus `lineas`,
`impuestos` y `pagosTicket`, y delega en `EmisorFacturas::emitir()`. Nada de eso depende de que las
líneas provengan de un formulario.

**Alternativa descartada**: un `RegistroCobroParcial` propio que replicara el cálculo. Rechazado de
plano: duplicaría la lógica fiscal en dos sitios, que es exactamente lo que prohíbe el Principio III
("el cálculo de huella/encadenamiento ocurre exclusivamente en el servicio dedicado, nunca de forma
ad-hoc en varios lugares").

---

## D4 — Cómo se materializa el suplemento de zona

**Decisión**: `CobradorCuenta` multiplica el `precio_unitario` de cada línea por
`(1 + suplemento/100)` **antes** de pasárselo a `RegistroTicket`, redondeando a 2 decimales por
línea.

**Rationale**: es lo que fija FR-050 y evita tener que repartir un importe único entre varios tipos
impositivos. Como el `tipo_impositivo` viaja en cada línea y `CalculadoraFactura` ya agrupa el
desglose por tipo, el suplemento hereda automáticamente el tipo del artículo que lo genera. Cero
lógica fiscal nueva.

**Nota de precisión**: se redondea por línea, no al final, para que el total impreso sea la suma
exacta de los importes de línea impresos. Es la misma regla que ya sigue `CalculadoraFactura`.

**Punto a probar (Principio IV)**: un ticket con dos líneas de tipos impositivos distintos y zona
con suplemento debe producir un desglose por tipo que sume exactamente el total.

---

## D5 — Opción con artículo vinculado y movimiento de stock

**Decisión**: el movimiento de stock del artículo vinculado se registra **después** de emitir,
dentro de la misma transacción, como movimiento propio del kardex, con referencia a la factura
emitida.

**Por qué hace falta un paso explícito**: la salida de stock al emitir se genera **por línea de
factura con `articulo_id`** (ver `docs/03-modelo-datos.md`: `facturas ──< movimientos_stock`, salida
al emitir). Como FR-052 decide que la opción **no** genera línea propia, su artículo vinculado nunca
aparecería en ninguna línea y su stock no se movería solo. Hay que añadirlo a mano.

**Restricción**: `movimientos_stock` es un ledger **append-only** por constitución (Additional
Constraints). El movimiento se crea, nunca se edita; una corrección sería un movimiento inverso.

**Riesgo identificado**: si el artículo vinculado no gestiona stock (`gestion_stock = false`), no se
registra movimiento. No es error: es el mismo criterio que ya aplica el resto del sistema.

---

## D6 — Control de acceso: permiso ≠ módulo activo

**Decisión**: dos capas independientes y ambas obligatorias en las rutas nuevas.

1. `can:ver-pos-sala` / `can:ver-pos-opciones` — permiso del usuario (patrón ya establecido).
2. Middleware nuevo `ModuloHosteleriaActivo` — estado del módulo en el tenant.

**Rationale**: son cosas distintas y confundirlas produce agujeros. Un usuario puede tener el
permiso y el tenant tener el módulo apagado (debe recibir 404/403, FR-003); o el módulo puede estar
encendido y el usuario no tener permiso. Resolver ambas con un solo mecanismo obligaría a manipular
permisos al encender/apagar el módulo, que es frágil y deja rastro sucio en los roles del tenant.

**Referencia de patrón**: `app/Http/Middleware/BloquearSuperAdminAreaTenant.php`, middleware de
bloqueo por contexto ya existente en el proyecto.

**Visibilidad en el menú**: `CatalogoMenu` filtra por permiso; hay que sumar el filtro por módulo
activo para que las entradas no aparezcan apagadas (FR-056). El enforcement real sigue siendo el
middleware, el menú es solo UX (regla explícita de `docs/04-front-guidelines.md`).

---

## D7 — Detección de edición concurrente de una cuenta (FR-024)

**Decisión**: bloqueo optimista con un contador de versión en `pos_cuentas`. El cliente envía la
versión que cargó; si no coincide con la de base de datos, se responde 409 y la UI recarga
avisando.

**Rationale**: es lo más simple que cumple el requisito (Principio V). Un bloqueo pesimista (marcar
la cuenta "en uso" por un camarero) exige liberar el bloqueo cuando alguien cierra la tablet o se
queda sin batería — un problema de expiración que no queremos.

**Alternativa descartada**: comparar `updated_at`. Funciona casi siempre, pero dos escrituras dentro
del mismo segundo pueden no distinguirse según la precisión de la columna. Un entero incremental no
tiene ese problema.

---

## D8 — Partición de `pos-form.js`

**Decisión**: partir el archivo actual (735 líneas) en módulos antes de añadir nada:
`pos-catalogo.js`, `pos-ticket.js`, `pos-cobro.js`, `pos-opciones.js`, `pos-cuenta.js`, con un
`pos-form.js` que orquesta.

**Rationale**: esta feature añade cuatro flujos nuevos (selección de opciones, contexto de mesa,
guardar/recuperar cuenta, cobro parcial). Sobre un único archivo quedaría en ~1.800 líneas, tamaño
en el que los conflictos y los bugs de estado compartido se disparan.

**Riesgo asumido y cómo se mitiga**: partir un archivo que hoy funciona es una refactorización sin
valor visible para el usuario, y puede romper cosas. Por eso va como **tarea propia y aislada, antes
de cualquier funcionalidad nueva**, con verificación manual del POS actual completo antes de seguir.
Nunca mezclada en el mismo commit que una funcionalidad.

---

## D9 — Orden de entrega: ¿el cobro dividido va en la misma tanda?

Es la pregunta explícita del encargo (punto 1) y el riesgo anotado en el checklist del spec.

**Decisión**: **el esquema de base de datos soporta cobro parcial desde el día uno; la
funcionalidad y su interfaz se implementan como incremento separado, después de que las cuentas
abiertas estén verdes.**

**Rationale**, en dos partes:

- *Por qué el esquema sí desde el principio*: soportar cobros parciales significa que una línea de
  cuenta lleva "unidades saldadas" y que una cuenta apunta a **varios** documentos. Retrofitear eso
  después obliga a migrar `pos_cuenta_lineas` con datos reales de producción y a revalidar toda la
  numeración. Es la parte barata ahora y cara después: dos columnas y una tabla.
- *Por qué la funcionalidad no*: el cobro parcial toca el estado de la cuenta, el importe mostrado en
  la mesa, el aviso de tope, la anulación y las reglas de transferencia/unión. Construirlo en
  paralelo a las cuentas abiertas significa depurar dos máquinas de estado a la vez sin tener
  ninguna de las dos estable.

**Consecuencia práctica**: el bloque de tareas de US5 depende de que US2 esté completa y verde, y no
comparte tareas con ella. Si hiciera falta recortar por tiempo, US5 es el corte natural: se puede
posponer sin tocar ni una migración.

**Alternativa descartada**: dejar el cobro parcial fuera del esquema y añadirlo luego. Rechazada por
el coste de migración descrito, y porque el usuario decidió explícitamente (Q3 = C) que entra en
esta fase.

---

## D10 — Qué NO se investiga porque ya está resuelto en el repo

Para dejar constancia de que no se reinventa nada:

| Necesidad | Pieza existente que se reutiliza |
|---|---|
| Cálculo de bases/cuotas/recargo | `App\Services\CalculadoraFactura` |
| Numeración con bloqueo + inmutabilidad + eventos | `App\Services\EmisorFacturas` |
| Tope de factura simplificada | `App\Support\TopeSimplificada` |
| Régimen impositivo (IVA/IGIC/IPSI) | `App\Support\TiposImpositivos` + `tenant()->regimen_impositivo` |
| Flags de módulo por tenant | patrón `App\Support\ConfigFichajes` |
| Permisos y menú | `CatalogoPermisos` + `CatalogoMenu` + `PermisosSeeder` |
| Filtros táctiles de categoría | `.pos-filtro` de `pos/create.blade.php` |
| Select de catálogo con CRUD inline | `<x-categoria-select>` / `<x-unidad-select>` |
| Avisos al usuario | `window.showToast` + `partials/flash-toastr` |
