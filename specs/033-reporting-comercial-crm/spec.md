# Feature Specification: Reporting Comercial CRM — Indicadores, Informes y Comparativa entre Ejercicios

**Feature Branch**: `033-reporting-comercial-crm`

**Created**: 2026-07-19

**Status**: Draft

**Input**: User description: "Reporting comercial CRM para Kit Digital (requisito 6 del modelo de evidencias de la categoría Gestión de Clientes). El dashboard actual es 100% de facturación y no incluye leads ni oportunidades; el único agregado de pipeline vive aparte en `OportunidadController::resumenPorEtapa()` y no admite filtro de fechas. Se necesita un reporting comercial unificado que cruce leads, oportunidades y presupuestos —además de la facturación ya existente— con filtros por comercial asignado, origen/canal del lead y etapa; con agregación adaptada al perfil del usuario; y con comparativa entre ejercicios."

## Objetivo y contexto

La feature 028 construyó el embudo comercial (**Lead → Oportunidad → Presupuesto → Factura**) y la
015/023 construyeron el dashboard financiero. Lo que **no existe** es la capa que mide ese embudo:
hoy un responsable comercial no puede responder preguntas como *"¿qué porcentaje de los leads que
entraron este trimestre acabó siendo cliente?"*, *"¿qué comercial cierra mejor?"*, *"¿de qué canal
vienen los leads que más facturan?"* o *"¿vamos mejor o peor que el mismo periodo del año pasado?"*.

Esto es un **gap de homologación**: el requisito 6 del modelo de evidencias oficial de Red.es
(*"Reporting, planificación y seguimiento"*, categoría Gestión de Clientes) exige explícitamente
indicadores con distintos niveles de agregación según el perfil del usuario, informes de actividad
comercial con **ratios de eficiencia, estado de fases y pipeline**, segmentación por **canales,
perfiles y fases**, y datos **mensuales, acumulados y/o comparativos entre ejercicios comerciales**.
`docs/06-kit-digital.md` lo lista hoy como parcialmente cubierto; esta feature lo cierra.

Dos precisiones de alcance que nacen del análisis del estado actual:

1. **El dashboard financiero no se toca ni se reemplaza.** Mide una cosa distinta (facturado,
   cobrado, IVA, gastos) con una audiencia distinta (administración/gerencia). Esta feature añade
   una sección de **informes comerciales** que se apoya en los mismos cimientos de rango de fechas
   ya existentes, sin reescribir lo que funciona.
2. **La segmentación "por canal" hoy sería vacía.** El origen de un lead solo distingue *alta
   manual* de *importación*, que describe **cómo se cargó el dato**, no **de dónde vino el
   cliente**. Segmentar informes por eso no aporta valor comercial ni satisface el requisito. Por
   eso la feature incluye enriquecer el catálogo de canales de captación.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Panel de indicadores comerciales con ratios de eficiencia (Priority: P1)

Un responsable comercial abre la sección de informes comerciales, elige un periodo (mes, trimestre,
año o rango personalizado) y ve de un vistazo el estado de su actividad comercial: cuántos leads
entraron, cuántos se cualificaron, cuántos se convirtieron en cliente, cuántas oportunidades están
abiertas y por cuánto importe, cuántas se ganaron y perdieron, y cuántos presupuestos se enviaron y
aceptaron. Junto a los recuentos ve los **ratios de eficiencia** derivados: tasa de conversión de
lead a cliente, tasa de oportunidades ganadas, tasa de aceptación de presupuestos, importe medio de
operación y duración media del ciclo comercial.

**Why this priority**: Es el núcleo del requisito ("seguimiento mediante indicadores" + "ratios de
eficiencia, estado de fases, pipeline"). Sin esto no hay evidencia que aportar. Entrega valor por sí
solo: convierte datos que ya se capturan en información accionable, sin depender de las otras
historias.

**Independent Test**: Se puede probar de forma aislada creando en un tenant un conjunto conocido de
leads, oportunidades y presupuestos con fechas dentro y fuera del periodo elegido, y verificando que
cada indicador y cada ratio coincide con el valor calculado a mano, que los registros fuera del
periodo no se cuentan, y que los datos de otro tenant nunca aparecen.

**Acceptance Scenarios**:

1. **Given** un tenant con 10 leads creados dentro del periodo seleccionado, de los cuales 3 están
   en estado convertido, **When** el usuario abre el panel de indicadores comerciales para ese
   periodo, **Then** ve 10 leads captados y una tasa de conversión de lead a cliente del 30%.
2. **Given** un tenant con 4 oportunidades ganadas y 6 perdidas cerradas dentro del periodo,
   **When** el usuario consulta los ratios, **Then** ve una tasa de oportunidades ganadas del 40%,
   calculada solo sobre oportunidades cerradas (las abiertas no entran en el denominador).
3. **Given** un tenant sin ninguna actividad comercial en el periodo seleccionado, **When** el
   usuario abre el panel, **Then** ve todos los indicadores a cero y los ratios como "sin datos" en
   lugar de un error o una división por cero.
4. **Given** dos tenants distintos, cada uno con su propia actividad comercial, **When** un usuario
   de uno de ellos consulta el panel, **Then** ningún indicador incluye registros del otro tenant.
5. **Given** un periodo seleccionado, **When** el usuario cambia el periodo a otro rango, **Then**
   todos los indicadores y ratios se recalculan para el nuevo rango sin recargar la página entera.

---

### User Story 2 - Segmentación por canal, comercial y fase (Priority: P2)

El responsable comercial necesita saber **de dónde** viene el negocio y **quién** lo cierra. Filtra
el informe por canal de captación (por ejemplo: web, feria, recomendación, campaña de email,
llamada entrante) y por comercial asignado, y ve cómo cambian los indicadores y ratios para ese
segmento. Además ve el desglose por fases: cuántos leads hay en cada estado, cuántas oportunidades
en cada etapa con su importe, y cuántos presupuestos en cada estado con su importe.

Para que el filtro por canal tenga sentido, al dar de alta o importar un lead se puede indicar de
qué canal de captación procede, eligiendo de un catálogo que el tenant administra.

**Why this priority**: El requisito exige explícitamente segmentar "según los canales, perfiles,
roles y/o fases comerciales". Es lo que convierte un panel de cifras globales en una herramienta de
decisión ("la feria no rentó, las recomendaciones cierran al 60%"). Va después de P1 porque los
indicadores deben existir antes de poder segmentarlos.

**Independent Test**: Se puede probar de forma aislada asignando leads a distintos canales y
comerciales, aplicando cada filtro por separado y en combinación, y verificando que los totales de
los segmentos suman el total sin filtrar y que el desglose por fases refleja los estados reales.

**Acceptance Scenarios**:

1. **Given** un tenant con leads repartidos entre tres canales de captación, **When** el usuario
   filtra por uno de esos canales, **Then** todos los indicadores se recalculan usando solo los
   leads de ese canal, y la suma de los tres segmentos coincide con el total sin filtrar.
2. **Given** un tenant con dos comerciales con oportunidades asignadas, **When** el usuario filtra
   por uno de ellos, **Then** ve solo el pipeline y los ratios de ese comercial.
3. **Given** un filtro de canal y un filtro de comercial aplicados a la vez, **When** el usuario
   consulta el informe, **Then** los indicadores reflejan la intersección de ambos criterios.
4. **Given** un lead dado de alta sin especificar canal, **When** el usuario consulta el desglose
   por canal, **Then** ese lead aparece agrupado bajo un canal "sin especificar" y no se pierde del
   total.
5. **Given** el desglose por fases, **When** el usuario lo consulta, **Then** ve para cada estado de
   lead, cada etapa de oportunidad y cada estado de presupuesto su recuento y, donde aplique, su
   importe agregado.
6. **Given** un canal de captación que ya tiene leads asociados, **When** un administrador intenta
   eliminarlo del catálogo, **Then** el sistema lo impide o lo desactiva conservando el histórico,
   sin dejar leads huérfanos de canal.

---

### User Story 3 - Comparativa entre ejercicios comerciales (Priority: P2)

El responsable compara el periodo seleccionado contra **el mismo periodo del ejercicio anterior**
(por ejemplo, el primer trimestre de este año contra el primer trimestre del año pasado) y ve, para
cada indicador y ratio, el valor actual, el valor del ejercicio comparado y la variación. Así
distingue crecimiento real de estacionalidad.

**Why this priority**: El requisito lo pide de forma literal ("comparativos entre diferentes
ejercicios comerciales"). Es distinto de lo que hace hoy el dashboard financiero, que compara
contra el periodo *inmediatamente anterior* de igual duración —útil para tendencia a corto plazo,
inútil para detectar estacionalidad. Va en P2 porque necesita que los indicadores de P1 existan.

**Independent Test**: Se puede probar de forma aislada creando actividad comercial conocida en dos
ejercicios distintos, activando la comparativa y verificando que cada indicador muestra ambos
valores y una variación correcta, incluyendo el caso de un ejercicio comparado sin datos.

**Acceptance Scenarios**:

1. **Given** un tenant con actividad comercial en el año en curso y en el anterior, **When** el
   usuario activa la comparativa entre ejercicios sobre un periodo, **Then** ve para cada indicador
   el valor del periodo actual, el del mismo periodo del ejercicio anterior y la variación entre
   ambos.
2. **Given** un ejercicio comparado sin ninguna actividad, **When** el usuario consulta la
   comparativa, **Then** ve el valor comparado a cero y la variación indicada como no calculable,
   en vez de un error o un porcentaje infinito.
3. **Given** un periodo seleccionado que incluye un 29 de febrero, **When** se compara contra un
   ejercicio no bisiesto, **Then** el sistema resuelve el rango equivalente sin fallar ni
   desplazar el periodo comparado.
4. **Given** una evolución temporal mostrada por meses, **When** el usuario activa la comparativa,
   **Then** ve la serie del ejercicio actual y la del comparado alineadas por posición dentro del
   periodo, no por fecha absoluta.

---

### User Story 4 - Alcance de datos según el perfil del usuario (Priority: P2)

Lo que un usuario ve en los informes depende de su perfil. Un usuario con permisos de gestión
comercial ve la actividad de **todo el tenant** y puede filtrar por cualquier comercial. Un usuario
sin esos permisos ve únicamente **su propia actividad** —los leads y oportunidades que tiene
asignados— y no puede consultar la de sus compañeros. Además, cada usuario solo ve los bloques del
informe correspondientes a los módulos a los que tiene acceso: quien no tiene acceso a
presupuestos no ve el bloque de presupuestos ni sus ratios.

**Why this priority**: El requisito exige "diferentes niveles de agregación de información en
función del perfil del usuario de la solución", y es además una cuestión de confidencialidad
interna: el rendimiento individual de un comercial no debería ser visible para sus pares. Se apoya
en el sistema de roles y permisos ya existente (feature 027).

**Independent Test**: Se puede probar de forma aislada creando dos usuarios con perfiles distintos
en el mismo tenant, con actividad asignada a cada uno, y verificando que el usuario sin permisos de
gestión ve solo sus cifras y que intentar forzar el filtro por otro comercial no le devuelve datos
ajenos.

**Acceptance Scenarios**:

1. **Given** un usuario con permisos de gestión comercial, **When** abre los informes, **Then** ve
   la actividad de todo el tenant y dispone del filtro por comercial con todos los usuarios.
2. **Given** un usuario sin permisos de gestión comercial, **When** abre los informes, **Then** ve
   únicamente los indicadores calculados sobre los leads y oportunidades que tiene asignados.
3. **Given** un usuario sin permisos de gestión comercial, **When** intenta consultar el informe
   filtrado por otro comercial manipulando la petición, **Then** el sistema no devuelve datos
   ajenos, sino que ignora el filtro o rechaza la petición.
4. **Given** un usuario sin acceso al módulo de presupuestos, **When** abre los informes, **Then**
   no ve el bloque de presupuestos ni los ratios derivados de presupuestos.
5. **Given** un usuario sin acceso a ninguno de los módulos comerciales, **When** intenta abrir los
   informes, **Then** no puede acceder a la sección.

---

### User Story 5 - Exportación del informe (Priority: P3)

El usuario descarga el informe que está viendo —con los filtros y el periodo aplicados— como un
fichero, para adjuntarlo a una justificación, presentarlo en una reunión o archivarlo. El fichero
refleja exactamente el mismo alcance de datos que el usuario tiene permitido ver en pantalla.

**Why this priority**: El modelo de evidencias de Red.es exige "generación de informes", y la
solución de referencia del propio documento muestra un diálogo de descarga. Es también lo que hace
el informe utilizable fuera de la aplicación. Va en P3 porque el valor principal está en pantalla y
la exportación se apoya en el mecanismo de exportación ya existente.

**Independent Test**: Se puede probar de forma aislada aplicando un filtro concreto, exportando y
verificando que el fichero contiene exactamente los mismos indicadores y segmentos que la pantalla,
y que un usuario con alcance restringido no obtiene en el fichero datos que no ve en pantalla.

**Acceptance Scenarios**:

1. **Given** un informe con un periodo y filtros aplicados, **When** el usuario lo exporta,
   **Then** obtiene un fichero cuyos indicadores coinciden con los de la pantalla y que deja
   constancia del periodo y los filtros usados.
2. **Given** un usuario con alcance restringido a su propia actividad, **When** exporta el informe,
   **Then** el fichero contiene únicamente sus datos.
3. **Given** un informe sin datos en el periodo, **When** el usuario lo exporta, **Then** obtiene un
   fichero válido con los indicadores a cero, no un fichero corrupto ni un error.

---

### Edge Cases

- **División por cero en ratios**: un periodo con 0 leads captados no puede producir una tasa de
  conversión; debe mostrarse como "sin datos", nunca como 0% (que significaría algo distinto) ni
  como error.
- **Oportunidades abiertas en el cálculo de la tasa de ganadas**: una oportunidad todavía abierta no
  es ni un éxito ni un fracaso; el denominador solo incluye oportunidades cerradas.
- **Registros que cruzan el límite del periodo**: un lead captado en enero y convertido en marzo
  debe contarse de forma coherente; el criterio de fecha usado por cada indicador (fecha de
  captación vs. fecha de conversión vs. fecha de cierre) debe ser explícito y consistente entre la
  pantalla y la exportación.
- **Registros eliminados**: leads y oportunidades usan borrado lógico; un registro eliminado no debe
  seguir contando en los indicadores del periodo.
- **Comercial dado de baja**: si un usuario con leads asignados deja de estar activo, su actividad
  histórica debe seguir siendo visible en informes de periodos pasados sin romper el filtro.
- **Lead convertido a cliente y luego facturado**: el mismo negocio no debe contarse dos veces al
  cruzar el embudo comercial con la facturación. Se resuelve por definición en FR-031: el indicador
  de negocio cerrado solo cuenta facturas originadas en un presupuesto, nunca las de alta directa.
- **Ejercicio comparado anterior a la puesta en marcha del CRM**: comparar contra un año en el que
  no existían leads en el sistema debe presentarse como ausencia de datos, no como una caída del
  100%.
- **Periodo personalizado muy largo**: un rango de varios años debe seguir presentando la evolución
  de forma legible, agregando por unidades mayores en lugar de generar cientos de puntos.
- **Cambio de permisos en caliente**: si a un usuario se le retira el acceso a un módulo mientras
  tiene el informe abierto, la siguiente consulta debe respetar el nuevo alcance.

## Requirements *(mandatory)*

### Functional Requirements

> **Numeración append-only**: los identificadores FR son estables y no se reordenan. FR-030 y FR-031
> se añadieron tras el análisis de consistencia y viven en el bloque de indicadores pese a llevar un
> número alto, para no invalidar las referencias ya existentes en el plan y las tareas.

#### Indicadores y ratios

- **FR-001**: El sistema DEBE ofrecer una sección de informes comerciales, separada del dashboard
  financiero existente, que no altere ni sustituya los indicadores de facturación ya disponibles.
- **FR-002**: El sistema DEBE permitir seleccionar el periodo del informe mediante los mismos
  presets ya disponibles en la aplicación (mes, trimestre, año en curso y rango personalizado).
- **FR-003**: El sistema DEBE presentar, para el periodo seleccionado, indicadores de volumen de
  actividad comercial: leads captados, leads convertidos, oportunidades creadas, oportunidades
  ganadas, oportunidades perdidas, oportunidades abiertas con su importe agregado, presupuestos
  emitidos y presupuestos aceptados con su importe agregado.
- **FR-004**: El sistema DEBE calcular y presentar ratios de eficiencia derivados de esos
  indicadores, incluyendo como mínimo: tasa de conversión de lead a cliente, tasa de oportunidades
  ganadas sobre oportunidades cerradas, tasa de aceptación de presupuestos, tasa de conversión de
  presupuesto a factura, importe medio de oportunidad ganada y **dos duraciones medias de ciclo
  comercial**: la de lead a cliente y la de apertura a cierre de oportunidad.
- **FR-005**: El sistema DEBE presentar cada ratio como "sin datos" cuando su denominador sea cero,
  sin producir errores ni valores engañosos.
- **FR-006**: El sistema DEBE documentar de forma visible, para cada indicador, qué fecha se usa
  para adscribirlo a un periodo, de modo que las cifras sean auditables.
- **FR-007**: El sistema DEBE presentar el estado de fases del embudo: recuento por estado de lead,
  recuento e importe por etapa de oportunidad, y recuento e importe por estado de presupuesto.
- **FR-008**: El sistema DEBE presentar una evolución temporal de la actividad comercial dentro del
  periodo, agregada por una unidad de tiempo proporcional a la longitud del rango.
- **FR-009**: El sistema DEBE presentar los artículos o servicios más presupuestados del periodo por
  importe, como atributo medible adicional de la actividad comercial.
- **FR-010**: Todos los recuentos, importes y ratios DEBEN calcularse en el servidor; el cliente
  nunca es fuente de verdad para un valor agregado (Principio III).
- **FR-030**: El sistema DEBE presentar el negocio cerrado **atribuible al embudo comercial**:
  número e importe de las facturas originadas en un presupuesto del periodo. Este indicador cierra
  el ciclo lead → oportunidad → presupuesto → factura y es la base de la tasa de conversión de
  presupuesto a factura exigida por FR-004.
- **FR-031**: El sistema NO DEBE incluir en ese indicador las facturas ajenas al embudo comercial
  (altas directas, ventas de punto de venta), para no duplicar lo que ya mide el dashboard
  financiero ni inflar la conversión del embudo.

#### Segmentación

- **FR-011**: El sistema DEBE permitir registrar el canal de captación de un lead, tanto en el alta
  manual como en la importación por fichero, eligiendo de un catálogo gestionado por el tenant.
- **FR-012**: El sistema DEBE permitir al tenant administrar su catálogo de canales de captación
  (alta, edición y desactivación), partiendo de un conjunto inicial razonable.
- **FR-013**: El sistema DEBE conservar el histórico al desactivar un canal: los leads ya asociados
  mantienen su canal y siguen apareciendo en informes de periodos pasados.
- **FR-014**: El sistema DEBE agrupar bajo un canal "sin especificar" los leads sin canal asignado,
  incluidos los existentes antes de esta feature, de modo que ningún lead quede fuera de los
  totales.
- **FR-015**: El sistema DEBE permitir filtrar el informe por canal de captación, por comercial
  asignado y por fase del embudo, de forma independiente y combinada.
- **FR-016**: El sistema DEBE garantizar que la suma de los segmentos de un filtro coincide con el
  total sin filtrar, para el mismo periodo.
- **FR-017**: El sistema DEBE mantener los filtros aplicados al cambiar el periodo, y viceversa.

#### Comparativa entre ejercicios

- **FR-018**: El sistema DEBE permitir comparar el periodo seleccionado contra el mismo periodo de
  un ejercicio anterior, presentando valor actual, valor comparado y variación para cada indicador
  y ratio.
- **FR-019**: El sistema DEBE resolver el periodo equivalente del ejercicio comparado de forma
  robusta ante años bisiestos y meses de distinta longitud, sin desplazar ni truncar el rango.
- **FR-020**: El sistema DEBE presentar la variación como no calculable cuando el ejercicio
  comparado no tenga datos, en lugar de mostrar un porcentaje engañoso.
- **FR-021**: El sistema DEBE alinear las series temporales del ejercicio actual y del comparado por
  posición dentro del periodo, para que sean visualmente comparables.

#### Alcance según perfil

- **FR-022**: El sistema DEBE limitar los bloques del informe visibles a los módulos comerciales a
  los que el usuario tiene acceso según sus permisos.
- **FR-023**: El sistema DEBE restringir el alcance de datos de un usuario sin permisos de gestión
  comercial a la actividad que tiene asignada, y permitir a un usuario con dichos permisos consultar
  la actividad de todo el tenant.
- **FR-024**: El sistema DEBE aplicar la restricción de alcance en el servidor, de forma que
  manipular los parámetros de la petición no permita acceder a datos de otros usuarios.
- **FR-025**: El sistema DEBE impedir el acceso a la sección de informes a usuarios sin acceso a
  ningún módulo comercial.
- **FR-026**: El sistema DEBE aplicar el aislamiento multi-tenant a todos los datos del informe, sin
  excepción (Principio I).

#### Exportación

- **FR-027**: El sistema DEBE permitir exportar el informe a un fichero descargable, reflejando el
  periodo y los filtros aplicados en el momento de la exportación.
- **FR-028**: El fichero exportado DEBE respetar exactamente el mismo alcance de datos que el
  usuario tiene permitido ver en pantalla.
- **FR-029**: El sistema DEBE generar un fichero válido aunque el periodo no tenga actividad.

### Key Entities

- **Canal de captación**: catálogo por tenant que describe **de dónde procede comercialmente** un
  lead (web, feria, recomendación, campaña, llamada entrante, etc.). Se distingue del origen técnico
  ya existente, que solo registra **cómo se cargó el dato** (alta manual o importación) y que se
  mantiene sin cambios. Un lead referencia como mucho un canal; un canal puede desactivarse pero no
  perder su histórico.
- **Informe comercial**: resultado calculado —no persistido— de aplicar un periodo, un conjunto de
  filtros y un alcance de usuario sobre la actividad comercial existente. Agrupa indicadores de
  volumen, ratios de eficiencia, desglose por fases, evolución temporal y, opcionalmente, la
  comparativa con un ejercicio anterior.
- **Lead / Oportunidad / Presupuesto / Factura**: entidades ya existentes (features 028 y
  anteriores). Esta feature las consume como fuente de datos y añade **dos datos a Lead**: la
  referencia a su canal de captación y la **fecha en que se convirtió en cliente** —hoy solo se
  guarda el cliente resultante, no cuándo ocurrió, y sin esa fecha la duración media del ciclo
  comercial de FR-004 no es calculable. Ninguna de las dos altera el ciclo de vida ni las reglas de
  negocio de las entidades; la fecha de conversión la escribe el propio proceso de conversión y no
  es editable por el usuario.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un responsable comercial puede responder "cuántos leads entraron, cuántos se
  convirtieron y a qué ritmo" para cualquier periodo en menos de 30 segundos desde que abre la
  aplicación, sin exportar datos ni hacer cálculos manuales.
- **SC-002**: El informe cubre los cuatro elementos exigidos por el requisito 6 del modelo de
  evidencias —ratios de eficiencia, estado de fases, pipeline y atributos medibles adicionales— y
  cada uno es demostrable con una captura de pantalla.
- **SC-003**: El informe permite segmentar por canal, por comercial y por fase, y la comparativa
  entre ejercicios está disponible para todos los indicadores, satisfaciendo la exigencia de datos
  "mensuales, acumulados y/o comparativos entre diferentes ejercicios comerciales".
- **SC-004**: Dos usuarios con perfiles distintos del mismo tenant obtienen alcances de datos
  distintos y verificables sobre el mismo periodo, evidenciando los "diferentes niveles de
  agregación en función del perfil del usuario".
- **SC-005**: Ningún dato de un tenant aparece jamás en el informe de otro, verificado con pruebas
  automatizadas sobre al menos dos tenants con actividad comercial.
- **SC-006**: Todos los indicadores y ratios coinciden con el cálculo manual sobre un conjunto de
  datos conocido, verificado con pruebas automatizadas, incluidos los casos límite de denominador
  cero.
- **SC-007**: El informe se presenta completo en menos de 3 segundos para un tenant con al menos
  5.000 leads, 1.000 oportunidades y 1.000 presupuestos en el periodo consultado.
- **SC-008**: `docs/06-kit-digital.md` deja de listar el reporting como gap parcial de la categoría
  Gestión de Clientes y pasa a estar cubierto.

## Assumptions

- **Se añade una sección nueva, no se reescribe el dashboard.** El dashboard financiero (features
  015/023) mide facturación para administración/gerencia y sigue intacto; los informes comerciales
  son una sección aparte con su propia audiencia. Se reutiliza el mecanismo de rango de fechas ya
  existente en lugar de inventar otro.
- **La comparativa entre ejercicios se ofrece contra el ejercicio inmediatamente anterior.**
  Comparar contra varios ejercicios simultáneamente queda fuera de alcance de esta versión; la
  comparación contra el periodo inmediatamente anterior que ya hace el dashboard financiero se
  mantiene allí y no se traslada aquí.
- **El catálogo de canales de captación es administrado por el tenant**, con un conjunto inicial
  sembrado por defecto, siguiendo el patrón de otros catálogos configurables ya existentes. Los
  leads anteriores a esta feature quedan sin canal y se agrupan como "sin especificar" en lugar de
  asignarles uno arbitrariamente.
- **El campo de origen técnico del lead (manual/importación) no se modifica ni se reutiliza como
  canal.** Son dimensiones distintas y ambas pueden convivir.
- **La distinción de perfiles se apoya en el sistema de roles y permisos existente (feature 027)**,
  sin introducir un modelo de jerarquía comercial nuevo (equipos, territorios, responsables de
  equipo), que queda fuera de alcance.
- **La exportación reutiliza la infraestructura de ficheros Excel ya existente (feature 031)**
  —librería, formatos de celda, patrón de descarga y registro en el log de actividad— pero **no su
  contrato de exportación por filas**: ese contrato describe "N filas de un modelo seleccionadas por
  IDs" y un informe es un agregado multi-bloque sin modelo de respaldo. Forzarlo ahí lo
  desvirtuaría, así que la generación del fichero es propia de esta feature.
- **El informe se calcula bajo demanda**, sin materializar tablas de agregados ni procesos
  programados de consolidación, mientras el volumen de datos objetivo lo permita (SC-007).
- **No se introducen datos personales nuevos.** La feature agrega y presenta datos ya existentes; el
  canal de captación es un dato comercial del lead, no un dato personal adicional, por lo que se
  acoge a la retención ya definida para leads y no requiere un mecanismo de purga propio.
- **La planificación en sentido de fijar objetivos o cuotas comerciales queda fuera de alcance.** El
  requisito habla de "reporting, planificación y seguimiento", pero la evidencia exigida se refiere
  a indicadores e informes; fijar objetivos y medir su cumplimiento es una mejora futura.

## Dependencies

- Features **028** (leads, oportunidades, presupuestos), **027** (roles y permisos), **023** (rango
  de fechas del dashboard) y **031** (importación/exportación Excel) deben estar operativas.
- El cierre de esta feature obliga a actualizar `docs/06-kit-digital.md` (estado del requisito 6),
  `docs/03-modelo-datos.md` (catálogo de canales de captación y su relación con leads),
  `resources/views/ayuda/` (guía in-app de la nueva sección y del campo canal en el alta de leads) y
  `resources/ia/conocimiento/` (base de conocimiento del asistente).
