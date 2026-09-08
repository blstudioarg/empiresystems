---

description: "Task list for 045-historial-conversaciones-asistente"
---

# Tasks: Historial de conversaciones del asistente IA

**Input**: Design documents from `/specs/045-historial-conversaciones-asistente/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md),
[data-model.md](./data-model.md), [contracts/endpoints.md](./contracts/endpoints.md)

**Tests**: SÍ, obligatorios. El Principio IV de la constitución es NON-NEGOTIABLE para aislamiento
multi-tenant: esos tests se escriben primero y deben fallar antes de implementar. Se aplica el mismo
rigor al corte de la compactación (research D4), donde un error es silencioso.

**Organization**: agrupadas por historia de usuario. La US1 incluye la retención y la purga porque,
como razona el spec, no puede desplegarse sin ellas sin incumplir el Principio II.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ir en paralelo (ficheros distintos, sin dependencias pendientes)
- **[Story]**: US1 / US2 / US3

---

## Phase 1: Setup

**Purpose**: no hay dependencias nuevas que instalar; solo fijar la configuración de la feature.

- [X] T001 Añadir la clave `asistente.retencion_dias` (grupo `ia`, default 90) a la tabla de claves de configuración documentada en `docs/03-modelo-datos.md`
- [X] T002 [P] Crear `app/Support/RetencionAsistenteTenant.php` con `CLAVE_RETENCION_DIAS = 'asistente.retencion_dias'` y `DEFAULT_RETENCION_DIAS = 90`, calcado de `app/Support/RetencionLogsTenant.php`
- [X] T003 [P] Crear `tests/Unit/RetencionAsistenteTenantTest.php`: devuelve el default cuando no hay fila y el valor del tenant cuando existe

---

## Phase 2: Foundational (bloqueante para todas las historias)

**Purpose**: esquema y capa de acceso. Ninguna historia puede empezar hasta que esto esté.

**⚠️ CRÍTICO**: T004–T013 bloquean las fases 3, 4 y 5.

### Tests primero (Principio IV)

- [X] T004 Crear `tests/Feature/Asistente/HistorialAislamientoTest.php` con ≥2 tenants: una conversación de un tenant no es visible ni accesible desde el otro, ni por listado ni por id directo (debe FALLAR antes de T006–T013)
- [X] T005 [P] Añadir a `tests/Feature/Asistente/HistorialAislamientoTest.php` el caso de dos usuarios del mismo tenant: cada uno ve solo sus conversaciones y un id ajeno responde 404, no 403

### Esquema

- [X] T006 Crear migración `database/migrations/..._create_asistente_conversaciones_table.php` con las columnas e índices de `data-model.md` (`tenant_id`, `user_id`, `titulo`, `resumen`, `resumido_hasta_mensaje_id`, `ultima_actividad_en`, timestamps), FKs en cascada y sin `softDeletes`
- [X] T007 Crear migración `database/migrations/..._create_asistente_mensajes_table.php` con `tenant_id`, `conversacion_id`, `rol`, `contenido`, `metadatos` (json) y el índice `(conversacion_id, id)`
- [X] T008 Ejecutar `php artisan migrate` y verificar el esquema resultante — NO usar `migrate:fresh` (regla de `CLAUDE.md`: destruiría los datos de demo)

### Modelos

- [X] T009 [P] Crear `app/Models/AsistenteConversacion.php` (tabla `asistente_conversaciones` vía `$table` explícito) con el trait `BelongsToTenant` de `stancl/tenancy`, `$fillable`, cast de `ultima_actividad_en` y relación `mensajes()`
- [X] T010 [P] Crear `app/Models/AsistenteMensaje.php` (tabla `asistente_mensajes` vía `$table` explícito) con `BelongsToTenant`, cast de `metadatos` a array y relación `conversacion()`
- [X] T011 [P] Crear factories `database/factories/AsistenteConversacionFactory.php` y `database/factories/AsistenteMensajeFactory.php` para los tests

### Capa de acceso

- [X] T012 Reescribir `app/Ia/ConversacionAsistente.php` para respaldarse en base de datos **manteniendo intacta su interfaz pública** (`mensajes()`, `agregar()`, `agregarCrudo()`, `agregarMensajeTool()`, `proponerAccion()`, `accionPendiente()`, `hayAccionPendiente()`, `limpiarAccion()`, `reiniciar()`); la sesión pasa a guardar solo el id de conversación activa y la acción pendiente con su `conversacion_id` (research D1, D2)
- [X] T013 Actualizar `ultima_actividad_en` de la conversación cada vez que se le añade un mensaje, dentro de `app/Ia/ConversacionAsistente.php` — es la base del orden del listado (FR-004, FR-005) y del criterio de purga (research D8)
- [X] T014 Verificar que `tests/Feature/Asistente/PersistenciaConversacionTest.php`, `TurnoTerminaAlProponerTest.php`, `ConfirmacionEscrituraTest.php` y `tests/Unit/ConversacionAsistenteTest.php` siguen en verde **sin modificarlos**; si alguno exige cambios, es señal de que T012 rompió la interfaz

**Checkpoint**: T004 y T005 deben pasar a verde al terminar esta fase.

---

## Phase 3: User Story 1 — Retomar una conversación anterior (P1) 🎯 MVP

**Goal**: la conversación sobrevive a la sesión, hay lista de conversaciones, se puede retomar
cualquiera, y lo guardado se purga a los 90 días.

**Independent Test**: mantener una conversación, cerrar sesión, volver a entrar y comprobar que sigue
ahí y que el asistente recuerda lo hablado. Ver `quickstart.md`, escenarios 1, 2, 3 y 6.

### Backend

- [X] T015 [P] [US1] Crear `tests/Feature/Asistente/HistorialEndpointsTest.php` cubriendo los cuatro endpoints de `contracts/endpoints.md` (listar, crear, abrir, borrar) con sus códigos de respuesta
- [X] T016 [US1] Crear `app/Http/Controllers/AsistenteConversacionController.php` con `index`, `store`, `show` y `destroy` según `contracts/endpoints.md`, resolviendo siempre por tenant activo + usuario autenticado
- [X] T017 [US1] Registrar en `routes/web.php` las rutas `GET/POST /asistente/conversaciones`, `GET /asistente/conversaciones/{id}` y `DELETE /asistente/conversaciones/{id}`, y retirar `POST /asistente/reiniciar` (sustituida por `store`)
- [X] T018 [US1] Actualizar `tests/Feature/Asistente/ChatEndpointTest.php`: `test_reiniciar_limpia_la_conversacion` apunta hoy a `POST /asistente/reiniciar`, que T017 elimina — debe pasar a `POST /asistente/conversaciones` sin perder lo que verifica
- [X] T019 [US1] Implementar en `show` la traducción de mensajes de rol `tool` al pseudo-rol `actividad` (solo el nombre de la herramienta, nunca el resultado crudo) y el flag `resumida`, según `contracts/endpoints.md`
- [X] T020 [US1] Implementar el descarte de la acción pendiente **también al abrir otra conversación** (`show`), no solo al crear una nueva: si la propuesta pertenece a otra conversación, se descarta (FR-023, efecto secundario obligatorio del contrato)
- [X] T021 [P] [US1] Añadir a `tests/Feature/Asistente/HistorialEndpointsTest.php` el caso de FR-023: con una acción pendiente viva, abrir otra conversación la descarta y confirmarla después responde 404
- [X] T022 [US1] Modificar `app/Http/Controllers/AsistenteChatController.php` para resolver la conversación activa desde sesión, crearla al vuelo en el primer mensaje con el título derivado (research D7) y emitir el evento SSE `conversacion`
- [X] T023 [US1] Verificar que el `session()->save()` al cerrar el stream sigue cubriendo el nuevo id de conversación activa (`docs/01-arquitectura.md`, Decisión 9)
- [X] T024 [P] [US1] Cubrir con test el caso límite de asistente sin clave configurada: `POST /asistente/mensaje` sigue devolviendo 409, pero los endpoints de historial siguen permitiendo listar, abrir y borrar (edge case del spec)

### Retención y purga (parte obligatoria de esta historia)

- [X] T025 [P] [US1] Crear `tests/Feature/Asistente/PurgaConversacionesTest.php`: purga por `ultima_actividad_en`, respeta el plazo configurado por tenant, borra los mensajes asociados y no toca conversaciones recientes
- [X] T026 [US1] Crear `app/Console/Commands/PurgarConversacionesAsistente.php` (`asistente:purgar`) siguiendo `app/Console/Commands/PurgarLeads.php`: recorrido por tenant, lotes de 500, `withoutGlobalScopes()` con `tenant_id` explícito y borrado en cascada de mensajes
- [X] T027 [US1] Programar `asistente:purgar` a diario en `bootstrap/app.php`, junto al resto de purgas RGPD
- [X] T028 [US1] Exponer `asistente.retencion_dias` en la pantalla de Configuración del tenant, en la pestaña del asistente (FR-018)
- [X] T029 [P] [US1] Cubrir el caso límite de purga de la conversación **activa**: si la purga elimina la que la sesión tenía abierta, el siguiente acceso al panel entrega un hilo nuevo y vacío, sin error (edge case del spec)

### Frontend

- [X] T030 [US1] Añadir a `resources/views/partials/asistente-chat.blade.php` el icono de historial en la cabecera y la vista deslizante del historial dentro del panel, con CSS scoped `.asistente-chat__*` en el bloque de estilos del propio partial (research D10, guías de front)
- [X] T031 [US1] Implementar en `public/js/asistente-chat.js` la apertura del historial, el pintado de la lista (título + fecha, más reciente primero) y la carga de una conversación al elegirla
- [X] T032 [US1] Hacer que el botón de conversación nueva llame a `POST /asistente/conversaciones` en vez de a la ruta de reiniciar, descartando la acción pendiente (FR-023)
- [X] T033 [US1] Al abrir el panel sin conversación elegida, cargar la de actividad más reciente (FR-009)
- [X] T034 [US1] Manejar en `public/js/asistente-chat.js` la respuesta 404 al enviar un mensaje a una conversación que ya no existe (borrada desde otra pestaña): avisar con `window.showToast` y dejar el panel en un hilo nuevo, sin escribir en un hilo inexistente (edge case del spec)

**Checkpoint**: US1 entregable y verificable de forma independiente.

---

## Phase 4: User Story 2 — Conversaciones largas que no pierden el hilo (P2)

**Goal**: al superar 30 mensajes, los más antiguos se resumen en vez de descartarse.

**Independent Test**: superar el umbral y comprobar que el asistente sigue respondiendo sobre datos
mencionados al principio. Ver `quickstart.md`, escenario 4.

- [X] T035 [P] [US2] Crear `tests/Unit/CompactadorConversacionTest.php` con el corte de research D4: se resumen los antiguos dejando los últimos 10 literales, y el corte **nunca** parte un par `assistant(tool_calls)` / `tool` (debe FALLAR antes de T036)
- [X] T036 [US2] Crear `app/Services/CompactadorConversacion.php`: decide si toca compactar, calcula el corte y pide el resumen con una llamada sin streaming ni tools, aislada en un método sustituible para poder probarla sin red (research D5, D11)
- [X] T037 [US2] Integrar el resumen previo como entrada del nuevo al recompactar, sin acumular resúmenes (FR-014), persistiéndolo en `resumen` y `resumido_hasta_mensaje_id`
- [X] T038 [US2] Reconstruir el contexto inyectando el resumen como mensaje de sistema delante de los literales en `app/Ia/ConversacionAsistente.php`
- [X] T039 [US2] Disparar la compactación al inicio de `AsistenteIa::responder()`, antes del loop, y emitir el evento SSE `compactando` (research D6, contrato)
- [X] T040 [US2] Implementar el fallback de FR-013: si la compactación falla, capturar, `report()` y recurrir al recorte simple existente, respondiendo con normalidad y sin emitir `error`
- [X] T041 [P] [US2] Añadir a `tests/Feature/Asistente/` un test del fallback: con el compactador fallando, el turno responde igual y no se pierde el mensaje del usuario (SC-006)
- [X] T042 [US2] Mostrar en `public/js/asistente-chat.js` el estado "Compactando la conversación…" al recibir `compactando`, reutilizando `.asistente-chat__estado` (FR-011)
- [X] T043 [US2] Pintar el separador de la parte resumida al cargar una conversación con `resumida: true`, diferenciado de los mensajes literales (FR-012)

**Checkpoint**: US2 entregable sobre US1.

---

## Phase 5: User Story 3 — Controlar qué queda guardado (P3)

**Goal**: borrar una conversación propia desde el historial.

**Independent Test**: borrar y comprobar que desaparece y deja de ser accesible. Ver
`quickstart.md`, escenario 5.

- [X] T044 [P] [US3] Añadir a `tests/Feature/Asistente/HistorialEndpointsTest.php` los casos de borrado: propia (200), ajena (404) y borrado de la conversación activa
- [X] T045 [US3] Implementar el botón de borrar en la lista del historial usando `window.confirmDelete(...)` (guías de front) y `window.showToast` para el resultado
- [X] T046 [US3] Al borrar la conversación activa, dejar el panel en un hilo nuevo y vacío y limpiar la sesión (contrato)

---

## Phase 6: Polish y documentación

**Purpose**: cerrar las cuatro capas de documentación que exige `CLAUDE.md` y verificar los
criterios de éxito medibles. No es opcional.

- [X] T047 [P] Actualizar `docs/03-modelo-datos.md`: la sección del asistente deja de titularse "sin tablas nuevas", se documentan las dos tablas, y el párrafo que declara la conversación "efímera por diseño, sin retención adicional" se reescribe con el plazo de 90 días y `asistente:purgar` (FR-024)
- [X] T048 [P] Añadir `asistente.retencion_dias` a la tabla de claves de configuración de `docs/03-modelo-datos.md`, junto a `logs.retencion_dias` y `leads.retencion_dias`
- [X] T049 [P] Actualizar `docs/01-arquitectura.md`, Decisión 9: el bullet de "Conversación efímera en la sesión de Laravel" pasa a describir persistencia, compactación y retención (FR-025)
- [X] T050 [P] Anotar en `docs/04-front-guidelines.md`, sección "Widget del asistente IA", la convención de la vista deslizante del historial dentro del panel y el motivo (el panel no admite una segunda columna)
- [X] T051 [P] Actualizar `resources/ia/conocimiento/asistente.md`: el asistente debe saber explicar el historial, los 90 días de conservación y el resumen automático (FR-025)
- [X] T052 [P] Revisar si `resources/views/ayuda/` necesita guía para el historial; si la pantalla cambia lo que el usuario ve o hace, actualizar o crear el `.blade.php` correspondiente
- [ ] T053 ⚠️ PENDIENTE (requiere entorno local en marcha) — Medir SC-002 y SC-007 siguiendo `quickstart.md` (escenarios 7 y 8): retomar una conversación con 50 hilos sembrados por debajo de 3 s, y turno que compacta como mucho el doble que uno normal. Anotar los números obtenidos
- [X] T054 Ejecutar `vendor/bin/pint` sobre los ficheros tocados y `php artisan test tests/Feature/Asistente tests/Unit` completo en verde — **232 tests, 828 aserciones**
- [ ] T055 ⚠️ PENDIENTE (requiere entorno local en marcha) — Recorrer `quickstart.md` de principio a fin en el entorno local, incluidos los escenarios de aislamiento y purga

---

## Dependencies

```text
Phase 1 (T001–T003)
      ↓
Phase 2 (T004–T014)  ← BLOQUEANTE para todo lo demás
      ↓
Phase 3 US1 (T015–T034)  ← MVP
      ↓
Phase 4 US2 (T035–T043)   (necesita el hilo persistido de US1)
      ↓
Phase 5 US3 (T044–T046)   (necesita la lista de US1; independiente de US2)
      ↓
Phase 6 (T047–T055)
```

- US2 y US3 son independientes entre sí: una vez cerrada US1, pueden ir en cualquier orden o a la vez.
- Dentro de Phase 2, T006 y T007 van antes que T009–T011; T012 y T013 después de los modelos.
- T018 depende de T017: el test se actualiza cuando la ruta desaparece, no antes.

## Parallel execution

- **Phase 1**: T002 y T003 en paralelo.
- **Phase 2**: T009, T010 y T011 en paralelo tras las migraciones.
- **Phase 3**: T015, T021, T024, T025 y T029 (tests) en paralelo; el frontend (T030–T034) puede avanzar en paralelo al backend una vez fijado el contrato.
- **Phase 6**: T047–T052 son ficheros distintos, todos paralelizables.

## Implementation strategy

**MVP = Phase 1 + Phase 2 + Phase 3 (US1)**. Entrega el historial persistente con lista, retomar y
purga: resuelve el problema que motivó la feature y es desplegable por sí solo.

La compactación (US2) y el borrado manual (US3) son incrementos posteriores. Ninguno de los dos es
condición para que US1 aporte valor.

**Recordatorio de cumplimiento**: US1 **no puede desplegarse sin T025–T029**. En cuanto las
conversaciones se guardan son datos personales conservados, y el Principio II exige plazo de
retención y purga desde el primer diseño. La purga no es trabajo de "más adelante".
