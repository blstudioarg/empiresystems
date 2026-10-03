# Tasks: Cierre de caja del POS

**Input**: Design documents from `/specs/048-cierre-caja-pos/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/rutas.md, contracts/ui-caja.md, quickstart.md

**Tests**: SÍ. La constitución (Principio IV, NON-NEGOTIABLE) exige test-first para aislamiento
multi-tenant y cálculo de importes: los tests marcados **(test-first)** se escriben antes que la
implementación y deben **fallar primero**.

**Reglas transversales para toda tarea de UI** (docs/04-front-guidelines.md, citadas en
[contracts/ui-caja.md](contracts/ui-caja.md)): assets propios con `@assetv`; `@stack('styles')`
compite con `style.css` → doble clase donde haga falta; botones de color propio con variables
`--bs-btn-*`; `withButtonLoading` en todo botón AJAX; `window.showToast` con el texto del servidor;
`X-CSRF-TOKEN` en POST sin form; modales `modal-dialog-centered`; hover solo bajo
`@media (hover: hover) and (pointer: fine)` y `:active { transform: scale(.97) }`;
`prefers-reduced-motion` respetado; importes en Blade con `Formato::moneda()`.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [X] T001 Cargar las skills de diseño `ui-ux-pro-max` y `emil-design-eng` (CLAUDE.md) y releer research.md D11 + contracts/ui-caja.md; anotar en `specs/048-cierre-caja-pos/research.md` (sección D11, subapartado "Ajustes tras skills") cualquier ajuste concreto de paleta, tamaños táctiles o curvas que surja, antes de escribir CSS
- [X] T002 [P] (test-first) Test unitario de `DenominacionesEuro` en `tests/Unit/DenominacionesEuroTest.php`: total desde un mapa mixto sin errores de coma flotante (p. ej. 3×5000 + 7×1 = 150,07), rechazo de claves fuera del catálogo y de cantidades negativas; debe fallar antes de T003
- [X] T003 Crear el catálogo fijo de denominaciones en `app/Support/DenominacionesEuro.php`: constante con los 15 valores en céntimos (50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1), tipo (`billete`/`moneda`), etiqueta ("50 €", "20 cént.") y metal/color para la UI; métodos `valores()`, `esValida(int)`, `totalDesde(array $conteo): string` (suma en céntimos enteros, devuelve decimal con 2) y `normalizar(array): array` (descarta ceros, castea a int)

---

## Phase 2: Foundational (bloquea todas las historias)

- [X] T004 Migración `database/migrations/2026_10_03_100000_create_caja_sesiones_table.php` según data-model.md (`caja_sesiones`: estado, `abierta_marca` + `UNIQUE (tenant_id, abierta_marca)`, fondo, conteos JSON, FKs a users `restrictOnDelete`, cifras congeladas nullable, `resumen` JSON, índice `(tenant_id, abierta_at)`, sin softDeletes)
- [X] T005 Migración `database/migrations/2026_10_03_100100_create_caja_movimientos_table.php` (`caja_movimientos`: tipo enum, importe, motivo varchar(160), usuario_id, FK a sesión `restrictOnDelete`, índice tenant)
- [X] T006 Migración `database/migrations/2026_10_03_100200_add_caja_sesion_id_to_ticket_pagos.php`: columna `caja_sesion_id` nullable + FK `nullOnDelete` + índice `(tenant_id, caja_sesion_id)`; `down()` solo elimina esa columna. Ejecutar con `php artisan migrate` (NUNCA `migrate:fresh`, CLAUDE.md)
- [X] T007 [P] Modelo `app/Models/CajaSesion.php` (`BelongsToTenant`, `HasFactory`, casts decimal/json/datetime, constantes de estado, relaciones `abiertaPor`, `cerradaPor`, `movimientos`, `ticketPagos`; scope `abierta()`; eventos `updating`/`deleting` que lanzan excepción si `getOriginal('estado') === 'cerrada'` — invariante C2) + factory `database/factories/CajaSesionFactory.php` que **fuerza el `tenant_id` del tenant activo** (memoria `project_factory_tenant_id_pitfall`)
- [X] T008 [P] Modelo `app/Models/CajaMovimiento.php` (`BelongsToTenant`, sin update/delete: eventos que lanzan excepción) + factory `database/factories/CajaMovimientoFactory.php` con el mismo cuidado de `tenant_id`
- [X] T009 [P] Añadir `caja_sesion_id` a `$fillable` y relación `cajaSesion()` en `app/Models/TicketPago.php`
- [X] T010 [P] Excepciones `app/Exceptions/CajaCerradaException.php`, `app/Exceptions/CajaYaAbiertaException.php` (lleva la sesión existente), `app/Exceptions/ObservacionRequeridaException.php` (lleva el resultado provisional) y `app/Exceptions/CajaYaCerradaException.php` (lleva la sesión cerrada) con mensajes de contracts/rutas.md
- [X] T011 Permiso y menú: `ver-pos-caja` ("Caja", módulo POS) en `app/Support/CatalogoPermisos.php`; entrada `pos-caja` ("Caja", ruta `pos.caja`, permiso `ver-pos-caja`, sin `modulo` de hostelería) en el grupo POS de `app/Support/CatalogoMenu.php` justo después de "Crear ticket"; actualizar los conteos de `tests/Unit/CatalogoPermisosTest.php` (`claves()` 34→35, `clavesUsuarioBase()` 24→25 porque el permiso no se excluye del rol base, y el conteo del módulo POS si se asierta) y de `tests/Feature/Configuracion/CatalogoMenuTest.php` (39→40 elementos, 29→30 entradas; grupos sin cambio) (docs/04 "Nueva entrada de menú ⇒ nuevo permiso", pasos 1 y 3)
- [X] T012 `Gate::define('abrir-caja', ...)` (`ver-pos-caja` || `ver-pos-crear`) en `app/Providers/AppServiceProvider.php`, junto al `Gate::before` existente
- [X] T013 Rutas en `routes/web.php` según contracts/rutas.md: grupo `can:ver-pos-caja` con `GET /pos/caja` (`pos.caja`), `POST /pos/caja/movimientos`, `POST /pos/caja/cerrar`, `GET /pos/caja/cierres`, `GET /pos/caja/sesiones/{sesion}/informe`; y `POST /pos/caja/abrir` con `can:abrir-caja`. Declararlas **antes** de `GET /pos/{factura}/pdf` para que `/pos/caja` no se resuelva como factura. Reflejarlas en `RutasPermisosTest::mapaRutas()` (`tests/Feature/RutasPermisosTest.php`), (solo las rutas GET sin parámetro, `pos.caja` y `pos.caja.cierres`, que es lo que ese mapa cubre); `pos.caja.abrir` se cubre en T016 por el gate. Crear en este mismo paso los controladores vacíos `app/Http/Controllers/Pos/CajaController.php` (`index`, `abrir`, `movimiento`, `cerrar`) y `app/Http/Controllers/Pos/CajaCierreController.php` (`index`, `informe`) con `abort(501)` en cada método, para que `route:list` y la suite no rompan antes de implementarlos
- [X] T014 [P] Trait de tests `tests/Concerns/ConCajaAbierta.php`: hook `setUpConCajaAbierta()` que abre una caja (fondo 0) para **cada tenant creado durante el test** (`Tenant::created`), más `abrirCaja(Tenant $tenant, $fondo = 0, ?User $por = null): CajaSesion` para los tests de caja que parten de caja cerrada. Los tests existentes crean el tenant en helpers propios, no en `setUp`, así que el hook evita tocar cada helper
- [X] T015 [P] Umbral de descuadre: constantes `CLAVE_CAJA_UMBRAL_DESCUADRE = 'pos.caja_umbral_descuadre'` y `DEFAULT_CAJA_UMBRAL_DESCUADRE = 5.00`, getter `cajaUmbralDescuadre(int $tenantId): float` **no** condicionado a `hosteleriaActivo`, inclusión en `todo()` y en `guardar()` (tipo `decimal`) en `app/Support/ConfigPos.php`. Sin fila en `ConfiguracionSeeder` (no siembra ninguna clave `pos.*`: la ausencia de la fila equivale al default, mismo patrón que el resto de `ConfigPos`)

**Checkpoint**: esquema, modelos, permiso y rutas registradas con controladores stub (T013); la suite existente sigue en verde.

---

## Phase 3: User Story 1 — Abrir la caja (P1) 🎯 MVP

**Goal**: abrir caja con fondo (importe o denominaciones); cobrar en el POS exige caja abierta y cada ticket queda atribuido a la sesión.

**Independent Test**: abrir caja con 150,00 € → estado "abierta" con fondo, usuario y hora; un ticket emitido después tiene sus `ticket_pagos` con esa `caja_sesion_id`; sin caja, cobrar devuelve 409 `caja_cerrada`.

### Tests (test-first: escribir, ver fallar, luego implementar)

- [X] T016 [P] [US1] (test-first) `tests/Feature/Caja/AperturaCajaTest.php`: abre con importe; abre con conteo y el fondo sale del servidor ignorando un total enviado; fondo 0 válido; negativo/clave inválida → 422; segunda apertura → 409 `caja_ya_abierta` con la sesión existente y sigue habiendo 1 sola fila abierta; un usuario con solo `ver-pos-crear` puede abrir (gate `abrir-caja`); sin ninguno de los dos permisos → 403
- [X] T017 [P] [US1] (test-first) `tests/Feature/Caja/CobrarSinCajaTest.php`: `POST /pos` sin caja → 409 `codigo: caja_cerrada` y no se crea factura ni se consume numeración de la serie S; con caja abierta → 201 y todos los `ticket_pagos` del ticket (incluido pago dividido) llevan `caja_sesion_id`; `POST /pos/cuentas/{cuenta}/cobrar` sin caja → 409 y la cuenta queda intacta (usar `Tests\Concerns\MontaSalaPos`)
- [X] T018 [P] [US1] (test-first) `tests/Feature/Caja/CajaTenantIsolationTest.php` (parte apertura/atribución): con caja abierta en tenant A y ninguna en B, un ticket de B → 409 y nunca se atribuye a la sesión de A; A y B pueden tener cada uno su sesión abierta a la vez (el UNIQUE es por tenant)

### Implementación

- [X] T019 [US1] Servicio `app/Services/AperturaCaja.php`: `abrir(User $usuario, ?string $fondo, ?array $conteo): CajaSesion` en transacción; si llega conteo, `fondo = DenominacionesEuro::totalDesde()`; crea con `abierta_marca = 1`; traduce la violación del índice único (`QueryException` 23000) y la existencia previa a `CajaYaAbiertaException`
- [X] T020 [US1] Modificar `app/Services/RegistroTicket.php`: dentro de la transacción y **antes** de crear la factura, `CajaSesion::abierta()->lockForUpdate()->first()`; si es null → `throw new CajaCerradaException`; pasar el id al crear cada `ticket_pagos` (`'caja_sesion_id' => $sesion->id`). Actualizar el docblock de la clase
- [X] T021 [US1] Capturar `CajaCerradaException` → 409 `{message, codigo: 'caja_cerrada'}` en `PosController@store` (`app/Http/Controllers/PosController.php`) y en `CuentaController@cobrar` (`app/Http/Controllers/Pos/CuentaController.php`, catch de la línea ~206), junto a las excepciones ya capturadas; en el camino no-JSON, `redirect()->back()->with('error', ...)`
- [X] T022 [US1] Añadir `use ConCajaAbierta;` (el hook abre caja a cada tenant creado) a cada test existente que emite tickets, **sin tocar aserciones**: `tests/Feature/PosTicketEmisionTest.php`, `PosPagoDivididoTest.php`, `PosTicketRegimenTest.php`, `PosTopeSimplificadaTest.php`, `PosListadoSeparadoTest.php`, `PosTenantIsolationTest.php` (abrir en cada tenant que emita), `TicketStockTest.php`, `RegistroVerifactuTest.php`, `Cliente/PerfilClienteTest.php`, `Pos/CobroParcialEncadenamientoVerifactuTest.php`, `Pos/CobroParcialLimitesTest.php`, `Pos/CuentaNoEsTicketTest.php`, `Pos/PosApagadoNoCambiaNadaTest.php`, y cualquier otro que `grep -rln "pos.store\|RegistroTicket\|/cobrar" tests` devuelva. Si un test necesitara algo más que abrir caja, parar y revisar (research D3)
- [X] T023 [US1] Form request `app/Http/Requests/AbrirCajaRequest.php` (exactamente uno de `fondo_inicial` numeric 0–99999.99 o `conteo` array con claves válidas de `DenominacionesEuro` y cantidades int 0–9999) y controlador `app/Http/Controllers/Pos/CajaController.php` con `abrir()` (201/409 según contracts/rutas.md) e `index()` mínimo: HTML de la pantalla con el estado cerrada/abierta y JSON con `abierta`, `sesion` (fechas con `enZonaTenant()`, `abierta_dia_anterior` comparando el día local de apertura con hoy en zona del tenant) y `ultimo_cierre`; **sin** `efectivo_esperado` (research D6)
- [X] T024 [P] [US1] Extraer la regla de tecleo compartida de `public/js/pos-cobro.js` (`aplicarTecla()` y helpers de formato de importe) a `public/js/pos-teclado.js` como `window.PosTeclado` (`aplicarTecla(str, key, prellenado)` decimal, `aplicarTeclaEntera(str, key)` para cantidades, `formatear(str)`); `pos-cobro.js` pasa a usarlo **a comportamiento constante** (teclado de importe y de "Entregado" iguales que antes). Cargar `pos-teclado.js` antes de `pos-cobro.js` en `resources/views/pos/create.blade.php` con `@assetv`
- [X] T025 [US1] Partial `resources/views/pos/_caja-apertura.blade.php`: panel de fondo inicial con display grande `readonly inputmode="none"`, teclado `.pos-keypad-grid`/`.pos-key`, conmutador `.filtro-segmentado` "Importe total | Billetes y monedas" (el segundo incluye `pos._caja-bandeja`), botones Cancelar y "Abrir caja con X €" (el texto repite el importe en vivo). Reutilizable en la pantalla de caja y en el modal del TPV
- [X] T026 [US1] Partial `resources/views/pos/_caja-bandeja.blade.php` — **elemento firma** (research D11, contracts/ui-caja.md paso 1): fichas de billete apaisadas con tinte del color real y franja saturada; fichas de moneda circulares con gradiente de metal (cobre / oro nórdico / bimetálica con anillo); cada ficha con `data-centimos`, valor, píldora `×n` y subtotal; generadas iterando `DenominacionesEuro`; con el teclado entero propio a la derecha y el total en vivo (`tabular-nums`). Sin ningún lugar donde pintar el esperado
- [X] T027 [US1] Vista `resources/views/pos/caja.blade.php` con los contenedores de los tres estados (A cerrada, B abierta, C cierre) — en esta historia, estado A completo (hero "La caja está cerrada", CTA 72px, bloque "Último cierre" con Ver informe/Historial ocultos si no hay cierres) y barra de estado del B (punto verde, "desde HH:MM (Xh Ymin)", usuario, fondo; ámbar si `abierta_dia_anterior`); `@section('ayuda-titulo','Caja')` + `@section('ayuda') @include('ayuda.pos-caja')`; CSS en `public/css/pos-caja.css` (tokens `--caja-*` de D11, heredando `--pos-money` y primario)
- [X] T028 [US1] JS `public/js/plugins-init/pos-caja.init.js`: estado de la pantalla, panel de apertura (teclado decimal vía `PosTeclado`, bandeja con selección de ficha, doble toque suma 1, tecla C pone a cero), POST a `pos.caja.abrir` con `withButtonLoading` + CSRF, en 201 pasar al estado B sin recargar, en 409 `caja_ya_abierta` mostrar la sesión existente
- [X] T029 [US1] Integración en el TPV: `PosController@create` pasa `cajaAbierta` y `puedeAbrirCaja` (`Gate::allows('abrir-caja')`); `resources/views/pos/create.blade.php` añade el chip de estado de caja en la cabecera del ticket y un modal centrado "Abrir caja" que incluye `pos._caja-apertura` (o el texto "Pide a un responsable que la abra" sin permiso); `public/js/pos-cobro.js` intercepta Cobrar con caja cerrada → modal de apertura → tras abrir, sigue al cobro; ante 409 `caja_cerrada` al emitir, no vacía el ticket y abre el mismo modal; lo mismo en el cobro de cuentas, que también vive en `public/js/pos-cobro.js` (`cobrarCuenta()`, `fetch('/pos/cuentas/{id}/cobrar')`): el 409 `caja_cerrada` no debe limpiar la cuenta. Reutilizar la lógica de apertura de `pos-caja.init.js` exponiéndola como `window.PosCajaApertura` en un archivo compartido `public/js/pos-caja-apertura.js` (cargado en ambas vistas). *Hecho así:* la bandeja es su propio componente (`pos-caja-bandeja.js`), y la integración en el TPV un módulo del orquestador (`pos-caja-tpv.js`, `PosApp.registrar('caja')`)

**Checkpoint**: MVP de apertura funcional; toda la suite existente en verde con la caja abierta en setUp.

---

## Phase 4: User Story 2 — Cerrar la caja con arqueo e informe Z (P1)

**Goal**: cerrar con conteo ciego, calcular esperado/descuadre en servidor, congelar el informe y verlo/imprimirlo en 80 mm o A4.

**Independent Test**: escenario de spec US2 (fondo 100, tickets 40 efectivo / 25 tarjeta / 10+20 dividido, contado 148) → esperado 150, falta 2, total 95, efectivo 50, tarjeta 45, 3 tickets.

### Tests (test-first)

- [X] T030 [P] [US2] (test-first) `tests/Feature/Caja/ResumenCajaTest.php`: el escenario de la spec al céntimo; un ticket anulado de la sesión no suma a total ni a efectivo y aparece en `anulados`; tickets sin sesión (`caja_sesion_id` null) o de otra sesión no cuentan; los cuatro métodos siempre presentes; desglose por impuesto agrupado por `(tipo_impuesto, porcentaje)`; con un tenant IGIC el desglose dice `igic`; primer/último número de ticket
- [X] T031 [P] [US2] (test-first) `tests/Feature/Caja/CierreCajaTest.php`: cierra con conteo y el contado sale del mapa (ignora totales del cliente); cierra con `efectivo_contado`; `estado` cuadra/sobra/falta; |descuadre| > umbral sin observación → 422 `observacion_requerida` con resultado provisional y la sesión sigue abierta; con observación → cierra; umbral configurado distinto al default respetado; `sesion_id` que ya no es la abierta → 409 `caja_ya_cerrada`; sin caja → 409 `caja_cerrada`; tras cerrar, `abierta_marca` es null y se puede abrir una nueva; una sesión cerrada no admite `update()` ni `delete()` (C2); una sesión sin ventas se cierra igual (0 tickets, arqueo contra fondo y movimientos); el esperado puede quedar negativo y se guarda tal cual; un ticket emitido justo antes de confirmar el cierre entra en el cierre (el cálculo es al confirmar, no al abrir la pantalla de conteo); anular después un ticket de la sesión no cambia `total_facturado` ni `resumen` (SC-005); la respuesta JSON del estado (`GET /pos/caja`) con la caja abierta no contiene `efectivo_esperado` (D6)
- [X] T032 [P] [US2] (test-first) Ampliar `tests/Feature/Caja/CajaTenantIsolationTest.php`: B no puede cerrar la sesión de A (409 `caja_cerrada` en B, A sigue abierta); el informe PDF de una sesión de A pedido desde B → 404; el resumen de A no incluye tickets de B
- [X] T033 [P] [US2] `tests/Feature/Caja/CajaInformeTest.php`: informe 80 mm y A4 de una sesión cerrada → 200 `application/pdf`; sesión abierta → 404

### Implementación

- [X] T034 [US2] Servicio `app/Services/ResumenCaja.php`: `calcular(CajaSesion $sesion): array` (array con la forma exacta de `resumen` de data-model.md más las cifras planas; forma documentada con `@return array{...}` en el docblock, sin clase DTO) con agregados SQL sobre `ticket_pagos` ⋈ `facturas` (no anuladas) por método, `COUNT(DISTINCT factura_id)`, `SUM(facturas.total)`, `factura_impuestos` agrupados, anulados, primer/último `numero_completo` ordenando por `facturas.id` (no por `numero`: la serie S reinicia cada año y una sesión puede cruzar el 31/12), movimientos con nombre de usuario, entradas/salidas, y `efectivo_esperado` (C3). Todo en céntimos enteros internamente, salida en strings con 2 decimales
- [X] T035 [US2] Servicio `app/Services/CierreCaja.php`: `cerrar(User $u, int $sesionId, ?array $conteo, ?string $contado, ?string $observacion): CajaSesion` en transacción con `lockForUpdate` de la sesión abierta; si no hay → `CajaCerradaException`; si la abierta no es `$sesionId` o `$sesionId` ya está cerrada → `CajaYaCerradaException`; calcula `ResumenCaja`, contado (desde el conteo si llega), descuadre, aplica umbral (`ConfigPos::cajaUmbralDescuadre`) → `ObservacionRequeridaException` con el resultado provisional (sin escribir nada); si pasa, escribe cifras congeladas + `resumen` + `conteo_cierre`, `estado = cerrada`, `abierta_marca = null`, `cerrada_por/at`, en un solo `save()`
- [X] T036 [US2] Form request `app/Http/Requests/CerrarCajaRequest.php` (`sesion_id` requerido; `conteo` o `efectivo_contado` — si llegan ambos manda el conteo; `observacion` nullable ≤ 1000) y `CajaController@cerrar` con las respuestas 200/409/422 de contracts/rutas.md (incluye `informe` y las dos `informe_url_*`)
- [X] T037 [P] [US2] Plantillas PDF: `resources/views/caja/_informe-contenido.blade.php` (contenido común), `resources/views/caja/informe-80mm.blade.php` (rollo 226.77 pt, monoespaciada DejaVu Sans Mono, alto variable como `facturas/ticket-80mm`) y `resources/views/caja/informe-a4.blade.php`; datos **siempre** desde lo congelado de la sesión (FR-017), importes con `Formato::moneda()`, fechas en zona del tenant, nombre fiscal del tenant en cabecera. Contenido: FR-015 completo
- [X] T038 [US2] Controlador `app/Http/Controllers/Pos/CajaCierreController.php` con `informe(Request, string $sesion)`: resolución manual `CajaSesion::where('estado','cerrada')->findOrFail()`, `formato=ticket|a4`, `stream()` con nombre `cierre-caja-<id>.pdf`
- [X] T039 [US2] UI estado C en `resources/views/pos/caja.blade.php`: indicador de pasos "1 Contar · 2 Resultado"; paso 1 con la bandeja (`pos._caja-bandeja`) + conmutador a "Importe total" + "Volver" + "Confirmar conteo"; paso 2 con el revelado (esperado → contado → diferencia, veredicto con icono+texto+color), campo obligatorio "¿Qué pasó?" + "Cerrar caja con esta diferencia" + "Volver a contar" para el caso provisional, acciones "Imprimir 80 mm" / "Ver en A4" / "Volver a la caja", y la **tira de papel térmico** (informe en HTML monoespaciado, fondo `--caja-papel`, borde inferior dentado con `mask`). Modal estándar de vista previa PDF (`modal-xl`, iframe, 80vh, `p-0`, reset de `src` en `hidden.bs.modal`)
- [X] T040 [US2] JS del cierre en `public/js/plugins-init/pos-caja.init.js`: entrar/salir del estado C, conteo con `PosTeclado.aplicarTeclaEntera`, persistencia del conteo en `sessionStorage` (`caja-conteo:<sesion_id>`, try/catch, se borra al cerrar), POST `pos.caja.cerrar` con `withButtonLoading`; 200 → revelado (secuencia D11, `cubic-bezier(.23,1,.32,1)`, 60 ms de escalonado, veredicto último, solo fundido con reduced-motion) y pintado del papel; 422 `observacion_requerida` → revelado provisional + campo; 409 → toast con el mensaje del servidor y, si trae informe, ofrecerlo; "Volver a la caja" → estado A con el último cierre actualizado
- [X] T041 [US2] Botón "Cerrar caja" en la columna derecha del estado B (sticky, anclado abajo, `--bs-btn-*`) que entra al estado C — en esta historia basta con el botón y la barra de estado; el resto del estado B llega en US3/US4

**Checkpoint**: abrir → vender → cerrar → informe Z imprimible: MVP completo (US1 + US2).

---

## Phase 5: User Story 3 — Entradas y salidas de efectivo (P2)

**Goal**: registrar movimientos manuales que el arqueo tiene en cuenta.

**Independent Test**: fondo 100, salida 30 + entrada 50, sin ventas, cerrar contando 120 → cuadra.

- [X] T042 [P] [US3] (test-first) `tests/Feature/Caja/MovimientosCajaTest.php`: alta de entrada y salida con usuario y hora; importe 0/negativo o sin motivo → 422; sin caja → 409 `caja_cerrada`; el cierre del escenario cuadra; un movimiento no se puede editar ni borrar (modelo lanza excepción); un movimiento de otro tenant no aparece en el resumen
- [X] T043 [US3] Servicio `app/Services/MovimientosCaja.php` (`registrar(User, string $tipo, string $importe, string $motivo): CajaMovimiento` contra la sesión abierta con `lockForUpdate`, o `CajaCerradaException`), form request `app/Http/Requests/MovimientoCajaRequest.php` y `CajaController@movimiento` (201 con `movimiento` + `en_vivo`, 409, 422)
- [X] T044 [US3] UI: tiles "+ Entrada" / "− Salida" en la columna derecha del estado B y modal centrado de movimiento en `resources/views/pos/caja.blade.php` (tipo preseleccionado, importe con teclado propio superpuesto dentro del modal, motivo con 4 chips "Pago a proveedor", "Retirada a caja fuerte", "Cambio", "Otro" que rellenan el texto editable), lista "Movimientos del turno" (hora, signo, importe, motivo); JS en `public/js/plugins-init/pos-caja.init.js` con `withButtonLoading`, toast con el mensaje del servidor ("Salida registrada.") y repintado de la lista

---

## Phase 6: User Story 4 — Seguir la caja en vivo (P2)

**Goal**: informe X en la pantalla de caja.

**Independent Test**: con caja abierta, emitir un ticket y al volver/actualizar la caja reflejan total y nº de tickets.

- [X] T045 [P] [US4] (test-first) Ampliar `tests/Feature/Caja/ResumenCajaTest.php`: `GET /pos/caja` JSON con caja abierta devuelve `en_vivo` (num_tickets, total_vendido, ticket_medio, por_metodo, movimientos) coherente con `ResumenCaja` y sin `efectivo_esperado`
- [X] T046 [US4] `CajaController@index` completa `en_vivo` desde `ResumenCaja` (sin exponer `efectivo_esperado`), y el HTML inicial lo renderiza en servidor
- [X] T047 [US4] UI estado B en `resources/views/pos/caja.blade.php`: fila de 3 cards `[data-metric]` (Vendido en `--pos-money`, Tickets, Ticket medio — importes escritos directo, solo el entero anima), bloque "Cómo te pagaron" con barra apilada por método (colores fijos de contracts/ui-caja.md) + lista con texto, botón "Actualizar" discreto; JS: recarga del resumen cada 60 s solo con la pestaña visible (`visibilitychange`) y tras cada movimiento

---

## Phase 7: User Story 5 — Histórico de cierres (P3)

**Goal**: listado de cierres con acceso al informe.

**Independent Test**: tras dos cierres, ambos aparecen con sus cifras y "Ver informe" abre el PDF en modal.

- [X] T048 [P] [US5] (test-first) `tests/Feature/Caja/CajaPermisosTest.php` + histórico: `GET /pos/caja/cierres` JSON lista solo sesiones cerradas del tenant, recientes primero, con `estado` y `informe_url_*`; sin `ver-pos-caja` → 403 en todas las rutas del grupo; la entrada "Caja" del menú solo aparece con el permiso
- [X] T049 [US5] `CajaCierreController@index` (HTML + JSON client-side según contracts/rutas.md, con resumen del mes para las cards: nº de cierres, facturado, descuadre acumulado)
- [X] T050 [US5] Vista `resources/views/pos/caja-cierres.blade.php`: 3 cards `[data-metric]` (descuadre con `text-danger`/`text-success` según signo), DataTable `#cierres-table` (`display responsive nowrap w-100`, thead + tbody vacío) con override de paginación "Anterior/Siguiente", columnas de contracts/ui-caja.md, badge `.badge.light` success/warning/danger con texto, dropdown "Acciones" (Ver informe 80 mm / A4), modal de vista previa PDF; ayuda `@include('ayuda.pos-caja-cierres')`; JS `public/js/plugins-init/pos-caja-cierres.init.js` (`ajax.url` fija + `dataSrc: 'data'`, nunca función en `ajax.url`)
- [X] T051 [US5] Enlaces "Historial" y "Ver informe" del bloque "Último cierre" del estado A en `resources/views/pos/caja.blade.php`

---

## Phase 8: Polish, configuración y documentación (4 capas, CLAUDE.md)

- [X] T052 [P] Campo "Diferencia máxima sin explicación (€)" en `resources/views/configuracion/_tab_pos.blade.php` (fuera del bloque de hostelería: la caja aplica a todo POS), validación en `app/Http/Controllers/Configuracion/PosConfiguracionController.php` (numeric 0–9999.99) y en `public/js/plugins-init/configuracion-pos.init.js` (el submit arma el `data` campo a campo: añadir `caja_umbral_descuadre`); test en `tests/Feature/Caja/CierreCajaTest.php` (`PUT /configuracion/pos` guarda el umbral y el siguiente cierre lo respeta; valor fuera de rango → 422)
- [X] T053 [P] Guía in-app `resources/views/ayuda/pos-caja.blade.php` (abrir, movimientos, contar, qué significa el resultado; nota: el conteo es a ciegas a propósito; un ticket anulado después de cerrar no cambia ese cierre) y `resources/views/ayuda/pos-caja-cierres.blade.php`; actualizar `resources/views/ayuda/pos-crear.blade.php` (cobrar exige caja abierta y se abre desde ahí mismo) y añadir a `resources/views/ayuda/pos.blade.php` (hoy no menciona la caja) una `ayuda-nota` de una línea: el total del día y el arqueo se ven en POS → Caja. Formato de docs/04 "Ayuda contextual" (intro, `<ol>`, `ayuda-nota`)
- [X] T054 [P] Base de conocimiento del asistente: nuevo `resources/ia/conocimiento/pos-caja.md` (sesión, apertura, movimientos y cómo corregir uno con un movimiento inverso, arqueo ciego, informe Z, histórico, permiso, umbral) y actualizar `resources/ia/conocimiento/pos.md` (cobrar exige caja abierta; el desglose por métodos alimenta la caja). Verificar que `ConocimientoAsistente` lo ensambla sin tocar otros archivos (SC-007 de la 030)
- [X] T055 [P] `docs/03-modelo-datos.md`: sección "Caja del POS (feature 048)" con `caja_sesiones`, `caja_movimientos`, columna `ticket_pagos.caja_sesion_id`, invariantes C1–C4, justificación de conservación sin purga (research D10), clave `pos.caja_umbral_descuadre` en la tabla de claves; actualizar el párrafo de `ticket_pagos` ("ya no lo lee nadie más que la datatable" → también la caja) y el diagrama de relaciones
- [X] T056 [P] `docs/04-front-guidelines.md`: nuevas secciones reutilizables — "Bandeja de denominaciones (conteo de efectivo)" (fichas billete/moneda, doble toque, tecla C, conteo en `sessionStorage`), "Revelado tras una acción ciega" (no enviar al cliente la cifra oculta; secuencia corta con veredicto último), "Teclado táctil compartido `pos-teclado.js`" (actualizar la sección "Entrada numérica en pantallas táctiles", que hoy apunta a `aplicarTecla()` en `pos-cobro.js`) y "Bloqueo de una acción por estado del servidor con resolución inline" (409 + `codigo` → modal que resuelve y reintenta, sin perder lo armado)
- [X] T057 [P] `docs/00-vision.md`: mencionar el control de caja (apertura, arqueo, informe Z) en el alcance del POS
- [X] T058 `php artisan db:seed --class=PermisosSeeder` en local; anotar en el resumen de entrega que en producción hay que correrlo tras desplegar (skill `deploy-empiresass`) y que los roles personalizados reciben `ver-pos-caja` opt-in desde `/roles`
- [X] T059 Ejecutar `vendor/bin/pint` sobre los archivos PHP tocados y la suite completa `php artisan test`; todo en verde
- [ ] T060 Recorrer `specs/048-cierre-caja-pos/quickstart.md` (escenario principal + bordes) en local, cronometrando apertura (< 20 s, SC-001) y cierre con 8 denominaciones + impresión (< 2 min, SC-002), y comprobando en viewport 1280×800 que la acción principal de cada estado se ve sin scroll y sin teclado del sistema (SC-006). Verificación visual en navegador **solo con confirmación del usuario** y eligiendo herramienta según CLAUDE.md (Oculo para ver lo mismo que el usuario en la tablet; no mezclar motores)

---

## Dependencies & Execution Order

- **Setup (T001–T003)** → **Foundational (T004–T015)** → historias.
- **US1 (T016–T029)** es requisito de todas: introduce la regla "cobrar exige caja" que rompe la
  suite si no va acompañada de T022, y la pantalla base.
- **US2 (T030–T041)** depende de US1. Con US1 + US2 se tiene el MVP entregable.
- **US3 (T042–T044)** depende de US1; se integra en el cálculo de US2 (`ResumenCaja` ya suma
  movimientos si existen: T034 lee la tabla aunque US3 no esté hecha, con 0 movimientos).
- **US4 (T045–T047)** depende de US2 (`ResumenCaja`).
- **US5 (T048–T051)** depende de US2 (sesiones cerradas, PDF).
- **Polish (T052–T060)** al final; T055–T057 pueden hacerse en paralelo con US5.

Dentro de cada historia: tests test-first → servicios → controladores → vistas → JS.

## Parallel Opportunities

- T002 (test) antes que T003; ambos en paralelo con T001.
- T007–T010, T014, T015 en paralelo tras las migraciones.
- Tests test-first de cada historia (T016–T018; T030–T033) en paralelo entre sí.
- T024 (extracción de `pos-teclado.js`) en paralelo con T019–T023.
- T037 (plantillas PDF) en paralelo con T034–T036.
- T052–T057 en paralelo entre sí.

## Implementation Strategy

1. **MVP = US1 + US2**: abrir, vender (atribuido), cerrar con arqueo ciego, informe Z en 80 mm/A4.
   Es exactamente lo que pidió el cliente ("saber todo lo que facturan en el día").
2. **Incremento 2 = US3 + US4**: movimientos (para que el arqueo cuadre en la vida real) y el
   informe X en vivo.
3. **Incremento 3 = US5 + Polish**: histórico, configuración del umbral y las 4 capas de
   documentación. **No se da la feature por cerrada sin T053–T057** (CLAUDE.md "Documentación al
   día en TODO cambio").
