# Research — Reporting Comercial CRM (033)

Decisiones técnicas previas al diseño. Resuelven las incógnitas de la spec y las tres observaciones
abiertas del checklist de calidad.

---

## D1 — Criterio de fecha de cada indicador (resuelve FR-006)

**Decisión**: cada indicador declara explícitamente su fecha de adscripción, y se usan **dos
criterios distintos según la naturaleza del dato**:

| Indicador | Criterio | Fecha usada |
|---|---|---|
| Leads captados | Cohorte | `leads.created_at` |
| Leads convertidos | Cohorte | `leads.created_at` (numerador = subconjunto con `convertido_at` no nulo) |
| Oportunidades creadas | Cohorte | `oportunidades.created_at` |
| Oportunidades ganadas / perdidas | Evento | `oportunidades.cerrada_at` |
| Oportunidades abiertas + importe | Instantánea | creadas ≤ `hasta` y sin cerrar a fecha `hasta` |
| Presupuestos emitidos | Cohorte | `presupuestos.fecha_emision` |
| Presupuestos aceptados | Cohorte | `presupuestos.fecha_emision` (numerador = estado `aceptado`/`facturado`) |
| Artículos más presupuestados | Cohorte | `presupuestos.fecha_emision` de su presupuesto |

**Rationale**: mezclar criterios sin control produce ratios sin sentido (numerador y denominador de
poblaciones distintas). La regla es: **un ratio se calcula siempre sobre una única población**.

- Las **tasas de conversión** (lead→cliente, aceptación de presupuestos) usan **cohorte**: de los
  leads que entraron en el periodo, cuántos acabaron convirtiendo. El numerador es un subconjunto
  estricto del denominador, así que el ratio nunca puede pasar del 100%.
- La **tasa de oportunidades ganadas** usa **evento** (`cerrada_at`): mide "de lo que se cerró en
  este periodo, cuánto se ganó". Numerador y denominador comparten criterio, así que sigue siendo
  consistente. Usar cohorte aquí sesgaría a la baja los periodos recientes (las oportunidades
  abiertas todavía no han podido ganarse).
- El **pipeline abierto** es una **instantánea** a fecha de corte, no un flujo; medirlo "a día de
  hoy" impediría compararlo entre ejercicios, que es justo lo que exige FR-018.

**Consecuencia sobre la cohorte**: un lead captado en el último día del periodo aparecerá casi
siempre como no convertido. Es correcto y es cómo funciona el análisis de cohortes; la UI debe
etiquetar cada indicador con su criterio (FR-006) para que el usuario lo interprete bien.

**Alternativas descartadas**: (a) todo por `created_at` — rompe la tasa de ganadas; (b) todo por
fecha de evento — impide medir conversión de lead, que no tiene "evento de captación" distinto de
su alta; (c) dejar el criterio configurable por el usuario — complejidad sin demanda (Principio V).

---

## D2 — Falta el dato de fecha de conversión de un lead

**Decisión**: añadir `leads.convertido_at` (datetime, nullable), poblada por
`App\Services\ConversorLeadCliente` en el momento de convertir. Los leads históricos quedan a
`NULL`.

**Rationale**: hoy `leads` solo guarda `convertido_a_cliente_id`; no hay forma fiable de saber
*cuándo* se convirtió (`updated_at` cambia con cualquier edición posterior). Sin esa fecha no se
puede calcular la duración media del ciclo comercial lead→cliente que exige FR-004, ni auditar la
cifra de conversiones.

**Impacto en indicadores**: los leads convertidos **antes** de esta feature cuentan como convertidos
(vía `convertido_a_cliente_id`) pero **no aportan** al cálculo de duración media del ciclo (su
`convertido_at` es `NULL`). El promedio se calcula solo sobre los que tienen fecha, y la UI indica
la muestra usada. Es preferible a inventar una fecha retroactiva a partir de `updated_at`.

**Alternativas descartadas**: (a) derivar la fecha de `clientes.created_at` del cliente vinculado —
frágil, el cliente pudo existir antes; (b) medir el ciclo solo sobre oportunidades y renunciar al
ciclo de lead — pierde la métrica más representativa de un CRM por ahorrar una columna nullable.

---

## D3 — Canal de captación: catálogo por tenant, no enum

**Decisión**: nueva tabla `canales_captacion` (por tenant, con `activo`) + FK nullable
`leads.canal_captacion_id`. El enum `OrigenLead` existente **no se toca**.

**Rationale**: FR-012 exige que el tenant administre su propio catálogo (alta/edición/desactivación)
— un enum PHP es código, no dato, y no se puede administrar desde la aplicación. Además cada negocio
tiene canales distintos (una asesoría no capta por los mismos medios que un taller).

`OrigenLead` (`manual`/`importacion`) responde a *cómo entró el dato al sistema* y sigue siendo útil
para auditoría; `canal_captacion` responde a *de dónde vino el cliente*. Son dimensiones ortogonales
y ambas se conservan (decisión ya fijada en la spec, sección "Objetivo y contexto").

**Desactivación en lugar de borrado** (FR-013): un canal con leads asociados no se borra; se marca
`activo = false`, desaparece de los selectores de alta pero sigue apareciendo en informes
históricos. Mismo patrón que `cuentas_bancarias` (soft delete + restore) pero con un booleano, que
es suficiente aquí.

**Siembra inicial**: conjunto por defecto (Web, Recomendación, Feria/Evento, Campaña de email,
Llamada entrante, Redes sociales, Otro) creado (a) al provisionar un tenant nuevo y (b) por la
propia migración para los tenants existentes. Los leads previos quedan con `canal_captacion_id` a
`NULL` y se agrupan como "Sin especificar" (FR-014), sin inventarles un canal.

**Alternativas descartadas**: (a) enum PHP — incumple FR-012; (b) columna de texto libre — imposible
agregar de forma fiable (typos generan segmentos fantasma); (c) tabla global compartida entre
tenants — viola el Principio I y no permite catálogos distintos por negocio.

---

## D4 — Alcance por perfil: dos permisos, no uno

**Decisión**: añadir al catálogo de permisos (`App\Support\CatalogoPermisos`) **dos** claves nuevas:

- `ver-informes-comerciales` — da acceso a la sección. Alcance por defecto: **solo la actividad
  asignada al propio usuario**.
- `ver-informes-equipo` — amplía el alcance a **todo el tenant** y habilita el filtro por comercial.

**Rationale**: el requisito 6 exige "diferentes niveles de agregación de información en función del
perfil del usuario". Un único permiso booleano solo puede expresar "ve / no ve", no dos niveles de
agregación distintos. Con dos claves, el administrador del tenant compone los perfiles desde la UI
de roles ya existente (feature 027) sin que haya jerarquía comercial hardcodeada.

**Desviación consciente**: el catálogo de permisos documenta que su granularidad es "una vista del
sidebar", y aquí se añaden dos permisos para una sola vista. Queda registrado en Complexity Tracking
del plan. La alternativa (reutilizar un permiso existente como `ver-usuarios` para inferir "es
responsable") acoplaría el alcance de un informe comercial a la administración de usuarios, que es
una responsabilidad distinta, y dejaría al tenant sin forma de dar informes de equipo a un jefe de
ventas que no administra usuarios.

**Aplicación en servidor** (FR-024): el alcance se resuelve en el servicio a partir del usuario
autenticado, **nunca** a partir de un parámetro de la petición. Si un usuario sin
`ver-informes-equipo` envía `comercial_id` de otro usuario, el filtro se ignora y se fuerza el
propio `id`. Se cubre con test explícito.

**Bloques visibles** (FR-022): cada bloque del informe se condiciona a su permiso de módulo ya
existente — leads a `ver-leads`, oportunidades a `ver-oportunidades`, presupuestos a
`ver-presupuestos`. Un usuario sin ninguno de los tres no accede a la sección (FR-025).

---

## D5 — Comparativa entre ejercicios

**Decisión**: nuevo método `RangoFechas::mismoPeriodoEjercicioAnterior()` que desplaza `desde` y
`hasta` un año atrás con `subYearNoOverflow()` de Carbon. Se añade **sin tocar** `anterior()`, que
sigue sirviendo al dashboard financiero.

**Rationale**: `anterior()` calcula el periodo inmediatamente precedente de igual duración (útil para
tendencia corta, que es lo que quiere el dashboard financiero) y **no sirve** para FR-018, que pide
comparar ejercicios. Son dos operaciones distintas sobre el mismo objeto de valor; añadir un método
es más simple y menos arriesgado que parametrizar el existente.

`subYearNoOverflow()` resuelve FR-019: un 29 de febrero comparado contra un año no bisiesto cae en
el 28 de febrero en lugar de desbordar al 1 de marzo, y un rango de mes completo mantiene su
longitud natural en el año comparado.

**Alcance**: solo el ejercicio inmediatamente anterior (asunción ya fijada en la spec). El método
acepta un parámetro de número de años para no cerrar la puerta a comparar contra N ejercicios, pero
la UI de esta versión solo expone uno.

**Alineación de series** (FR-021): las series temporales se alinean **por índice de bucket**, no por
fecha absoluta, de modo que "el mes 1 del periodo" se compare contra "el mes 1 del periodo del año
anterior". Se reutiliza la lógica de buckets ya existente en `DashboardEstadisticas`, extraída a un
sitio compartido.

---

## D6 — Ubicación y arquitectura del cálculo

**Decisión**: sección nueva `/informes-comerciales`, con `InformeComercialController` y un servicio
`InformeComercial` dedicado. **No se modifica** `DashboardEstadisticas` ni el dashboard financiero.

Los buckets temporales (`bucketsDelRango`/`bucketsDiarios`/`bucketsMensuales`), hoy privados en
`DashboardEstadisticas`, se extraen a `App\Support\BucketsRango` y ambos servicios lo consumen. Es
la única refactorización que se hace sobre código existente, y es mecánica (mover métodos sin
cambiar su lógica), cubierta por los tests de dashboard ya existentes.

**Rationale**: el dashboard financiero mide facturación para gerencia; el informe comercial mide el
embudo para ventas. Distinta audiencia, distintas entidades, distinto criterio de fechas (D1).
Fusionarlos produciría un servicio enorme con dos responsabilidades. Duplicar la lógica de buckets,
en cambio, sí sería deuda evitable: por eso se extrae.

**Cálculo bajo demanda, sin tablas de agregados**: con el volumen objetivo de SC-007 (5.000 leads,
1.000 oportunidades, 1.000 presupuestos por periodo) las consultas agregadas con `GROUP BY` sobre
columnas indexadas responden de sobra. Materializar agregados introduciría invalidación de caché y
procesos programados sin necesidad actual (Principio V, YAGNI).

**Índices**: los existentes (`tenant_id + estado`, `tenant_id + asignado_a`, `tenant_id + etapa`)
cubren los filtros. Se añaden `leads(tenant_id, created_at)`, `oportunidades(tenant_id, cerrada_at)`
y `presupuestos(tenant_id, fecha_emision)` para los recorridos por rango de fechas, y
`leads(tenant_id, canal_captacion_id)` para la segmentación por canal.

**Agregación en SQL, no en PHP**: a diferencia de `DashboardEstadisticas`, que en varios puntos trae
colecciones a memoria y suma con `->sum(fn ...)` (necesario allí por el neteo de rectificativas),
aquí no hay lógica de negocio por fila: todos los recuentos e importes se resuelven con `COUNT`/`SUM`
+ `GROUP BY` en base de datos. Cumple SC-007 y el Principio III (cálculo en backend).

---

## D7 — Exportación

**Decisión**: exportador dedicado `ExportadorInformeComercial` que produce un `.xlsx` multi-hoja
usando la librería Excel ya presente (`maatwebsite/excel`), **sin** pasar por el contrato
`DefinicionExportable` de la feature 031.

**Rationale**: la spec asumía reutilizar el mecanismo de la 031, pero al inspeccionarlo se comprueba
que su contrato es *"N filas de un modelo, seleccionadas por IDs"* (`consultaExportacion(array $ids)`,
una columna por atributo). Un informe es un agregado multi-bloque sin modelo de respaldo ni IDs;
forzarlo en ese contrato implicaría desvirtuarlo. Se reutiliza la **infraestructura** (librería,
`FormatoCelda`, patrón de descarga y registro en el log de actividad) pero no el contrato de filas.

**Contenido**: una hoja de portada con periodo, filtros aplicados y alcance del usuario (para que el
fichero sea auditable, FR-027), y una hoja por bloque visible. Los bloques ocultos por permisos no se
escriben (FR-028).

---

## D8 — Frontend

**Decisión**: se reutiliza el stack de gráficos ya vendorizado y usado por el dashboard —
**Chart.js** para donuts/barras y **Morris** para líneas, con `daterangepicker` para el rango
personalizado— y el patrón de recarga parcial vía JSON del `DashboardController` (respuesta con
`html` renderizado + datos de gráficos, sin recargar la página).

**Rationale**: no introducir una librería de gráficos nueva para un caso que las existentes cubren
(Principio V). El patrón de recarga parcial ya está probado en producción en el dashboard.

**Recordatorios de front que aplican** (`docs/04-front-guidelines.md` y CLAUDE.md):

- `@push('styles')` de plugins va **antes** de `css/style.css`.
- Notificaciones siempre con `window.showToast(...)` / flash + toastr, nunca alerts ad-hoc.
- Si se usa DataTable en algún bloque, aplicar el override de ancho de paginación previo/siguiente.
