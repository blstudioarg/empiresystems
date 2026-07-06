# Feature Specification: Gestión de horarios de trabajo y cumplimiento de jornada

**Feature Branch**: `025-gestion-horarios-trabajo`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Gestión de horarios de trabajo (cuadrantes) por miembro de equipo, como evolución del módulo de control horario/fichajes (feature 024). Modelar el horario PLANIFICADO de cada trabajador (su turno teórico), distinto del registro real de jornada que ya existe en `fichajes`, y cruzar ambos para medir cumplimiento. Plantillas de horario reutilizables por tenant; asignación a miembro con vigencia temporal (histórico); turnos partidos y jornada variable por día. Alcance v1: definir plantillas + tramos, asignar con vigencia, mostrar turno esperado en Mi Jornada, informe de previsto vs. real (horas esperadas/trabajadas, retrasos, ausencias, exceso), y alertas de incumplimiento reutilizando `alertas`. Fuera de v1: festivos, vacaciones/ausencias justificadas, gestión formal de horas extra."

## Contexto

El módulo de control horario (feature 024) registra la jornada **real**: los `fichajes` son un
ledger append-only de lo que efectivamente ocurrió (entrada/salida/pausas, con hora de servidor y
validación de perímetro). Lo que no existe hoy es el horario **planificado** de cada trabajador —su
cuadrante o turno teórico— ni ninguna forma de contrastar lo previsto con lo realmente fichado.

Esta feature añade esa capa planificada y el cruce con la realidad: administración define horarios
reutilizables, los asigna a los miembros con vigencia temporal, cada miembro ve su turno esperado en
su portal "Mi jornada", y un informe de cumplimiento compara horas esperadas vs. trabajadas,
detectando retrasos, ausencias y exceso de jornada. Los incumplimientos generan alertas sobre la
misma infraestructura de `alertas` que ya usa el fichaje fuera de rango.

El registro de jornada del art. 34.9 ET obliga a documentar la jornada real (ya cubierto por 024);
el horario planificado no es en sí una obligación legal del registro, pero es la base para
interpretar el registro (¿este trabajador cumplió su jornada?) y para su uso habitual en gestión de
plantilla.

## Clarifications

### Session 2026-07-06

- Q: ¿Cómo se define el horario de un miembro? → A: Plantillas de horario **reutilizables** por tenant, asignadas a los miembros (editar la plantilla afecta a todos los que la comparten).
- Q: ¿La asignación de horario conserva histórico? → A: Sí, asignación **con vigencia temporal** (vigente_desde / vigente_hasta); un informe de un periodo pasado se compara contra el horario vigente **entonces**, no el actual.
- Q: ¿Turnos partidos / jornada variable por día? → A: Sí, **varios tramos** (hora_inicio/hora_fin) por día de la semana; un día sin tramos = día libre.
- Q: ¿Alcance v1? → A: Horarios **+ cumplimiento** (definición, asignación, verlo en Mi Jornada, informe previsto-vs-real, alertas de incumplimiento).
- Q: ¿Cómo se evalúa el cumplimiento y se generan las alertas de ausencia/retraso? → A: Un **comando programado diario** (vía `withSchedule` + una sola entrada de cron `schedule:run`, compatible con hosting compartido, mismo patrón que las purgas de 024) evalúa el día anterior y crea las alertas; el **informe se calcula al vuelo** al abrirlo. No se persiste una tabla de resultados de cumplimiento.
- Q: ¿Un día previsto con fichaje incompleto (entrada sin salida) cómo cuenta? → A: **Incidencia a revisar**, no computa jornada completa (no se inventa hora de salida).
- Q: Con turno partido, ¿contra qué se mide el retraso del día? → A: Contra el **inicio de cada tramo** previsto (llegar tarde tras la pausa también cuenta), no solo el primero.
- Q: ¿Un día previsto donde fichó pero menos horas de las previstas cómo se clasifica? → A: **Cumplimiento parcial** (déficit de horas trabajadas vs. previstas); la ausencia se reserva para días con 0 fichajes.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Definir horarios reutilizables (Priority: P1)

Como administrador del tenant, quiero crear y mantener horarios de trabajo con nombre (p. ej.
"Jornada completa mañana", "Media jornada tarde"), cada uno con sus tramos por día de la semana,
para reutilizarlos entre varios miembros sin volver a teclearlos.

**Why this priority**: Es la base del módulo: sin horarios definidos no hay nada que asignar ni con
qué comparar. Es independientemente testeable y ya entrega valor (catálogo de cuadrantes del tenant).

**Independent Test**: Crear un horario "Jornada mañana" con tramos L-V 09:00–13:00 y 15:00–19:00
(turno partido) y sábado/domingo sin tramos; verificar que se guarda, se lista y se puede editar,
que las horas semanales previstas se calculan correctamente (40 h), y que un tramo con
hora_fin ≤ hora_inicio se rechaza.

**Acceptance Scenarios**:

1. **Given** el listado de horarios, **When** el administrador crea un horario con nombre y varios tramos por día, **Then** el horario queda guardado y disponible para asignar.
2. **Given** un horario con un turno partido (dos tramos el mismo día), **When** se guarda, **Then** ambos tramos se conservan y las horas previstas de ese día suman los dos tramos.
3. **Given** un día sin ningún tramo, **When** se guarda el horario, **Then** ese día se interpreta como día libre (0 horas previstas).
4. **Given** un tramo con hora de fin anterior o igual a la de inicio, o dos tramos que se solapan el mismo día, **When** se intenta guardar, **Then** el sistema lo rechaza con un mensaje claro.
5. **Given** un horario ya asignado a miembros, **When** el administrador edita sus tramos, **Then** el cambio aplica a todos los miembros que lo comparten (según la vigencia de su asignación).
6. **Given** un horario asignado a algún miembro, **When** el administrador intenta eliminarlo, **Then** el sistema impide el borrado (o exige reasignar antes) para no dejar asignaciones huérfanas.

---

### User Story 2 - Asignar horarios a los miembros con vigencia (Priority: P1)

Como administrador del tenant, quiero asignar un horario a cada miembro con una fecha de inicio de
vigencia (y opcionalmente de fin), y poder cambiarlo en el tiempo, para que el horario aplicable en
cada fecha quede registrado sin perder el histórico de cuadrantes anteriores.

**Why this priority**: Es lo que conecta el catálogo de horarios con las personas y con el tiempo;
sin vigencia, un cambio de turno reescribiría el pasado y falsearía cualquier informe histórico.

**Independent Test**: Asignar a un miembro el horario A vigente desde el 1 de enero, luego el
horario B desde el 1 de marzo; verificar que para una fecha de febrero el horario aplicable es A y
para una de marzo es B, y que no se pueden solapar dos asignaciones vigentes a la vez.

**Acceptance Scenarios**:

1. **Given** un miembro sin horario, **When** el administrador le asigna un horario con `vigente_desde`, **Then** ese horario pasa a ser el aplicable desde esa fecha en adelante.
2. **Given** un miembro con un horario vigente, **When** se le asigna uno nuevo desde una fecha posterior, **Then** el horario anterior se cierra (su vigencia termina el día antes) y el nuevo pasa a aplicar, conservándose ambos en el histórico.
3. **Given** una fecha cualquiera, **When** se consulta el horario aplicable de un miembro, **Then** se devuelve el que estaba vigente esa fecha (o "sin horario" si no había ninguno).
4. **Given** dos asignaciones cuyos rangos de vigencia se solaparían, **When** se intenta guardar, **Then** el sistema lo rechaza con un mensaje claro (un miembro no tiene dos horarios a la vez).
5. **Given** un miembro dado de baja, **When** se consulta su histórico, **Then** sus asignaciones pasadas se conservan y siguen siendo consultables.

---

### User Story 3 - Ver el turno esperado en "Mi jornada" (Priority: P2)

Como miembro del equipo, quiero ver en mi portal "Mi jornada" cuál es mi turno esperado (hoy y la
semana), junto a mis fichajes, para saber a qué hora me toca entrar y salir sin preguntar.

**Why this priority**: Aporta valor directo al trabajador y aprovecha el portal ya existente, pero
depende de que primero existan horarios (US1) y asignaciones (US2).

**Independent Test**: Con un miembro que tiene asignado un horario, abrir "Mi jornada" y verificar
que muestra los tramos previstos del día de hoy y de la semana según su horario vigente; un miembro
sin horario asignado ve un estado vacío claro.

**Acceptance Scenarios**:

1. **Given** un miembro con horario vigente hoy, **When** abre "Mi jornada", **Then** ve los tramos previstos de hoy (o "día libre" si no tiene tramos hoy).
2. **Given** el mismo miembro, **When** consulta la vista semanal, **Then** ve el turno previsto de cada día de la semana según su horario vigente.
3. **Given** un miembro sin horario asignado, **When** abre "Mi jornada", **Then** ve un estado vacío ("sin horario asignado") sin errores.

---

### User Story 4 - Informe de cumplimiento previsto vs. real (Priority: P2)

Como administrador del tenant, quiero un informe que compare, para un rango de fechas y por miembro,
las horas previstas por su horario vigente frente a las horas realmente fichadas, señalando
retrasos, ausencias (día previsto sin fichar) y exceso de jornada, para gestionar el cumplimiento de
la plantilla.

**Why this priority**: Es el pago de haber modelado el horario planificado; convierte datos en una
lectura accionable. Depende de US1/US2 (horario vigente) y de los `fichajes` de 024.

**Independent Test**: Un miembro con horario L-V 09:00–17:00 que un día ficha 09:20–17:00 (retraso),
otro día no ficha (ausencia) y otro ficha 09:00–18:30 (exceso); el informe del rango muestra las
horas previstas totales, las trabajadas totales, y marca ese retraso, esa ausencia y ese exceso.

**Acceptance Scenarios**:

1. **Given** un rango de fechas y un miembro con horario vigente, **When** se genera el informe, **Then** muestra las horas previstas y las horas realmente trabajadas (derivadas de sus fichajes) del periodo.
2. **Given** un día en que el miembro fichó su entrada más tarde que el inicio de su tramo previsto (más allá de una tolerancia), **When** se ve el informe, **Then** ese día se marca como retraso con la magnitud.
3. **Given** un día previsto con tramos pero sin ningún fichaje del miembro, **When** se ve el informe, **Then** ese día se marca como ausencia.
4. **Given** un día en que las horas trabajadas superan las previstas (más allá de una tolerancia), **When** se ve el informe, **Then** ese día se marca como exceso de jornada, mostrando la diferencia.
5. **Given** un rango que abarca un cambio de horario del miembro, **When** se genera el informe, **Then** cada día se compara contra el horario que estaba vigente **ese** día.
6. **Given** un día que cae en día libre del horario del miembro, **When** hay o no fichajes ese día, **Then** el día no cuenta como ausencia (0 horas previstas); un fichaje en día libre se refleja como horas trabajadas fuera de horario, no como cumplimiento.

---

### User Story 5 - Alertas de incumplimiento (Priority: P3)

Como administrador del tenant, quiero que los incumplimientos relevantes (ausencia en día previsto,
retraso significativo) generen una alerta gestionable, igual que ya ocurre con el fichaje fuera de
rango, para no tener que revisar el informe a diario para enterarme.

**Why this priority**: Mejora la proactividad pero es incremental sobre el informe (US4); el valor
central ya está sin ella. Reutiliza la infraestructura de `alertas` existente.

**Independent Test**: Configurar el umbral de retraso; provocar una ausencia y un retraso por encima
del umbral; verificar que se crean alertas del nuevo tipo, visibles y gestionables (nueva/vista/
resuelta) en la bandeja de alertas existente, sin duplicar la de fichaje fuera de rango.

**Acceptance Scenarios**:

1. **Given** un día previsto sin fichaje del miembro, **When** el sistema evalúa el cumplimiento, **Then** genera una alerta de ausencia gestionable en la bandeja de alertas.
2. **Given** un retraso superior al umbral configurado, **When** el sistema evalúa el cumplimiento, **Then** genera una alerta de retraso.
3. **Given** una alerta de incumplimiento, **When** el administrador la gestiona, **Then** puede marcarla vista/resuelta igual que las alertas existentes, y no se borra.
4. **Given** las alertas de fichaje fuera de rango ya existentes, **When** se añaden las de incumplimiento, **Then** conviven en la misma bandeja sin romper ni duplicar las anteriores.

---

### Edge Cases

- **Tramo que cruza medianoche** (turno de noche 22:00–06:00): definir si un tramo puede terminar el día siguiente o si se parte en dos tramos; en v1 se asume que los tramos no cruzan medianoche (ver Assumptions).
- **Cambio de horario a mitad de día**: la vigencia se resuelve por fecha (día), no por hora; un cambio aplica a días completos.
- **Miembro sin ubicación de trabajo pero con horario**: el cumplimiento de horas no depende del geofencing; un fichaje `SinUbicacion` sigue contando sus horas trabajadas.
- **Fichaje incompleto** (entrada sin salida, o salida sin entrada, en un día previsto): se marca como incidencia a revisar y no computa jornada completa (FR-015a); no se infiere hora de salida.
- **Alerta de ausencia sin evento disparador**: la ausencia (día previsto sin fichar) no la origina ninguna acción del trabajador, por eso la evalúa el comando diario sobre el día anterior (FR-019), no un trigger en tiempo de fichaje.
- **Pausas** (`inicio_pausa`/`fin_pausa` del ledger de 024): definir si las horas trabajadas descuentan las pausas fichadas (ver Assumptions).
- **Horario con todos los días sin tramos**: horario válido de 0 horas (p. ej. plantilla base a completar); no debería romper informes.
- **Rango de informe muy amplio o miembro dado de baja a mitad de rango**: el informe debe seguir siendo correcto y legible.
- **Solapamiento de tramos dentro del mismo día**: se rechaza al guardar el horario.

## Requirements *(mandatory)*

### Functional Requirements

#### Definición de horarios (US1)

- **FR-001**: El sistema MUST permitir crear, editar y listar horarios de trabajo con un nombre, propios de cada tenant y reutilizables entre miembros.
- **FR-002**: Cada horario MUST poder definir, por día de la semana, cero o más tramos de trabajo (hora de inicio y hora de fin), soportando turnos partidos (varios tramos el mismo día) y jornada variable por día.
- **FR-003**: Un día sin tramos MUST interpretarse como día libre (0 horas previstas) de forma consistente en toda la feature.
- **FR-004**: El sistema MUST validar que cada tramo tiene hora de fin posterior a la de inicio y que dos tramos del mismo día no se solapan, rechazando lo contrario con un mensaje claro.
- **FR-005**: El sistema MUST calcular las horas previstas de un horario (por día y semanales) en el backend, a partir de sus tramos.
- **FR-006**: El sistema MUST impedir eliminar un horario que tenga asignaciones (vigentes o históricas) sin una acción explícita que preserve la integridad del histórico (p. ej. exigir que no queden miembros asignados), con mensaje claro.

#### Asignación con vigencia (US2)

- **FR-007**: El sistema MUST permitir asignar un horario a un miembro con una fecha de inicio de vigencia y una fecha de fin opcional.
- **FR-008**: El sistema MUST resolver, para un miembro y una fecha dada, cuál es su horario aplicable (el vigente esa fecha), o indicar que no tiene horario.
- **FR-009**: El sistema MUST impedir que un miembro tenga dos asignaciones de horario con vigencias solapadas para la misma fecha.
- **FR-010**: Al asignar un horario nuevo desde una fecha, el sistema MUST cerrar automáticamente la vigencia de la asignación anterior (terminándola el día previo), conservando ambas en el histórico.
- **FR-011**: El histórico de asignaciones MUST conservarse aunque el miembro cambie de horario o sea dado de baja (no se reescribe ni se borra el pasado).

#### Turno esperado en Mi Jornada (US3)

- **FR-012**: El portal "Mi jornada" del miembro MUST mostrar los tramos previstos de hoy y de la semana según su horario vigente.
- **FR-013**: Cuando el miembro no tiene horario vigente, "Mi jornada" MUST mostrar un estado vacío claro sin error.

#### Informe de cumplimiento (US4)

- **FR-014**: El sistema MUST ofrecer un informe, por rango de fechas y por miembro, que compare horas previstas (según el horario vigente cada día) con horas realmente trabajadas (derivadas de los `fichajes`).
- **FR-015**: El informe MUST marcar por día: retraso (primer fichaje de entrada de **cada tramo** previsto más tardío que el inicio de ese tramo, más allá de una tolerancia configurable), ausencia (día previsto con horas > 0 y **cero fichajes**), cumplimiento parcial (fichó pero las horas trabajadas quedan por debajo de las previstas: déficit de horas) y exceso de jornada (horas trabajadas por encima de las previstas más allá de una tolerancia).
- **FR-015a**: Un día previsto con fichaje **incompleto** (entrada sin su salida correspondiente) MUST marcarse como incidencia a revisar y NO computar como jornada completa (no se infiere una hora de salida).
- **FR-016**: El cálculo del cumplimiento MUST usar, para cada día del rango, el horario que estaba vigente ese día (no el horario actual), respetando el histórico de asignaciones.
- **FR-017**: Los días libres (0 horas previstas) MUST NOT contar como ausencia; los fichajes en día libre se reflejan como horas trabajadas fuera de horario, no como incumplimiento.
- **FR-018**: Todos los cálculos de horas y de cumplimiento MUST realizarse en el backend, con precisión coherente con el resto del proyecto.

#### Alertas de incumplimiento (US5)

- **FR-019**: El sistema MUST generar las alertas de incumplimiento (al menos: ausencia en día previsto y retraso por encima del umbral) mediante un **comando programado diario** que evalúa el día anterior (registrado vía `withSchedule`, ejecutable en hosting compartido con una sola entrada de cron `schedule:run`, mismo patrón que las purgas de la feature 024), reutilizando la infraestructura de `alertas` existente (extendiendo sus tipos, sin duplicar tablas). El comando MUST ser idempotente (no duplicar alertas si se re-ejecuta sobre el mismo día).
- **FR-019a**: El informe de cumplimiento (FR-014/FR-015) MUST calcularse **al vuelo** al consultarlo, sin depender de una tabla persistida de resultados, para reflejar correctamente cambios retroactivos de horario.
- **FR-020**: Las alertas de incumplimiento MUST ser gestionables (nueva/vista/resuelta) igual que las alertas de fichaje fuera de rango, sin borrarse y sin romper ni duplicar las existentes.
- **FR-021**: El umbral de retraso (y cualquier tolerancia de cumplimiento) MUST ser configurable por tenant, siguiendo el patrón de configuración por tenant ya usado en el proyecto.

#### Restricciones transversales

- **FR-022**: Todas las tablas y consultas nuevas MUST respetar el aislamiento multi-tenant (solo datos del tenant activo).
- **FR-023**: La feature MUST reutilizar `miembros_equipo`, `fichajes` y `alertas` (feature 024) en lugar de duplicar entidades equivalentes.
- **FR-024**: La feature MUST mantenerse enfocada al alcance v1, sin construir festivos, vacaciones/ausencias justificadas ni gestión formal de horas extra (fuera de alcance).

### Key Entities *(include if feature involves data)*

- **Horario**: plantilla de cuadrante con nombre, propia del tenant, reutilizable. Se compone de tramos por día de la semana. Atributos: nombre, estado (activo), horas previstas derivadas.
- **Tramo de horario**: intervalo de trabajo dentro de un horario, asociado a un día de la semana (0–6) con hora de inicio y hora de fin. Varios tramos por día = turno partido; ningún tramo = día libre.
- **Asignación de horario a miembro**: vínculo entre un miembro de equipo y un horario, con vigencia temporal (desde / hasta opcional). Conserva el histórico; determina el horario aplicable de un miembro en cada fecha.
- **Miembro de equipo** (existente, 024): la persona a la que se asigna el horario y de quien se leen los fichajes.
- **Fichaje** (existente, 024): fuente de las horas realmente trabajadas para el cruce de cumplimiento; se lee, no se modifica.
- **Alerta** (existente, 024): soporte de las alertas de incumplimiento (nuevos tipos), gestionable por administración.
- **Resultado de cumplimiento (derivado)**: por miembro y día/rango: horas previstas, horas trabajadas, y marcas de retraso/ausencia/exceso. No necesariamente persistido; puede calcularse al consultar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un administrador puede definir un horario con turno partido y jornada variable por día y asignarlo a varios miembros sin volver a teclearlo, editándolo en un solo lugar.
- **SC-002**: Para cualquier fecha, el horario aplicable de un miembro que ha cambiado de cuadrante es siempre el vigente esa fecha (verificable con una prueba automatizada sobre el histórico).
- **SC-003**: El informe de cumplimiento muestra, para un rango dado, horas previstas vs. trabajadas por miembro y marca correctamente retraso, ausencia y exceso en el 100% de los casos de prueba definidos.
- **SC-004**: Un cambio de horario a mitad del rango del informe se refleja correctamente día a día (cada día contra su horario vigente), sin falsear el pasado.
- **SC-005**: Una ausencia en día previsto y un retraso por encima del umbral generan alertas gestionables en la misma bandeja que las alertas existentes, sin duplicarlas ni romperlas.
- **SC-006**: Un miembro sin horario, un día libre y un tenant sin datos no producen errores en ninguna de las vistas ni en el informe (estados vacíos claros).
- **SC-007**: Ningún cálculo de horas o de cumplimiento depende del cliente; recargar o reproducir el informe da el mismo resultado (cálculo server-side).

## Assumptions

- **Reutilización del módulo 024**: `miembros_equipo`, `fichajes` y `alertas` se reutilizan; esta feature añade el horario planificado y el cruce, sin tocar la naturaleza append-only del ledger de fichajes.
- **Tramos dentro del mismo día natural**: en v1 un tramo no cruza medianoche; un turno de noche se modela con la parte de cada día o queda fuera de alcance hasta que se pida explícitamente.
- **Vigencia por día**: la resolución del horario aplicable es a nivel de fecha (día completo), no por hora.
- **Horas trabajadas desde fichajes**: se derivan emparejando entrada/salida del día; las pausas fichadas (`inicio_pausa`/`fin_pausa`) se descuentan de las horas trabajadas. Un día con fichaje incompleto (entrada sin salida) se marca como incidencia a revisar, no como jornada completa (FR-015a).
- **Evaluación diaria en hosting compartido**: el comando de evaluación de cumplimiento/alertas se registra vía `withSchedule` (`bootstrap/app.php`) y corre con la única entrada de cron `schedule:run` que el hosting compartido ya necesita para las purgas de la feature 024; no requiere worker ni supervisor (coherente con Principio V).
- **Tolerancias configurables por tenant**: umbral de retraso y margen de exceso son configuración por tenant (patrón `configuraciones`/clase `Support`), con defaults razonables.
- **Ausencias justificadas fuera de alcance**: en v1 una ausencia es "día previsto sin fichaje"; no se distingue baja/vacaciones/permiso (eso es una feature posterior). Por eso las alertas de ausencia son gestionables (se pueden resolver a mano si estaban justificadas).
- **Sin festivos ni calendario laboral en v1**: un festivo se comporta como un día previsto normal salvo que el horario del miembro no tenga tramos ese día de la semana; el calendario de festivos es fuera de alcance.
- **Reutilización de patrones de UI**: se usan los patrones del template ya vendorizado (DataTable, modal CRUD, etc., `docs/04-front-guidelines.md`) y las skills de diseño del proyecto para las vistas nuevas.
- **Alcance visual**: no se introducen dependencias de front nuevas salvo justificación documentada.
