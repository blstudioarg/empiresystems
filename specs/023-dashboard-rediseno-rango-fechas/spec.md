# Feature Specification: Rediseño del Dashboard con filtro por rango de fechas

**Feature Branch**: `023-dashboard-rediseno-rango-fechas`

**Created**: 2026-07-05

**Status**: Draft

**Input**: User description: "Rediseño y actualización del Dashboard de estadísticas (evolución de la feature 015), añadir filtro por rango de fechas, corregir el doble conteo de rectificativas por sustitución en el importe facturado, y revisar KPIs/widgets ahora que la app creció (stock/compras, rectificativas, cobros, POS, Facturae, logs)."

## Contexto

El dashboard actual (feature 015-dashboard-estadisticas) se diseñó cuando la app solo tenía
clientes, artículos y facturas. Desde entonces se añadieron: control de stock y compras, facturas
rectificativas (por sustitución y por diferencias), registro de cobros, POS/facturas simplificadas,
emisión/recepción Facturae y logs de actividad. El dashboard quedó desactualizado en dos frentes:
(1) su cifra de "facturado" cuenta dos veces las rectificativas por sustitución, y (2) no refleja las
nuevas áreas de negocio ni permite acotar el análisis a un periodo distinto del mes en curso.

## Clarifications

### Session 2026-07-05

- Q: Cuando la factura original y su rectificativa caen en periodos distintos, ¿a qué periodo se imputa la facturación neta? → A: Al periodo de la factura **original** (el neto ya ajustado, vía `totalCobrable()` de la original; una corrección posterior recalcula retroactivamente el periodo de la original).
- Q: Los presets "en curso" (mes/trimestre/año), ¿hasta qué fecha llegan? → A: Desde el inicio del periodo natural **hasta hoy** (no hasta el fin del periodo natural).
- Q: Las ventas de POS/facturas simplificadas, ¿cómo aparecen en el dashboard? → A: Como **métrica separada** de "ventas POS" del periodo, excluidas del "facturado" fiscal (no sumadas a él).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Ver métricas correctas de facturación (Priority: P1)

Como responsable de una empresa (tenant), quiero que la cifra de facturación del dashboard sea la
facturación real neta, para tomar decisiones sobre datos correctos y no sobre importes inflados por
las rectificativas.

**Why this priority**: Es un bug de integridad financiera. Un KPI que sobreestima la facturación
(sumando la factura original rectificada *y* su sustituta) induce a error directo en la lectura del
negocio. Corregirlo es el mínimo imprescindible; el resto de la feature construye sobre esta base.

**Independent Test**: Crear una factura de 100 € y rectificarla por sustitución con una nueva de
75 €; el dashboard debe mostrar 75 € de facturación (no 175 €). Repetir por diferencias (original
100 € + rectificativa delta −25 €) y verificar que también muestra 75 €.

**Acceptance Scenarios**:

1. **Given** una factura emitida de 100 € rectificada por sustitución por otra de 75 €, **When** el usuario abre el dashboard, **Then** el importe facturado del periodo cuenta esa operación como 75 € una sola vez.
2. **Given** una factura emitida de 100 € rectificada por diferencias (delta −25 €), **When** el usuario abre el dashboard, **Then** el importe facturado del periodo cuenta esa operación como 75 € (neto).
3. **Given** facturas en estado borrador o anulada, **When** el usuario abre el dashboard, **Then** no se incluyen en el importe facturado.
4. **Given** el ranking de top clientes, **When** hay rectificativas por sustitución, **Then** el importe atribuido a cada cliente usa el importe efectivo neto, sin doble conteo.
5. **Given** las facturas simplificadas del POS, **When** se calcula la facturación del dashboard, **Then** no se incluyen (viven en su propio módulo), salvo que la feature decida explícitamente lo contrario.

---

### User Story 2 - Filtrar el dashboard por rango de fechas (Priority: P1)

Como usuario del tenant, quiero elegir el periodo del dashboard (mes actual, trimestre, año o un
rango personalizado desde/hasta), para analizar la evolución del negocio en el intervalo que me
interesa, no solo el mes en curso.

**Why this priority**: Es el valor funcional nuevo principal que pide el usuario. Sin él, el
dashboard solo responde "¿cómo va este mes?", limitando su utilidad para cierres trimestrales,
anuales o análisis puntuales.

**Independent Test**: Seleccionar "Año en curso" y verificar que los KPIs y el importe facturado
reflejan todas las facturas del año; luego un rango personalizado de dos días con una única factura
dentro y verificar que solo esa cuenta.

**Acceptance Scenarios**:

1. **Given** el dashboard recién abierto, **When** no se elige rango, **Then** por defecto muestra el mes en curso (comportamiento equivalente al actual).
2. **Given** el selector de rango, **When** el usuario elige "Trimestre en curso", "Año en curso" o "Mes en curso", **Then** todos los KPIs, series y rankings se recalculan para ese periodo.
3. **Given** el rango personalizado, **When** el usuario introduce una fecha desde y una hasta válidas, **Then** el dashboard muestra los datos de ese intervalo (ambos extremos incluidos).
4. **Given** un rango personalizado con "desde" posterior a "hasta", **When** se aplica, **Then** el sistema lo rechaza con un mensaje claro y no rompe la vista.
5. **Given** un rango sin actividad, **When** se aplica, **Then** los widgets muestran su estado vacío ("sin datos en el periodo") en lugar de error o cifras engañosas.
6. **Given** el rango aplicado, **When** el usuario recarga o comparte la URL, **Then** el mismo rango se conserva.

---

### User Story 3 - Dashboard actualizado a la app actual (Priority: P2)

Como usuario del tenant, quiero que el dashboard refleje las áreas de negocio que la app ya cubre
(compras/gastos, IVA repercutido vs. soportado, márgenes, actividad reciente), para tener una foto
completa y no solo de ventas.

**Why this priority**: Aporta valor significativo pero depende de que primero existan datos
correctos (US1) y el marco temporal (US2). Es una mejora incremental sobre una base sólida.

**Independent Test**: Con compras y facturas registradas en el periodo, verificar que el dashboard
muestra el resultado (facturado − comprado/gastos) y el IVA repercutido vs. soportado del periodo.

**Acceptance Scenarios**:

1. **Given** compras registradas en el periodo, **When** se abre el dashboard, **Then** se muestra el total de gastos/compras del periodo y el resultado (ingresos − gastos).
2. **Given** facturas emitidas y compras con IVA en el periodo, **When** se abre el dashboard, **Then** se muestra el IVA repercutido (ventas) frente al soportado (compras) del periodo.
2b. **Given** ventas de POS/simplificadas en el periodo, **When** se abre el dashboard, **Then** se muestran como métrica separada de "ventas POS", sin sumarse al facturado fiscal.
3. **Given** el dashboard, **When** se renderiza, **Then** mantiene un conjunto acotado de widgets (no sobrecargado) priorizando los más accionables.
4. **Given** un tenant que no gestiona stock ni tiene compras, **When** abre el dashboard, **Then** los widgets no aplicables muestran su estado vacío sin romper el layout.

---

### Edge Cases

- **Rango que cruza el reinicio anual de series**: el importe facturado debe seguir siendo correcto aunque el periodo abarque facturas de dos años/series distintas.
- **Rectificativa dentro del rango pero original fuera (o viceversa)**: la atribución temporal de la facturación neta se hace por la fecha de expedición de cada documento; hay que definir cómo cuenta el neto cuando original y rectificativa caen en periodos distintos (ver Assumptions).
- **Cobros de un pago cuya factura fue emitida en otro periodo**: "cobrado" se imputa por la fecha del pago, no la de la factura.
- **Rango personalizado muy amplio (varios años)**: la serie temporal debe agrupar de forma legible (p. ej. por mes) sin degradar el rendimiento.
- **Zona horaria del tenant**: los límites de "mes/trimestre/año en curso" deben calcularse de forma consistente para no incluir/excluir facturas del día frontera por error.
- **Tenant recién creado sin datos**: todos los widgets en estado vacío, sin errores.

## Requirements *(mandatory)*

### Functional Requirements

#### Corrección de la facturación (US1)

- **FR-001**: El importe facturado del dashboard MUST contar cada operación económica una sola vez con su importe efectivo neto, excluyendo las facturas rectificativas del sumatorio y usando el importe efectivo de la factura original (que ya refleja el neto por diferencias y el total de la sustituta por sustitución).
- **FR-002**: El cálculo de facturación MUST incluir solo facturas en estados de facturación real (emitida y equivalentes, factura original rectificada incluida) y MUST excluir borradores y anuladas.
- **FR-003**: La corrección de FR-001 MUST aplicarse de forma consistente a todos los indicadores derivados de facturación: KPI de facturado del periodo, serie temporal de facturación, comparativo facturado vs. cobrado y ranking de top clientes.
- **FR-004**: Las facturas simplificadas (POS) MUST excluirse del "facturado" fiscal del dashboard y MUST mostrarse en su lugar como una métrica separada de "ventas POS" del periodo (no sumadas al facturado fiscal).
- **FR-004b**: La facturación neta de una operación rectificada MUST imputarse íntegramente al periodo de la factura **original** (según su fecha de expedición), reflejando el ajuste de la rectificativa vía el importe efectivo de la original; la rectificativa no traslada el neto a su propio periodo.

#### Filtro por rango de fechas (US2)

- **FR-005**: El dashboard MUST ofrecer un selector de rango con, al menos: mes en curso, trimestre en curso, año en curso y rango personalizado (fecha desde / fecha hasta).
- **FR-006**: El rango por defecto (sin selección) MUST ser el mes en curso. Los presets "en curso" (mes/trimestre/año) MUST abarcar desde el inicio del periodo natural hasta la fecha de hoy (no hasta el fin del periodo natural).
- **FR-007**: Todos los KPIs, series y rankings del dashboard MUST recalcularse según el rango seleccionado.
- **FR-008**: La facturación se filtra por la fecha de expedición de la factura y el cobrado por la fecha del pago, dentro del rango (ambos extremos incluidos).
- **FR-009**: El sistema MUST validar el rango personalizado (fechas válidas, "desde" ≤ "hasta") y rechazar rangos inválidos con un mensaje claro, sin romper la vista.
- **FR-010**: El rango seleccionado MUST persistir al recargar o compartir la URL del dashboard.
- **FR-011**: Las comparativas relativas ("vs. periodo anterior") MUST compararse contra el periodo inmediatamente anterior de igual duración al rango seleccionado.
- **FR-012**: Los indicadores que son intrínsecamente "a fecha de hoy" (p. ej. pendiente de cobro total, alertas de stock actuales) MUST indicar claramente que no dependen del rango, o quedar fuera del área filtrada.

#### Rediseño y nuevos widgets (US3)

- **FR-013**: El dashboard MUST presentar, además de la facturación, la actividad de compras/gastos del periodo y el resultado (ingresos − gastos).
- **FR-014**: El dashboard MUST mostrar el IVA/impuesto repercutido (ventas) frente al soportado (compras) del periodo, respetando el régimen impositivo del tenant.
- **FR-015**: El dashboard MUST conservar los widgets vigentes que siguen aportando valor (facturado, cobrado, pendiente de cobro, top clientes, alertas de stock, distribución por estado, facturas recientes) revisando su relevancia.
- **FR-016**: El dashboard MUST mantenerse enfocado: un número acotado de widgets, priorizando los accionables, evitando sobrecarga.
- **FR-017**: Cada widget MUST tener un estado vacío claro cuando no hay datos en el periodo o no aplica al tenant (sin stock, sin compras, etc.).

#### Restricciones transversales

- **FR-018**: Todos los cálculos MUST respetar el aislamiento multi-tenant (solo datos del tenant activo).
- **FR-019**: Todos los cálculos financieros MUST realizarse en el backend, con la precisión monetaria del proyecto (céntimos donde aplique), coherentes con la lógica de cobros/rectificativas existente (el importe efectivo es el neto de la factura original).
- **FR-020**: El dashboard MUST seguir siendo accesible y legible en los tamaños de pantalla soportados por el resto de la app.

### Key Entities *(include if feature involves data)*

- **Factura**: fuente de la facturación. Atributos relevantes: estado, tipo (ordinaria/rectificativa/simplificada), modalidad de rectificación, importe efectivo neto, fecha de expedición, cliente. La facturación del periodo se deriva de las facturas originales (no rectificativas) en estados de facturación.
- **Pago (cobro)**: fuente del "cobrado". Atributos: importe, fecha, estado (vigente/anulado). Se imputa por fecha del pago.
- **Compra**: fuente de gastos e IVA soportado del periodo. Atributos: importe, impuestos, fecha, estado.
- **Rango de fechas (parámetro de consulta)**: no persiste en base de datos; es un parámetro de la vista (preset o desde/hasta) que acota todos los cálculos.
- **Artículo (stock)**: fuente de las alertas de stock (indicador "a fecha de hoy", no dependiente del rango).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En una operación de 100 € rectificada por sustitución a 75 € (o por diferencias a neto 75 €), la facturación mostrada por el dashboard es exactamente 75 €, no 175 €, en el 100% de los casos.
- **SC-002**: El importe facturado del dashboard coincide, para cualquier rango, con la suma de los importes efectivos netos de las facturas facturables del periodo (excluyendo borradores, anuladas, simplificadas y las propias rectificativas), verificable con una prueba automatizada.
- **SC-003**: El usuario puede cambiar el periodo analizado (mes/trimestre/año/personalizado) y ver todos los KPIs recalculados sin recargar mentalmente ni salir del dashboard.
- **SC-004**: Un rango personalizado inválido nunca produce un error de página; siempre devuelve un mensaje comprensible.
- **SC-005**: El dashboard muestra al menos una métrica de gastos/resultado y una de impuestos (repercutido vs. soportado) que antes no existían, para el periodo seleccionado.
- **SC-006**: Para un tenant sin datos o sin actividad en el periodo, todos los widgets muestran estado vacío y ninguno produce error.

## Assumptions

- **Atribución temporal de la facturación neta** (resuelto en Clarifications): la facturación del periodo = suma de los importes efectivos netos de las facturas **originales** facturables cuya fecha de expedición cae en el rango. El efecto de una rectificativa se imputa siempre al periodo de su original (vía `totalCobrable()`), aunque la rectificativa se emita en otro periodo.
- **"Cobrado" por fecha de pago**: los cobros se imputan por la fecha del pago, no por la fecha de la factura.
- **Presets de rango** (resuelto en Clarifications): "en curso" = desde el inicio del periodo natural (mes/trimestre/año) hasta hoy.
- **Pendiente de cobro y alertas de stock** son indicadores "a fecha de hoy" y no se filtran por el rango; se marcarán como tales.
- **Régimen impositivo**: el widget de impuestos usa el régimen del tenant (IVA/IGIC/IPSI); el término "IVA" en la UI se adapta al régimen, coherente con el resto de la app.
- **Reutilización**: se reutiliza el servicio de estadísticas existente (evolución de `DashboardEstadisticas`) y la lógica de importe efectivo de `Factura`, en lugar de duplicar reglas de negocio.
- **Alcance visual**: el rediseño usa las skills de diseño del proyecto y el sistema de componentes del template ya vendorizado; no introduce dependencias de front nuevas salvo justificación.
- **Fuera de alcance (v1)**: exportación del dashboard a PDF/Excel, comparativas contra objetivos/presupuestos, y personalización por usuario de qué widgets ver.
