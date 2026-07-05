# Feature Specification: Dashboard de estadísticas

**Feature Branch**: `015-dashboard-estadisticas`

**Created**: 2026-07-04

**Status**: Draft

**Input**: User description: "Dashboard de estadísticas (home del CRM, vista `/`), que reemplaza el placeholder vacío de `dashboard.blade.php`. Al entrar al panel, el usuario ve de un vistazo la salud de su facturación, cobros y stock del período reciente: KPIs de facturación/cobro/pendiente del mes con variación vs mes anterior, evolución de facturación de 12 meses, cobrado vs facturado de 6 meses, distribución de facturas por estado, top 5 clientes, alertas de stock bajo/negativo y últimas facturas emitidas."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Vistazo rápido de facturación y cobros del mes (Priority: P1)

Un usuario del tenant (admin o usuario operativo) entra al panel cada mañana y, sin navegar a ningún módulo, quiere saber cuánto se facturó este mes, cuánto se cobró, cuánto queda pendiente de cobro y si eso mejora o empeora respecto al mes anterior.

**Why this priority**: Es el valor central del dashboard — la razón de ser de la feature. Sin esto, la vista no aporta nada que el usuario no tuviera ya buscando manualmente en `/facturas` y `/pagos`. Es el mínimo que hace viable la home como "panel de control".

**Independent Test**: Con datos de facturas/pagos ya cargados en un tenant de prueba, entrar a `/` y verificar que las cuatro cifras (facturado del mes, cobrado del mes, pendiente de cobro, nº de facturas emitidas) coinciden con lo calculado manualmente desde `/facturas` y `/pagos`, incluyendo el signo y valor de la variación porcentual contra el mes anterior.

**Acceptance Scenarios**:

1. **Given** un tenant con facturas emitidas y pagos registrados en el mes actual y en el mes anterior, **When** el usuario abre `/`, **Then** ve el total facturado del mes, el total cobrado del mes, el saldo pendiente de cobro y el número de facturas emitidas del mes, cada uno con su variación porcentual respecto al mes anterior.
2. **Given** un tenant sin ninguna factura registrada (tenant recién creado), **When** el usuario abre `/`, **Then** las cifras se muestran en cero y no aparece ninguna variación porcentual (no se calcula un porcentaje contra una base de cero), sin errores ni pantallas rotas.
3. **Given** una factura en estado `borrador` o `anulada` dentro del mes, **When** se calculan los totales del mes, **Then** esa factura no se incluye en el total facturado (solo cuentan facturas emitidas y sus estados derivados: emitida, pagada, vencida, rectificada).

---

### User Story 2 - Tendencia de facturación y brecha de cobro (Priority: P2)

Un usuario quiere entender cómo evolucionó la facturación en el último año y detectar de un vistazo si hay un desfase creciente entre lo que se factura y lo que efectivamente se cobra, para anticipar problemas de caja.

**Why this priority**: Da contexto histórico y una señal de alerta temprana (gap facturado vs cobrado) que el usuario no obtendría mirando solo el mes en curso. Es valioso pero no imprescindible para el primer vistazo del día — depende de que exista el dato mensual básico de la Historia 1.

**Independent Test**: Con facturas y pagos distribuidos en varios meses (incluyendo algún mes con facturación pero cobro parcial), abrir `/` y verificar que el gráfico de evolución de 12 meses y el gráfico comparativo de 6 meses muestran los importes correctos mes a mes, incluyendo meses sin actividad (mostrados en cero, no omitidos).

**Acceptance Scenarios**:

1. **Given** un tenant con actividad de facturación en los últimos 12 meses (con algunos meses sin ninguna factura), **When** el usuario ve el gráfico de evolución de facturación, **Then** aparecen los 12 meses en orden cronológico, cada uno con su importe total facturado (en cero si no hubo actividad ese mes).
2. **Given** un tenant con facturación de un mes solo parcialmente cobrada, **When** el usuario ve el gráfico comparativo de 6 meses, **Then** ese mes muestra un importe facturado mayor que el importe cobrado, evidenciando la brecha.
3. **Given** un tenant sin ninguna actividad histórica, **When** el usuario ve ambos gráficos, **Then** se muestra un estado vacío explicativo en lugar de un gráfico con todas las series en cero sin contexto.

---

### User Story 3 - Composición del estado de cartera y acceso rápido (Priority: P3)

Un usuario quiere ver de un vistazo cómo se distribuyen sus facturas por estado (cuántas están pendientes, vencidas, pagadas, etc.), quiénes son sus mejores clientes, qué artículos necesitan reposición urgente de stock, y acceder rápidamente a las últimas facturas emitidas sin tener que abrir el listado completo.

**Why this priority**: Son señales complementarias de valor (priorización de cobranza, relación comercial, gestión de inventario, acceso directo) que enriquecen el dashboard pero no son el motivo principal por el que un usuario entraría a la home. Pueden lanzarse después de que el vistazo financiero (Historias 1 y 2) esté disponible.

**Independent Test**: Con un conjunto de datos que incluya facturas en distintos estados, varios clientes con historiales de facturación distintos, y artículos con stock por debajo del mínimo o negativo, verificar que el dashboard muestra la distribución de estados correcta, el ranking de los 5 clientes con mayor facturación acumulada, la lista de artículos con problema de stock, y las últimas facturas emitidas ordenadas de más a menos reciente.

**Acceptance Scenarios**:

1. **Given** facturas en distintos estados (borrador, emitida, pagada, vencida, anulada, rectificada), **When** el usuario ve la distribución por estado, **Then** el conteo por cada estado coincide con lo que muestra el listado de `/facturas` filtrado por ese estado.
2. **Given** múltiples clientes con distintos volúmenes de facturación acumulada, **When** el usuario ve el ranking de clientes, **Then** aparecen como máximo los 5 clientes con mayor facturación acumulada, ordenados de mayor a menor.
3. **Given** un tenant que gestiona stock y tiene artículos con `stock_actual` por debajo de `stock_minimo` o en negativo, **When** el usuario ve la sección de alertas de stock, **Then** aparecen esos artículos; si el tenant no gestiona stock o no tiene artículos en esa condición, la sección lo indica en vez de mostrarse vacía sin explicación.
4. **Given** un usuario que hace clic en una factura de la lista de "últimas facturas", **When** navega, **Then** llega al detalle de esa factura concreta.

### Edge Cases

- Tenant recién creado sin ninguna factura, pago, cliente o artículo: todas las secciones deben degradar a un estado vacío legible, sin cálculos que dividan por cero ni gráficos rotos.
- Mes anterior sin ninguna facturación pero mes actual con facturación (o viceversa): la variación porcentual no se calcula como número indefinido; se comunica como "sin datos previos para comparar" en vez de un porcentaje engañoso (p. ej. "+∞%").
- Tenant sin gestión de stock activada en ningún artículo: la sección de alertas de stock no debe aparecer vacía sin explicación; debe indicar que no aplica.
- Facturas rectificativas y su factura original: cada una se cuenta según su propio estado; la rectificación no debe hacer que la facturación del mes original quede sobrestimada o duplicada en los agregados.
- Usuario sin permisos para ver ciertos módulos (si en el futuro hay roles con visibilidad restringida): fuera de alcance para esta versión — se asume que cualquier usuario autenticado del tenant puede ver el dashboard completo, igual que puede ver el resto de módulos hoy.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE mostrar, en la página de inicio del panel (`/`), el importe total facturado en el mes en curso, considerando únicamente facturas en estados que representen una emisión válida (emitida, pagada, vencida, rectificada), excluyendo `borrador` y `anulada`.
- **FR-002**: El sistema DEBE mostrar el importe total cobrado en el mes en curso, sumando únicamente pagos vigentes (no anulados) con fecha dentro del mes.
- **FR-003**: El sistema DEBE mostrar el saldo total pendiente de cobro del tenant, calculado a partir de las facturas emitidas cuyo saldo pendiente sea mayor que cero.
- **FR-004**: El sistema DEBE mostrar el número de facturas emitidas durante el mes en curso (mismo criterio de estados que FR-001).
- **FR-005**: El sistema DEBE mostrar, junto a cada una de las cifras de FR-001, FR-002 y FR-004, la variación porcentual respecto al mismo cálculo para el mes calendario inmediatamente anterior.
- **FR-006**: El sistema DEBE mostrar la variación como "sin datos previos para comparar" (u equivalente) en lugar de un porcentaje, cuando el valor base del mes anterior sea cero.
- **FR-007**: El sistema DEBE mostrar la evolución de la facturación mensual (importe total facturado, mismo criterio de estados que FR-001) de los últimos 12 meses calendario, incluyendo meses sin actividad con valor cero, en orden cronológico.
- **FR-008**: El sistema DEBE mostrar una comparación mensual, para los últimos 6 meses calendario, entre el importe total facturado y el importe total cobrado de cada mes.
- **FR-009**: El sistema DEBE mostrar la distribución de facturas por estado (borrador, emitida, pagada, vencida, anulada, rectificada), contando cada factura una única vez según su estado actual.
- **FR-010**: El sistema DEBE mostrar un ranking de como máximo los 5 clientes con mayor importe de facturación acumulada, ordenados de mayor a menor.
- **FR-011**: El sistema DEBE mostrar la lista de artículos cuyo `stock_actual` esté por debajo de su `stock_minimo` o sea negativo, para tenants que gestionen artículos con control de stock activado.
- **FR-012**: El sistema DEBE indicar explícitamente cuando no hay artículos en condición de alerta de stock, y cuando el tenant no tiene ningún artículo con gestión de stock activada, diferenciando ambos casos de "sección vacía sin explicación".
- **FR-013**: El sistema DEBE mostrar las últimas facturas emitidas (entre 5 y 8), con al menos estado, cliente, importe total y fecha, ordenadas de más a menos reciente.
- **FR-014**: El sistema DEBE permitir navegar desde cada factura listada en FR-013 a su vista de detalle correspondiente.
- **FR-015**: El sistema DEBE restringir todos los datos mostrados en el dashboard al tenant activo de la sesión (ningún dato de otro tenant debe ser visible ni contarse en los agregados).
- **FR-016**: El sistema DEBE mostrar un estado vacío explicativo (no un gráfico o listado vacío sin contexto, ni un error) en cada sección cuando el tenant no tenga datos suficientes para calcularla (p. ej. tenant recién creado sin facturas).
- **FR-017**: Todos los cálculos de importes, agregados y variaciones porcentuales mostrados en el dashboard DEBEN calcularse en el servidor; la interfaz solo DEBE renderizar los valores ya calculados.

### Key Entities

- **Resumen mensual de facturación**: agregado (no persistido como tabla nueva) de importe total facturado, importe total cobrado y número de facturas emitidas, calculado a partir de `facturas` y `pagos` para un mes calendario y un tenant dados.
- **Variación mensual**: comparación entre el resumen del mes en curso y el del mes calendario anterior, expresada como porcentaje o como "sin datos previos".
- **Distribución de facturas por estado**: conteo de facturas del tenant agrupado por el campo `estado` existente en `facturas`.
- **Ranking de clientes por facturación**: agregado de facturación acumulada por `cliente_id` sobre `facturas`, limitado a los 5 primeros.
- **Alerta de stock**: artículo (`articulos`) cuyo `stock_actual` está por debajo de `stock_minimo` o es negativo, ya definido como criterio existente en el módulo de stock.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un usuario puede identificar el estado de facturación y cobro del mes en curso (facturado, cobrado, pendiente) en menos de 5 segundos desde que carga la página de inicio, sin navegar a ningún otro módulo.
- **SC-002**: El 100% de las cifras y gráficos del dashboard reflejan exactamente los mismos totales que se obtendrían filtrando manualmente los listados de `/facturas`, `/pagos` y `/stock` con los mismos criterios (mes, estado, cliente).
- **SC-003**: Un tenant sin ningún dato de facturación ve una página de inicio completamente utilizable (sin errores, sin gráficos rotos, sin divisiones por cero visibles) en el 100% de los casos.
- **SC-004**: La página de inicio carga y muestra todos sus datos en menos de 2 segundos en un tenant con volumen de datos típico (hasta unos pocos miles de facturas).

## Assumptions

- Se asume que cualquier usuario autenticado del tenant puede ver el dashboard completo, con la misma visibilidad de datos que ya tiene hoy en los módulos de facturas, pagos, clientes y stock (no se introduce un nuevo esquema de permisos por rol para esta feature).
- "Mes en curso" y "mes anterior" se interpretan como meses calendario (naturales), no períodos móviles de 30 días.
- El ranking de clientes y la distribución de facturas por estado se calculan sobre el histórico completo del tenant (no limitado a los últimos 12 meses), salvo que una futura iteración pida acotarlo.
- Las facturas rectificativas se tratan como registros independientes con su propio estado para todos los agregados; no se resta automáticamente el importe de la factura original al calcular totales del período (el efecto de la rectificación ya queda reflejado en el estado de la factura original, p. ej. `rectificada`).
- No se requiere capacidad de exportar, imprimir o personalizar (elegir rango de fechas, ocultar secciones) el dashboard en esta primera versión — es una vista de solo lectura con período fijo por sección (mes en curso, últimos 6 o 12 meses según corresponda).
- El dashboard no necesita actualizarse en tiempo real (vía websockets/polling); se recalcula al cargar la página.
