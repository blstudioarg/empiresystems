# Research: Perfil del cliente

No quedaron `NEEDS CLARIFICATION` en el spec ni en el Technical Context del plan. Este documento
recoge las decisiones técnicas tomadas para las áreas donde había más de una forma razonable de
resolverlo, con su alternativa descartada.

## 1. Cómo cargar los datos de las 4 pestañas de documentos (facturas/presupuestos/albaranes/oportunidades)

- **Decisión**: El método `ClienteController::show()` carga, en una sola respuesta server-rendered,
  un paginador Eloquent independiente por pestaña (`Cliente->facturas()->paginate(15, ['*'],
  'facturas_page')`, etc.), usando un `pageName` distinto por relación para que cada pestaña
  pueda paginar de forma independiente sin que un `?page=` choque con otra. Solo se consultan los
  paginadores de los módulos para los que el usuario autenticado tiene el permiso `ver-*`
  correspondiente (ver punto 3).
- **Rationale**: Es el patrón más simple que cumple los requisitos (FR-006 a FR-009 piden listados
  paginados) sin añadir una dependencia nueva ni un endpoint JSON adicional. Sigue la convención ya
  usada en el resto del CRM para tablas server-rendered (Laravel `paginate()` + Bootstrap
  pagination), y es coherente con Principio V (simplicidad, sin JS build step nuevo).
- **Alternativas consideradas**:
  - *Un DataTable AJAX por pestaña* (como el listado principal de clientes/facturas): más trabajo
    (4 endpoints JSON nuevos + 4 inits JS nuevos) para un listado que, a diferencia del índice
    global, ya viene acotado a un solo cliente y normalmente no tendrá volúmenes que justifiquen
    server-side processing vía AJAX. Descartado por YAGNI.
  - *Cargar todo sin paginar*: incumple el edge case ya documentado en el spec ("¿Qué pasa si el
    cliente tiene un volumen muy alto de documentos?") y el requisito explícito de que los
    listados estén paginados (FR-006–FR-009).

## 2. Cómo construir la línea de tiempo de actividad combinada (FR-010)

- **Decisión**: El controller arma la línea de tiempo combinando en PHP (no en SQL) los primeros
  N registros (N=10) más recientes ya cargados de cada una de las 4 colecciones (facturas,
  presupuestos, albaranes, oportunidades) — reutilizando las mismas queries del punto 1 en vez de
  una consulta `UNION` nueva — normalizando cada uno a `{fecha, tipo, etiqueta, url}` y ordenando
  el conjunto combinado por fecha descendente, cortando a los N más recientes.
- **Rationale**: Evita escribir una query `UNION` cruzando 4 tablas con columnas de fecha
  distintas (`fecha_expedicion` en facturas, `fecha_emision` en presupuestos, `fecha_entrega` en
  albaranes, `created_at`/`cerrada_at` en oportunidades) y distintos regímenes de permisos; con
  como mucho 4×15 registros ya en memoria, combinarlos y ordenarlos en PHP es trivial en coste y
  mucho más simple de testear. Sigue el mismo espíritu que el timeline ya existente en
  `resources/views/profile/show.blade.php` (tabla simple, sin astrucciones SQL cruzadas).
- **Alternativas consideradas**:
  - *Query SQL `UNION ALL` entre las 4 tablas*: más eficiente en teoría para volúmenes grandes,
    pero añade complejidad de mantenimiento (4 SELECTs con columnas mapeadas a un esquema común) y
    dificulta respetar el filtrado por permiso de módulo (punto 3) dentro de una sola query.
    Descartado por YAGNI/Principio V — puede reconsiderarse si en el futuro se detecta que el
    volumen por cliente lo justifica.

## 3. Cómo aplicar los permisos por módulo dentro del perfil (FR-013, FR-014)

- **Decisión**: El controller comprueba `Auth::user()->can('ver-facturas')` (y equivalentes) antes
  de construir cada paginador; si el usuario no tiene el permiso, esa variable se pasa como `null`
  a la vista y la pestaña correspondiente no se renderiza (`@can`/`@if` en Blade, igual que ya hace
  `profile/show.blade.php` con `@can('ver-logs')`). Los accesos rápidos de creación (FR-011) usan
  el permiso de creación específico de cada módulo (`ver-facturas-crear` para facturas; los demás
  módulos no tienen un permiso de creación separado del de "ver", así que se reutiliza el mismo
  `ver-*` que ya protege sus rutas de alta hoy — ver `routes/web.php`).
- **Rationale**: Reutiliza exactamente los permisos ya definidos (constitución: no se crean
  permisos nuevos sin necesidad) y el mismo mecanismo `@can` ya usado en el perfil de usuario.
- **Alternativas consideradas**: Definir un permiso nuevo "ver-perfil-cliente-completo" que agrupe
  todo. Descartado: fragmenta el modelo de permisos existente y contradice FR-013, que pide
  respetar el permiso propio de cada módulo, no uno nuevo agregador.

## 4. Accesos rápidos de creación preseleccionando el cliente (FR-011)

- **Decisión**: Los accesos rápidos de "+ Nuevo presupuesto" y "+ Nuevo albarán" reutilizan
  exactamente los enlaces `?cliente_id={id}` que ya existen hoy en
  `public/js/plugins-init/clientes-datatable.init.js` (`PresupuestoController::create` y
  `AlbaranController::create` ya leen `$request->query('cliente_id')`). El de "+ Nueva
  oportunidad" reutiliza el enlace ya existente `/oportunidades?cliente_id={id}`. El de "+ Nueva
  factura" requiere un cambio menor: `FacturaController::create()` hoy no lee `cliente_id` de la
  query string (a diferencia de presupuestos/albaranes), así que se extiende para preseleccionar
  el cliente si se recibe `?cliente_id=`, sin cambiar su firma pública ni su permiso
  (`ver-facturas-crear`).
- **Rationale**: Mantiene consistencia entre "accesos rápidos desde el listado de clientes" (ya
  existentes) y "accesos rápidos desde el perfil de cliente" (nuevos): mismo mecanismo, mismas
  URLs, mismo permiso. Minimiza el diff sobre `FacturaController`.
- **Alternativas consideradas**: Duplicar el formulario de alta de factura dentro del perfil del
  cliente. Descartado explícitamente en el spec (sección Assumptions: el perfil es de solo
  lectura respecto al propio cliente, y reutiliza los flujos de alta ya existentes).

## 5. Resumen financiero (FR-005)

- **Decisión**: Se calcula iterando las facturas ya cargadas del cliente (no simplificadas, es
  decir mismo criterio de exclusión que usa `FacturaController::index` para POS) usando los
  métodos ya existentes en el modelo `Factura`: `totalCobrable()`, `montoCobrado()`,
  `saldoPendiente()`, `estadoCobro()`. "Facturas vencidas" = facturas con `saldoPendiente() > 0` y
  `fecha_vencimiento` anterior a hoy. Confirmado por código: `FacturaController::index` ya calcula
  `saldoPendiente()` por factura pero **no** existe hoy ningún cálculo de "vencida" centralizado
  (ni en el modelo `Factura` ni en el controller) — el enum `estado` sí incluye un valor
  `vencida` conceptual (referenciado en `app/Ia/Tools/BuscarFacturas.php`) pero no hay lógica que
  lo derive automáticamente. Esta feature introduce ese cálculo por primera vez, únicamente como
  criterio de agregación de solo lectura dentro del resumen financiero del perfil (no cambia el
  campo `estado` persistido de `Factura`, que sigue siendo el que gestiona el ciclo de vida
  documentado en `docs/02-facturacion-espana.md`).
- **Rationale**: Reutiliza lógica financiera ya probada por tests existentes de `Factura`, en vez
  de reimplementar el cálculo de importe pendiente/cobrado (cumple Principio III: los importes se
  calculan siempre en backend, con una única fuente de verdad).
- **Alternativas consideradas**: Precalcular y cachear el resumen en una tabla/columna nueva.
  Descartado explícitamente en el Constitution Check (Principio V, YAGNI) mientras el volumen de
  facturas por cliente no lo justifique.
