# Research: Historial de conversaciones del asistente IA

**Feature**: 045-historial-conversaciones-asistente | **Fecha**: 2026-09-07

Decisiones técnicas tomadas antes de diseñar. Cada una cita el código o el documento que la
condiciona, para que el plan sea trazable.

---

## D1 — Dónde vive la conversación

**Decisión**: dos tablas nuevas, `asistente_conversaciones` y `asistente_mensajes`, ambas con
`tenant_id` y el trait `BelongsToTenant` de `stancl/tenancy`. La sesión de Laravel deja de guardar
el historial y pasa a guardar únicamente **el id de la conversación activa** y la acción pendiente.

**Rationale**: FR-001 exige que la conversación sobreviva al cierre de sesión, y la sesión no puede
darlo. Separar mensajes en su propia tabla (en vez de un JSON dentro de la conversación) permite
paginar, borrar por lotes en la purga y compactar sustituyendo filas concretas sin reescribir el
hilo entero.

**Alternativas descartadas**:
- *Un JSON con todos los mensajes dentro de la fila de conversación*: más simple de escribir, pero
  la compactación y la purga obligan a leer y reescribir el documento completo en cada turno, y el
  tamaño crece sin techo en la misma fila.
- *Cache (Redis/archivo) en vez de base de datos*: el Principio V fija hosting compartido; no hay
  Redis garantizado, y el dato debe sobrevivir semanas, no minutos.

**Nota sobre el scope de tenant**: `docs/03-modelo-datos.md` habla de un `TenantScope` sobre un
`BaseModel`, pero en el código real ese `BaseModel` no existe: los modelos de negocio (p. ej.
`app/Models/Lead.php`) usan `Stancl\Tenancy\Database\Concerns\BelongsToTenant`. Se sigue el código,
que es el patrón vigente, y se deja constancia aquí porque el doc induce a error.

---

## D2 — La acción pendiente sigue en sesión

**Decisión**: la propuesta de escritura pendiente (máx. 1) **no** se persiste: sigue en la sesión,
pero pasa a guardar también el id de la conversación en la que se propuso.

**Rationale**: es estado de un turno, no historial. El Principio II empuja a no persistir datos
personales sin necesidad, y una propuesta sin confirmar no aporta nada al historial. Guardar junto
a ella la conversación de origen es lo que permite cumplir FR-023: al cambiar de hilo, la propuesta
queda descartada porque ya no corresponde a la conversación activa.

**Alternativa descartada**: persistirla junto a la conversación. Obligaría a purgarla, a decidir qué
pasa con propuestas viejas al reabrir un hilo de hace semanas, y a resolver si una propuesta hecha
con datos de hace un mes sigue siendo válida. Complejidad sin valor.

---

## D3 — Formato de los mensajes almacenados

**Decisión**: cada fila de `asistente_mensajes` guarda el mensaje **tal como lo consume la API de
Chat Completions**: `rol` (`user`/`assistant`/`tool`), `contenido` (texto, nullable) y un JSON
opcional con `tool_calls` o `tool_call_id`.

**Rationale**: `AsistenteIa::ejecutarLoop()` arma hoy `messages` directamente desde
`ConversacionAsistente::mensajes()`. Guardando el mismo formato, la reconstrucción del contexto es
un `map` sobre filas y el orquestador no se entera del cambio: sigue recibiendo el mismo array.

**Alternativa descartada**: un formato propio, neutral respecto al proveedor. Sería más limpio si
algún día se cambia de proveedor, pero hoy obligaría a traducir en los dos sentidos en cada turno
para resolver un problema que no existe (el proveedor es fijo, `config/ia.php`).

**Cuidado conocido**: `ConversacionAsistente::truncar()` ya documenta que nunca puede quedar un
mensaje `tool` huérfano ni empezar el hilo en `assistant`/`tool`. Esa invariante pasa íntegra a la
reconstrucción desde base de datos y a la compactación (ver D4).

---

## D4 — Cuándo y cómo se compacta

**Decisión**: se compacta cuando la conversación supera **30 mensajes** (el valor que hoy usa
`config('ia.max_mensajes')` para truncar). Se resumen los mensajes más antiguos **dejando intactos
los últimos 10**, y el resumen resultante se guarda en la propia conversación, no como un mensaje
más. Al reconstruir el contexto, el resumen se inyecta como un mensaje de sistema adicional delante
de los mensajes literales que quedan.

**Rationale**: reutilizar el umbral existente evita introducir un número nuevo sin fundamento, y
mantiene el comportamiento actual como límite de referencia. Dejar los últimos 10 literales conserva
el detalle inmediato, que es donde el usuario espera precisión. Guardar el resumen en la
conversación (y no como mensaje) es lo que permite cumplir FR-014 sin acumular resúmenes: al
compactar de nuevo, el resumen anterior entra como entrada del nuevo y se sobrescribe.

**Recorte de seguridad**: el corte no puede partir un par `assistant(tool_calls)` / `tool`. Si el
mensaje número 10 desde el final es un `tool`, el corte se desplaza hacia atrás hasta el `assistant`
que lo originó, misma regla que ya aplica `truncar()`.

**Alternativas descartadas**:
- *Umbral por tokens en vez de por mensajes*: más preciso, pero exige contar tokens en servidor
  (dependencia nueva o llamada extra) para un beneficio marginal en conversaciones de este tamaño.
- *Compactar siempre al cargar la conversación*: gastaría llamadas al proveedor en hilos que el
  usuario solo abre para leer.

---

## D5 — Cómo se pide el resumen

**Decisión**: una llamada **sin streaming** a `chat()->create()` con un system prompt dedicado y sin
tools, aislada en un servicio propio (`CompactadorConversacion`). Si falla por cualquier motivo, se
captura y se recurre al recorte actual (`truncar()`), dejando la conversación utilizable.

**Rationale**: el resumen no se muestra token a token, así que el streaming no aporta. Aislarlo en un
servicio replica la separación que ya funcionó en la feature 044 (`InterpretadorDocumentoCompra`
habla con el proveedor; `ProponedorCompraDesdeDocumento` hace el trabajo de negocio), y permite
probar la lógica de corte sin red. El fallback es lo que sostiene FR-013 y SC-006: una compactación
fallida nunca puede dejar al usuario sin respuesta.

**Coste**: es una llamada adicional pagada con la clave del tenant, y solo ocurre en los turnos que
cruzan el umbral (aproximadamente 1 de cada 20 en una conversación larga). Se avisa al usuario con
el indicador de progreso ya existente (FR-011).

---

## D6 — Dónde se dispara la compactación

**Decisión**: al principio de `AsistenteIa::responder()`, antes de entrar al loop, y **dentro de la
closure de la `StreamedResponse`** — es decir, en el mismo sitio donde ya corre todo lo demás.

**Rationale**: es el único momento en que se conoce el mensaje nuevo del usuario y se puede avisar
por el canal SSE de que se está compactando. Como el historial pasa a base de datos, la escritura del
resumen ya no depende de la sesión; pero el id de conversación activa **sí** sigue en sesión, así que
la trampa documentada en `docs/01-arquitectura.md` (Decisión 9) sigue vigente: cualquier escritura de
sesión dentro del stream necesita el `session()->save()` explícito que ya está en el controlador.

---

## D7 — Título de la conversación

**Decisión**: el primer mensaje del usuario, recortado a 60 caracteres en el borde de palabra. Se
fija al crear la conversación y no cambia.

**Rationale**: el spec deja fuera de alcance los títulos generados por IA, y esta regla no cuesta
ninguna llamada al proveedor. Es la heurística que la mayoría de clientes de chat usa como fallback.

---

## D8 — Retención y purga

**Decisión**: `App\Support\RetencionAsistenteTenant` con `CLAVE_RETENCION_DIAS =
'asistente.retencion_dias'` y `DEFAULT_RETENCION_DIAS = 90`, más el comando `asistente:purgar`
programado a diario en `bootstrap/app.php`. Se purgan las conversaciones cuya **última actividad**
supera el plazo, en lotes, borrando en cascada sus mensajes.

**Rationale**: es literalmente el patrón que la constitución exige reutilizar (Additional
Constraints: "toda tabla nueva con datos personales reutiliza ese patrón... en vez de definir su
propio mecanismo"). `App\Support\RetencionLogsTenant` y `App\Console\Commands\PurgarLeads` son los
modelos a copiar, incluido el troceado en lotes de 500 y el `withoutGlobalScopes()` con
`where('tenant_id', ...)` explícito para recorrer todos los tenants desde el comando.

**Por última actividad, no por fecha de creación**: una conversación viva de hace meses no debe
purgarse por haber empezado hace mucho. `PurgarLeads` usa `created_at` porque un lead descartado no
se "usa"; aquí el criterio correcto es distinto y conviene que quede razonado.

---

## D9 — Superficie HTTP

**Decisión**: se añaden cuatro endpoints bajo `/asistente/conversaciones` (listar, abrir, crear,
borrar) y `POST /asistente/mensaje` pasa a aceptar opcionalmente la conversación destino. El endpoint
existente `POST /asistente/reiniciar` queda sustituido por la creación de conversación.

**Rationale**: mantiene el estilo del resto del widget (endpoints AJAX pequeños, CSRF, respuestas
JSON) descrito en `docs/04-front-guidelines.md`, sección "Widget del asistente IA".

---

## D10 — Interfaz del historial

**Decisión**: el historial es una vista deslizante **dentro del propio panel** del asistente, que
tapa la lista de mensajes y se cierra volviendo al hilo. Se abre desde un icono nuevo en la cabecera,
junto a los de conversación nueva y cerrar.

**Rationale**: el panel ya ocupa 440 px fijos y en móvil el 100 % del ancho
(`resources/views/partials/asistente-chat.blade.php`); una segunda columna al estilo del sidebar de
Claude no cabe sin rehacer el layout. Las convenciones obligatorias de la sección "Widget del
asistente IA" se respetan: CSS scoped bajo `.asistente-chat__*` inline en el partial, render
condicional en servidor y `window.showToast` para el feedback de borrado.

**Confirmación de borrado**: se usa `window.confirmDelete(...)`, el mecanismo ya documentado en
`docs/04-front-guidelines.md`, que además trae su propio estado de carga en el botón de confirmar.

---

## D11 — Estrategia de tests

**Decisión**: test-first en aislamiento multi-tenant (Principio IV, no negociable) y en el corte de
la compactación. La compactación se prueba **sin red** sustituyendo el punto que habla con el
proveedor, igual que ya se hace en `tests/Feature/Asistente/TurnoTerminaAlProponerTest.php` con
`abrirStream()`.

**Rationale**: el Principio IV exige test-first para aislamiento; el resto (endpoints, UI) admite un
flujo más flexible. La lógica de dónde cortar el hilo sin romper pares `assistant`/`tool` es
exactamente el tipo de cálculo que conviene fijar con tests antes de escribirlo.
