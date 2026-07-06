# Feature Specification: Calendario de fichajes y horarios

**Feature Branch**: `026-calendario-fichajes`

**Created**: 2026-07-06

**Status**: Draft

**Input**: User description: "Calendario de fichajes y horarios: nuevo módulo en el dropdown de fichajes del sidebar, con un calendario (FullCalendar vendorizado del banco del template) que proyecta los datos ya existentes de las features 024/025 sin tablas nuevas. Alcance v1: vista mensual con veredicto de cumplimiento por día (colores por veredicto de ServicioCumplimiento: libre/ausencia/retraso/parcial/cumplido/exceso), vista semanal/diaria con los tramos previstos del horario vigente y los fichajes reales superpuestos, filtro por miembro de equipo, y acciones vía modales que reutilizan endpoints existentes (asignar/cambiar horario del miembro, abrir corrección de fichaje desde un evento). Los eventos se cargan por rango visible desde un endpoint JSON tenant-scoped bajo can:gestiona-fichajes, calculado en backend. Fuera de alcance v1: drag & drop de escritura (mover tramos o fichajes arrastrando), edición de plantillas de horario desde el calendario, excepciones/turnos puntuales por día, festivos. El ledger de fichajes sigue append-only: nunca se crean/mueven fichajes desde el calendario, solo el flujo de corrección existente."

## Contexto

Las features 024 (control horario/fichajes) y 025 (gestión de horarios y cumplimiento) ya modelan
todo el dato: el ledger real de `fichajes`, el horario planificado (`horarios`/`horario_tramos`/
`asignaciones_horario`) y el veredicto de cumplimiento por día calculado al vuelo. Hoy esa
información se consume en tablas e informes (listado de fichajes, informe de jornada, Mi Jornada).

Esta feature añade una **proyección visual de calendario** para administración: el mismo dato,
leído por rango de fechas y pintado sobre un calendario navegable (mes/semana/día), con el turno
previsto y los fichajes reales superpuestos y el veredicto de cumplimiento como color del día. Es
una capa de **solo lectura sobre los datos** (no crea tablas ni escribe en el ledger); las únicas
acciones de escritura disponibles desde el calendario reutilizan flujos ya existentes (asignar
horario, corregir un fichaje) a través de sus endpoints actuales.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Calendario mensual de cumplimiento por miembro (Priority: P1)

Como administrador del tenant, quiero seleccionar un miembro del equipo y ver un calendario mensual
donde cada día muestra su veredicto de cumplimiento (libre, ausencia, retraso, parcial, cumplido,
exceso) con un color distintivo, para detectar patrones de incumplimiento de un vistazo sin leer el
informe tabular día a día.

**Why this priority**: Es el valor central del módulo: convertir el informe de cumplimiento
existente en una lectura visual inmediata. Sin esto, el calendario no aporta nada sobre lo que ya
existe.

**Independent Test**: Con un miembro que en un mes tiene días de ausencia, retraso, exceso,
cumplidos y días libres, abrir el calendario en vista mensual y verificar que cada día muestra el
color/etiqueta de su veredicto correcto, coherente con el informe de cumplimiento existente para el
mismo rango.

**Acceptance Scenarios**:

1. **Given** un miembro con horario vigente y fichajes en el mes visible, **When** el administrador abre el calendario y selecciona ese miembro, **Then** cada día del mes muestra su veredicto de cumplimiento con el color correspondiente, coherente con el informe de cumplimiento del mismo rango.
2. **Given** un día libre del horario del miembro (sin tramos), **When** se ve el mes, **Then** ese día se distingue visualmente como libre y no aparece como incumplimiento.
3. **Given** un día con fichaje incompleto (entrada sin salida), **When** se ve el mes, **Then** ese día se marca como incidencia a revisar, distinguible del resto de veredictos.
4. **Given** que el administrador navega a un mes anterior o posterior, **When** cambia el rango visible, **Then** los datos del nuevo rango se cargan y pintan correctamente, incluyendo rangos que cruzan un cambio de horario del miembro (cada día contra su horario vigente entonces).
5. **Given** un miembro sin horario asignado, **When** se selecciona en el calendario, **Then** se muestra un estado claro (días sin previsión, fichajes visibles como trabajo fuera de horario) sin errores.
6. **Given** días futuros del mes visible, **When** se pinta el calendario, **Then** los días futuros muestran solo el turno previsto (sin veredicto de cumplimiento, que solo aplica a días pasados o al día en curso).

---

### User Story 2 - Vista semanal/diaria: previsto vs. real superpuestos (Priority: P1)

Como administrador del tenant, quiero cambiar a la vista de semana o día y ver, en franjas
horarias, los tramos previstos del horario vigente del miembro junto a los intervalos realmente
fichados (entrada→salida, descontando pausas), para comparar visualmente a qué hora debía estar
trabajando y a qué hora lo estuvo.

**Why this priority**: Es la otra mitad del valor visual: el mes da el "qué días", la semana/día da
el "qué horas". Comparte la misma carga de datos que US1 y completa la proyección previsto-vs-real.

**Independent Test**: Con un miembro con turno partido (09:00–13:00 y 15:00–19:00) que un día fichó
09:20–13:00 y 15:00–19:30, abrir la vista semanal y verificar que se ven los dos tramos previstos y
los dos intervalos reales superpuestos, visualmente distinguibles entre sí, en sus horas correctas.

**Acceptance Scenarios**:

1. **Given** un miembro con horario vigente, **When** el administrador abre la vista semanal, **Then** ve los tramos previstos de cada día de la semana como bloques horarios, según el horario vigente cada día.
2. **Given** fichajes reales en la semana visible, **When** se pinta la vista, **Then** los intervalos trabajados (entrada→salida, con las pausas excluidas) aparecen superpuestos y visualmente distinguibles de los tramos previstos.
3. **Given** un turno partido, **When** se ve el día, **Then** cada tramo previsto aparece como bloque independiente en su franja horaria.
4. **Given** un fichaje incompleto (entrada sin salida) en un día visible, **When** se pinta la vista, **Then** ese día señala la incidencia sin inventar una hora de fin del intervalo real.
5. **Given** fichajes en un día libre del horario, **When** se ve la semana, **Then** los intervalos reales aparecen aunque no haya tramos previstos ese día.

---

### User Story 3 - Filtro por miembro y vista general del equipo (Priority: P2)

Como administrador del tenant, quiero elegir qué miembro visualizo mediante un filtro, y disponer de
una vista general que resuma la situación del equipo por día (cuántos incumplimientos hubo cada
día), para pasar de la foto global al detalle individual sin cambiar de pantalla.

**Why this priority**: Multiplica la utilidad del calendario para tenants con varios miembros, pero
el valor central (US1/US2) ya funciona con la selección de un miembro.

**Independent Test**: Con dos miembros con incumplimientos en días distintos, verificar que la vista
general muestra el recuento por día de incumplimientos del equipo, y que al filtrar por cada
miembro el calendario muestra solo sus datos.

**Acceptance Scenarios**:

1. **Given** varios miembros activos, **When** el administrador cambia la selección del filtro, **Then** el calendario se recarga con los datos solo del miembro elegido.
2. **Given** la opción "todo el equipo" seleccionada, **When** se ve el mes, **Then** cada día muestra un resumen agregado (número de ausencias/retrasos/incidencias del equipo ese día), no el detalle de un solo miembro.
3. **Given** la vista general, **When** el administrador pulsa sobre el resumen de un día, **Then** puede ver qué miembros tuvieron incumplimiento ese día y saltar al calendario individual de uno de ellos.
4. **Given** miembros dados de baja, **When** se abre el filtro, **Then** solo se ofrecen los miembros activos (los datos históricos de un miembro dado de baja siguen siendo consultables si se accede a él).

---

### User Story 4 - Acciones desde el calendario (Priority: P3)

Como administrador del tenant, quiero poder actuar desde el propio calendario: abrir el detalle de
un día para ver sus fichajes y lanzar la corrección de un fichaje, o asignar/cambiar el horario del
miembro, reutilizando los flujos que ya existen, para no tener que saltar a otras pantallas al
detectar un problema.

**Why this priority**: Es comodidad incremental sobre la visualización; los flujos de corrección y
asignación ya existen en sus pantallas propias, así que el valor central no depende de esto.

**Independent Test**: Desde un día con un fichaje erróneo, abrir el detalle del día en el
calendario, lanzar la corrección con motivo, y verificar que el resultado es idéntico al del flujo
de corrección existente (evento nuevo enlazado, ledger intacto) y que el calendario refleja el dato
corregido al recargar.

**Acceptance Scenarios**:

1. **Given** un día con fichajes, **When** el administrador abre el detalle del día desde el calendario, **Then** ve los fichajes de ese día (tipo, hora, resultado de ubicación) y las marcas de cumplimiento.
2. **Given** el detalle de un fichaje, **When** el administrador lanza una corrección con motivo, **Then** la corrección se registra igual que en el flujo existente (evento nuevo enlazado, original intacto) y el calendario muestra el dato corregido tras recargar.
3. **Given** un miembro seleccionado, **When** el administrador abre la acción de asignar horario desde el calendario, **Then** puede asignar un horario con fecha de vigencia igual que en el flujo existente, y el calendario refleja el nuevo turno previsto en las fechas afectadas.
4. **Given** cualquier acción de escritura desde el calendario, **When** falla la validación (p. ej. solape de vigencias), **Then** el administrador recibe el mismo mensaje de error claro que en el flujo original y el calendario no queda en estado inconsistente.

---

### Edge Cases

- **Rango visible que cruza un cambio de horario**: cada día se pinta contra el horario vigente ese día (histórico de asignaciones), nunca contra el actual.
- **Días futuros**: solo turno previsto; no se calcula ni pinta veredicto de cumplimiento (no hay realidad que evaluar). El día en curso muestra los fichajes que ya existen sin veredicto cerrado.
- **Miembro sin horario y sin fichajes**: calendario vacío con estado claro, sin errores.
- **Tenant sin miembros**: el módulo muestra un estado vacío que orienta a crear miembros primero.
- **Fichaje incompleto**: se señala como incidencia; no se dibuja un intervalo real sin hora de fin conocida.
- **Fichajes corregidos**: el calendario pinta la realidad efectiva (con correcciones aplicadas), igual que el informe de jornada; nunca muestra duplicado el evento original y su corrección como dos hechos.
- **Meses con muchos datos / rangos amplios**: la carga es por el rango visible; navegar de mes no debe degradar la experiencia (ver Success Criteria).
- **Cambio de veredicto retroactivo** (p. ej. corrección de fichaje o cambio retroactivo de asignación): al recargar el rango, el calendario refleja el nuevo cálculo, porque el cumplimiento se calcula al vuelo (decisión heredada de 025).
- **Acceso de un usuario sin permiso de gestión de fichajes**: el módulo no es accesible ni por navegación ni por URL directa.

## Requirements *(mandatory)*

### Functional Requirements

#### Visualización (US1, US2)

- **FR-001**: El sistema MUST ofrecer un módulo de calendario, accesible desde el grupo de fichajes de la navegación, restringido a usuarios con permiso de gestión de fichajes.
- **FR-002**: La vista mensual MUST mostrar, para el miembro seleccionado, el veredicto de cumplimiento de cada día pasado (libre, ausencia, retraso, parcial, cumplido, exceso, incidencia) con un color/etiqueta distinguible por veredicto, coherente con el informe de cumplimiento existente.
- **FR-003**: Las vistas semanal y diaria MUST mostrar los tramos previstos del horario vigente de cada día como bloques horarios, y los intervalos realmente trabajados (derivados del ledger de fichajes con correcciones aplicadas y pausas excluidas) superpuestos y visualmente distinguibles.
- **FR-004**: Los datos del calendario MUST cargarse por el rango de fechas visible y recalcularse al navegar (mes/semana/día anterior o siguiente), usando para cada día el horario vigente ese día (histórico de asignaciones).
- **FR-005**: Los días futuros MUST mostrar solo la previsión (tramos del horario vigente), sin veredicto de cumplimiento; el día en curso muestra los fichajes existentes sin veredicto cerrado.
- **FR-006**: Un día con fichaje incompleto MUST señalarse como incidencia a revisar, sin inferir horas de fin.
- **FR-007**: Todos los cálculos (veredictos, horas, intervalos efectivos) MUST realizarse en el backend; el calendario solo pinta resultados ya calculados.

#### Filtro y vista de equipo (US3)

- **FR-008**: El sistema MUST permitir filtrar el calendario por miembro de equipo activo del tenant.
- **FR-009**: El sistema MUST ofrecer una vista general de equipo en el calendario mensual que resuma por día los incumplimientos agregados (ausencias, retrasos, incidencias) de los miembros activos.
- **FR-010**: Desde el resumen de un día en la vista general, el administrador MUST poder identificar qué miembros tuvieron incumplimiento y acceder al calendario individual de cualquiera de ellos.

#### Acciones (US4)

- **FR-011**: El sistema MUST permitir abrir desde el calendario el detalle de un día (fichajes del día con tipo, hora y resultado de ubicación, más las marcas de cumplimiento).
- **FR-012**: El sistema MUST permitir lanzar la corrección de un fichaje desde el calendario reutilizando el flujo de corrección existente (mismas validaciones, mismo resultado: evento nuevo enlazado con motivo, original inmutable), sin crear un flujo de corrección paralelo.
- **FR-013**: El sistema MUST permitir asignar/cambiar el horario del miembro seleccionado desde el calendario reutilizando el flujo de asignación existente (mismas validaciones de vigencia y solape), reflejando el cambio en el calendario tras recargar.
- **FR-014**: El calendario MUST NOT ofrecer ninguna forma de crear, mover o redimensionar fichajes ni tramos mediante interacción directa (arrastrar/soltar u otras): el ledger de fichajes es append-only y los tramos pertenecen a plantillas compartidas que no se editan desde aquí.

#### Restricciones transversales

- **FR-015**: Toda carga de datos del calendario MUST respetar el aislamiento multi-tenant (solo datos del tenant activo) y el permiso de gestión de fichajes, también ante acceso directo por URL.
- **FR-016**: La feature MUST NOT crear tablas nuevas ni escribir en el ledger de fichajes: es una proyección de lectura sobre los datos de las features 024/025, con las acciones de escritura delegadas en los endpoints existentes.
- **FR-017**: La feature MUST mantenerse en el alcance v1: sin drag & drop de escritura, sin edición de plantillas de horario desde el calendario, sin excepciones/turnos puntuales por día y sin festivos.

### Key Entities *(include if feature involves data)*

- **Fichaje** (existente, 024): fuente de los intervalos reales; se lee con correcciones aplicadas, nunca se escribe desde el calendario.
- **Horario / Tramo de horario** (existentes, 025): fuente de los bloques previstos por día de la semana.
- **Asignación de horario** (existente, 025): determina el horario vigente de cada miembro en cada fecha del rango visible.
- **Miembro de equipo** (existente, 024): sujeto del filtro; solo los activos se ofrecen para seleccionar.
- **Resultado de cumplimiento** (derivado, 025): veredicto por día calculado al vuelo; el calendario lo consume, no lo persiste.
- **Evento de calendario (derivado)**: representación por rango visible de un tramo previsto, un intervalo real, un veredicto de día o un resumen de equipo. No persistido.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Para cualquier mes con datos, el veredicto pintado en cada día coincide al 100% con el informe de cumplimiento existente para el mismo miembro y rango (verificable con pruebas automatizadas).
- **SC-002**: En vista semanal, los bloques previstos y los intervalos reales de un turno partido se muestran en sus horas correctas y son distinguibles entre sí en el 100% de los casos de prueba definidos.
- **SC-003**: Un administrador puede pasar de la vista general de equipo al detalle de un miembro concreto en 2 interacciones o menos.
- **SC-004**: Una corrección de fichaje lanzada desde el calendario produce exactamente el mismo resultado en los datos que el flujo de corrección existente (mismo evento enlazado, ledger intacto).
- **SC-005**: Navegar entre meses/semanas carga y pinta el nuevo rango en menos de 2 segundos con volúmenes de datos típicos de una pyme (≤50 miembros, ≤4 años de fichajes).
- **SC-006**: Un usuario sin permiso de gestión de fichajes no puede acceder al módulo ni a sus datos por ninguna vía (navegación o URL directa), verificado con pruebas automatizadas de autorización y aislamiento de tenant.
- **SC-007**: Miembro sin horario, día libre, tenant sin miembros y días futuros muestran estados claros sin errores en todas las vistas.

## Assumptions

- **Solo administración en v1**: el calendario vive bajo el permiso de gestión de fichajes; el portal "Mi jornada" del trabajador ya muestra su turno y no se toca en esta feature.
- **Proyección pura**: no hay tablas nuevas ni persistencia de resultados; todo se calcula al vuelo por rango visible, reutilizando el servicio de cumplimiento y el histórico de asignaciones de 025 (coherente con la decisión FR-019a de esa feature).
- **Vista general agregada, detalle individual**: el veredicto por día solo tiene sentido por miembro; con "todo el equipo" seleccionado, la vista mensual muestra recuentos agregados de incumplimientos, no veredictos individuales superpuestos.
- **Veredicto solo para días pasados**: el cumplimiento de un día se considera evaluable cuando el día terminó; el día en curso muestra la realidad parcial sin veredicto cerrado.
- **Realidad efectiva**: los intervalos reales se derivan del ledger con las correcciones aplicadas, con la misma semántica que el informe de jornada existente (emparejar entrada/salida, excluir pausas, incidencia si incompleto).
- **Componente de calendario del banco del template**: la pieza visual proviene del banco de componentes ya adquirido con el template, vendorizada según el patrón del proyecto; no se introduce ninguna dependencia de front nueva de terceros fuera de ese banco.
- **Escala pyme**: los volúmenes esperados (decenas de miembros, meses de ~31 días) permiten el cálculo al vuelo por rango sin precálculo ni cachés adicionales.
- **Fuera de alcance v1**: drag & drop de escritura, edición de plantillas de horario desde el calendario, excepciones/turnos puntuales por día, festivos/calendario laboral, exportación del calendario, y vista de calendario para el propio trabajador.
