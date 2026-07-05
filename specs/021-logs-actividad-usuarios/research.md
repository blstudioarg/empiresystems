# Research: Logs de actividad de usuarios

Feature 021. Todas las incógnitas técnicas quedan resueltas abajo; no queda ningún
`NEEDS CLARIFICATION`.

## D1 — Datatable client-side (como usuarios/facturas) vs. server-side real

**Decisión**: Implementar el protocolo **server-side** de DataTables a mano en
`LogActividadController@index` (parámetros `draw`, `start`, `length`, `search[value]`,
`order[0][column]`/`order[0][dir]`; respuesta `draw`/`recordsTotal`/`recordsFiltered`/`data`), sin
añadir el paquete `yajra/laravel-datatables`.

**Rationale**: `usuarios-datatable.init.js` y `facturas-datatable.init.js` cargan el listado
completo del tenant de una sola vez (`ajax.url = window.location.href`, sin `serverSide: true`) y
dejan que DataTables pagine/busque en el navegador. Eso funciona porque `usuarios` y `facturas` son
catálogos acotados por el tamaño real del negocio del tenant. `logs_actividad`, en cambio, crece
con **cada** login/logout y cada alta/baja/modificación de 5 tipos de entidad distintas: no tiene
cota natural y, a diferencia de facturas, la única duracion de la sesión que puede acotar el
crecimiento no aplica aquí (no hay borrado del log, FR-008 lo prohíbe). FR-004 de la spec ya pide
explícitamente no cargar el histórico completo de una sola vez. Por eso, para este listado en
particular, se justifica desviarse del patrón client-side y paginar en el servidor.

Se implementa a mano (sin Yajra) porque el proyecto no tiene ese paquete instalado y el protocolo
de DataTables es sencillo de replicar con Eloquent (`skip`/`take`/`orderBy`/`where...like`); añadir
una dependencia nueva solo para esto violaría el Principio V (simplicidad, sin dependencias no
justificadas).

**Alternativas consideradas**:
- *Mantener el patrón client-side actual*: rechazada porque contradice FR-004 y degrada con el
  tiempo (a diferencia de usuarios/facturas, este log no tiene un techo natural).
- *Añadir `yajra/laravel-datatables-oracle`*: rechazada por Principio V — resolvería el protocolo
  server-side con menos código propio, pero añade una dependencia nueva para un solo listado
  cuando implementarlo a mano es igual de simple aquí (una query con `where`/`orderBy`/`paginate`).

## D2 — Una tabla unificada vs. una tabla de log por entidad

**Decisión**: Una única tabla `logs_actividad` con columnas genéricas (`accion`, `entidad_tipo`,
`entidad_id`, `descripcion`) en vez de una tabla de auditoría por módulo (`clientes_log`,
`articulos_log`, etc.) o de reutilizar `factura_eventos` para todo.

**Rationale**: La spec (US1) pide explícitamente **una** vista unificada cronológica de toda la
actividad del tenant, no una vista por entidad. Una tabla por módulo obligaría a un `UNION` costoso
(o a 5 consultas) para construir esa vista única. `factura_eventos` ya existe pero está diseñada
para el encadenamiento Verifactu (huella, huella anterior) de una entidad concreta — mezclar ahí
logins y altas de clientes rompería su propósito. Una tabla genérica, con `entidad_tipo`/`entidad_id`
como referencia débil (sin FK, porque apunta a 5 tablas distintas), es el diseño más simple que
cumple el requisito (Principio V).

**Alternativas consideradas**:
- *Tabla de auditoría por entidad*: rechazada por complejidad de agregación para la vista unificada
  que pide la spec.
- *Reutilizar `factura_eventos` ampliándola a otras entidades*: rechazada porque mezclaría el
  propósito de cumplimiento normativo (Verifactu) con el de auditoría general de uso, y porque esa
  tabla no tiene columna de usuario responsable.

## D3 — Dónde se dispara el registro de cada evento

**Decisión**: Llamada directa a `RegistradorActividad::registrar(...)` al final de cada acción
relevante, dentro de los controladores existentes (`store`/`update`/`destroy` de Cliente/Articulo,
las 4 acciones `update*` de Configuración, `aprobar`/`rechazar` de Usuario, `store`/`update`/
`destroy`/`emitir`/`rectificar` de Factura, `store` de `RegisterController`). Para login/logout se
extiende el listener ya existente `LogAuthenticationActivity` (enganchado a los eventos
`Illuminate\Auth\Events\Login`/`Logout` en `AppServiceProvider`) para que, además de escribir en
`storage/logs/laravel.log` (`Log::info`, que se mantiene sin cambios), también persista una fila en
`logs_actividad`.

**Rationale**: El proyecto no usa Observers ni Model Events en ningún punto existente (Cliente,
Articulo, Factura, etc. son modelos Eloquent simples); introducir Observers solo para esta feature
sería un patrón nuevo no justificado (Principio V, YAGNI). Los controladores ya son el lugar donde
vive la lógica de orquestación de cada acción (ver `EmisorFacturas`, `RegistroPagos` invocados desde
sus controladores), así que una llamada más al final de cada método es consistente con el estilo
actual. Para login/logout, el `Listener` ya existe y ya está conectado a los eventos correctos: solo
hay que ampliar su responsabilidad, no crear infraestructura nueva.

**Alternativas consideradas**:
- *Observers de Eloquent (`created`/`updated`/`deleted`) por modelo*: rechazada por introducir un
  patrón nuevo no usado en el resto del proyecto, y porque el evento `deleted` no distingue bien la
  "baja" semántica (p. ej. `SoftDeletes` en Cliente/Articulo) de un borrado accidental sin contexto
  de quién lo hizo con los datos ya disponibles en el controlador.
- *Middleware genérico que loguea toda request POST/PUT/DELETE*: rechazada porque no puede producir
  una `descripcion` legible por humanos ni distinguir alta/baja/modificación con precisión.

## D4 — Nombre de usuario: solo FK vs. snapshot

**Decisión**: Cada fila de `logs_actividad` guarda tanto `usuario_id` (FK nullable a `users`, con
`nullOnDelete()`) como `usuario_nombre` (snapshot de texto tomado en el momento del evento). La UI
siempre muestra `usuario_nombre`.

**Rationale**: La spec pide explícitamente (edge case) que si un usuario se elimina después, su fila
en el log conserve el nombre en vez de romperse o desaparecer. Aunque hoy no existe una ruta de
borrado físico de usuarios en la UI (solo `aprobar`/`rechazar`, que desactivan), el modelo `User` no
tiene `SoftDeletes`, así que un borrado físico futuro (o vía Super Admin) es plausible; el snapshot
es la forma más simple y barata de cumplir el edge case sin depender de que `users` nunca borre
filas.

**Alternativas consideradas**:
- *Solo FK con `nullOnDelete()`, mostrando "Usuario eliminado" cuando sea null*: rechazada porque no
  cumple literalmente el edge case de la spec ("conserva el nombre del usuario en el momento del
  evento").
- *Añadir `SoftDeletes` a `User` para este propósito*: rechazada por ser un cambio de alcance mucho
  mayor (afecta login, unicidad de email, etc.) para resolver un problema que el snapshot resuelve
  de forma aislada.
