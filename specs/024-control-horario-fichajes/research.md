# Research — Control horario y fichajes con geolocalización

Fase 0. Todas las NEEDS CLARIFICATION del spec se resolvieron en `/speckit-clarify` (ver
`spec.md` › Clarifications). Aquí se consolidan las decisiones técnicas del plan.

## D1 — Mapa interactivo: Leaflet + OpenStreetMap (no Google Maps)

- **Decisión**: Leaflet (JS/CSS vendorizados en `public/vendor/leaflet/`) con tiles de
  OpenStreetMap para mostrar la posición en vivo y dibujar el perímetro (círculo).
- **Rationale**: gratis, sin API key ni cuenta de facturación → coherente con Principio V
  (hosting compartido, simplicidad). La captura de posición no la hace el mapa sino la
  **Geolocation API** del navegador (`navigator.geolocation.watchPosition`), 100 % cliente. El
  mapa es solo visualización.
- **Alternativas descartadas**: Google Maps JS API (requiere key + billing + dependencia de pago,
  sin ventaja funcional aquí); Mapbox (freemium con token). Ambas añaden coste/fricción sin
  necesidad para geofencing de radio fijo.

## D2 — Geofencing por Haversine en backend (sin PostGIS)

- **Decisión**: distancia entre la coordenada del fichaje y la **ubicación de trabajo del propio
  miembro** con la fórmula de **Haversine** en PHP (`App\Support\Haversine`), comparada contra la
  `distancia_max_metros` del miembro. El veredicto (Dentro/Fuera/SinUbicacion) lo decide el servicio
  `RegistroFichajes`.
- **Rationale**: cada miembro tiene una sola ubicación de trabajo → cálculo O(1), sin índices
  espaciales ni extensiones. Cumple Principio III (el cliente nunca decide "estaba dentro").
- **Alternativas descartadas**: funciones espaciales MySQL `ST_Distance_Sphere` (ata a versión/engine,
  innecesario); cálculo en cliente (viola integridad server-side).
- **Nota**: si distancia ≤ `distancia_max_metros` → Dentro; si > → Fuera (y se dispara alerta, D11);
  si el miembro no tiene ubicación de trabajo configurada → SinUbicacion (sin alerta de distancia).

## D3 — CSP y tiles externos

- **Decisión**: permitir `img-src` de `*.tile.openstreetmap.org` en la CSP para las vistas con mapa;
  Leaflet JS/CSS se sirven locales (self). Documentar el ajuste al implementar.
- **Rationale**: los tiles son imágenes externas; sin este ajuste el mapa carga en blanco. El resto
  de assets quedan self, minimizando superficie externa.
- **Alternativas descartadas**: auto-hospedar tiles (volumen de almacenamiento y complejidad
  desproporcionados para el MVP).

## D4 — Geo minimizada: qué se persiste (confirma Clarify Q1)

- **Decisión**: en `fichajes` se guarda `resultado_ubicacion` (enum Dentro/Fuera/SinUbicacion),
  `distancia_metros` (distancia calculada a la ubicación de trabajo del miembro) y `precision_metros`
  reportada por el navegador. **No** se guardan lat/long crudas del fichaje.
- **Rationale**: minimización RGPD/AEPD (art. 90 LOPDGDD). Elimina el problema de retener coordenadas
  históricas; la purga de geo se reduce a nulificar `precision_metros`/`distancia_metros` (el veredicto
  puede conservarse por ser no identificable de posición exacta, o nulificarse también — ver D5).
- **Alternativas descartadas**: guardar coordenadas del instante (opciones B/C del clarify) — mayor
  exposición sin beneficio para control horario de radio fijo.

## D5 — Dos retenciones separadas (confirma FR-022)

- **Decisión**: la **fila `fichajes`** (registro de jornada) se conserva 4 años y **no** se purga
  antes (art. 34.9 ET). El **dato de geo** asociado (`distancia_metros`, `precision_metros`, y opcional
  `resultado_ubicacion`) se purga a los `DEFAULT_RETENCION_GEO_DIAS` (configurable por tenant) vía
  `fichajes:purgar-geo`, que hace `UPDATE` nulificando solo esas columnas — nunca borra la fila.
- **Rationale**: separa el plazo legal largo del dato de jornada del plazo corto del dato personal de
  ubicación. Reutiliza el patrón `RetencionLogsTenant` (`configuraciones` clave/valor + comando en
  `withSchedule`). Nulificar columnas (no borrar fila) preserva la inmutabilidad del evento de
  jornada mientras cumple minimización.
- **Matiz de inmutabilidad**: la purga de geo es la **única** operación de `UPDATE` admitida sobre
  `fichajes`, y solo sobre las columnas de geo, ejecutada por el comando de sistema (no por usuarios).
  Documentado explícitamente para no contradecir el append-only del evento de jornada.
- **Default**: se propone `DEFAULT_RETENCION_GEO_DIAS = 30`. Clave de config
  `fichajes.retencion_geo_dias`.

## D6 — Correcciones como evento enlazado (confirma FR-014)

- **Decisión**: una corrección es una **fila nueva** en `fichajes` con `corrige_fichaje_id`
  apuntando al original, más `motivo`, `usuario_id` del corrector y (para "salida olvidada" etc.) el
  `tipo`/`ocurrido_at` corregidos. El original no se toca. El informe usa el valor corregido pero
  puede mostrar la traza.
- **Rationale**: idéntico al patrón "movimiento inverso" de `movimientos_stock`; mantiene el ledger
  append-only y la trazabilidad exigida por la reforma digital.
- **Alternativas descartadas**: columna editable + tabla de auditoría aparte (rompe el patrón único
  de escritura y duplica mecanismos).

## D7 — Identidad del empleado y roles (revisado — ver Clarifications sesión 2)

- **Decisión**: se introduce una entidad **`miembros_equipo`** (perfil de empleado) vinculada **1:1**
  a un `User` con login (`user_id` UNIQUE → `users.id`). El fichaje cuelga del miembro
  (`miembro_equipo_id`), no del `user` directo. El rol `Usuario` (que además es miembro) ficha por sí
  mismo y ve su portal; el rol `Admin` gestiona miembros, corrige fichajes, gestiona alertas y
  consulta/exporta el informe de toda la plantilla. Un `user` sin perfil de miembro no puede fichar.
- **Rationale**: el negocio necesita atributos de RRHH (ubicación de trabajo, distancia máxima,
  dirección de casa) que no pertenecen al `User` de autenticación. Separar el perfil de empleado del
  usuario de login es el modelo estándar y evita ensuciar `users`. Revisa la decisión previa
  (empleado = user directo), que se descartó al ampliarse el alcance.
- **Alternativas descartadas**: añadir columnas de RRHH a `users` (mezcla auth con datos de empleado,
  y no todo user es empleado que ficha); entidad sin login (se descartó: los miembros fichan ellos
  mismos, Clarify Q sesión 2).

## D10 — Ubicación de trabajo por miembro (no tabla compartida)

- **Decisión**: cada `miembro_equipo` almacena `trabajo_latitud`, `trabajo_longitud`,
  `trabajo_direccion` y `distancia_max_metros`. Se **elimina** la tabla compartida
  `ubicaciones_trabajo` prevista antes.
- **Rationale**: el perímetro es individual (cada empleado ficha en su sitio con su tolerancia); una
  tabla compartida añadía indirección innecesaria. Coordenadas del centro de trabajo no son dato
  personal de posición de la persona.
- **Alternativas descartadas**: catálogo `ubicaciones_trabajo` + FK por miembro (más tablas sin
  beneficio dado que la relación es 1:1 miembro↔ubicación en este alcance).

## D11 — Alertas ligadas al fichaje

- **Decisión**: tabla `alertas` (tenant-scoped) con `miembro_equipo_id`, `fichaje_id`, `tipo`
  (`FichajeFueraDeRango`), `distancia_metros`, `estado` (`Nueva`/`Vista`/`Resuelta`), timestamps. La
  alerta la **crea el servicio `RegistroFichajes`** en la misma transacción del fichaje cuando el
  resultado es `Fuera`. Administración la consulta y cambia de estado; no se borra.
- **Rationale**: mismo patrón "un evento de negocio dispara un registro" ya usado en el proyecto; un
  `POST` puntual, sin colas ni tiempo real (Principio V). Enlazar a `fichaje_id` da trazabilidad.
- **Extensible**: el `tipo` es enum para admitir futuros disparadores sin rehacer el modelo, pero el
  MVP solo implementa `FichajeFueraDeRango` (YAGNI).

## D12 — Dirección de casa: minimización

- **Decisión**: se guardan `casa_latitud`/`casa_longitud`/`casa_direccion` del miembro solo para
  calcular `distancia_casa_trabajo_metros` (métrica). La finalidad es exclusivamente esa (no valida
  fichajes, D). Es dato personal: se conserva mientras el miembro está activo y se **purga al darlo
  de baja** según plazo configurable (`RetencionMiembroTenant`, patrón feature 021).
- **Rationale**: minimización RGPD/LOPDGDD (Principio II). La distancia derivada puede conservarse
  como métrica no sensible; las coordenadas/dirección crudas de casa se purgan tras la baja.
- **Alternativa considerada**: guardar solo la distancia calculada y descartar las coordenadas de
  casa de inmediato (minimización máxima). Se descarta para poder **recalcular** la distancia si
  cambia la ubicación de trabajo; se compensa con la purga tras baja.

## D8 — Geofencing informativo vs bloqueante (confirma Clarify Q3)

- **Decisión**: informativo por defecto (se registra el fichaje aunque esté Fuera/SinUbicacion),
  configurable a bloqueante por tenant vía `configuraciones` clave `fichajes.geofencing_bloqueante`
  (bool, default false). En modo bloqueante, un fichaje Fuera se rechaza con mensaje; SinUbicacion
  (tenant sin ubicaciones) nunca bloquea.
- **Rationale**: habilita teletrabajo/campo sin perder el fichaje (default), pero permite endurecer.

## D9 — Pausas opcionales (confirma Clarify Q4)

- **Decisión**: el enum `TipoEventoFichaje` incluye `InicioPausa`/`FinPausa` desde el diseño; su uso
  en la UI se activa por config de tenant `fichajes.registrar_pausas` (default false). El cálculo de
  horas efectivas de `InformeJornada` resta los intervalos de pausa cerrados.
- **Rationale**: modelo listo sin rehacer; MVP mínimo exige solo entrada/salida.
