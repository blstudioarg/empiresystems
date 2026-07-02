<!--
Sync Impact Report
- Version change: 1.0.0 → 1.1.0
- Modified principles:
  - II. Cumplimiento Normativo España-First — generalizado de "IVA" a impuesto indirecto
    agnóstico al régimen territorial (IVA/IGIC/IPSI), reflejando la nueva sección de
    docs/02-facturacion-espana.md sobre regímenes territoriales.
- Added sections: n/a (no se añadieron secciones de primer nivel)
- Added guidance within Additional Constraints:
  - Catálogo y stock (articulos, movimientos_stock append-only)
  - Compras (Fase 2) — documentado pero no implementado hasta que el MVP lo incorpore
  - Billing del propio SaaS — confirmado fuera de alcance (plans/subscriptions retirados del
    modelo de datos)
- Removed sections: none
- Rationale for MINOR bump: expansión material de guía existente sobre un principio (nuevos
  regímenes fiscales) y nuevas restricciones documentadas; no hay redefinición incompatible ni
  eliminación de principios.
- Templates requiring updates:
  - ✅ .specify/templates/plan-template.md (Constitution Check section es genérica, compatible tal cual)
  - ✅ .specify/templates/spec-template.md (sin referencias en conflicto)
  - ✅ .specify/templates/tasks-template.md (sin referencias en conflicto)
  - ✅ .claude/skills/speckit-*/SKILL.md (referencias genéricas, sin renombres agent-specific necesarios)
- Follow-up TODOs: ninguno. Si se implementa Fase 2 (compras) o billing del SaaS, revisar
  Principio V y esta sección de Additional Constraints en ese momento.
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
Rationale: el corazón del producto es facturar cumpliendo la ley española; un incumplimiento
aquí implica sanciones de hasta 50.000€ por ejercicio y bloquea el uso legal del software. Al
tener tenants en distintos territorios fiscales, asumir IVA en código de forma implícita
sería un bug de cumplimiento, no solo de diseño.

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
- **Compras (Fase 2):** `proveedores`/`compras`/`compra_lineas` quedan documentados en el
  modelo de datos pero NO se implementan hasta que el alcance del MVP (`docs/00-vision.md`)
  los incorpore explícitamente (Principio V, YAGNI).
- **Billing del propio SaaS:** por decisión explícita, `plans`/`subscriptions` NO se modelan
  por ahora; ningún tenant referencia un plan o suscripción en el modelo de datos hasta que se
  defina la pasarela de cobro (ya reflejado como fuera de alcance en `docs/00-vision.md`).

## Development Workflow

- Toda spec/plan/tarea que toque tablas de negocio, cálculo de impuestos, numeración o
  Verifactu DEBE pasar explícitamente por el "Constitution Check" del plan antes de
  implementar.
- Las revisiones (propias o de PR) DEBEN verificar: (1) scope de tenant aplicado, (2) tests de
  aislamiento y de cálculo presentes, (3) inmutabilidad de facturas emitidas respetada, (4) no
  se introdujo complejidad no justificada por el alcance del MVP.
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

**Version**: 1.1.0 | **Ratified**: 2026-07-02 | **Last Amended**: 2026-07-02
