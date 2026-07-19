---

description: "Task list for feature implementation"
---

# Tasks: Reporting Comercial CRM

**Input**: Design documents from `/specs/033-reporting-comercial-crm/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: SÍ se incluyen. El Principio IV de la constitución exige test-first en aislamiento
multi-tenant y cálculo (aquí, los ratios), y SC-005/SC-006 los piden explícitamente. Las tareas de
test de esas áreas están marcadas ⚠️ y **deben fallar antes** de implementar.

**Organization**: agrupadas por historia de usuario para poder entregar de forma incremental.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (fichero distinto, sin dependencias pendientes)
- **[Story]**: historia a la que pertenece (US1..US5)

## Path Conventions

Proyecto monolítico Laravel: `app/`, `resources/`, `database/`, `public/`, `tests/` en la raíz del
repositorio (ver "Source Code" en [plan.md](./plan.md)).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: preparar los cimientos compartidos sin cambiar comportamiento existente.

- [ ] T001 Extraer los métodos privados `bucketsDelRango`, `bucketsDiarios` y `bucketsMensuales` de `app/Services/DashboardEstadisticas.php` a una clase nueva `app/Support/BucketsRango.php`, sin cambiar su lógica
- [ ] T002 Actualizar `app/Services/DashboardEstadisticas.php` para consumir `App\Support\BucketsRango` y verificar que `php artisan test --filter=Dashboard` sigue en verde (refactor sin cambio de comportamiento)
- [ ] T003 [P] Crear migración en `database/migrations/` que añada los índices `leads(tenant_id, created_at)`, `oportunidades(tenant_id, cerrada_at)` y `presupuestos(tenant_id, fecha_emision)` (data-model §3)

**Checkpoint**: lógica de buckets compartida y disponible; dashboard financiero intacto.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: acceso, rutas y esqueleto de cálculo. Sin esto no se puede abrir la sección.

**⚠️ CRITICAL**: ninguna historia puede empezar hasta completar esta fase.

- [ ] T004 Añadir las claves `ver-informes-comerciales` y `ver-informes-equipo` (módulo `CRM`) a `app/Support/CatalogoPermisos.php`, incluyendo `ver-informes-equipo` en `EXCLUIDOS_USUARIO_BASE` (data-model §5)
- [ ] T005 Ejecutar/actualizar el seeder de permisos en `database/seeders/` para registrar los dos permisos nuevos en tenants existentes
- [ ] T006 [P] Crear `app/Http/Requests/InformeComercialFiltroRequest.php` con validación de `preset`/`desde`/`hasta`/`canal_id`/`comercial_id`/`fase`/`comparar`, replicando el patrón de `DashboardFiltroRequest` que **no lanza 422** (contrato: rango inválido cae a mes en curso + aviso)
- [ ] T007 [P] Crear `app/Support/AlcanceInformeComercial.php` que resuelva, a partir del usuario autenticado, los bloques visibles (`ver-leads`/`ver-oportunidades`/`ver-presupuestos`) y el tipo de alcance (propio vs tenant)
- [ ] T008 Crear `app/Services/InformeComercial.php` con el esqueleto del método `generar(RangoFechas $rango, FiltrosInforme $filtros, AlcanceInformeComercial $alcance): array` devolviendo la estructura de data-model §4 con valores vacíos
- [ ] T009 Registrar en `routes/web.php` el grupo `can:ver-informes-comerciales` con `GET /informes-comerciales`, crear `app/Http/Controllers/InformeComercialController.php` y la vista `resources/views/informes-comerciales/index.blade.php`, denegando acceso si el usuario no tiene ninguno de los tres permisos de módulo (FR-025)

**Checkpoint**: la sección abre, con permisos aplicados y estructura vacía.

---

## Phase 3: User Story 1 - Panel de indicadores comerciales con ratios (Priority: P1) 🎯 MVP

**Goal**: convertir la actividad comercial ya capturada en indicadores y ratios de eficiencia para
un periodo seleccionable.

**Independent Test**: crear en un tenant un conjunto conocido de leads, oportunidades y
presupuestos con fechas dentro y fuera del periodo, y verificar que cada indicador y cada ratio
coincide con el cálculo manual, que lo de fuera del periodo no cuenta y que no aparecen datos de
otro tenant.

### Tests for User Story 1 ⚠️

> **Escribir primero, verificar que FALLAN antes de implementar** (Principio IV)

- [ ] T010 [P] [US1] Test de indicadores de volumen en `tests/Feature/InformeComercialIndicadoresTest.php`: leads captados, convertidos, oportunidades creadas/ganadas/perdidas/abiertas con importe, presupuestos emitidos/aceptados con importe, sobre un dataset conocido (FR-003)
- [ ] T011 [P] [US1] Test de criterios de fecha en `tests/Feature/InformeComercialIndicadoresTest.php`: registros justo fuera del rango no cuentan; cohorte vs evento vs instantánea según research D1 (FR-006)
- [ ] T012 [P] [US1] Test de ratios en `tests/Feature/InformeComercialRatiosTest.php`: conversión lead→cliente, ganadas sobre cerradas, aceptación de presupuestos, importe medio y ciclos medios (FR-004)
- [ ] T013 [P] [US1] Test de denominador cero en `tests/Feature/InformeComercialRatiosTest.php`: periodo sin actividad devuelve ratios `null`, nunca `0` ni excepción (FR-005)
- [ ] T014 [P] [US1] Test de aislamiento multi-tenant en `tests/Feature/InformeComercialAislamientoTenantTest.php` con dos tenants con actividad; ningún indicador cruza (SC-005)

### Implementation for User Story 1

- [ ] T015 [US1] Crear migración en `database/migrations/` que añada `leads.convertido_at` (datetime nullable) (data-model §2, research D2)
- [ ] T016 [US1] Añadir `convertido_at` a `$fillable` y al cast `datetime` en `app/Models/Lead.php`
- [ ] T017 [US1] Poblar `convertido_at` con `now()` al convertir en `app/Services/ConversorLeadCliente.php`, junto a `convertido_a_cliente_id`
- [ ] T018 [US1] Implementar en `app/Services/InformeComercial.php` los indicadores de leads (captados y convertidos por cohorte de `created_at`) mediante agregación SQL
- [ ] T019 [US1] Implementar en `app/Services/InformeComercial.php` los indicadores de oportunidades: creadas por cohorte, ganadas/perdidas por `cerrada_at`, y abiertas + importe como instantánea a fecha `hasta`
- [ ] T020 [US1] Implementar en `app/Services/InformeComercial.php` los indicadores de presupuestos por cohorte de `fecha_emision`, con importes agregados
- [ ] T021 [US1] Implementar en `app/Services/InformeComercial.php` el cálculo de ratios y ciclos medios, devolviendo `null` cuando el denominador sea cero (FR-005)
- [ ] T022 [US1] Implementar en `app/Services/InformeComercial.php` el desglose por fases (leads por estado, oportunidades por etapa con importe, presupuestos por estado con importe) usando `GROUP BY` (FR-007)
- [ ] T023 [US1] Implementar en `app/Services/InformeComercial.php` la serie de evolución temporal usando `App\Support\BucketsRango` (FR-008) y el top de artículos más presupuestados por importe (FR-009)
- [ ] T024 [US1] Renderizar los bloques en `resources/views/partials/informe-comercial-contenido.blade.php`, mostrando junto a cada indicador su criterio de fecha (FR-006) y los ratios sin datos como texto, no como 0%
- [ ] T025 [US1] Añadir la respuesta JSON de recarga parcial en `app/Http/Controllers/InformeComercialController.php` (`html` + `graficos` + `periodo` + `aviso`) según el contrato
- [ ] T026 [US1] Crear `public/js/plugins-init/informe-comercial.init.js` con el selector de periodo (daterangepicker) y el renderizado de gráficos con Chart.js/Morris, cargando los assets desde `resources/views/informes-comerciales/index.blade.php` con `@push('styles')` **antes** de `css/style.css`
- [ ] T027 [US1] Añadir la entrada "Informes comerciales" al sidebar en `resources/views/partials/sidebar.blade.php`, condicionada al permiso

**Checkpoint**: US1 funcional y demostrable por sí sola — es el MVP y ya sirve como evidencia del
requisito 6 en sus partes de indicadores, ratios, fases y pipeline.

---

## Phase 4: User Story 2 - Segmentación por canal, comercial y fase (Priority: P2)

**Goal**: poder responder de dónde viene el negocio y quién lo cierra.

**Independent Test**: asignar leads a distintos canales y comerciales, aplicar cada filtro por
separado y combinado, y verificar que los segmentos suman el total sin filtrar.

### Tests for User Story 2

- [ ] T028 [P] [US2] Test de segmentación en `tests/Feature/InformeComercialSegmentacionTest.php`: filtro por canal, por comercial y combinado; la suma de segmentos coincide con el total (FR-015, FR-016)
- [ ] T029 [P] [US2] Test en `tests/Feature/InformeComercialSegmentacionTest.php` de que los leads sin canal se agrupan como "Sin especificar" y no se pierden del total (FR-014)
- [ ] T030 [P] [US2] Test de CRUD y reglas del catálogo en `tests/Feature/CanalCaptacionCrudTest.php`: nombre único por tenant, desactivación en vez de borrado si tiene leads, aislamiento entre tenants (FR-012, FR-013)

### Implementation for User Story 2

- [ ] T031 [P] [US2] Crear migración en `database/migrations/` para la tabla `canales_captacion` (`tenant_id`, `nombre`, `activo`, `orden`, timestamps, `unique(tenant_id, nombre)`, `index(tenant_id, activo)`) (data-model §1)
- [ ] T032 [P] [US2] Crear `app/Models/CanalCaptacion.php` con `BelongsToTenant`, `HasFactory` y la relación `leads()`
- [ ] T033 [US2] Crear migración en `database/migrations/` que añada `leads.canal_captacion_id` (FK nullable, `nullOnDelete`) y el índice `leads(tenant_id, canal_captacion_id)` (data-model §2)
- [ ] T034 [US2] Sembrar el catálogo por defecto (Web, Recomendación, Feria/Evento, Campaña de email, Llamada entrante, Redes sociales, Otro) en `database/seeders/` para tenants nuevos y desde la propia migración para los existentes (research D3)
- [ ] T035 [US2] Añadir `canal_captacion_id` a `$fillable` y la relación `canalCaptacion()` en `app/Models/Lead.php`
- [ ] T036 [US2] Crear `app/Http/Controllers/CanalCaptacionController.php` y `app/Http/Requests/StoreCanalCaptacionRequest.php` con el CRUD del catálogo, impidiendo borrar un canal con leads (lo desactiva e informa), y registrar las rutas bajo `can:ver-configuracion` en `routes/web.php`
- [ ] T037 [US2] Añadir la pestaña de gestión de canales de captación en `resources/views/configuracion/` reutilizando el patrón de catálogos existente
- [ ] T038 [US2] Añadir el selector de canal (solo canales activos) al alta y edición de leads en `resources/views/leads/index.blade.php` y validar en `app/Http/Requests/` que el canal pertenece al tenant
- [ ] T039 [US2] Admitir una columna opcional de canal en la importación de leads, resolviéndola por nombre y reportando la fila sin canal —sin abortar— si el nombre es desconocido (contrato "Cambios en contratos existentes")
- [ ] T040 [US2] Añadir la columna de canal a la exportación de leads en `app/Excel/Definiciones/DefinicionLeads.php`
- [ ] T041 [US2] Implementar los filtros de canal, comercial y fase en `app/Services/InformeComercial.php`, tratando un `canal_id` inexistente como filtro sin resultados y un `fase` desconocido como ignorado
- [ ] T042 [US2] Añadir los controles de filtro a `resources/views/informes-comerciales/index.blade.php` y mantenerlos al cambiar de periodo en `public/js/plugins-init/informe-comercial.init.js` (FR-017)

**Checkpoint**: US1 + US2 funcionan de forma independiente; la segmentación por canal ya es real.

---

## Phase 5: User Story 3 - Comparativa entre ejercicios (Priority: P2)

**Goal**: distinguir crecimiento real de estacionalidad comparando contra el mismo periodo del año
anterior.

**Independent Test**: crear actividad en dos ejercicios, activar la comparativa y verificar valores
y variaciones, incluido el caso de ejercicio comparado vacío.

### Tests for User Story 3

- [ ] T043 [P] [US3] Test de comparativa en `tests/Feature/InformeComercialComparativaTest.php`: cada indicador devuelve actual, comparado y variación sobre un dataset de dos ejercicios (FR-018)
- [ ] T044 [P] [US3] Test en `tests/Feature/InformeComercialComparativaTest.php` de ejercicio comparado sin datos → variación `null`, sin división por cero (FR-020)
- [ ] T045 [P] [US3] Test de años bisiestos en `tests/Unit/RangoFechasEjercicioAnteriorTest.php`: un rango con 29 de febrero comparado contra un año no bisiesto no desborda ni trunca (FR-019)

### Implementation for User Story 3

- [ ] T046 [US3] Añadir `mismoPeriodoEjercicioAnterior(int $anios = 1): self` a `app/Support/RangoFechas.php` usando `subYearNoOverflow()`, **sin modificar** el método `anterior()` existente (research D5)
- [ ] T047 [US3] Calcular el bloque `comparativa` en `app/Services/InformeComercial.php` reutilizando el mismo cálculo sobre el rango del ejercicio anterior, con variaciones `null` cuando no sean calculables
- [ ] T048 [US3] Alinear las series temporales por índice de bucket (no por fecha absoluta) en `app/Services/InformeComercial.php` (FR-021)
- [ ] T049 [US3] Añadir el conmutador de comparativa y el pintado de la segunda serie en `resources/views/informes-comerciales/index.blade.php` y `public/js/plugins-init/informe-comercial.init.js`

**Checkpoint**: US1 + US2 + US3 operativas; SC-003 cubierto.

---

## Phase 6: User Story 4 - Alcance de datos según el perfil (Priority: P2)

**Goal**: que un comercial vea solo lo suyo y un responsable vea todo el tenant, evidenciando los
"diferentes niveles de agregación en función del perfil".

**Independent Test**: dos usuarios del mismo tenant con perfiles distintos y actividad asignada a
cada uno; el restringido solo ve lo suyo y no puede forzar el filtro por otro comercial.

### Tests for User Story 4 ⚠️

> **Área de seguridad: escribir primero y verificar que FALLAN**

- [ ] T050 [P] [US4] Test en `tests/Feature/InformeComercialAlcancePerfilTest.php`: usuario con `ver-informes-equipo` ve todo el tenant y dispone del filtro por comercial (FR-023)
- [ ] T051 [P] [US4] Test en `tests/Feature/InformeComercialAlcancePerfilTest.php`: usuario sin `ver-informes-equipo` ve solo su actividad asignada (FR-023)
- [ ] T052 [P] [US4] Test de forzado en `tests/Feature/InformeComercialAlcancePerfilTest.php`: enviar `comercial_id` ajeno no devuelve datos de otro usuario (FR-024)
- [ ] T053 [P] [US4] Test de bloques por permiso en `tests/Feature/InformeComercialAlcancePerfilTest.php`: sin `ver-presupuestos` no aparece el bloque ni sus ratios; sin ninguno de los tres, no se accede (FR-022, FR-025)

### Implementation for User Story 4

- [ ] T054 [US4] Completar `app/Support/AlcanceInformeComercial.php` para resolver el alcance efectivo (propio vs tenant) y descartar cualquier `comercial_id` recibido cuando el usuario no tenga `ver-informes-equipo`
- [ ] T055 [US4] Aplicar el alcance en todas las consultas de `app/Services/InformeComercial.php`, forzando `asignado_a` sobre leads y oportunidades, y acotando presupuestos por el comercial de su oportunidad asociada (data-model §4)
- [ ] T056 [US4] Omitir el cálculo y el envío de los bloques sin permiso en `app/Services/InformeComercial.php` y `app/Http/Controllers/InformeComercialController.php` (FR-022)
- [ ] T057 [US4] Ocultar el filtro por comercial y los bloques no visibles en `resources/views/informes-comerciales/index.blade.php` y `resources/views/partials/informe-comercial-contenido.blade.php`

**Checkpoint**: SC-004 demostrable con dos usuarios del mismo tenant.

---

## Phase 7: User Story 5 - Exportación del informe (Priority: P3)

**Goal**: llevarse el informe a un fichero para justificaciones y reuniones.

**Independent Test**: exportar con filtros aplicados y comparar contra la pantalla; exportar con un
usuario restringido y comprobar que no se filtran datos ajenos.

### Tests for User Story 5

- [ ] T058 [P] [US5] Test en `tests/Feature/InformeComercialExportacionTest.php`: el fichero refleja el mismo periodo, filtros e indicadores que la pantalla (FR-027)
- [ ] T059 [P] [US5] Test en `tests/Feature/InformeComercialExportacionTest.php`: usuario con alcance restringido obtiene solo sus datos y sin los bloques ocultos (FR-028)
- [ ] T060 [P] [US5] Test en `tests/Feature/InformeComercialExportacionTest.php`: periodo sin actividad produce un fichero válido con ceros (FR-029)

### Implementation for User Story 5

- [ ] T061 [US5] Crear `app/Services/ExportadorInformeComercial.php` que genere un `.xlsx` multi-hoja con `maatwebsite/excel`: hoja de portada con periodo, filtros y alcance, y una hoja por bloque visible (research D7)
- [ ] T062 [US5] Añadir la acción `exportar` a `app/Http/Controllers/InformeComercialController.php` y la ruta `POST /informes-comerciales/exportar` en `routes/web.php`, resolviendo los filtros de nuevo en servidor
- [ ] T063 [US5] Registrar la exportación en el log de actividad vía `app/Services/RegistradorActividad.php`, añadiendo la entidad correspondiente en `app/Enums/EntidadLogActividad.php` si hiciera falta
- [ ] T064 [US5] Añadir el botón de exportación a `resources/views/informes-comerciales/index.blade.php`, enviando los filtros activos

**Checkpoint**: las cinco historias operativas.

---

## Phase 8: Polish & Cross-Cutting Concerns

- [ ] T065 Verificar SC-007 sembrando un tenant con ≥5.000 leads, 1.000 oportunidades y 1.000 presupuestos y midiendo que el informe responde en <3 s; ajustar índices si no se cumple
- [ ] T066 [P] Actualizar `docs/06-kit-digital.md` marcando el requisito 6 de la categoría Gestión de Clientes como cubierto (SC-008)
- [ ] T067 [P] Actualizar `docs/03-modelo-datos.md` con `canales_captacion`, `leads.canal_captacion_id` y `leads.convertido_at`
- [ ] T068 [P] Crear la guía in-app `resources/views/ayuda/informes-comerciales.blade.php` y registrarla en el mecanismo de ayuda contextual
- [ ] T069 [P] Actualizar la guía in-app de leads en `resources/views/ayuda/leads.blade.php` con el nuevo campo de canal de captación
- [ ] T070 [P] Crear `resources/ia/conocimiento/informes-comerciales.md` y actualizar `resources/ia/conocimiento/leads.md` con el canal de captación (FR-013 de la feature 030)
- [ ] T071 [P] Añadir a `docs/04-front-guidelines.md` cualquier convención de UI reutilizable que haya surgido (solo si aplica)
- [ ] T072 Ejecutar `php artisan test` completo y confirmar que ninguna suite existente se rompe, en especial `--filter=Dashboard` tras la extracción de `BucketsRango`
- [ ] T073 Recorrer manualmente [quickstart.md](./quickstart.md) de principio a fin

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias.
- **Foundational (Phase 2)**: depende de Setup. **Bloquea todas las historias.**
- **US1 (Phase 3)**: depende de Foundational. Es el MVP.
- **US2, US3, US4 (Phases 4-6)**: dependen de Foundational **y de US1**, porque extienden el
  servicio de cálculo que US1 construye. Entre ellas son independientes.
- **US5 (Phase 7)**: depende de US1; si US2/US3/US4 ya están, exporta también sus bloques.
- **Polish (Phase 8)**: depende de las historias que se decidan entregar.

### Nota honesta sobre independencia

Las historias 2-5 **no son independientes de US1**: todas son extensiones del mismo informe. Sí son
independientes entre sí y cada una es demostrable por separado una vez US1 está en pie. La
independencia real que aporta esta división es la de **entrega incremental**: se puede parar tras
US1 y ya se tiene una feature útil y evidenciable.

### Parallel Opportunities

- T003 puede ir en paralelo a T001-T002 (fichero distinto).
- T006 y T007 en paralelo dentro de Foundational.
- Todos los tests de una misma historia (T010-T014, T028-T030, T043-T045, T050-T053, T058-T060)
  son paralelizables entre sí.
- T031 y T032 en paralelo (migración y modelo, ficheros distintos).
- Casi todo el Polish documental (T066-T071) es paralelizable.
- Con equipo: tras US1, un desarrollador puede tomar US2 (la más grande) mientras otro hace US3+US4.

---

## Parallel Example: User Story 1

```bash
# Escribir primero todos los tests de US1 (deben fallar):
Task: "Test de indicadores en tests/Feature/InformeComercialIndicadoresTest.php"
Task: "Test de criterios de fecha en tests/Feature/InformeComercialIndicadoresTest.php"
Task: "Test de ratios en tests/Feature/InformeComercialRatiosTest.php"
Task: "Test de denominador cero en tests/Feature/InformeComercialRatiosTest.php"
Task: "Test de aislamiento en tests/Feature/InformeComercialAislamientoTenantTest.php"
```

---

## Implementation Strategy

### MVP First (solo US1)

1. Phase 1: Setup
2. Phase 2: Foundational (crítico, bloquea todo)
3. Phase 3: US1
4. **PARAR Y VALIDAR**: indicadores, ratios, fases y pipeline contra cálculo manual
5. Ya es evidenciable para el requisito 6 en sus partes principales

### Entrega incremental

1. Setup + Foundational → base lista
2. US1 → validar → demo (**MVP**)
3. US2 → la segmentación por canal deja de ser vacía → SC-003 a medias
4. US3 → comparativa entre ejercicios → SC-003 completo
5. US4 → alcance por perfil → SC-004
6. US5 → exportación
7. Polish → documentación de las 4 capas + validación de rendimiento

### Orden recomendado si hay que recortar

US1 → US4 → US2 → US3 → US5. US4 va pronto porque es la única historia con implicación de
**confidencialidad** (un comercial viendo cifras de sus compañeros), y cuanto antes se fije el
alcance en servidor, menos código hay que revisar después.

---

## Notes

- `[P]` = ficheros distintos, sin dependencias pendientes.
- Los tests marcados ⚠️ (US1 ratios/aislamiento, US4 alcance) van **antes** de implementar y deben
  fallar primero: es requisito no negociable del Principio IV.
- En ningún punto se usa `withoutGlobalScopes()` (Principio I).
- Todos los importes se redondean en servidor; el cliente solo pinta (Principio III).
- Commit por tarea o grupo lógico; parar en cualquier checkpoint para validar.
