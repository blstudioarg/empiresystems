# Research — 030 Chat flotante con asistente IA

## D1 — Cliente de la API de OpenAI: SDK oficial PHP

> **Nota (cambio de proveedor post-implementación):** la feature se especificó y se implementó primero
> sobre la API de Anthropic (Claude). A pedido del usuario se cambió el proveedor a **OpenAI** (ya tenía
> clave y créditos allí). El diseño (D3–D11: bloqueo estructural, permisos, confirmación de escrituras,
> conversación en sesión, streaming SSE, clave por tenant, conocimiento modular) es agnóstico del
> proveedor y no cambió; solo se reescribió la capa de cliente (D1/D2, formato de mensajes/tools).

**Decision**: usar el SDK oficial `openai-php/client` (Composer) contra la Chat Completions API (`POST /v1/chat/completions`), con function calling y streaming.

**Rationale**: SDK oficial mantenido por OpenAI para PHP; evita mantener un cliente HTTP a mano (reintentos, tipado de errores, streaming SSE ya resueltos). Una sola dependencia nueva cumple el Principio V. Los errores tipados (`ErrorException` con status 401 → clave inválida, 429 → límite, `TransporterException` → servicio no disponible) mapean directo a los mensajes amigables de FR-011.

**Alternatives considered**: cliente HTTP manual con Guzzle/`Http::` (más código propio, streaming SSE a mano, sin tipado de errores — descartado); wrappers comunitarios de Laravel sobre el SDK (capa extra innecesaria — descartado).

## D2 — Modelo fijo del sistema

**Decision**: modelo único definido en `config/ia.php` (`ia.modelo`, default `gpt-4o-mini`, sobrescribible por `.env` a nivel de instalación, nunca por tenant). `max_tokens` y límites de conversación también en ese config.

**Rationale**: clarificación de la spec (el tenant no elige modelo). Centralizarlo en config permite al operador del SaaS cambiar el modelo (p. ej. a uno más económico) sin tocar código ni datos. `gpt-4o-mini` es el default recomendado actual: barato, rápido y con buen soporte de function calling y streaming.

**Alternatives considered**: selector por tenant (descartado en clarify); hardcodear el ID en el servicio (impide ajustar sin deploy — descartado).

## D3 — Bloqueo de acciones prohibidas: estructural, no por prompt

**Decision**: el catálogo de tools **solo contiene** las acciones permitidas (lecturas por sección + crear/editar cliente, artículo, presupuesto, factura borrador). Emitir, pagar, borrar, configurar o gestionar usuarios no existen como tools. El servidor ejecuta únicamente tools del catálogo, filtrado además por los permisos del usuario en el momento del request.

**Rationale**: FR-006 exige bloqueo en servidor, no solo por instrucciones al modelo. Si la tool no existe, ninguna inyección de prompt puede ejecutarla: el modelo puede "querer" emitir una factura, pero no tiene mecanismo. El system prompt además instruye rechazar esas peticiones con la explicación adecuada (UX), pero la seguridad no depende de eso. `EditarFacturaBorrador` valida en servidor que la factura esté en estado `borrador` antes de tocar nada (inmutabilidad de emitidas, Principio II).

**Alternatives considered**: tool genérica "ejecutar acción" con lista negra (evadible por diseño y frágil — descartado); validación solo por system prompt (viola FR-006 — descartado).

## D4 — Confirmación de escrituras: flujo de dos fases con acción pendiente en sesión

**Decision**: las tools de escritura no escriben. Al invocarse, validan parámetros (incluida existencia/permiso), construyen un **resumen legible** y persisten una *acción pendiente* (tool + parámetros validados + resumen + id aleatorio) en la sesión del usuario. Devuelven al modelo un tool_result indicando "propuesta presentada al usuario, pendiente de confirmación". El widget renderiza el resumen con botones **Confirmar / Cancelar**. Confirmar dispara `POST /asistente/accion/{id}/confirmar` (request normal con CSRF), que re-verifica permiso y estado y ejecuta la escritura vía los servicios existentes (`CalculadoraFactura`, `RegistroPresupuesto`, etc.). Cancelar (o enviar otro mensaje) descarta la pendiente. Una pendiente por vez; cada escritura requiere su propia confirmación (edge case de la spec).

**Rationale**: la confirmación la da el usuario con un click autenticado, no el modelo con texto (que sería falsificable con "el usuario ya confirmó"). Cumple FR-007/SC-004 de forma verificable: la ejecución solo puede originarse en el endpoint de confirmación. Los importes los calcula el servidor al ejecutar (Principio III).

**Alternatives considered**: confirmación conversacional ("sí, dale" → el modelo re-llama la tool con `confirmado=true`) — el flag lo controla el modelo, inyectable (descartado); doble tool propose/execute visible al modelo — mismo problema (descartado).

## D5 — Conversación: sesión de Laravel, truncado en servidor

**Decision**: historial de mensajes (formato OpenAI: user/assistant/tool con tool_calls/tool_result) en la sesión de Laravel bajo una clave propia. Truncado en servidor: al superar el límite configurado (`config/ia.php`, p. ej. ~30 mensajes o presupuesto de caracteres), se descartan los turnos más antiguos preservando pares assistant(tool_calls)/tool completos. Endpoint para "conversación nueva" que limpia la clave de sesión.

**Rationale**: cumple FR-014 (sobrevive navegación, muere con la sesión) sin tablas nuevas ni retención RGPD adicional (la sesión ya expira/purga por el mecanismo estándar). El cliente nunca envía el historial (no manipulable). Coherente con el driver de sesión ya en uso en hosting compartido.

**Alternatives considered**: historial en el cliente re-enviado por request (manipulable, pesado — descartado); tabla `conversaciones` persistente (alcance descartado por la spec: sin histórico entre logins; además obligaría a política de retención — YAGNI).

## D6 — Streaming: SSE dentro del request

**Decision**: `POST /asistente/mensaje` responde `text/event-stream` vía `response()->stream()`: se reenvían al navegador los deltas de texto del stream de OpenAI, más eventos propios (`tool` cuando ejecuta una tool de lectura, `accion_pendiente` con el resumen a confirmar, `error`, `fin`). El JS consume el stream con `fetch` + `ReadableStream` (no `EventSource`, porque es POST con CSRF). Desactivar buffering (`X-Accel-Buffering: no`, `flush()`).

**Rationale**: FR-016 (clarificación de streaming) sin infraestructura nueva: es un request HTTP normal que emite progresivamente — compatible con hosting compartido y con el edge proxy de Railway (Principio V). El loop de tool use ocurre dentro del mismo request: entre tools se emite un evento de actividad para que el usuario vea progreso.

**Alternatives considered**: respuesta completa sin streaming (descartado en clarify); websockets/Pusher/Reverb (requiere infra dedicada — viola Principio V); cola + polling (sin workers garantizados — descartado).

## D7 — API key por tenant: grupo `ia` en `configuraciones`, patrón EmailTenant

**Decision**: support class `App\Support\IaTenant` con claves `ia.api_key` (cifrada con `Crypt::encryptString`, igual que `email.smtp_password`) y helper `configurada(): bool`. UI: sección "Asistente IA" en la pantalla de Configuración existente (permiso `ver-configuracion`), con input de clave que se guarda cifrada y luego se muestra solo enmascarada (últimos 4 caracteres), botón de quitar clave, y un botón "Probar conexión" que hace una llamada mínima para validar la clave (espejo del "email de prueba" de la feature 017). Guardar/quitar la clave se registra en `logs_actividad`.

**Rationale**: reutiliza al 100% el patrón ya establecido (Decisión 5 de arquitectura, feature 017): tabla `configuraciones` por grupo, cifrado, vista de configuración, log de actividad. Cero tablas nuevas.

**Alternatives considered**: tabla propia `ia_credenciales` (innecesaria para 1 clave — descartado); `.env` global compartido (viola el requisito de clave por tenant y haría que el SaaS pague el consumo de todos — descartado).

## D8 — Base de conocimiento modular: markdown por módulo en `resources/ia/conocimiento/`

**Decision**: un archivo markdown por módulo funcional (`clientes.md`, `facturas.md`, `albaranes.md`, …) más `00-general.md` (navegación, convenciones, límites del asistente). `ConocimientoAsistente` los concatena (orden alfabético, determinista) dentro del system prompt junto con: reglas de seguridad, idioma, capacidades/límites y contexto del usuario (nombre, secciones permitidas). Regla de mantenimiento (FR-013): al cerrar una feature se añade/actualiza su archivo — se documenta en `CLAUDE.md` como cuarta capa de documentación obligatoria, junto a las guías de ayuda in-app.

**Rationale**: SC-007 (agregar una feature = añadir un archivo, sin tocar el resto). Markdown plano es editable sin conocimientos de código y versionado en git. Prompt determinista y estable → compatible con prompt caching de OpenAI (el prefijo estable abarata cada request del chat).

**Alternatives considered**: derivar el conocimiento de las vistas `resources/views/ayuda/*.blade.php` (tienen markup Blade/HTML, ruido para el modelo y acopla dos propósitos — descartado; sí se usarán como fuente al redactar los md); RAG con embeddings (sobredimensionado para ~20 archivos que caben en contexto — YAGNI, Principio V).

## D9 — Filtrado por permisos: cada tool declara su permiso de sección

**Decision**: `ToolAsistente` (contrato base) declara `permisoRequerido(): string` con la clave del catálogo existente (`ver-clientes`, `ver-facturas`, …). `CatalogoTools::paraUsuario($user)` devuelve solo las tools cuyo permiso tiene el usuario (spatie `can()`); esa lista es la que se envía a la API y la única que el servidor acepta ejecutar (doble verificación al ejecutar y al confirmar). El system prompt informa al modelo qué secciones tiene el usuario para que explique correctamente los rechazos.

**Rationale**: hereda permisos del usuario (decisión previa al spec) reutilizando el catálogo de la feature 027 sin permisos nuevos. La doble capa (no enviar la tool + rechazar si llega igual) espeja el patrón del proyecto "el menú oculta, la ruta exige".

**Alternatives considered**: permiso nuevo "usar-asistente" (descartado por el usuario); enviar todas las tools y filtrar solo al ejecutar (el modelo prometería cosas que luego fallan — peor UX, descartado).

## D10 — Visibilidad del widget

**Decision**: el partial `asistente-chat.blade.php` se incluye en `layouts/app.blade.php` (área tenant autenticada, nunca en superadmin ni layouts fullwidth). Render condicional en servidor: con clave configurada → widget completo para todos los usuarios; sin clave → solo usuarios con `ver-configuracion` ven el widget en modo "activación" (link a Configuración); el resto no recibe el markup.

**Rationale**: clarificación #1 de la spec. Decidir en servidor evita filtrar al cliente la existencia de la funcionalidad y no carga JS innecesario.

**Alternatives considered**: renderizar siempre y ocultar por CSS/JS (expone estado de configuración a quien no corresponde — descartado).

## D11 — Privacidad de datos hacia la API externa

**Decision**: documentar (en la guía in-app de configuración y en `docs/`) que al activar el asistente, fragmentos de datos del negocio (los resultados de las tools de lectura y lo que el usuario escriba) se envían a la API de OpenAI bajo la clave y responsabilidad del tenant. El system prompt y las tools minimizan datos: las búsquedas devuelven campos necesarios y paginados (p. ej. top 10), nunca dumps completos de tablas.

**Rationale**: Principio II ampliado (RGPD, minimización). La decisión de activar es del tenant (su clave, su costo, su responsabilidad como responsable del tratamiento), pero el SaaS debe informarlo y minimizar por diseño.

**Alternatives considered**: anonimizar/seudonimizar datos antes de enviar (rompería la utilidad del asistente: "buscá el cliente García" necesita el nombre — descartado); no enviar datos de negocio (equivale a recortar US2/US3 — descartado).
