<!--
Sync Impact Report
- Version change: 1.1.1 → 1.2.0
- Modified principles:
  - II. Cumplimiento Normativo España-First — AMPLIADO (no redefinido): se agrega un párrafo
    que extiende el principio de "normativa España-First" a RGPD (Reglamento UE 2016/679) y
    LOPDGDD (LO 3/2018) sobre datos personales, con reglas concretas de minimización, retención/
    purga configurable, y registro de accesos (autorizado/denegado, IP, user-agent, intentos
    fallidos). El párrafo de facturación/Verifactu existente queda intacto.
- Added sections:
  - Additional Constraints: nuevo bullet "Datos personales y retención (RGPD/LOPDGDD)" que fija
    `logs_actividad`/`RetencionLogsTenant`/`logs:purgar` (feature 021) como el patrón a reutilizar.
  - Development Workflow: nuevo ítem (5) en la lista de verificación de revisiones, sobre plazo
    de retención/purga cuando una feature toca datos personales.
- Removed sections: none
- Rationale for MINOR bump: se amplía materialmente el alcance de un principio existente
  (Principio II) con una nueva categoría de obligación legal no negociable (RGPD/LOPDGDD); no es
  una redefinición incompatible de lo ya existente (facturación/Verifactu sigue igual) ni una
  aclaración cosmética, así que no corresponde ni MAJOR ni PATCH.
- Origen: feature 021-logs-actividad-usuarios implementó registro de accesos + retención/purga
  tras investigar que el "registro de eventos" formal de Verifactu (hash/firma) no aplica a este
  log (solo es obligatorio en sistemas NO-VERI*FACTU); en su lugar se identificó la obligación de
  RGPD/LOPDGDD como la que sí aplica, y se decidió formalizarla aquí para que futuras tablas con
  datos personales sigan el mismo patrón sin tener que redescubrirlo.
- Templates requiring updates:
  - ✅ .specify/templates/plan-template.md (Constitution Check es genérico, sin referencias a
    principios específicos por nombre; no requiere cambios)
  - ✅ .specify/templates/spec-template.md (sin cambios necesarios)
  - ✅ .specify/templates/tasks-template.md (sin cambios necesarios)
- Follow-up TODOs: ninguno.
-->

# Empire Systems CRM Constitution

## Core Principles

### I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)
Toda tabla de negocio (no central) DEBE llevar `tenant_id` indexado y estar cubierta por un
global scope de Eloquent (`TenantScope` sobre `BaseModel`) que filtre automáticamente por el
tenant activo. NINGUNA query de negocio puede saltarse este scope salvo en contexto explícito
de Super Admin. Toda feature que toque datos de tenant DEBE incluir tests que verifiquen que
no hay fuga de datos entre tenants (crear ≥2 tenants de prueba y afirmar el aislamiento).
Rationale: la arquitectura elegida es base de datos compartida con aislamiento lógico
(docs/01-arquitectura.md); una fuga entre clientes es una violación de confianza y,
potencialmente, de protección de datos.

### II. Cumplimiento Normativo España-First
El diseño de facturación DEBE cumplir la normativa vigente descrita en
`docs/02-facturacion-espana.md`: numeración correlativa sin huecos por serie, desglose de
impuestos por tipo, series separadas para rectificativas, y los campos mínimos de Verifactu
(huella SHA-256, huella del registro anterior, QR, estado Verifactu) desde el primer diseño
del modelo de datos, aunque el envío efectivo a la AEAT se active más adelante. El impuesto
indirecto NO es siempre IVA: es **agnóstico al régimen territorial** del emisor —IVA
(Península/Baleares), IGIC (Canarias) o IPSI (Ceuta/Melilla)—; toda lógica de cálculo e
impuestos DEBE parametrizarse por `regimen_impositivo` en vez de asumir IVA, y ese régimen
se congela en la factura al emitir igual que el resto de campos inmutables. El recargo de
equivalencia solo aplica bajo régimen IVA. Una factura en estado `emitida` es INMUTABLE: no
se edita ni se borra; toda corrección posterior se hace mediante una factura rectificativa.
Solo el estado `borrador` es editable. Cambios normativos (fechas, tipos, umbrales, nuevos
regímenes) DEBEN reflejarse primero en `docs/02-facturacion-espana.md` antes que en el código.

El cumplimiento normativo España-First no se limita a facturación: cualquier dato personal que
el sistema trate (usuarios, clientes, direcciones IP, agentes de usuario, o cualquier otro dato
identificable) DEBE cumplir RGPD (Reglamento UE 2016/679) y la LOPDGDD (LO 3/2018). En concreto:
(a) principio de minimización — ninguna tabla que almacene datos personales puede conservarlos
indefinidamente sin justificación; DEBE existir un plazo de retención configurable y un mecanismo
de purga periódica, siguiendo el patrón ya establecido por `App\Support\RetencionLogsTenant` +
el comando `logs:purgar` (feature 021) en vez de inventar uno nuevo por feature; (b) registro de
accesos — cuando el dato personal incluye actividad de usuario (altas, bajas, modificaciones,
inicio/cierre de sesión), el registro DEBE distinguir acceso autorizado de denegado y capturar IP
de origen y agente de usuario, igual que `logs_actividad`; (c) los intentos de acceso fallidos
(credenciales inválidas) también son un evento a registrar, no solo los exitosos, para poder
detectar accesos no autorizados. Cambios en el alcance de qué datos se consideran personales o en
los plazos de retención DEBEN reflejarse primero en `docs/03-modelo-datos.md` antes que en el
código, igual que los cambios normativos de facturación en `docs/02-facturacion-espana.md`.

Rationale: el corazón del producto es facturar cumpliendo la ley española; un incumplimiento
aquí implica sanciones de hasta 50.000€ por ejercicio y bloquea el uso legal del software. Al
tener tenants en distintos territorios fiscales, asumir IVA en código de forma implícita
sería un bug de cumplimiento, no solo de diseño. El mismo razonamiento aplica a RGPD/LOPDGDD:
es una obligación legal externa y no negociable para cualquier SaaS que trate datos personales
de ciudadanos españoles/UE, con sanciones propias (hasta 20M€ o 4% de facturación global bajo
RGPD) independientes de las de Verifactu; tratarla como un principio de "normativa España-First"
evita que quede como una ocurrencia tardía feature por feature.

### III. Integridad Financiera Server-Side
Los totales de una factura (bases, cuotas de IVA, recargo, IRPF, total) SIEMPRE se calculan
en el backend a partir de las líneas; el cliente NUNCA es fuente de verdad para un importe.
La asignación de `numero` dentro de una serie se hace dentro de una transacción con bloqueo
para evitar huecos o duplicados en concurrencia. El cálculo de huella/encadenamiento Verifactu
ocurre exclusivamente en el servicio dedicado al emitir, nunca en el cliente ni de forma
ad-hoc en varios lugares del código.
Rationale: los importes y la cadena de hashes son objeto de auditoría fiscal; cualquier
inconsistencia o manipulación posible desde el cliente invalida la fiabilidad del sistema.

### IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)
Para aislamiento multi-tenant, cálculo de impuestos, numeración de series y encadenamiento
Verifactu: los tests se escriben antes que la implementación, deben fallar primero, y luego
se implementa hasta que pasen (Red-Green-Refactor). Ninguna tarea de estas áreas se marca
completa sin sus tests correspondientes en verde. El resto del código (UI, endpoints no
críticos) puede seguir un flujo de test más flexible, pero no estas áreas.
Rationale: son las áreas donde un bug es costoso (fuga de datos entre clientes, factura mal
calculada, sanción regulatoria) y difícil de detectar tarde.

### V. Simplicidad y Compatibilidad con Hosting Compartido
El sistema DEBE poder correr en hosting compartido tipo cPanel/Hostinger (MySQL/MariaDB, sin
permisos para crear bases de datos por código, sin acceso root). No se introducen
dependencias, extensiones o patrones que requieran VPS o infraestructura dedicada mientras el
proyecto siga en esta fase, salvo decisión explícita documentada en `docs/01-arquitectura.md`.
Se prefiere la solución más simple que cumpla los principios I-IV (YAGNI): no se construyen
capacidades para escenarios fuera del alcance del MVP definido en `docs/00-vision.md` (p. ej.
pasarela de cobro de suscripciones, database-per-tenant) hasta que el propio documento de
alcance las incorpore.
Rationale: el camino de escalado ya está decidido (mover a VPS cuando el tráfico concurrente
lo exija) sin rehacer la arquitectura de datos; complejidad prematura solo añade riesgo y
coste de mantenimiento sin necesidad actual.

## Additional Constraints

- **Stack:** Laravel 12 (backend), MySQL/MariaDB (base de datos), multi-tenancy vía
  `stancl/tenancy` (o `spatie/laravel-multitenancy` como alternativa) en modo single-database.
- **Convenciones de datos:** `id` BIGINT autoincrement; `timestamps` en toda tabla;
  `softDeletes` donde aplique; importes monetarios en `DECIMAL(12,2)`; porcentajes en
  `DECIMAL(5,2)`, según `docs/03-modelo-datos.md`.
- **Frontend:** fuera de alcance de las decisiones de este repo por ahora (template externo
  aplicado por Federico); no bloquear features de backend por falta de UI final.
- **Fuentes normativas:** cualquier cambio a reglas fiscales/Verifactu debe citar su fuente
  (BOE, AEAT) igual que en `docs/02-facturacion-espana.md`.
- **Catálogo y stock:** `articulos` unifica producto/servicio; solo `producto` con
  `gestion_stock=true` mueve inventario. `movimientos_stock` es un ledger **append-only**
  (igual que `factura_eventos`): nunca se edita ni se borra un movimiento, las correcciones se
  hacen con un movimiento inverso. `stock_actual` en `articulos` es una caché de lectura;
  `movimientos_stock` es la fuente de verdad. Aplica Principio I (tenant_id) y Principio IV
  (test-first) igual que al resto de tablas de negocio.
- **Compras:** `proveedores`/`compras`/`compra_lineas` están implementadas (feature
  `014-control-stock`): confirmar una compra genera entradas de stock por línea con artículo
  producto+`gestion_stock`; anular genera el reverso. Igual que facturas/movimientos_stock,
  aplica Principio I (tenant_id) y Principio IV (test-first).
- **Billing del propio SaaS:** por decisión explícita, `plans`/`subscriptions` NO se modelan
  por ahora; ningún tenant referencia un plan o suscripción en el modelo de datos hasta que se
  defina la pasarela de cobro (ya reflejado como fuera de alcance en `docs/00-vision.md`).
- **Datos personales y retención (RGPD/LOPDGDD):** referencia de implementación en
  `logs_actividad`/`App\Support\RetencionLogsTenant`/comando `logs:purgar` (feature 021). Toda
  tabla nueva con datos personales reutiliza ese patrón (clave de configuración por tenant +
  comando de purga programado vía `bootstrap/app.php` → `withSchedule`) en vez de definir su
  propio mecanismo de retención ad-hoc.

## Development Workflow

- Toda spec/plan/tarea que toque tablas de negocio, cálculo de impuestos, numeración o
  Verifactu DEBE pasar explícitamente por el "Constitution Check" del plan antes de
  implementar.
- Las revisiones (propias o de PR) DEBEN verificar: (1) scope de tenant aplicado, (2) tests de
  aislamiento y de cálculo presentes, (3) inmutabilidad de facturas emitidas respetada, (4) no
  se introdujo complejidad no justificada por el alcance del MVP, (5) si la feature introduce o
  amplía una tabla con datos personales, que defina plazo de retención y purga (Principio II).
- Cualquier desviación de un principio debe justificarse por escrito en el plan (sección
  "Complexity Tracking" o equivalente) antes de mergear.

## Governance

Esta constitución prevalece sobre cualquier otra práctica o preferencia de estilo dentro del
proyecto. Las enmiendas requieren: (1) documentar el cambio y su motivación, (2) actualizar
este archivo con el Sync Impact Report correspondiente, (3) revisar plantillas dependientes
(`.specify/templates/*`) por si necesitan ajuste, (4) bump de versión según semver:
- **MAJOR**: eliminación o redefinición incompatible de un principio.
- **MINOR**: nuevo principio o sección, o expansión material de guía existente.
- **PATCH**: aclaraciones, redacción, correcciones no semánticas.

Toda spec, plan y PR debe poder justificar su cumplimiento de estos principios; el
incumplimiento sin justificación documentada es motivo de bloqueo en revisión.

**Version**: 1.2.0 | **Ratified**: 2026-07-02 | **Last Amended**: 2026-07-05
