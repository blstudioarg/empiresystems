# Research: Email Marketing (Campañas a Clientes)

## D1 — Estrategia de envío sin colas ni cron

**Decisión**: Envío síncrono por **tandas** iniciadas desde el frontend. El JS trocea la lista
de destinatarios en grupos de N y llama secuencialmente a `POST /campanas/{campana}/enviar-tanda`
con los IDs de la tanda; cada respuesta actualiza la barra de progreso. El backend envía cada
correo con `TenantMailer` dentro del request y devuelve el resultado por destinatario.

**Rationale**: El Principio V prohíbe depender de `queue:work`/supervisor y de `schedule:run`
(no garantizados en hosting compartido). Un único request para todos los destinatarios arriesga
el timeout del servidor web (~30–60 s) y deja estado indeterminado si se corta. Trocear mantiene
cada request corto y seguro (mismo patrón validado en el envío individual de la feature 017), da
progreso real y permite retomar/reintentar sin duplicar.

**Alternativas descartadas**:
- **Cola con driver `database` + cron `schedule:run` → `queue:work --stop-when-empty`**: viable
  técnicamente pero añade infra externa (cron de Hostinger) sin visibilidad de fallos y contradice
  el Principio V para el volumen actual. Documentado como migración futura (VPS / volumen alto).
- **Un solo request con todos los envíos + spinner**: riesgo de timeout y estado parcial opaco.
- **`ShouldQueue` en el Mailable**: requiere worker persistente. Descartado por Principio V.

## D2 — Tamaño de tanda

**Decisión**: **8** destinatarios por tanda (constante configurable en el JS/controlador).

**Rationale**: Con ~1–2 s por envío SMTP, 8 envíos son ~8–16 s por request, con holgura frente a
un timeout de 30 s incluso si un destinatario tiene el SMTP lento. Dentro del rango 5–10 pedido
por el usuario; 8 equilibra número de round-trips (overhead de bootstrap por request) y margen.

**Alternativas descartadas**: 5 (más requests, más overhead); 10 (menos margen ante SMTP lento).

## D3 — Persistencia del resultado por destinatario

**Decisión**: Tabla `campana_destinatarios` (1 fila por cliente incluido en la campaña) con
estado `pendiente|enviado|fallido`, `error`, `enviado_at`. Es la **fuente de verdad** del
resultado; el JS solo refleja lo que devuelve el backend. El reintento consulta filas `fallido`.

**Rationale**: FR-005/FR-011/FR-012 exigen resultado por destinatario, detalle consultable y
reintento solo de fallidos. Una fila por destinatario permite todo eso, sobrevive a cortes del
navegador (los ya `enviado` quedan grabados) y evita duplicados (no se reenvía a `enviado`).

**Alternativas descartadas**: guardar solo un JSON agregado en `campanas` (no permite reintento
selectivo fiable ni consulta estructurada); apoyarse en `factura_eventos` (esos eventos cuelgan
de una factura y aquí no hay factura).

## D4 — Registro en histórico

**Decisión**: El propio registro por destinatario en `campana_destinatarios` (append de estado +
`enviado_at` + `error`) es el histórico del módulo. No se reutiliza `factura_eventos` porque su
FK es `factura_id` y estos envíos no cuelgan de una factura.

**Rationale**: FR-008 pide registrar cada envío reutilizando "el patrón" de eventos (append,
resultado ok/error, sin encadenamiento Verifactu) — no necesariamente la misma tabla. Mantener el
histórico en `campana_destinatarios` evita ensuciar `factura_eventos` con filas sin factura.

**Alternativas descartadas**: añadir `campana_id` nullable a `factura_eventos` (mezcla dominios,
rompe la semántica de esa tabla).

## D5 — Reutilización de infraestructura SMTP (feature 017)

**Decisión**: Reutilizar `TenantMailer` y `EmailTenant` tal cual. Nuevo Mailable `CampanaMail`
(asunto + cuerpo HTML, **sin** adjunto PDF, a diferencia de `FacturaMail`). Envío vía
`$tenantMailer->mailer()->to($email)->send($mailable)`, con `from`/`reply-to` del tenant.

**Rationale**: No cambiar cómo se configura el SMTP (fuera de alcance). Un Mailable propio permite
un cuerpo HTML libre por campaña sin acoplar a la factura.

**Alternativas descartadas**: reutilizar `FacturaMail` (acoplado a una factura y a un adjunto).

## D6 — Selección de destinatarios y editor

**Decisión**: Selección múltiple sobre el listado de clientes existente (checkboxes en la tabla
de clientes, patrón DataTable ya presente). Cuerpo con el editor de texto enriquecido del banco
del template (bloque de composición de `email-compose.blade.php`). CRUD de plantillas sobre el
patrón de `email-template.blade.php` (listado + filtro + modal).

**Rationale**: Reutiliza piezas ya vendorizadas y patrones de UI existentes; no introduce gestor
de contactos ni constructor visual (fuera de alcance por Assumptions de la spec).

**Alternativas descartadas**: constructor drag & drop (sobre-ingeniería); importar contactos
externos (fuera de alcance).

## D7 — Manejo de fallos y edge cases

**Decisión**: Un fallo de envío marca esa fila `fallido` con `error` y continúa con el resto
(try/catch por destinatario dentro de la tanda). Cliente sin email → `fallido` motivo "sin email"
sin intentar SMTP. Destinatarios duplicados → dedup por `cliente_id` al crear la campaña. Sin
SMTP configurado / sin destinatarios / sin asunto o cuerpo → se impide crear/enviar con mensaje.

**Rationale**: Cubre FR-006/FR-007/FR-013/FR-015 y los edge cases de la spec.
