# Tasks: Mesas redimensionables por celdas en el plano del POS

**Feature**: 040-pos-mesas-redimensionables | **Fecha**: 2026-08-19

**Input**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/plano-guardar.md](./contracts/plano-guardar.md),
[quickstart.md](./quickstart.md)

**Tests**: se incluyen tareas de test. Motivo: el aislamiento multi-tenant es área test-first
**no negociable** (Principio IV) y el plan extiende ese rigor a la geometría de solapamiento, por
ser el corazón de la feature y fallar de forma silenciosa.

**Convención de formato**: `- [ ] T### [P?] [US?] Descripción con ruta de archivo`.
`[P]` = paralelizable (archivo distinto, sin dependencias pendientes).

---

## Phase 1: Setup

- [ ] T001 Verificar la línea base antes de tocar nada: abrir la Sala del POS con emulación táctil y anotar si el arrastre de posición (asa circular) funciona hoy en táctil, según el paso 5 de [quickstart.md](./quickstart.md). Registrar el resultado en un comentario de la PR — si ya estaba roto, es un defecto preexistente de la feature 039, no una regresión de esta.
- [ ] T002 [P] Confirmar que `resizable.js` está incluido en el bundle vendorizado `public/vendor/jqueryui/js/jquery-ui.min.js` (cabecera del archivo) y que la vista ya lo carga en `resources/views/pos/sala.blade.php` (líneas 6 y 365), para dejar por escrito que no hace falta ninguna dependencia nueva.
- [ ] T003 [P] Localizar y anotar todos los puntos que hoy leen o escriben `tamano` de una mesa: `app/Models/PosMesa.php`, `app/Http/Controllers/Pos/PlanoSalaController.php`, `app/Http/Controllers/Pos/SalaController.php`, `app/Support/PosPlanoReacomodo.php`, `public/js/plugins-init/pos-sala-plano.init.js`, `resources/views/pos/sala.blade.php`, factories y seeders del POS. Es la lista de sitios que la retirada del atributo debe dejar limpios.

---

## Phase 2: Foundational — datos y barrera de servidor

**BLOQUEANTE**: ninguna historia de usuario puede completarse antes de esta fase. Al terminarla, el
servidor ya acepta, valida y persiste rectángulos aunque la interfaz siga enviando 1×1.

### Tests primero (Principio IV)

- [ ] T004 [P] Escribir `tests/Feature/Pos/PlanoRectangulosTest.php` cubriendo los invariantes G1/G2/G3 de [data-model.md](./data-model.md): guardado válido de una mesa 2×1; rechazo 422 con `ancho_celdas: 0`; rechazo 422 de un rectángulo que se sale de la rejilla (`columna + ancho > 8`, `fila + alto > 6`); rechazo 422 de dos mesas solapadas; y que tras un 422 **ningún** dato cambió (atomicidad, FR-013/SC-004). Debe fallar en rojo.
- [ ] T005 [P] Escribir `tests/Feature/Pos/PlanoConversionTest.php`: partiendo de un plano con una `barra` en la última columna y una `rectangular` pegada a otra mesa, tras la conversión ninguna mesa desaparece (SC-003), ninguna viola G2 ni G3 (SC-002), y el orden de reducción es determinista. Debe fallar en rojo.
- [ ] T006 [P] Ampliar `tests/Feature/Pos/AislamientoMesasTest.php`: un usuario del tenant A no puede redimensionar ni leer la geometría de mesas del tenant B (Principio I). Debe fallar en rojo si la implementación se saltase el scope.

### Implementación

- [ ] T007 Crear la migración `database/migrations/2026_08_19_HHMMSS_mesas_ocupan_rectangulo_de_celdas.php`: añadir `ancho_celdas` y `alto_celdas` (`unsignedTinyInteger`, default 1, `->after('columna')`) a `pos_mesas`, según [data-model.md](./data-model.md).
- [ ] T008 En la misma migración, implementar la conversión de datos (D8 de [research.md](./research.md)): derivar de `forma` (`rectangular`→2×1, `barra`→3×1, resto 1×1), y luego corregir por zona en orden determinista (`fila`, `columna`, `id`) reduciendo `ancho_celdas` de la mesa que viole G2 o G3 hasta que quepa, con suelo en 1.
- [ ] T009 En la misma migración, eliminar la columna `tamano` y escribir el `down()` que la reintroduce con default `mediana` y descarta las columnas nuevas, documentando en el docblock que la reversión pierde información (un 3×2 no es representable en el modelo viejo).
- [ ] T010 [P] Actualizar `app/Models/PosMesa.php`: añadir `ancho_celdas` y `alto_celdas` a `$fillable` y a `$casts` como `integer`; retirar `tamano` de ambos.
- [ ] T011 Reescribir la validación de `app/Support/PosPlanoReacomodo.php`: sustituir el mapa de claves `"fila-columna"` de una entrada por mesa por el marcado de **todas** las celdas de cada rectángulo, comprobando G1, G2 y G3 en el mismo recorrido, con los mensajes de error que nombran la mesa definidos en [contracts/plano-guardar.md](./contracts/plano-guardar.md).
- [ ] T012 [P] Actualizar `app/Support/PosPlanoCeldas.php`: `celdasOcupadas()` marca todas las celdas de cada mesa (bucle sobre ancho y alto), no solo su origen, para que `primeraCeldaLibre()` no coloque una mesa nueva encima de una mesa grande. Firma y contrato de retorno se mantienen.
- [ ] T013 Actualizar las reglas de `app/Http/Controllers/Pos/PlanoSalaController.php@update` según [contracts/plano-guardar.md](./contracts/plano-guardar.md): quitar `mesas.*.tamano`, añadir `mesas.*.ancho_celdas` y `mesas.*.alto_celdas` (`required|integer|min:1|max:` las constantes de `PosPlanoCeldas`), y persistir los campos nuevos en el `update()` dentro de la transacción existente.
- [ ] T014 [P] Actualizar `app/Http/Controllers/Pos/SalaController.php` para incluir `ancho_celdas`/`alto_celdas` y dejar de incluir `tamano` en el payload de `window.posSalaData` (dos puntos del archivo, ~líneas 75 y 96).
- [ ] T015 [P] Actualizar factories y seeders del POS que asignen `tamano` a una mesa (los localizados en T003) para que usen la ocupación en celdas. Recordar el pitfall del proyecto: forzar el `tenant_id` activo, nunca dejar que el factory genere un tenant nuevo.
- [ ] T016 Ejecutar `php artisan migrate` (**nunca** `migrate:fresh`, hay datos de demo) y `php artisan test --filter=Plano --filter=AislamientoMesas` hasta dejar T004, T005 y T006 en verde.

**Checkpoint**: el backend valida y persiste rectángulos; los planos existentes están convertidos y
son geométricamente válidos.

---

## Phase 3: User Story 1 — Agrandar y encoger una mesa arrastrando su borde (P1)

**Objetivo**: el encargado da tamaño a una mesa arrastrando su borde, con encaje a la rejilla, y el
cambio persiste al guardar el plano.

**Test independiente**: entrar en modo edición, arrastrar el borde de una mesa suelta en una zona con
espacio libre, guardar y recargar; la mesa reaparece con el tamaño nuevo (pasos 2 de
[quickstart.md](./quickstart.md)).

- [ ] T017 [US1] En `public/js/plugins-init/pos-sala-plano.init.js`, generalizar el modelo en memoria: cada entrada de `pendiente` pasa a llevar `ancho`/`alto` en celdas en lugar de `tamano`; actualizar `cargarZona()` y el mapeo del payload de guardado (`{id, fila, columna, ancho_celdas, alto_celdas, forma}`) según [contracts/plano-guardar.md](./contracts/plano-guardar.md).
- [ ] T018 [US1] Reescribir `dimensiones(forma, tamano)` como `dimensiones(ancho, alto)` en `public/js/plugins-init/pos-sala-plano.init.js`, devolviendo `{width: ancho * STEP - GAP, height: alto * STEP - GAP}` (aritmética de encaje de D1 en [research.md](./research.md)). Retirar `tamanoEscala()` y la constante `TAMANOS`; la forma deja de decidir el tamaño y pasa a decidir solo el aspecto.
- [ ] T019 [US1] Activar el widget `resizable` de jQuery UI sobre cada `.plano-mesa` en modo edición, en `public/js/plugins-init/pos-sala-plano.init.js`, con `grid: [STEP, STEP]`, `containment: '#pos-plano-canvas'`, `minWidth: CELL`, `minHeight: CELL` y las asas de D6 (siete asas: se renuncia a `ne` por el asa de arrastre existente).
- [ ] T020 [US1] En el callback `stop` del `resizable`, traducir píxeles a celdas y escribir `ancho`/`alto` (y `fila`/`columna` si el arrastre fue por el borde norte u oeste) en el estado `pendiente`, sin persistir nada: el guardado sigue siendo por botón (FR-018).
- [ ] T021 [P] [US1] Retirar el selector de tamaños de la interfaz: quitar `#plano-popover-tamanos` de `resources/views/pos/sala.blade.php` (~línea 304) y su renderizado y manejador de clic (`data-tamano`) de `public/js/plugins-init/pos-sala-plano.init.js`. El selector de **forma** se mantiene.
- [ ] T022 [P] [US1] Añadir en `resources/views/pos/sala.blade.php` el CSS de las asas de redimensionado: 16 px de agarre (D6), visibles solo en modo edición, con `touch-action: none`, y sin tapar el asa circular de arrastre (`z-index` superior para esta última).
- [ ] T023 [US1] Verificar manualmente los pasos 2 de [quickstart.md](./quickstart.md), incluido que salir sin guardar revierte el tamaño y que tras guardar y recargar persiste.

**Checkpoint**: US1 entregable de forma independiente — ya se puede redimensionar y guardar, con la
barrera de servidor de la fase 2 protegiendo cualquier geometría inválida.

---

## Phase 4: User Story 2 — El plano deja de permitir solapamientos (P1)

**Objetivo**: el crecimiento se detiene en el último tamaño válido al chocar o al llegar al límite de
la rejilla, sin reacomodar a nadie; y mover una mesa considera su rectángulo completo.

**Test independiente**: dos mesas contiguas y una mesa en el borde de la rejilla (pasos 3 de
[quickstart.md](./quickstart.md)).

- [ ] T024 [US2] Reescribir `celdaLibre(fila, columna, ignorarId)` como `rectanguloLibre(fila, columna, ancho, alto, ignorarId)` en `public/js/plugins-init/pos-sala-plano.init.js`, con la fórmula AABB de D3 en [research.md](./research.md) — la misma regla que aplica el servidor en T011.
- [ ] T025 [US2] Implementar el clamp en el callback `resize` (no en `stop`) según D2: calcular el rectángulo candidato desde `ui.position` y `ui.size`, y si viola la rejilla o solapa, reescribir `ui.size` y `ui.position` con el último rectángulo válido, manteniendo la variable `ultimoValido`. El clamp es **por eje** (FR-004, escenario 3 de US2): probar el candidato completo, luego con el eje X revertido, luego con el eje Y revertido, y solo por último ambos — revertir el rectángulo entero bloquearía también la dirección que sí tiene hueco. Incluir la comprobación de rejilla (G2) en el clamp: `containment` es ayuda visual, no garantía.
- [ ] T026 [US2] Reescribir `celdaLibreMasCercana()` como `huecoMasCercano(fila, columna, ancho, alto, ignorarId)` en `public/js/plugins-init/pos-sala-plano.init.js` (D4): busca la primera posición de origen, en orden de distancia creciente, donde cabe el rectángulo completo.
- [ ] T027 [US2] Adaptar el manejador de `stop` del `draggable` para que el reacomodo por colisión al **mover** use `huecoMasCercano` con el rectángulo de la mesa desplazada, y actualizar el texto del toast de cancelación para que explique el motivo real ("no hay espacio suficiente para la mesa desplazada"), usando `window.showToast` como manda la convención de notificaciones.
- [ ] T028 [P] [US2] Añadir el feedback visual de bloqueo en `resources/views/pos/sala.blade.php` y activarlo desde el JS (D7/FR-009): clase temporal con resalte por `box-shadow` y micro-desplazamiento de rechazo, respetando el bloque `@media (prefers-reduced-motion: reduce)` que ya existe en la vista. **No** teñir el borde: su color está reservado para el estado libre/ocupada/olvidada (`docs/04-front-guidelines.md`, "Tarjeta de mesa y sus tres estados"). **No** lanzar toast: se dispararía en bucle durante el arrastre.
- [ ] T029 [US2] Verificar manualmente los pasos 3 y 6 de [quickstart.md](./quickstart.md), incluido el reenvío de un payload manipulado desde las herramientas de red para confirmar que la barrera de servidor responde 422 sin cambios parciales.

**Checkpoint**: el plano ya no admite solapamientos ni por interfaz ni por API.

---

## Phase 5: User Story 3 — Las sillas reflejan el tamaño real (P2)

**Objetivo**: el número de sillas dibujadas comunica el tamaño de la mesa.

**Test independiente**: comparar dos mesas de la misma forma y distinta ocupación (pasos 4 de
[quickstart.md](./quickstart.md)).

- [ ] T030 [US3] Reescribir `sillasParaForma(forma)` como `sillasParaMesa(forma, ancho, alto)` en `public/js/plugins-init/pos-sala-plano.init.js` según D9: ~2 plazas por celda de lado para `redonda`/`cuadrada`/`rectangular`, y solo en el lado largo para `barra`. Explícitamente fuera de alcance: derivar de aquí la capacidad de comensales como dato de negocio.
- [ ] T031 [US3] Recalcular las sillas al soltar el borde (dentro del `stop` del `resizable`), para que la previsualización sea inmediata sin necesidad de guardar.
- [ ] T032 [US3] Comprobar que ninguna vista fuera del editor consume `tamano` ni el dibujo por forma antiguo: la Sala en modo servicio usa la rejilla de tarjetas `#pos-sala-mesas` y **no** dibuja el plano (`resources/views/pos/sala.blade.php`, `.pos-plano-wrap` solo visible con la clase `activo`), así que queda intacta. Confirmar que no hay ninguna referencia residual (FR-015).
- [ ] T033 [US3] Verificar manualmente los pasos 4 de [quickstart.md](./quickstart.md).

---

## Phase 6: Táctil (FR-019, SC-005)

Transversal a US1 y US2: sin esto, la feature es solo de escritorio y la Sala es una pantalla de
tablet.

- [ ] T034 Implementar en `public/js/plugins-init/pos-sala-plano.init.js` el reenviador de eventos táctiles de D5 (~20 líneas propias, **sin** vendorizar `jquery.ui.touch-punch`): `touchstart`/`touchmove`/`touchend` sobre las asas se traducen a los `mousedown`/`mousemove`/`mouseup` que espera el widget `mouse` de jQuery UI 1.11.1, que no tiene soporte táctil.
- [ ] T035 Verificar los pasos 5 de [quickstart.md](./quickstart.md) en tablet o emulación táctil: se agarra el borde con el dedo, la página no hace scroll bajo el dedo, y no se confunden el asa de arrastre y las de borde. Comparar con la línea base anotada en T001 y reportar explícitamente si el arrastre de posición ya estaba roto antes.

---

## Phase 7: Documentación y cierre (obligatorio)

Las cuatro capas de la regla transversal de `CLAUDE.md`. Ninguna es opcional.

- [ ] T036 [P] Actualizar `resources/views/ayuda/pos-sala.blade.php` (~líneas 26-30): describir el redimensionado arrastrando el borde y retirar la mención al tamaño pequeña/mediana/grande. Respetar la convención de markup de la ayuda (intro, `<ol>` de pasos, `<p class="ayuda-nota">` final) y que casi no haga falta scrollear.
- [ ] T037 [P] Actualizar `resources/ia/conocimiento/pos-hosteleria.md`, sección "Plano de sala arrastrable" (~líneas 21-36): hoy dice literalmente que la mesa tiene "su **tamaño** (pequeña, mediana, grande)"; pasa a describir la ocupación en celdas, el bloqueo al crecer y que las formas ya no desbordan sobre celdas ajenas.
- [ ] T038 [P] Actualizar `docs/03-modelo-datos.md`: columnas `ancho_celdas`/`alto_celdas` en `pos_mesas`, retirada de `tamano`, y el invariante de no solapamiento.
- [ ] T039 [P] Añadir a `docs/04-front-guidelines.md` la convención reutilizable que salió de esta feature: cuando el borde de un elemento ya comunica estado, el feedback de bloqueo/rechazo va por sombra y micro-movimiento, nunca por color de borde. Una convención que solo vive en el código se vuelve a violar.
- [ ] T040 Revisar el tamaño de `public/js/plugins-init/pos-sala-plano.init.js` tras el cambio: si supera ~600 líneas, partirlo con el patrón orquestador + módulos registrados de la feature 038 (`docs/04-front-guidelines.md`, "Partición de un archivo JS grande"), no dejarlo crecer.
- [ ] T041 Comprobar que no queda ninguna referencia viva a `tamano` de mesa en el código (lista de T003) y ejecutar la suite completa del POS: `php artisan test --filter=Pos`.
- [ ] T042 Recorrer [quickstart.md](./quickstart.md) de principio a fin como validación final, incluida la sección 1 (conversión) sobre datos de demo reales.

---

## Dependencias

```
Phase 1 (Setup)
   └─> Phase 2 (Foundational: datos + servidor)   ← BLOQUEANTE
          ├─> Phase 3 (US1: redimensionar)
          │      └─> Phase 4 (US2: bloqueo por colisión)   [necesita dimensiones() y el resizable de US1]
          │             └─> Phase 5 (US3: sillas)
          │                    └─> Phase 6 (táctil)
          └─────────────────────────────────────────> Phase 7 (documentación y cierre)
```

- **US1 → US2**: US2 opera sobre el widget y el modelo en celdas que instala US1. No son
  independientes entre sí; sí lo son sus criterios de aceptación y su validación manual.
- **US3** es cosmética y podría entregarse aparte, pero necesita `ancho`/`alto` del estado (US1).
- **Phase 6** aplica a lo construido en US1/US2 y por eso va después, aunque es transversal.
- **T016** cierra la fase 2 y es el punto donde los tests de T004-T006 pasan a verde.

## Oportunidades de paralelización

- **Fase 2, tests**: T004, T005 y T006 son tres archivos distintos → en paralelo.
- **Fase 2, implementación**: T010 (modelo), T012 (`PosPlanoCeldas`), T014 (`SalaController`) y T015
  (factories) tocan archivos distintos → en paralelo, una vez existe la migración (T007-T009).
  T011 y T013 van en serie con el resto porque T013 depende del contrato que T011 impone.
- **Fase 3**: T021 (vista + retirada del popover) y T022 (CSS de asas) son paralelizables entre sí,
  pero ambas dependen de T017-T020, que tocan el mismo archivo JS y van en serie.
- **Fase 7**: T036, T037, T038 y T039 son cuatro archivos independientes → en paralelo.

## Estrategia de entrega

- **MVP mínimo real**: Phase 1 + Phase 2 + Phase 3 (US1). Ya entrega la petición del usuario
  —agrandar y encoger arrastrando el borde— con la barrera de servidor protegiendo los datos.
- **Incremento imprescindible**: Phase 4 (US2). Sin él, la interfaz permitiría intentos que el
  servidor rechaza al guardar, lo que sería una experiencia peor que la actual. **No entregar US1 sin
  US2 a usuarios reales**, aunque sean fases separadas.
- **Incrementos siguientes**: Phase 5 (sillas) y Phase 6 (táctil). La 6 es la que decide si la
  feature sirve realmente en el sitio donde se usa el POS.
- **Phase 7 no es opcional ni "para después"**: una guía in-app que sigue hablando de un selector de
  tamaños que ya no existe le miente al usuario, y una base de conocimiento desactualizada hace que
  el asistente IA explique mal la app.
