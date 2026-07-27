---

description: "Task list — Menú lateral personalizable por tenant"
---

# Tasks: Menú lateral personalizable por tenant

**Input**: Design documents from `specs/036-menu-personalizable-tenant/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/configuracion-menu.md](./contracts/configuracion-menu.md)

**Tests**: incluidos y **obligatorios** para el aislamiento multi-tenant (Principio IV de la
constitución: test-first, deben fallar primero). El resto de tests son de apoyo y siguen el flujo
flexible que el propio principio permite.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivo distinto, sin dependencias)
- **[Story]**: US1 (renombrar, P1), US2 (reordenar, P2), US3 (restaurar, P3), o `-` para trabajo compartido

## Path Conventions

Monolito Laravel en la raíz del repo: `app/`, `resources/views/`, `public/`, `routes/`, `tests/`,
`docs/`. Ver "Source Code" en [plan.md](./plan.md).

---

## Phase 1: Setup (infraestructura compartida)

**Propósito**: dejar disponibles las piezas que las tres historias necesitan. Ninguna cambia el
comportamiento visible todavía.

- [X] **T001** `[-]` Vendorizar jQuery UI (solo `sortable` y sus dependencias) desde
  `template/Laravel-NexaDash-v1.0-28_May_2025/package/public/vendor/jqueryui/` a
  `public/vendor/jqueryui/` (`js/jquery-ui.min.js`, `css/jquery-ui.min.css`). Verificar el
  `require`/`define` del wrapper UMD por si arrastra dependencias que también haya que vendorizar
  (regla de `CLAUDE.md`). **No** cargarlo globalmente en `layouts/app.blade.php`: se carga desde la
  tab con `@push('styles')` / `@push('scripts')`. Recordar que `@stack('styles')` va **antes** de
  `css/style.css` (regla de orden de CSS de `CLAUDE.md`).

- [X] **T002** `[-]` Crear `app/Support/CatalogoMenu.php` con la constante del catálogo completo
  (10 elementos de primer nivel + 26 entradas de segundo nivel = 36), con los campos `clave`, `etiqueta`, `icono`, `ruta`, `permiso`,
  `hijos`, transcritos **uno a uno** desde `resources/views/partials/sidebar.blade.php` —
  data-model.md §1. Exponer además un accesor plano (clave → elemento) para las validaciones.

- [X] **T003** `[P]` `[-]` Test `tests/Feature/Configuracion/CatalogoMenuTest.php`: verifica los
  invariantes INV-1 a INV-4 de data-model.md §1 — claves únicas, toda `ruta` registrada
  (`Route::has`), todo `permiso` presente en `App\Support\CatalogoPermisos::PERMISOS`, y jerarquía
  de exactamente dos niveles. Es la red que impide que un renombrado de permiso o de ruta deje una
  sección invisible en silencio.

**Checkpoint**: el catálogo existe y es coherente con las rutas y permisos reales; nada visible ha
cambiado.

---

## Phase 2: Núcleo — capa de personalización y render del sidebar

**Propósito**: el trabajo del que dependen las tres historias. ⚠️ Es la fase de mayor riesgo (se
reescribe la navegación de toda la app); se cierra solo cuando T008 esté en verde.

- [X] **T004** `[-]` **(TEST-FIRST, Principio I — debe fallar primero)** Crear
  `tests/Feature/Configuracion/MenuAislamientoTenantTest.php` con ≥2 tenants: la personalización
  guardada por el tenant A no es legible ni modificable desde el tenant B, y el menú de B sigue en
  sus valores por defecto (FR-012, SC-004). Ejecutarlo y **comprobar que falla** antes de T005.

- [X] **T005** `[-]` Crear `app/Support/MenuTenant.php` con `estructura()`, `guardar()` y
  `restaurar()` (data-model.md §5), incluyendo el algoritmo de fusión de §3 (orden parcial, claves
  desconocidas descartadas, elementos ausentes al final, etiqueta vacía → valor por defecto) y la
  memoización por request indexada por `tenant_id`. Persistencia: fila única
  `menu.personalizacion` / `grupo = menu` / `tipo = json` en `configuraciones`, con el mismo patrón
  `updateOrCreate` que `ConfiguracionController::updateCrm`. Al terminar, **T004 debe pasar**.

- [X] **T006** `[P]` `[-]` Test unitario/feature `tests/Feature/Configuracion/MenuFusionTest.php`
  sobre las reglas de fusión: JSON vacío, JSON corrupto, clave inexistente en `orden` y en
  `etiquetas`, elemento del catálogo ausente del `orden` guardado (debe quedar al final de su
  nivel), y etiqueta en blanco (debe caer al valor por defecto). Cubre FR-007, FR-010, FR-017,
  FR-018.

- [X] **T007** `[-]` Reescribir el bloque de menú del tenant en
  `resources/views/partials/sidebar.blade.php` para iterar `MenuTenant::estructura()`
  (research.md D2): visibilidad de entrada por `@can($elemento['permiso'])`, visibilidad de grupo
  derivada de tener ≥1 hijo visible, omisión de elementos cuya ruta no exista (`Route::has`),
  `<x-lordicon ... size="30">` y `<span class="nav-text ms-2">` emitidos por la plantilla del bucle
  (regla "Menú lateral: tamaño de ícono y texto"), enlace directo cuando el elemento no tiene hijos
  pero sí ruta, y badge de alertas condicionado a la **clave** `control-fichaje`/`alertas`, nunca a
  la etiqueta (research.md D6). **No tocar** el bloque `isSuperAdmin()`, la tarjeta de usuario, el
  botón de ayuda ni el toggle de Dark Mode.

- [X] **T008** `[-]` Crear `tests/Feature/Configuracion/MenuSidebarRenderTest.php`: para al menos
  tres roles distintos (administrador, rol acotado sin `ver-facturas`, rol mínimo), el conjunto de
  entradas visibles en el sidebar es **idéntico** al esperado del catálogo filtrado por permisos, y
  el badge de alertas sigue apareciendo aunque se renombre "Alertas" (FR-011, SC-003, SC-005).
  Afirmar sobre marcadores de HTML (rutas/atributos), **no** sobre texto plano que también pueda
  aparecer en el panel de ayuda.

**Checkpoint**: el menú se pinta desde el catálogo y ningún rol ve más ni menos secciones que antes.
La app es utilizable aunque todavía no exista la tab.

---

## Phase 3: User Story 1 — Renombrar elementos del menú (P1) 🎯 MVP

**Objetivo**: una persona con `ver-configuracion` puede cambiar el nombre visible de cualquier
elemento y verlo en su sidebar.

**Independent test**: renombrar un único elemento, guardar, navegar a otra pantalla y ver el nombre
nuevo; otro tenant sigue viendo el valor por defecto.

- [X] **T009** `[P]` `[US1]` Crear `app/Http/Requests/ActualizarMenuRequest.php` con las reglas de
  data-model.md §4: `etiquetas` array opcional; `etiquetas.*` requerido, string, `max:40`, no solo
  espacios; `orden` array opcional de arrays de strings. `authorize()` apoyado en
  `ver-configuracion`. Mensajes en español y claves de error por elemento (`etiquetas.<clave>`).

- [X] **T010** `[US1]` Añadir `updateMenu()` a `app/Http/Controllers/ConfiguracionController.php`
  según [contracts/configuracion-menu.md](./contracts/configuracion-menu.md) §1: valida con T009,
  delega en `MenuTenant::guardar()`, registra la actividad con `RegistradorActividad`
  (`AccionLogActividad::Modificacion` + `EntidadLogActividad::Configuracion`, FR-022) y responde
  **siempre JSON**. Registrar la ruta `PUT|PATCH /configuracion/menu`
  (`configuracion.menu.update`) en `routes/web.php`, **dentro** del grupo
  `can:ver-configuracion` existente.

- [X] **T011** `[US1]` Pasar `menuEstructura` a la vista desde `ConfiguracionController::show()`
  (contrato §3), con `clave`, `etiqueta`, `etiqueta_defecto` e `hijos` por elemento.

- [X] **T012** `[US1]` Crear `resources/views/configuracion/_tab_menu.blade.php`: lista jerárquica
  (grupos con sus entradas anidadas, FR-020a) donde cada fila lleva el input de nombre — **sin**
  clase `.form-control-sm` (el tamaño "sm" es global) —, el nombre por defecto como texto de apoyo
  (FR-020) y un `<div class="invalid-feedback" data-error-for="etiquetas.<clave>">`. Al pie, el
  botón **Guardar** y el botón **Restaurar valores por defecto**. Registrar la tab "Menú" en
  `resources/views/configuracion/index.blade.php` (botón `nav-link` + `tab-pane` con el `@include`),
  siguiendo el patrón exacto de las tabs ya presentes.

- [X] **T013** `[US1]` Crear `public/js/plugins-init/configuracion-menu.init.js` con el submit del
  formulario por `$.ajax` (`dataType: 'json'`, `Accept: application/json`), usando
  `window.withButtonLoading` en el botón Guardar (regla "Estado de carga en botones"),
  `window.showToast` para éxito y error genérico (regla "Notificaciones"), y volcado de un `422` en
  el `.invalid-feedback` de cada elemento afectado. Limpiar errores previos en cada envío.

- [X] **T014** `[P]` `[US1]` Crear `public/css/configuracion-menu.css` con los estilos de la lista
  jerárquica (indentación de las entradas respecto a su grupo, asa de arrastre, fila y estado
  arrastrando). Cargar CSS y JS desde la tab con `@push('styles')` / `@push('scripts')`.

- [X] **T015** `[US1]` Crear `tests/Feature/Configuracion/MenuPersonalizadoTest.php` (parte de
  renombrar): guardado correcto persiste la fila; nombre vacío y nombre de 41 caracteres devuelven
  `422` con la clave de error del elemento; clave desconocida en `etiquetas` se descarta sin error;
  una etiqueta idéntica al valor por defecto **no** se persiste (research.md D4); un usuario sin
  `ver-configuracion` recibe `403` (FR-013); y se registra una entrada en `logs_actividad`
  (FR-022).

**Checkpoint**: US1 entregable por sí sola. Renombrar funciona de punta a punta; el orden todavía es
el de por defecto y no hay arrastre.

---

## Phase 4: User Story 2 — Reordenar arrastrando y soltando (P2)

**Objetivo**: reordenar grupos entre sí y entradas dentro de su grupo, confinado a su nivel.

**Independent test**: arrastrar un grupo a la primera posición, guardar y verlo primero en el
sidebar, sin haber renombrado nada.

- [X] **T016** `[US2]` Ampliar `configuracion-menu.init.js`: inicializar el `sortable` de la lista
  de grupos y **un `sortable` independiente por cada grupo** para sus entradas —
  **sin `connectWith`**, que es lo que confina el arrastre a su nivel por construcción
  (research.md D3, FR-008a). Configurar `handle` sobre el asa de arrastre (para que tocar el input
  de texto no inicie un arrastre en tablet) y una `distance` mínima para no confundir arrastre con
  scroll (research.md D5).

- [X] **T017** `[US2]` Ampliar el submit de `configuracion-menu.init.js` para enviar también
  `orden`, leyendo el DOM en el momento del envío: `orden._raiz` con las claves de los grupos en su
  orden actual, y una entrada por grupo con las claves de sus hijos. Nada se persiste hasta pulsar
  Guardar (FR-020b): el arrastre solo mueve el DOM.

- [X] **T018** `[US2]` Añadir a `MenuPersonalizadoTest` los casos de orden: el orden guardado se
  persiste y se refleja en `MenuTenant::estructura()`; un `orden` parcial deja el resto de elementos
  al final de su nivel en su orden por defecto; claves desconocidas en `orden` se descartan; y el
  servidor reconstruye desde el catálogo aunque la petición llegue incompleta (FR-018, D4).

- [X] **T019** `[US2]` Añadir a `MenuSidebarRenderTest` la comprobación de que el sidebar respeta el
  orden personalizado (afirmando sobre la **posición relativa** de los marcadores HTML de cada
  entrada) y de que un rol acotado ve sus entradas permitidas en ese orden, sin huecos (FR-009,
  escenario 3 de la US2).

**Checkpoint**: US1 + US2 funcionando juntas. Falta la red de seguridad de restaurar.

---

## Phase 5: User Story 3 — Restaurar valores por defecto (P3)

**Objetivo**: volver al menú original en una acción confirmada.

**Independent test**: personalizar nombre y orden, restaurar, confirmar, y comprobar que el sidebar
vuelve al menú original.

- [X] **T020** `[US3]` Añadir `restaurarMenu()` al `ConfiguracionController` según el contrato §2:
  delega en `MenuTenant::restaurar()`, registra la actividad y responde `200` con el mensaje y la
  estructura por defecto. Es **idempotente**. Registrar la ruta
  `DELETE /configuracion/menu` (`configuracion.menu.restaurar`) dentro del grupo
  `can:ver-configuracion`.

- [X] **T021** `[US3]` Cablear el botón "Restaurar valores por defecto" en
  `configuracion-menu.init.js` con `window.confirmDelete(mensaje, onConfirm, { confirmLabel:
  'Restaurar', confirmClass: 'btn-primary' })` — **nunca** `confirm()` nativo, y sin el aspecto de
  borrado por defecto (regla "Confirmación de acciones irreversibles"). El `onConfirm` **debe
  devolver** el `$.ajax` para que el modal muestre su spinner y se cierre al terminar. Al recibir la
  respuesta, repintar la lista con la estructura por defecto y mostrar el toast.

- [X] **T022** `[US3]` Añadir a `MenuPersonalizadoTest`: restaurar borra la fila de configuración;
  restaurar un tenant nunca personalizado responde `200` sin error (idempotencia); tras restaurar,
  `MenuTenant::estructura()` es idéntica al catálogo (FR-016, SC-006); y un usuario sin
  `ver-configuracion` recibe `403`.

**Checkpoint**: las tres historias completas.

---

## Phase 6: Pulido y cierre documental

**Obligatorio antes de dar la feature por terminada** (regla "Documentación al día en TODO cambio"
de `CLAUDE.md`: las 4 capas).

- [X] **T023** `[P]` `[-]` **Capa 2 — Guías de front**: anotar en `docs/04-front-guidelines.md` (a)
  la **excepción documentada** a "Listados: SIEMPRE DataTable" para el editor de menú, con su
  motivo (no es un listado de registros; DataTables impide el arrastre anidado), y (b) el patrón
  reutilizable de lista jerárquica arrastrable (sortables sin `connectWith` para confinar por nivel,
  asa de arrastre obligatoria, guardado por botón único). Añadir también la nota de que el sidebar
  pasó a estar dirigido por `CatalogoMenu`, de modo que **una entrada nueva del menú se añade al
  catálogo**, no al Blade — actualizando en consecuencia la sección "Nueva entrada de menú ⇒ nuevo
  permiso", cuyo paso 3 hoy dice editar `sidebar.blade.php`.

- [X] **T024** `[P]` `[-]` **Capa 3 — Ayuda in-app**: actualizar
  `resources/views/ayuda/configuracion.blade.php` con la tab Menú (qué hace, pasos, error común a
  evitar), respetando la convención de markup (`<p>` de intro, `<ol>` de pasos, `<strong>` en los
  términos, `<p class="ayuda-nota">` final) y la regla de longitud (que no haga falta scrollear).

- [X] **T025** `[P]` `[-]` **Capa 4 — Base de conocimiento IA**: actualizar
  `resources/ia/conocimiento/configuracion.md` con la personalización del menú (FR-013 de la
  feature 030), sin tocar el resto de archivos del directorio (invariante SC-007 de aquella
  feature).

- [X] **T026** `[-]` **Capa 1 — Docs técnicos**: revisar `docs/03-modelo-datos.md` y documentar la
  clave `menu.personalizacion` del grupo `menu` en `configuraciones` **si** el documento ya enumera
  claves de configuración; si no lo hace, dejar constancia explícita de que no hace falta tocarlo
  (no hay tabla nueva). No es necesario `/speckit-constitution`: ningún principio se ve afectado
  (ver Constitution Check del plan).

- [ ] **T027** `[-]` Recorrer la verificación manual completa de [quickstart.md](./quickstart.md)
  (secciones A–H) y dejar `php artisan test --filter=Menu` en verde.

---

## Dependencias

```text
T001 ─┐
T002 ─┴─> T003
T002 ────> T004 (test-first) ──> T005 ──> T006
                                   └────> T007 ──> T008
T005, T008 ──> Phase 3 (US1): T009 ──> T010 ──> T011 ──> T012 ──> T013 ──> T015
                              T014 [P con T013]
Phase 3 ──> Phase 4 (US2): T016 ──> T017 ──> T018, T019
Phase 4 ──> Phase 5 (US3): T020 ──> T021 ──> T022
Phase 5 ──> Phase 6: T023, T024, T025 [P entre sí] ──> T026 ──> T027
```

- **T004 antes que T005**, sin excepción (Principio IV: el test de aislamiento debe fallar primero).
- **T007/T008 antes de cualquier tarea de la tab**: si el sidebar dirigido por catálogo no reproduce
  el menú actual, todo lo que se construya encima está sobre arena.
- US2 depende de US1 solo por reutilizar la tab y su JS; el orden P1 → P2 → P3 del spec se respeta.

## Tareas paralelizables

- **T003** con T004 (archivos de test distintos).
- **T014** con T013 (CSS vs JS).
- **T023**, **T024** y **T025** entre sí (tres archivos de documentación independientes).

## Trazabilidad requisito → tarea

| Requisito | Tareas |
|---|---|
| FR-001, FR-002, FR-003 | T002, T003 |
| FR-004, FR-005, FR-006, FR-007 | T009, T012, T013, T015 |
| FR-008, FR-008a, FR-009, FR-010 | T016, T017, T018, T019 |
| FR-011, FR-014 | T007, T008 |
| FR-012 | T004, T005 |
| FR-013 | T010, T015, T020, T022 |
| FR-015, FR-016 | T020, T021, T022 |
| FR-017, FR-018 | T005, T006, T018 |
| FR-019, FR-020, FR-020a, FR-020b | T011, T012, T017 |
| FR-021 | T013, T021 |
| FR-022 | T010, T015, T020 |
| SC-001 | T012, T027 (sección A del quickstart) |
| SC-002 | T002, T003 (catálogo completo: 36 elementos) |
| SC-003, SC-005 | T008, T019 |
| SC-004 | T004 |
| SC-006 | T022, T027 (sección G) |
| SC-007 | T005, T006 |
| SC-008 | T017, T018 |
</content>
