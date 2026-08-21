# Tasks: Vista de plano en la Sala en modo servicio

**Feature**: 041-plano-sala-servicio | **Fecha**: 2026-08-20

**Input**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/vista-sala.md](./contracts/vista-sala.md),
[quickstart.md](./quickstart.md)

**Tests**: se incluye **un** test de backend. Motivo: no hay lógica de negocio nueva (D8), pero la
vista pasa a depender de diez campos del payload de la Sala que hoy solo protege el uso de la vista
de tarjetas — y esa no usa la geometría. Sin ese test, un refactor del controller rompería el plano
en silencio. El resto de la validación es manual (`quickstart.md`), porque el proyecto no tiene
runner de JS y montarlo para esta feature sería desproporcionado (Principio V).

**Convención de formato**: `- [ ] T### [P?] [US?] Descripción con ruta de archivo`.
`[P]` = paralelizable (archivo distinto, sin dependencias pendientes).

---

## Phase 1: Setup

- [ ] T001 Anotar la línea base antes de tocar nada: abrir la Sala con los **dos** usuarios de los prerrequisitos de [quickstart.md](./quickstart.md) (uno con `ver-configuracion` y otro sin él) y registrar qué ve cada uno hoy. Es la referencia contra la que se comprobará FR-004 y que no se rompió FR-019.
- [X] T002 [P] Confirmar que `App\Http\Controllers\Pos\SalaController` ya entrega los diez campos que [contracts/vista-sala.md](./contracts/vista-sala.md) declara contrato (`fila`, `columna`, `ancho_celdas`, `alto_celdas`, `forma`, `estado`, `olvidada`, `pendiente`, `abierta_hace_min`, `abrir_url`), para dejar por escrito que esta feature **no necesita cambios de servidor** (D8).
- [X] T003 [P] Inventariar en `public/js/plugins-init/pos-sala-plano.init.js` qué funciones son de **dibujo puro** (`dimensiones`, `aPx`, `aCeldas`, `sillasParaMesa`, la parte de `renderMesaHtml` que calcula contorno y clases) y cuáles son de **edición** (arrastre, redimensionado, popover, guardado). Es la lista que decide qué se extrae en T005 y qué se queda.

---

## Phase 2: Foundational — el dibujo deja de ser propiedad del editor

**BLOQUEANTE**: ninguna historia puede completarse antes de esta fase. Al terminarla, el editor
funciona **exactamente igual que antes** pero dibujando a través del módulo compartido, que es lo
que permite que la vista de servicio dibuje lo mismo sin copiar nada (D1).

### Test primero

- [X] T004 Escribir `tests/Feature/Pos/SalaPayloadPlanoTest.php`: el payload de `GET /pos/sala` incluye, para cada mesa, los diez campos de contrato de [contracts/vista-sala.md](./contracts/vista-sala.md) con sus tipos; una mesa sin posición llega con `fila`/`columna` a `null`; y `olvidada` viene decidido por el servidor según el umbral del tenant (no derivable en cliente, FR-008). Debe fallar en rojo si alguno de esos campos desaparece.

### Implementación

- [X] T005 Crear `public/js/plugins-init/pos-plano-dibujo.js` exponiendo `window.PosPlanoDibujo` con la API de [contracts/vista-sala.md](./contracts/vista-sala.md) §2 (constantes de rejilla, `dimensiones`, `aPx`, `aCeldas`, `sillasParaMesa`, `claseEstado`, `mesaHtml(mesa, opciones)`). Mover ahí las funciones de dibujo identificadas en T003, **sin cambiar su comportamiento**. Crítico: este archivo va **fuera** del guard `if (!state.puedeEditar) { return; }`, o el usuario sin permiso de configuración se queda sin dibujo — que es justo el caso que la feature viene a servir (D1).
- [X] T006 En `mesaHtml(mesa, opciones)`, implementar los dos modos que fija el contrato: `opciones.modo === 'edicion'` añade el asa circular de arrastre; `'servicio'` añade el bloque de texto de servicio y **nunca** asas. El contorno (posición, tamaño, forma, sillas, clase de estado) debe ser idéntico en ambos: es el invariante del módulo.
- [X] T007 Unificar la regla de estado en `claseEstado(mesa)` (`libre` / `ocupada` / `olvidada`) y hacer que la consuman **las tres** vistas: el editor, el plano de servicio y la rejilla de tarjetas de `public/js/plugins-init/pos-sala.init.js`. Hoy esa regla está escrita dos veces (una en cada archivo); dejarla duplicada es lo que permitiría que plano y tarjeta se contradigan (I2 de [data-model.md](./data-model.md)). **Ojo**: esta tarea toca la vista de tarjetas, que FR-019 obliga a dejar idéntica — el resultado de `claseEstado()` debe coincidir con el de la regla actual en todos los casos, y T033 lo verifica contra la línea base de T001.
- [X] T008 Reescribir `public/js/plugins-init/pos-sala-plano.init.js` para que consuma `PosPlanoDibujo` en vez de sus copias locales, y borrar las funciones ya movidas. El editor debe seguir pasando el guion de la feature 040 sin ningún cambio de comportamiento (FR-020).
- [X] T009 Registrar el nuevo `<script>` en `resources/views/pos/sala.blade.php` **antes** que `pos-sala-plano.init.js` y que el init de servicio, siguiendo el orden orquestador→módulos de `docs/04-front-guidelines.md` ("Partición de un archivo JS grande…").
- [ ] T010 Ejecutar `php artisan test --filter=Pos` y dejar T004 en verde, y recorrer los pasos 2 y 3 de `specs/040-pos-mesas-redimensionables/quickstart.md` para confirmar que **el editor no se rompió** con la extracción.

**Checkpoint**: existe una sola implementación del dibujo, el editor la usa y sigue intacto.

---

## Phase 3: User Story 1 — Ver la sala como es y abrir cuentas tocando (P1)

**Objetivo**: el camarero cambia a vista de plano sin entrar en edición, ve el lienzo y toca una mesa
para ir a su ticket.

**Test independiente**: pasos 1 y 2 de [quickstart.md](./quickstart.md) — entrar en la Sala, cambiar
a plano sin tocar "Editar plano", tocar una mesa libre y otra ocupada.

- [X] T011 [US1] Añadir en `resources/views/pos/sala.blade.php` el selector de vista (tarjetas / plano) en `.pos-sala-acciones`, junto a "Actualizar", **sin `@can`** (FR-004), con altura táctil mínima de 44px y el estado activo marcado (FR-002). No reutilizar `.pos-filtro`: ese componente está reservado al filtro por zona y este control no filtra (D3).
- [X] T012 [US1] Añadir en `resources/views/pos/sala.blade.php` el contenedor de la vista de plano en servicio, hermano de `#pos-sala-mesas` y **distinto** de `.pos-plano-wrap` (que es del editor), con su lienzo y el hueco de la franja de mesas sin sitio.
- [X] T013 [US1] Crear `public/js/plugins-init/pos-sala-plano-servicio.init.js`: lee `window.posSalaData`, filtra las mesas de la zona activa con posición, y pinta el lienzo con `PosPlanoDibujo.mesaHtml(mesa, { modo: 'servicio' })` — misma posición, forma y ocupación en celdas que en el editor (FR-001, FR-005). Sin `draggable`, sin `resizable`, sin popover y sin ninguna petición de escritura (FR-015, I3 de [data-model.md](./data-model.md)).
- [X] T014 [US1] Implementar el alternado de vista en `public/js/plugins-init/pos-sala.init.js`: mostrar/ocultar la rejilla de tarjetas y el contenedor de plano según la vista activa, y repintar el plano al activarlo. La vista por defecto es **tarjetas**, para no cambiarle la Sala a nadie sin que lo pida (FR-019).
- [X] T015 [US1] Implementar el toque por **delegación** en el contenedor del plano (D7): al tocar una mesa se resuelve el destino con la misma regla que la tarjeta —libre → `abrir_url` + `?mesa=<id>`; ocupada → `abrir_url`— reutilizando esa decisión en vez de reescribirla, para que ambas vistas no puedan divergir (FR-013, FR-014). Resolver el destino desde el elemento tocado **en el momento del toque**, nunca desde un índice capturado antes, para que un repintado no desvíe un toque en curso (FR-017).
- [X] T016 [US1] Suscribir el plano de servicio a `pos-sala:actualizado` y `pos-sala:zona-cambiada` en `public/js/plugins-init/pos-sala-plano-servicio.init.js`, para que refleje el estado y la zona nuevos sin recargar la página ni reelegir la vista (FR-016).
- [ ] T017 [US1] Verificar manualmente los pasos 1 y 2 de [quickstart.md](./quickstart.md): que con el plano activo basta **un toque** sobre la mesa para llegar a su ticket (SC-001), y que arrastrar una mesa o su borde no hace absolutamente nada (SC-007).

**Checkpoint**: US1 entregable — ya se puede trabajar sobre el plano, aunque todavía no comunique
estado ni recuerde la preferencia.

---

## Phase 4: User Story 2 — Leer el estado de un vistazo (P1)

**Objetivo**: el plano dice qué mesas están libres, ocupadas y olvidadas, y cuánto deben.

**Test independiente**: pasos 3 de [quickstart.md](./quickstart.md), con tres mesas en los tres
estados.

- [X] T018 [US2] Aplicar en `PosPlanoDibujo` (`public/js/plugins-init/pos-plano-dibujo.js`) la clase de estado devuelta por `claseEstado()` al contorno de la mesa en modo servicio, de modo que el **borde** comunique libre/ocupada/olvidada con el mismo código de color que la tarjeta (FR-006). Es la convención "Tarjeta de mesa y sus tres estados" de `docs/04-front-guidelines.md`: el color del borde está reservado a esto.
- [X] T019 [US2] Añadir en modo servicio el bloque de texto de la mesa: nombre siempre; si está ocupada, importe pendiente y tiempo en **formato compacto** (`12′`, no `Hace 12 min`), según D4 — la misma información que muestra su tarjeta (FR-007). El importe se muestra tal cual lo entrega el servidor, sin recalcularlo ni reformatearlo con aritmética propia (Principio III).
- [X] T020 [P] [US2] Añadir en `resources/views/pos/sala.blade.php` el CSS del texto de mesa en modo servicio: truncado con elipsis, sin desbordar el contorno y legible en una mesa de 1×1 (96px), incluido el caso de importe de cuatro cifras (FR-009).
- [X] T021 [US2] Comprobar que las métricas de cabecera (total / libres / ocupadas / olvidadas) siguen calculándose una sola vez en `public/js/plugins-init/pos-sala.init.js` y coinciden con lo dibujado en **ambas** vistas (FR-018, SC-006). No duplicar el conteo en el init de servicio.
- [ ] T022 [US2] Verificar manualmente los pasos 3 de [quickstart.md](./quickstart.md), contrastando importe y estado de cada mesa contra su tarjeta equivalente: que los tres estados se distinguen **sin leer el texto** (SC-002) y que tras cobrar una cuenta el plano refleja el cambio al refrescar, sin recargar la página (SC-005).

**Checkpoint**: el plano ya es una herramienta de servicio, no un dibujo.

---

## Phase 5: User Story 3 — Elegir la vista y que la recuerde (P2)

**Objetivo**: la vista elegida sobrevive a salir y volver a la Sala, por usuario.

**Test independiente**: pasos 4 de [quickstart.md](./quickstart.md).

- [X] T023 [US3] Persistir la vista elegida en `localStorage` bajo `pos-sala-vista:<userId>` desde `public/js/plugins-init/pos-sala.init.js` (D2), con `tarjetas` como valor por defecto ante clave ausente o valor inválido.
- [X] T024 [US3] Exponer el id del usuario a la vista en `resources/views/pos/sala.blade.php` (en el objeto de estado que ya se pasa al JS) para poder componer la clave. Sin el id, dos personas que comparten la misma tablet —el caso normal en hostelería— se pisarían la preferencia (FR-003).
- [X] T025 [US3] Restaurar la vista guardada al cargar la Sala, **antes** del primer pintado, para que no se vea un parpadeo de tarjetas→plano en cada entrada.
- [ ] T026 [US3] Verificar manualmente los pasos 4 de [quickstart.md](./quickstart.md): que la vista elegida se conserva en todas las vueltas a la Sala dentro de la sesión (SC-003), incluida la comprobación con dos usuarios distintos en el mismo navegador (FR-003).

---

## Phase 6: Casos borde (transversal a US1 y US2)

Sin esta fase la feature es insegura de usar en un turno real: una mesa invisible es una mesa a la
que no se le puede cobrar.

- [X] T027 Resolver el filtro "Todas" al entrar en vista de plano (D6): seleccionar la primera zona disponible y **reflejarlo en el filtro** disparando el evento `pos-sala:zona-cambiada` que ya existe, y mostrar "Todas" deshabilitado mientras dure la vista de plano. Deshabilitar y no ocultar, para que la fila de filtros no cambie de tamaño al alternar (FR-010).
- [X] T028 Implementar la franja de mesas **sin posición** bajo el lienzo en `public/js/plugins-init/pos-sala-plano-servicio.init.js`, reutilizando el componente de tarjeta `.pos-mesa` con su estado y su mismo comportamiento al tocarlas (D5, FR-011). Cero markup y cero CSS nuevos para este caso.
- [X] T029 [P] Añadir el encabezado de esa franja y el mensaje de zona sin mesas dibujables en `resources/views/pos/sala.blade.php` (FR-012), de modo que un lienzo vacío nunca parezca un fallo.
- [X] T030 Implementar el encaje del lienzo en pantalla (D9): cuando el ancho disponible sea menor que el del lienzo, escalarlo proporcionalmente para que el ancho siempre quepa, dejando como mucho scroll vertical (FR-021). **Solo en la vista de servicio**: el editor conserva el tamaño real de celda (FR-020).
- [ ] T031 Verificar manualmente los pasos 5 de [quickstart.md](./quickstart.md) — los cinco casos borde, con especial atención al de la mesa sin sitio, que es el que decide si la feature es segura en un turno (SC-004).

---

## Phase 7: Permisos y no-regresión

- [ ] T032 Verificar los pasos 6 de [quickstart.md](./quickstart.md) con el usuario **sin** `ver-configuracion`: ve el selector, usa el plano y abre cuentas, pero no ve "Editar plano" ni ningún control de edición (FR-004, FR-020). Confirmar en las herramientas de red que la vista **no emite ninguna petición de escritura**.
- [ ] T033 Recorrer el guion completo de `specs/040-pos-mesas-redimensionables/quickstart.md` para confirmar que el editor de plano quedó intacto tras la extracción del dibujo (FR-020), y comprobar que la vista de tarjetas se comporta igual que en la línea base de T001 (FR-019).

---

## Phase 8: Documentación y cierre (obligatorio)

Las cuatro capas de la regla transversal de `CLAUDE.md`. Ninguna es opcional.

- [X] T034 [P] Actualizar `resources/views/ayuda/pos-sala.blade.php`: explicar que la Sala se puede ver como tarjetas o como plano, que la elección se recuerda, y que tocar una mesa del plano abre su cuenta igual que su tarjeta. Respetar la convención de markup de la ayuda (intro, `<ol>` de pasos, `<p class="ayuda-nota">` final) y que casi no haga falta scrollear.
- [X] T035 [P] Actualizar `resources/ia/conocimiento/pos-hosteleria.md`: hoy la sección del plano dice que solo se ve en modo edición; pasa a describir las dos vistas de la Sala, quién puede usar cada una y qué pasa con "Todas" y con las mesas sin sitio.
- [X] T036 [P] Añadir a `docs/04-front-guidelines.md` la convención reutilizable que sale de esta feature: cuando dos vistas muestran **los mismos datos en envases distintos**, el cálculo de estado y el destino de la interacción se comparten en un solo sitio; duplicarlos es lo que hace que las vistas acaben contradiciéndose. Citar `PosPlanoDibujo.claseEstado()` como ejemplo vivo.
- [X] T037 Revisar el tamaño de `public/js/plugins-init/pos-sala-plano.init.js` tras la extracción y confirmar que bajó del umbral de partición; si `pos-sala-plano-servicio.init.js` superase ~400 líneas, aplicarle el mismo patrón antes de cerrar.
- [X] T038 **No** actualizar `docs/03-modelo-datos.md`: esta feature no toca el esquema (ver [data-model.md](./data-model.md)). Dejar constancia explícita de esa revisión al cerrar, según la regla de "razonar si queda documentación por actualizar, aunque la conclusión sea que no hace falta".
- [ ] T039 Ejecutar `php artisan test --filter=Pos` y recorrer [quickstart.md](./quickstart.md) de principio a fin como validación final.

---

## Dependencias

```
Phase 1 (Setup)
   └─> Phase 2 (Foundational: extracción del dibujo)   ← BLOQUEANTE
          └─> Phase 3 (US1: ver el plano y tocar)
                 ├─> Phase 4 (US2: estado y texto)     [necesita mesaHtml en modo servicio]
                 │      └─> Phase 6 (casos borde)
                 ├─> Phase 5 (US3: preferencia)        [independiente de US2]
                 └─> Phase 7 (permisos y no-regresión)
                        └─> Phase 8 (documentación y cierre)
```

- **US1 → US2**: US2 escribe dentro del contorno que US1 hace aparecer. No son independientes entre
  sí; sí lo son sus criterios de aceptación.
- **US3** es independiente de US2: la preferencia de vista no necesita que el plano comunique estado.
  Puede desarrollarse en paralelo a la fase 4.
- **Phase 6** se apoya en US1 y US2 y por eso va después, aunque sus casos son transversales.

## Oportunidades de paralelización

- **Fase 1**: T002 y T003 miran archivos distintos → en paralelo.
- **Fase 4**: T020 (CSS en la vista) es paralelizable con T018/T019 (JS del módulo de dibujo).
- **Fase 5 y Fase 4**: son ramas independientes del grafo; dos personas podrían llevarlas a la vez.
- **Fase 6**: T029 (markup en la vista) es paralelizable con T027/T028/T030 (JS).
- **Fase 8**: T034, T035 y T036 son tres archivos independientes → en paralelo.

## Estrategia de entrega

- **MVP mínimo real**: Phase 1 + Phase 2 + Phase 3 (US1) + Phase 4 (US2). US1 sin US2 sería un plano
  que no dice el estado de las mesas: bonito e inútil para el servicio, y peor que las tarjetas que
  viene a mejorar. **No entregar US1 sin US2 a usuarios reales.**
- **Incremento siguiente**: Phase 6 (casos borde). Es la que hace la feature **segura**: sin ella,
  una mesa sin sitio en la rejilla desaparece de la vista y nadie puede cobrarla. Debe entrar antes
  de que alguien la use en un turno de verdad.
- **Incremento cómodo**: Phase 5 (US3). Sin ella la feature funciona, pero obliga a repetir el gesto
  decenas de veces por turno.
- **Phase 8 no es opcional ni "para después"**: una guía in-app que solo habla de tarjetas le miente
  al usuario, y una base de conocimiento desactualizada hace que el asistente IA explique mal la app.
