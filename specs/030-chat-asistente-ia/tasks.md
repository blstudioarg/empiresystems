# Tasks: Chat flotante con asistente IA


> **Nota:** las tareas se ejecutaron originalmente contra Anthropic y luego se migraron a **OpenAI**
> (`openai-php/client`, modelo `gpt-4o-mini`) a pedido del usuario. Las referencias a `anthropic-ai/sdk`
> y `claude-*` en las tareas de abajo son el registro histórico; el estado actual usa OpenAI (ver research D1).
**Input**: Design documents from `/specs/030-chat-asistente-ia/`
**Prerequisites**: plan.md, research.md, data-model.md, contracts/endpoints.md, quickstart.md

**Tests**: INCLUIDOS — la constitución (Principio IV) exige test-first en: aislamiento multi-tenant, filtrado por permisos, bloqueo de acciones prohibidas, flujo de confirmación y cifrado de la clave. Los tests marcados **(test-first)** se escriben antes de su implementación y deben fallar primero.

**Organización**: por user story, para que cada una sea implementable y testeable de forma independiente. Orden de fases por prioridad: US4 (P1, prerequisito funcional), US1 (P1), US2 (P2), US3 (P3).

---

## Phase 1: Setup

- [X] T001 Instalar el SDK oficial de Anthropic: `composer require anthropic-ai/sdk` y verificar que la app arranca (`php artisan about`)
- [X] T002 Crear `config/ia.php` con `modelo` (env `IA_MODELO`, default `claude-opus-4-8`), `max_tokens` (4096), `max_mensajes` (30), `max_resultados_tool` (10), `max_iteraciones_tools` (8); añadir `IA_MODELO` comentado a `.env.example`

---

## Phase 2: Foundational (bloquea todas las user stories)

- [X] T003 [P] Crear `app/Support/IaTenant.php` (patrón `EmailTenant`): constantes `CLAVE_API_KEY = 'ia.api_key'`, métodos `configurada(): bool`, `apiKey(): string` (descifra con `Crypt`), `apiKeyEnmascarada(): string` (`sk-ant-…XXXX`), `guardarApiKey(?string)` (cifra o borra la fila del grupo `ia`)
- [X] T004 [P] Crear contrato base `app/Ia/Tools/ToolAsistente.php` (interface o abstract): `nombre()`, `descripcion()`, `schema()`, `permisoRequerido()`, `esLectura()`, `ejecutar(array): array`, `proponer(array): array` (default: excepción en lecturas)
- [X] T005 Crear `app/Ia/CatalogoTools.php`: registro estático de clases tool, `paraUsuario(User): array` filtrando por `can(permisoRequerido())`, `resolver(string $nombre, User): ?ToolAsistente` con re-verificación de permiso (contrato de seguridad #2)
- [X] T006 [P] Crear `app/Ia/ConversacionAsistente.php`: historial en sesión (`asistente.conversacion`), `agregar()`, `mensajes()`, `truncar()` (descarta turnos viejos preservando pares tool_use/tool_result, umbral `config('ia.max_mensajes')`), gestión de acción pendiente (`proponerAccion()`, `accionPendiente()`, `limpiarAccion()`, máx. 1), `reiniciar()`
- [X] T007 [P] Crear `app/Ia/ConocimientoAsistente.php`: `systemPrompt(User): string` = reglas fijas (idioma español, capacidades/límites, rechazo de datos sensibles FR-015, instrucción de confirmación) + concatenación determinista (orden alfabético) de `resources/ia/conocimiento/*.md` + contexto del usuario (nombre, secciones con permiso)
- [X] T008 [P] Test unitario `tests/Unit/ConversacionAsistenteTest.php`: truncado preserva pares tool_use/tool_result, una sola acción pendiente, mensaje nuevo descarta pendiente, reiniciar limpia todo
- [X] T009 [P] Test unitario `tests/Unit/ConocimientoAsistenteTest.php`: ensamblado modular (agregar un archivo md lo incluye sin tocar el resto — SC-007), contexto de permisos del usuario presente
- [X] T010 Crear `app/Services/AsistenteIa.php` (esqueleto): construye cliente Anthropic con `IaTenant::apiKey()`, arma request (modelo de `config/ia.php`, system prompt de `ConocimientoAsistente`, tools de `CatalogoTools::paraUsuario()`), loop de tool use con tope `max_iteraciones_tools`, callbacks de streaming (`onTexto`, `onActividad`, `onAccionPendiente`, `onError`), mapeo de excepciones del SDK a códigos amigables (`clave_invalida`, `servicio_no_disponible`, `limite_excedido`, `interno`) — FR-011
- [X] T011 Registrar rutas en `routes/web.php` (grupo auth + tenant): `POST /asistente/mensaje`, `POST /asistente/accion/{id}/confirmar`, `POST /asistente/accion/{id}/cancelar`, `POST /asistente/reiniciar`, `PUT /configuracion/ia`, `POST /configuracion/ia/probar`; crear `app/Http/Controllers/AsistenteChatController.php` (esqueleto con los 4 métodos del chat)

**Checkpoint**: base compilable y testeada — las user stories pueden arrancar.

---

## Phase 3: User Story 4 — Configurar la clave de IA del tenant (P1) 🎯 MVP junto con US1

**Goal**: el admin activa el asistente pegando su API key; cifrada, enmascarada, con prueba de conexión.

**Independent Test**: quickstart.md §2 — sin clave (estados del widget), guardar clave, enmascarado, probar conexión.

- [X] T012 [US4] Test feature **(test-first)** `tests/Feature/Asistente/ConfiguracionIaTest.php`: guardar clave la persiste cifrada (valor en BD ≠ texto plano, descifra correcto), respuesta/vista nunca contiene la clave completa, quitar clave la borra, endpoints exigen `ver-configuracion` (403 sin él), aislamiento: clave de tenant A invisible desde tenant B (2 tenants), guardar/quitar registra en `logs_actividad`
- [X] T013 [US4] Implementar en `app/Http/Controllers/ConfiguracionController.php`: métodos para `PUT /configuracion/ia` (guardar/quitar vía `IaTenant`, log de actividad patrón 021, flash toastr) y `POST /configuracion/ia/probar` (llamada mínima al SDK con la clave guardada, respuesta `{ok, mensaje}`)
- [X] T014 [US4] Añadir sección "Asistente IA" a la vista de Configuración (`resources/views/configuracion/`): input de clave, clave actual enmascarada vía `IaTenant::apiKeyEnmascarada()`, botón quitar, botón "Probar conexión" (AJAX + `window.showToast`), nota de privacidad (research D11); seguir `docs/04-front-guidelines.md`
- [X] T015 [US4] Verificar test verde + quickstart §2 pasos 3–4

**Checkpoint**: US4 completa — la clave se gestiona de punta a punta.

---

## Phase 4: User Story 1 — Consultar el funcionamiento de la app (P1) 🎯 MVP

**Goal**: widget flotante en todas las pantallas; responde dudas de funcionamiento por streaming.

**Independent Test**: quickstart.md §3 — pregunta de funcionamiento respondida en streaming < 30 s; rechazo de temas sensibles; visibilidad del widget según clave/permiso (§2 paso 2).

- [X] T016 [US1] Test feature **(test-first)** `tests/Feature/Asistente/ChatEndpointTest.php`: `POST /asistente/mensaje` exige auth y tenant; 409 `no_configurado` sin clave; con clave (cliente Anthropic fakeado) responde SSE con eventos `texto`/`fin`; la conversación persiste en sesión entre requests; `POST /asistente/reiniciar` la limpia; widget: sin clave el HTML del layout NO contiene el widget para usuario sin `ver-configuracion` y SÍ lo contiene (modo activación) para quien lo tiene; con clave lo contiene para todos
- [X] T017 [US1] Redactar base de conocimiento inicial en `resources/ia/conocimiento/`: `00-general.md` (qué es la app, navegación, límites del asistente) + un md por módulo funcional existente (clientes, facturas, presupuestos, albaranes, articulos, stock, proveedores, compras, pagos, bancos, archivos, campanas, usuarios-roles, configuracion, fichajes, dashboard, leads-oportunidades) usando `resources/views/ayuda/*.blade.php` y `docs/` como fuente
- [X] T018 [US1] Implementar `AsistenteChatController::mensaje()`: validación (`mensaje` requerido, max 4000), 409 sin clave, `response()->stream()` SSE (headers `text/event-stream`, `X-Accel-Buffering: no`, `flush()`), delega en `AsistenteIa` conectando callbacks → eventos SSE del contrato; `reiniciar()` limpia sesión
- [X] T019 [US1] Completar `AsistenteIa`: llamada streaming real al SDK (Messages API, `thinking adaptive`), acumulación del assistant turn en `ConversacionAsistente`, truncado post-turno, manejo de errores → evento `error` con `detalle` solo si `can('ver-configuracion')`
- [X] T020 [P] [US1] Crear widget `resources/views/partials/asistente-chat.blade.php`: botón flotante + panel de chat; render condicional en servidor (research D10: con clave → todos; sin clave → solo `ver-configuracion` en modo activación con link a Configuración); incluirlo en `resources/views/layouts/app.blade.php` (nunca superadmin/fullwidth)
- [X] T021 [P] [US1] Crear `public/js/asistente-chat.js`: abrir/cerrar panel, envío con `fetch` + lectura de `ReadableStream` (SSE parseado a mano por ser POST+CSRF), render progresivo de `texto`, indicador de actividad, manejo de `error` y `fin`, botón "conversación nueva"; cargarlo desde el layout junto al widget
- [X] T022 [US1] Diseño del widget según guías del proyecto (invocar skills de diseño antes de pulir): coherente con NexaDash, `docs/04-front-guidelines.md`; sin taparse con toasts ni botones existentes; responsive
- [X] T023 [US1] Verificar tests verdes + quickstart §3 manual con clave real

**Checkpoint**: MVP — asistente de ayuda funcionando de punta a punta.

---

## Phase 5: User Story 2 — Consultar datos de negocio (P2)

**Goal**: el asistente consulta datos reales del tenant vía tools de lectura filtradas por permisos.

**Independent Test**: quickstart.md §4 — datos reales con permiso, rechazo sin permiso, cero fuga entre 2 tenants.

- [X] T024 [US2] Test feature **(test-first)** `tests/Feature/Asistente/ToolsAislamientoTest.php`: con 2 tenants sembrados, cada tool de lectura ejecutada en contexto del tenant A solo devuelve datos de A (recorrer el catálogo completo); ninguna tool acepta `tenant_id` como parámetro de su schema (contrato de seguridad #1)
- [X] T025 [US2] Test feature **(test-first)** `tests/Feature/Asistente/ToolsPermisosTest.php`: `CatalogoTools::paraUsuario()` excluye tools de secciones sin permiso; `resolver()` rechaza (null/excepción) una tool sin permiso aunque se pida por nombre; usuario con rol limitado no recibe esas tools en el request a la API (fake)
- [X] T026 [P] [US2] Implementar tools de lectura `app/Ia/Tools/`: `BuscarClientes.php` (`ver-clientes`), `BuscarArticulos.php` (`ver-articulos`), `BuscarFacturas.php` (`ver-facturas`), `BuscarPresupuestos.php` (`ver-presupuestos`), `ResumenDatosNegocio.php` (`ver-dashboard`, reutiliza `DashboardEstadisticas`); todas: búsqueda por texto/filtros simples, top `config('ia.max_resultados_tool')`, campos mínimos (minimización D11), modelos bajo `TenantScope`
- [X] T027 [US2] Registrar las tools de lectura en `CatalogoTools` y conectar la ejecución en el loop de `AsistenteIa` (evento `actividad` por cada tool ejecutada, tool_result al modelo)
- [X] T028 [US2] Verificar tests verdes + quickstart §4 manual (incluida prueba de aislamiento con 2 tenants)

**Checkpoint**: consultas de datos operativas con permisos y aislamiento probados.

---

## Phase 6: User Story 3 — Crear/editar entidades con confirmación (P3)

**Goal**: escrituras (cliente, artículo, presupuesto, factura borrador) en dos fases con confirmación explícita; prohibidas bloqueadas estructuralmente.

**Independent Test**: quickstart.md §5 — propuesta con botones, confirmar crea, cancelar no crea, factura queda en borrador con totales del servidor, prohibidas siempre rechazadas.

- [X] T029 [US3] Test feature **(test-first)** `tests/Feature/Asistente/ConfirmacionEscrituraTest.php`: una tool de escritura NO escribe al proponerse (BD intacta, acción pendiente en sesión con resumen); `POST /asistente/accion/{id}/confirmar` ejecuta y crea el registro; `cancelar` no crea y limpia; id inexistente → 404; usuario que perdió el permiso entre propuesta y confirmación → 403; mensaje nuevo descarta la pendiente; una segunda propuesta de escritura en el mismo turno es rechazada (tool_result "ya hay una acción pendiente de confirmación", BD intacta y la primera pendiente se conserva); confirmación registra en `logs_actividad`
- [X] T030 [US3] Test feature **(test-first)** `tests/Feature/Asistente/AccionesProhibidasTest.php`: el catálogo completo no contiene ninguna tool de emitir/anular factura, registrar pago, borrar registros, configuración ni usuarios/roles (contrato de seguridad #3); `EditarFacturaBorrador` sobre factura emitida → 422 sin cambios; los importes de una factura creada provienen de `CalculadoraFactura` (total coincide con el flujo manual, SC-006) y el schema de las tools de factura no acepta importes/totales (contrato #5); ninguna escritura posible fuera del endpoint de confirmación (contrato #4)
- [X] T031 [P] [US3] Implementar tools de escritura de fichas: `CrearCliente.php`, `EditarCliente.php` (`ver-clientes`), `CrearArticulo.php`, `EditarArticulo.php` (`ver-articulos`): `proponer()` valida (reutilizando reglas de los form requests existentes, p. ej. NIF único) y devuelve resumen legible; `ejecutar()` crea/edita vía Eloquent scoped
- [X] T032 [P] [US3] Implementar tools de documentos: `CrearPresupuesto.php` (`ver-presupuestos`, vía `RegistroPresupuesto`), `CrearFacturaBorrador.php` y `EditarFacturaBorrador.php` (`ver-facturas`): schema solo con cliente + líneas (artículo, cantidad, descripción), estado `borrador` forzado, importes vía `CalculadoraFactura`, editar aborta si no está en borrador
- [X] T033 [US3] Implementar en `AsistenteChatController`: `confirmar()` (re-verifica permiso vía `CatalogoTools::resolver`, ejecuta, log de actividad, limpia pendiente, agrega turno de resultado a la conversación, respuesta `{ok, mensaje, url}`) y `cancelar()`; en `AsistenteIa`: tool de escritura → `proponer()` → guardar pendiente → evento SSE `accion_pendiente` → tool_result "pendiente de confirmación del usuario"; si ya existe una pendiente en el turno, la nueva propuesta se rechaza con tool_result explicativo (no reemplaza ni encola — resolución U1 del análisis)
- [X] T034 [US3] UI de confirmación en `public/js/asistente-chat.js` + partial: tarjeta con resumen y botones Confirmar/Cancelar (AJAX a los endpoints), estados éxito/error con `window.showToast`, link al recurso creado; deshabilitar la tarjeta al enviar otro mensaje
- [X] T035 [US3] Reforzar system prompt en `ConocimientoAsistente`: explicar el flujo de confirmación al modelo y el rechazo cortés de acciones prohibidas (la seguridad ya es estructural; esto es UX)
- [X] T036 [US3] Verificar tests verdes + quickstart §5 manual (incluidos intentos de evasión — SC-002)

**Checkpoint**: todas las user stories completas.

---

## Phase 7: Polish & Cross-Cutting

- [X] T037 [P] Actualizar `docs/01-arquitectura.md` (nueva Decisión: asistente IA — SDK oficial, SSE síncrono, bloqueo estructural, clave por tenant) y `docs/03-modelo-datos.md` (grupo `ia` en `configuraciones`, conversación efímera en sesión)
- [X] T038 [P] Crear guía in-app `resources/views/ayuda/` para la sección "Asistente IA" de Configuración (cómo obtener la clave de Anthropic, costos a cargo del tenant, nota de privacidad D11) y registrar el slug donde corresponda
- [X] T039 [P] Actualizar `CLAUDE.md`: añadir la regla transversal FR-013 — al cerrar cualquier feature, revisar/actualizar `resources/ia/conocimiento/` (cuarta capa de documentación junto a docs/, front-guidelines y ayuda in-app)
- [X] T040 [P] Actualizar `docs/04-front-guidelines.md` si el widget introdujo convenciones reutilizables (patrón SSE con fetch, tarjetas de confirmación en chat)
- [X] T041 Añadir `resources/ia/conocimiento/asistente.md` (el asistente se documenta a sí mismo) y verificar suite completa (`php artisan test`) + quickstart §6 (navegación, truncado, quitar clave)

---

## Dependencies

- **Phase 1 → Phase 2 → (Phases 3–6) → Phase 7**
- US4 (Phase 3) primero: sin clave no se puede probar nada manualmente (los tests de US1 fakean el cliente, pero el MVP demostrable necesita US4).
- US1 (Phase 4) depende de Foundational; no depende de US4 para sus tests (cliente fakeado).
- US2 (Phase 5) depende del loop de tools de `AsistenteIa` (T019) y del catálogo (T005).
- US3 (Phase 6) depende de US2 solo conceptualmente (mismo loop); técnicamente depende de T019 + T006 (acción pendiente). Puede arrancar tras Phase 4.

## Parallel Opportunities

- Phase 2: T003, T004, T006, T007 en paralelo (archivos distintos); T008/T009 en paralelo tras sus clases.
- Phase 4: T020 y T021 (Blade + JS) en paralelo con T017 (contenido markdown).
- Phase 5: T026 — las 5 tools de lectura son archivos independientes.
- Phase 6: T031 y T032 en paralelo.
- Phase 7: T037–T040 todos en paralelo.

## Implementation Strategy

**MVP = Phases 1–4** (Setup + Foundational + US4 + US1): asistente de ayuda configurado y funcionando por streaming. Entregable y demostrable por sí solo.

**Incremento 2 = Phase 5** (US2): consultas de datos → valor de "asistente de trabajo".

**Incremento 3 = Phase 6** (US3): escrituras con confirmación → capacidad completa.

**Cierre = Phase 7**: documentación en las 4 capas (regla transversal del proyecto).
