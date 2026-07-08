# Modelo de datos — facturación multi-tenant

> MySQL/MariaDB. Base compartida: **toda tabla de negocio lleva `tenant_id`** con índice y global scope.
> Convención: `id` BIGINT autoincrement, `timestamps` (created_at/updated_at), `softDeletes` donde aplique.
> Importes monetarios: `DECIMAL(12,2)`; porcentajes: `DECIMAL(5,2)`.

## Diagrama de relaciones (resumen)

```
tenants ──< users
tenants ──< clientes
tenants ──< cuentas_bancarias
bancos ──< cuentas_bancarias
tenants ──< series ──< facturas
tenants ──< facturas ──< factura_lineas
                      ├──< factura_impuestos   (desglose por tipo)
                      ├──< pagos                (010-pagos-facturas)
                      └──< factura_eventos      (log Verifactu)
clientes ──< facturas
cuentas_bancarias ──(opcional, solo forma_pago=transferencia)── facturas
facturas ──< facturas   (rectificativa → factura_rectificada_id)
articulos ──(opcional)── factura_lineas
articulos ──< movimientos_stock   (kardex, solo producto con stock)
facturas ──< movimientos_stock    (salida al emitir)
proveedores ──< compras ──< compra_lineas
compras ──< movimientos_stock     (entrada al confirmar)
tenants ──< carpetas ──< carpetas   (árbol autorreferenciado, gestor documental)
carpetas ──< archivos
users ──< archivos                (subido_por, opcional)
```

---

## Capa CENTRAL (global del SaaS)

### `tenants` — empresa cliente del SaaS
Datos fiscales del emisor + config de facturación.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| nombre_comercial | varchar | |
| razon_social | varchar | |
| nif | varchar(15) | NIF/CIF del emisor |
| direccion, cp, ciudad, provincia, pais | varchar | domicilio fiscal (país default 'ES') |
| email, telefono | varchar | |
| regimen_fiscal | enum | `general`, `autonomo`, `recargo_equivalencia`… |
| regimen_impositivo | enum | **`iva`** (Península/Baleares), `igic` (Canarias), `ipsi` (Ceuta/Melilla) — impuesto indirecto por defecto |
| irpf_defecto | decimal(5,2) | retención por defecto (ej. 15.00) |
| aplica_recargo_equivalencia | boolean | |
| logo_path | varchar, nullable | logo del menú expandido; sin configurar usa `public/images/image.png` |
| logo_mini_path | varchar, nullable | logo del menú comprimido; sin configurar usa `public/images/contracted.png` |
| login_logo_path | varchar, nullable | logo mostrado en la pantalla de login; sin configurar usa el logo/texto por defecto de la marca |
| login_imagen_path | varchar, nullable | imagen lateral de la pantalla de login; sin configurar usa `public/images/login.png` |
| logo_facturacion_path | varchar, nullable | logo mostrado en el PDF de factura; sin configurar usa `public/images/logardo.png` |
| favicon_path | varchar, nullable | favicon del panel (`<link rel="shortcut icon">`); sin configurar usa `public/images/fav.png` |
| verifactu_activo | boolean | activa el registro con huella/QR |
| activo | boolean | en `false` bloquea el login de todos sus usuarios |
| data | json, nullable | requerida por `stancl/tenancy` (virtual column); no se usa directamente |
| timestamps | | |

> Implementado hasta ahora: `nombre_comercial`, `razon_social`, `nif`, `email`, `activo`, `data`
> (feature 001-user-auth) + `direccion`, `cp`, `ciudad`, `provincia`, `pais` (domicilio fiscal del
> emisor, mismo patrón que `clientes`: selects encadenados provincia→ciudad contra
> `provincias`/`localidades`, `pais` texto libre con default `'ES'`; se muestra en la cabecera del
> emisor del PDF de factura). El resto de columnas fiscales (`regimen_fiscal`, `irpf_defecto`,
> `aplica_recargo_equivalencia`, `verifactu_activo`) las agrega la primera feature de facturación
> que las necesite.

### `users` (Laravel) — usuarios que operan un tenant
Se añade `tenant_id` (fk, nullable solo para `super_admin`) + `rol` (`super_admin`, `admin`,
`usuario`) + `activo` (boolean; en `false` bloquea el login). El `super_admin` gestiona todos los
tenants y no pertenece a ninguno (`tenant_id` null). Desde la feature 027, `rol` deja de ser la
fuente de verdad del acceso a vistas de un tenant (eso lo dan los roles dinámicos de spatie, ver
más abajo); se conserva solo para distinguir el `super_admin` central del resto.

Registro y aprobación: `estado` (enum `pendiente`/`aprobado`/`rechazado`, default `pendiente`) +
`aprobado_por` (fk nullable a `users`) + `aprobado_en` (timestamp nullable). El auto-registro
público crea la cuenta en `pendiente` con `activo=false`; cualquier usuario aprobado del mismo
tenant puede aprobar o rechazar desde `/usuarios`, lo que ajusta `activo` en consecuencia (tabla
de correspondencia estado↔activo en `specs/006-registro-usuarios/data-model.md`). Índice
`(tenant_id, estado)`.

Segunda vía de alta (sin flujo de aprobación): al crear un tenant, el super admin indica el
email y la contraseña de un administrador inicial en el mismo formulario; ese `User` se crea
junto con el tenant, ya con `rol=admin`, `estado=aprobado`, `activo=true` (evita el problema
huevo-y-gallina de un tenant nuevo sin nadie que apruebe al primer usuario). Ver
`specs/020-tenant-admin-inicial/data-model.md`.

> **Nota:** por ahora **no se modela el billing del SaaS** (planes, suscripciones, cobro a los tenants). Los precios son flexibles y se gestionan fuera del sistema. Ningún registro indica qué plan o suscripción tiene un tenant.

### `domains` (feature 007-super-admin-tenants) — dominio de cada tenant
Tabla estándar de `stancl/tenancy` (modelo `Domain::class`), grupo central. El host de la petición
se resuelve contra esta tabla para fijar el tenant activo (`docs/01-arquitectura.md`, Decisión 4).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| domain | varchar(255) | **único**; normalizado (minúsculas, sin esquema/path/barra final) |
| tenant_id | fk → tenants | `onDelete cascade` |
| timestamps | | |

Relación `Tenant hasMany Domain`, restringida a nivel de aplicación a **un** dominio por tenant
(helper `Tenant::dominio()`). El panel `super_admin` (`app/Http/Controllers/SuperAdmin/TenantController.php`)
gestiona `tenants` + su `domains` como una unidad (alta/edición transaccional).

### Roles y permisos (`spatie/laravel-permission`, feature 027)
Tablas publicadas por el paquete con la feature **teams** activada (`team_foreign_key =
tenant_id`), sin tablas propias. Detalle completo en `specs/027-roles-permisos-tenant/data-model.md`.

| Tabla | Alcance | Notas |
|-------|---------|-------|
| `permissions` | global (SIN `tenant_id`) | catálogo de ~17 claves (`ver-facturas`, `ver-clientes`…), una por sección del menú; etiqueta/módulo se resuelven en código (`App\Support\CatalogoPermisos`), no en BD |
| `roles` | por tenant (`tenant_id` FK, `cascadeOnDelete`) | nombre único **dentro** del tenant, repetible entre tenants; columna propia `es_defecto` (boolean) = rol asignado a altas públicas, uno por tenant |
| `model_has_roles` | por tenant | pivote usuario↔rol; un usuario tiene como máximo un rol (convención de la app, el esquema soporta varios) |
| `role_has_permissions` | — | pivote rol↔permiso |
| `model_has_permissions` | — | sin uso en esta feature (no hay permisos directos a usuario) |

El tenant activo (`SetTenantContext`) fija el team de spatie (`setPermissionsTeamId`); el
`super_admin` central pasa cualquier check vía `Gate::before` y no tiene roles. Cada alta de
tenant aprovisiona automáticamente el rol "Administrador" (catálogo completo) y "Usuario" (rol
por defecto, catálogo acotado) — `App\Support\ProvisionadorRoles`.

### `bancos` — catálogo de entidades bancarias (por tenant)
Catálogo **tenant-dependiente**: cada tenant gestiona su propia lista de bancos (mismo patrón que
`unidades`). Pasa por el global scope de tenant vía `BelongsToTenant`. El `BancoSeeder` solo siembra
el catálogo de partida (principales entidades que operan en España) para el **primer tenant**; el
resto de tenants parten vacíos y crean sus propios bancos desde la app.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk → tenants | global scope de tenant |
| nombre | varchar | ej. `BBVA`, `CaixaBank`, `Banco Santander`; único por `(tenant_id, nombre)` |
| timestamps | | |

> Se gestiona con el `<x-banco-select>`: un Select2 dinámico (cargado por AJAX desde `GET /bancos`)
> con botones inline de alta/edición/borrado (CRUD completo vía `bancos.store/update/destroy`,
> mismo patrón que `<x-unidad-select>`), usado al dar de alta o editar una `cuenta_bancaria` (ver
> Capa TENANT). El `banco_id` de una cuenta se valida acotado al tenant activo. Un banco no se
> puede borrar si alguna cuenta lo referencia (FK `RESTRICT`).

---

## Capa TENANT (negocio — todas con `tenant_id`)

### `clientes`
Receptor de las facturas.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| tipo | enum | `empresa`, `particular` |
| nombre / razon_social | varchar | |
| nif | varchar(15) | NIF/CIF/NIE; puede ir nulo en particular de simplificada |
| direccion, cp, ciudad, provincia, pais | varchar | pais default 'ES' |
| email, telefono | varchar | |
| aplica_recargo_equivalencia | boolean | minorista en recargo |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, nif)`, `(tenant_id, nombre)`.

### `series`
Series de numeración. Numeración correlativa sin huecos por serie.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| codigo | varchar(10) | prefijo, ej. `F`, `S`, `R` |
| tipo | enum | `ordinaria`, `simplificada`, `rectificativa` |
| ejercicio | year | opcional, si se resetea por año |
| proximo_numero | int unsigned | contador |
| formato | varchar | ej. `{serie}-{anio}-{numero:0000}` |
| activa | boolean | |
| timestamps | | |

Índice único: `(tenant_id, codigo, ejercicio)`.

### `cuentas_bancarias` — cuentas del tenant para cobrar por transferencia
Un tenant puede dar de alta una o varias cuentas bancarias propias desde la vista de
configuración (alta y baja lógica, sin tocar código). Al crear una factura con `forma_pago =
transferencia`, el usuario elige una de estas cuentas para mostrarla en el PDF.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| banco_id | fk → bancos | |
| alias | varchar | nombre identificativo para el usuario, ej. "Cuenta principal" |
| iban | varchar(34) | validado por formato (ISO 13616), no contra el banco real |
| titular | varchar | nombre/razón social del titular (normalmente el propio tenant) |
| activa | boolean | default `true`; controla si aparece en el selector de facturas nuevas |
| softDeletes, timestamps | | |

Índices: `(tenant_id, activa)`.

> **Baja lógica, no física:** eliminar una cuenta desde configuración es un soft delete /
> desactivación (`activa = false` + `deleted_at`), nunca un borrado físico — evita romper las
> facturas ya creadas que la referencian (ver snapshot en `facturas` más abajo).
>
> **Fuera de alcance:** IBAN/mandato SEPA del **cliente** para domiciliación bancaria (distinto
> caso de uso: cobrar de la cuenta del cliente, no mostrar la del tenant); cuenta bancaria por
> defecto/predeterminada; múltiples divisas por cuenta; conciliación o integración bancaria real.

### `facturas`
Cabecera de factura. **Núcleo del sistema.**

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| serie_id | fk → series | |
| numero | int unsigned | correlativo dentro de la serie |
| numero_completo | varchar | ej. `F-2026-0001` (desnormalizado para mostrar) |
| tipo | enum | `ordinaria`, `simplificada`, `rectificativa` |
| estado | enum | `borrador`, `emitida`, `pagada`, `vencida`, `anulada`, `rectificada` |
| cliente_id | fk → clientes | nullable en simplificada |
| cliente_nombre, cliente_razon_social, cliente_nif | varchar | **snapshot** del receptor, precargado desde `clientes` al elegirlo pero editable y propio de la factura (no se re-sincroniza si el cliente cambia después) |
| cliente_direccion, cliente_cp, cliente_ciudad, cliente_provincia, cliente_pais | varchar | ídem; domicilio del receptor congelado en la factura (pais default 'ES') |
| fecha_expedicion | date | |
| fecha_operacion | date | nullable si = expedición |
| fecha_vencimiento | date | nullable |
| forma_pago | enum | `transferencia`, `tarjeta`, `efectivo`, `domiciliacion`… |
| moneda | char(3) | default `EUR` |
| regimen_impositivo | enum | `iva` / `igic` / `ipsi` — **congelado al emitir** (copiado del tenant/cliente) |
| aplica_recargo | boolean | si la factura lleva recargo de equivalencia (copiado del cliente/tenant al emitir) |
| **Cuenta bancaria (solo si `forma_pago = transferencia`):** | | |
| cuenta_bancaria_id | fk → cuentas_bancarias | nullable; referencia informativa elegida al crear la factura |
| cuenta_bancaria_banco, cuenta_bancaria_iban, cuenta_bancaria_titular | varchar | **snapshot** de la cuenta elegida, precargado al crear la factura y congelado igual que los campos `cliente_*` — editar o dar de baja la cuenta en configuración no afecta a facturas ya creadas |
| **Totales (calculados desde líneas):** | | |
| base_total | decimal(12,2) | suma de bases |
| cuota_impuesto_total | decimal(12,2) | cuota del impuesto indirecto (IVA/IGIC/IPSI según régimen) |
| cuota_recargo_total | decimal(12,2) | recargo de equivalencia (solo IVA) |
| irpf_porcentaje | decimal(5,2) | nullable |
| irpf_cuota | decimal(12,2) | se **resta** del total |
| total | decimal(12,2) | base + impuesto + recargo − IRPF |
| notas / observaciones | text | |
| **Rectificativa:** | | |
| es_rectificativa | boolean | |
| factura_rectificada_id | fk → facturas | nullable |
| motivo_rectificacion | text | nullable |
| tipo_rectificacion | enum | `sustitucion`, `diferencias` |
| **Verifactu (RD 1007/2023):** | | |
| huella | varchar(64) | hash SHA-256 del registro |
| huella_anterior | varchar(64) | hash del registro anterior (encadenamiento) |
| qr_contenido | text | URL/datos de verificación AEAT |
| verifactu_estado | enum | `pendiente`, `registrada`, `enviada`, `error` |
| registro_xml | longtext / path | XML del registro (Orden HAC/1177/2024) |
| registrada_at | datetime | timestamp del registro inalterable |
| **Ciclo B2B (Ley Crea y Crece):** | | |
| estado_b2b | enum | nullable: `emitida`, `aceptada`, `rechazada`, `pagada` |
| estado_b2b_fecha | datetime | nullable |
| softDeletes, timestamps | | |

Índices: `(tenant_id, serie_id, numero)` único, `(tenant_id, cliente_id)`, `(tenant_id, estado)`, `(tenant_id, fecha_expedicion)`.

> **Regla de inmutabilidad:** una factura `emitida` no se edita ni se borra (Verifactu). Cualquier corrección se hace con una **rectificativa**. En `borrador` sí es editable.

> **Facturas simplificadas — implementado en la feature 012 (módulo POS):**
> el `tipo = simplificada` vive en su propio módulo **POS** (`pos.*`), separado del de facturas
> ordinarias pero sobre la misma tabla `facturas`. Decisiones tomadas:
> - **Serie propia "S"** (mismo patrón que "R"), numeración correlativa con reinicio anual
>   reutilizando `NumeradorFacturas`. **Corrección 2026-07-04:** "F"/"R"/"S" se siembran ahora las
>   tres automáticamente en `Tenant::booted()` (`Tenant::seriesPorDefecto()`) para todo tenant
>   nuevo; antes solo se creaba "F" ahí y "R"/"S" dependían de `SerieSeeder` (que solo corría una
>   vez para el tenant demo), dejando a cualquier tenant real sin esas series y provocando un
>   `ModelNotFoundException` al primer intento de rectificar o emitir un ticket. `SerieSeeder` sigue
>   existiendo como red de seguridad idempotente para datos anteriores a la corrección.
> - **Receptor opcional**: la variante simple deja `cliente_id` y el snapshot `cliente_*` vacíos; la
>   **cualificada** los completa (NIF+domicilio). Se hizo `facturas.cliente_id` nullable vía migración
>   `2026_07_04_120000_make_cliente_id_nullable_on_facturas` (el snapshot `cliente_*` ya lo era).
> - **Tope de importe con bloqueo duro**: 400 € por defecto, 3.000 € si el tenant marca el flag de
>   configuración `factura.simplificada_tope_ampliado` (`App\Support\TopeSimplificada`). Se valida en
>   backend en `App\Services\RegistroTicket` sobre el importe bruto (impuestos incl.).
> - **PDF** del ticket en 80 mm (`facturas/ticket-80mm.blade.php`) o A4 (reutiliza `facturas/pdf`),
>   elegible al ver/descargar (`pos.pdf?formato=ticket|a4`).
> - El `FacturaController::index` excluye las simplificadas; el listado POS sólo muestra `tipo = simplificada`.
>
> **Fuera de alcance (012):** la "rectificativa en formato simplificado" (la normativa permite
> simplificada sin tope cuando el motivo es rectificar) sigue sin soportarse — `simplificada` y
> `rectificativa` siguen siendo valores mutuamente excluyentes de `tipo`.

### `factura_lineas`
Detalle de conceptos.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | índice |
| articulo_id | fk → articulos | nullable (línea de catálogo o concepto libre) |
| concepto | varchar | descripción (siempre presente, aunque venga de catálogo) |
| unidad | varchar(20) | `ud`, `hora`, `kg`, `m2`, `servicio`… (copiada del artículo o libre) |
| cantidad | decimal(12,4) | |
| precio_unitario | decimal(12,4) | |
| descuento_porcentaje | decimal(5,2) | nullable |
| base | decimal(12,2) | cantidad × precio − descuento |
| tipo_impositivo | decimal(5,2) | % del impuesto indirecto según régimen (IVA 21/10/4/0 · IGIC 0/3/7/9,5/15/20 · IPSI propio) |
| cuota_impuesto | decimal(12,2) | |
| tipo_recargo | decimal(5,2) | recargo de equivalencia, solo IVA (5.2 / 1.4 / 0.5 / 0) |
| cuota_recargo | decimal(12,2) | |
| calificacion_operacion | enum | `S1` (sujeta, sin ISP · default), `S2` (sujeta con inversión del sujeto pasivo), `N1`/`N2` (no sujeta). Ver `02` §6. Requerido por Verifactu por desglose |
| causa_exencion | enum, nullable | `E1`–`E6` (art. LIVA de la exención); solo cuando la línea es exenta. Ver `02` §6 |
| mencion_legal | varchar(255), nullable | texto de la mención para el PDF (p. ej. "Inversión del sujeto pasivo, art. 84.Uno.2º LIVA"). Autogenerable desde `calificacion_operacion`/`causa_exencion` |
| orden | smallint | ordenación en el PDF |
| timestamps | | |

> **Menciones especiales / tipo de operación (`02` §6):** con **ISP** (`S2`) la `cuota_impuesto` es 0
> pero la operación **está sujeta** (no exenta). Con **exención** (`causa_exencion` E1–E6) la cuota
> también es 0 pero la calificación es de exención. Estos tres campos alimentan tanto el **desglose**
> (`factura_impuestos`) como el **XML de Verifactu** y la **mención legal del PDF**. Una factura puede
> mezclar líneas sujetas y exentas.

### `factura_impuestos`
**Desglose por tipo impositivo** (obligatorio: base imponible por cada tipo). Una fila por combinación de impuesto+tipo dentro de la factura.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | |
| tipo_impuesto | enum | `iva`, `igic`, `ipsi`, `recargo`, `irpf` |
| porcentaje | decimal(5,2) | |
| base_imponible | decimal(12,2) | base sujeta a ese tipo |
| cuota | decimal(12,2) | |

### `pagos` — implementada (feature 010-pagos-facturas)
Cobros aplicados a facturas **emitidas** (soporta pagos parciales). Sin pasarelas ni conciliación
bancaria (fuera de alcance).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | |
| fecha | date | |
| importe | decimal(12,2) | > 0 |
| metodo | enum | igual que forma_pago |
| referencia | varchar(100) | nullable |
| anulado_at | dateTime | nullable; `NULL` = vigente, con valor = anulado (soft, sin `deleted_at`) |
| timestamps | | |

El **estado de cobro** (`pendiente` / `parcial` / `cobrada`) y el **saldo pendiente** son
**derivados**, no columnas: se calculan en `Factura::estadoCobro()` / `saldoPendiente()` a partir de
la suma de `pagos` vigentes (`anulado_at IS NULL`), comparando en céntimos para evitar residuos de
redondeo. La columna `Factura::estado` (fiscal) no cambia al cobrarse.

**Rectificativas y cobro** (`Factura::totalCobrable()`): el importe cobrable depende de la
modalidad de rectificación. Por **sustitución**, la deuda vive en la rectificativa (que lleva el
importe completo corregido) y la original rectificada deja de ser cobrable. Por **diferencias**,
la original rectificada **sigue siendo el documento de cobro**: su importe cobrable pasa a ser el
neto (`total` de la original + `total` de la rectificativa emitida, que es el delta, típicamente
negativo). La rectificativa por diferencias nunca se cobra por sí misma — es un documento de
ajuste; su historial de cobros es consultable pero no admite registrar pagos (saldo ≤ 0). Si los
cobros previos superan el nuevo neto, el saldo queda negativo indicando devolución pendiente al
cliente (la devolución en sí no se modela aún).

### `factura_eventos`
**Registro de eventos** exigido por Verifactu (log inalterable de operaciones del SIF).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| factura_id | fk | nullable (eventos de sistema) |
| tipo_evento | varchar | `alta`, `anulacion`, `rectificacion`, `envio_aeat`, `envio_email`, `error`… |
| detalle | json | payload del evento |
| huella | varchar(64) | hash del evento; `null` en eventos que no participan del encadenamiento Verifactu (p. ej. `envio_email`) |
| ocurrido_at | datetime | |

**`tipo_evento = 'envio_email'`** (envío de factura por correo, feature `017-envio-facturas-email`):
`detalle` = `{ destinatario, resultado: 'ok'|'error', error? }`. Una factura se considera "enviada"
(`Factura::fueEnviada()`) si existe ≥1 evento de este tipo con `resultado = 'ok'`; el reenvío añade un
evento nuevo sin borrar los anteriores (log append-only, igual que el resto de `factura_eventos`).

### `articulos` — catálogo unificado (producto o servicio)
Catálogo del que se pueden traer líneas de factura. Un artículo puede ser un **producto** (bien físico, puede llevar stock) o un **servicio** (mano de obra, mantenimiento… nunca lleva stock). El catálogo es opcional: siempre se puede facturar un concepto libre sin artículo asociado.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| tipo | enum | `producto`, `servicio` |
| sku / codigo | varchar(50) | nullable, código interno/referencia |
| nombre | varchar | |
| descripcion | text | |
| unidad | varchar(20) | `ud`, `hora`, `kg`, `m2`, `servicio`… |
| categoria_id | fk → categorias_articulo | nullable; `nullOnDelete` (catálogo opcional del tenant) |
| precio | decimal(12,4) | precio unitario base |
| tipo_impositivo | decimal(5,2) | % del impuesto indirecto según régimen del tenant (IVA/IGIC/IPSI) |
| **Stock (solo `producto`):** | | |
| gestion_stock | boolean | si `false` o es servicio → no se controla stock |
| stock_actual | decimal(12,4) | nullable; solo si `gestion_stock` |
| stock_minimo | decimal(12,4) | nullable; umbral de alerta de reposición |
| aplica_recargo_equivalencia | boolean | minorista en recargo (mismo criterio que `clientes`) |
| activo | boolean | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, sku)`, `(tenant_id, tipo)`.

> **Adaptabilidad:** el `tipo servicio` cubre mantenimiento, horas de trabajo, consultoría, etc. — sin stock. El `tipo producto` con `gestion_stock=false` cubre productos que no querés inventariar. Solo `producto` + `gestion_stock=true` mueve inventario.

### `categorias_articulo` — catálogo de categorías (select dinámico)
Catálogo del tenant para clasificar artículos (tabla simple id + nombre, mismo patrón que
`unidades`). Alimenta el componente `<x-categoria-select>` (CRUD inline vía modal, ver
`docs/05-dynamic-select-component.md`). Pasa por el global scope de tenant vía `BelongsToTenant`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| nombre | varchar(60) | único por tenant |
| timestamps | | |

Índice: `unique(tenant_id, nombre)`. Al borrar una categoría, los artículos que la usaban quedan
con `categoria_id = null` (`nullOnDelete`).

### `movimientos_stock` — kardex de inventario (implementado)
Historial de entradas/salidas de stock. Solo aplica a artículos `producto` con `gestion_stock=true`. Da trazabilidad y permite recalcular/auditar el stock. Ledger append-only: el único punto de escritura es `App\Services\RegistroMovimientoStock`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| articulo_id | fk → articulos | índice |
| tipo | enum | `entrada`, `salida`, `ajuste` |
| cantidad | decimal(12,4) | positiva; el `tipo` marca el sentido |
| stock_resultante | decimal(12,4) | snapshot tras el movimiento (auditoría) |
| origen | enum | `factura`, `compra`, `ajuste_manual`, `inventario`, `devolucion` |
| factura_id | fk → facturas | nullable (si el movimiento viene de una venta) |
| compra_id | fk → compras | nullable (si el movimiento viene de una compra) |
| motivo | varchar | nullable |
| ocurrido_at | datetime | |
| timestamps | | |

Índice: `(tenant_id, articulo_id, ocurrido_at)`.

**Reglas de stock:**
- Al **emitir** una factura, cada línea con `articulo` producto+`gestion_stock` genera una **`salida`** y descuenta `stock_actual`.
- Al **anular/rectificar** una venta, se genera el movimiento inverso (`entrada`/`devolucion`).
- Compras/reposición y ajustes manuales generan `entrada`/`ajuste`.
- `stock_actual` en `articulos` es el valor vivo (rápido de leer); `movimientos_stock` es la fuente de verdad histórica.
- **Stock negativo permitido:** la emisión de una factura nunca se bloquea por falta de stock —
  `stock_resultante` puede quedar negativo (decisión explícita de producto). El listado de kardex
  (`/stock`) marca visualmente los artículos con `stock_actual` negativo como descuadre a corregir.

### `proveedores` — proveedores de compra (implementado)
Para trazar de quién se compra el stock. Estructura análoga a `clientes`.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| nombre / razon_social | varchar | |
| nif | varchar(15) | |
| direccion, cp, ciudad, provincia, pais | varchar | pais default 'ES' |
| email, telefono | varchar | |
| notas | text | |
| softDeletes, timestamps | | |

Índices: `(tenant_id, nif)`, `(tenant_id, nombre)`.

### `compras` — documento de compra / factura de proveedor (implementado)
Registra la factura recibida del proveedor. Al confirmarla, genera **entradas** de stock.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice |
| proveedor_id | fk → proveedores | |
| numero_documento | varchar | nº de factura del proveedor (externo) |
| fecha | date | fecha de la factura de compra |
| estado | enum | `borrador`, `confirmada`, `anulada` |
| base_total | decimal(12,2) | |
| cuota_impuesto_total | decimal(12,2) | IVA/IGIC/IPSI soportado según régimen |
| total | decimal(12,2) | |
| notas | text | |
| **Recepción electrónica (factura electrónica B2B — `02` §2):** | | |
| origen | enum | `manual` (default) / `facturae` (importada de un XML recibido) / `otro` |
| formato_recepcion | varchar(20), nullable | `facturae`, `ubl`, `cii`… cuando `origen != manual` |
| archivo_recibido_path | varchar, nullable | ruta del XML/documento electrónico recibido del proveedor (se conserva) |
| estado_b2b | enum, nullable | ciclo comercial del lado receptor: `recibida`, `aceptada`, `rechazada`, `pagada` |
| estado_b2b_fecha | datetime, nullable | fecha del último cambio de `estado_b2b` (reportable en 4 días hábiles) |
| softDeletes, timestamps | | |

Índices: `(tenant_id, proveedor_id)`, `(tenant_id, fecha)`, `(tenant_id, estado_b2b)`.

> **Recepción de facturas (Kit Digital + ley B2B):** cuando llega un **Facturae** de un proveedor, un
> **importador** lee el XML y crea la `compra` con `origen=facturae`, volcando cabecera y líneas y
> guardando el archivo en `archivo_recibido_path`. El `estado_b2b` permite **reportar** al emisor si
> la factura fue aceptada/rechazada/pagada dentro del plazo legal. La carga **manual** existente sigue
> igual (`origen=manual`); la recepción electrónica es un canal adicional, no la reemplaza.

### `compra_lineas` — (implementado)
Detalle de la compra. Igual que las líneas de factura, con `articulo_id` opcional.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | |
| compra_id | fk → compras | índice |
| articulo_id | fk → articulos | nullable |
| concepto | varchar | |
| unidad | varchar(20) | |
| cantidad | decimal(12,4) | |
| precio_unitario | decimal(12,4) | coste de compra |
| base | decimal(12,2) | |
| tipo_impositivo | decimal(5,2) | % IVA/IGIC/IPSI soportado |
| cuota_impuesto | decimal(12,2) | |

> **Flujo de stock en compras:** al **confirmar** una compra, cada línea con `articulo` producto+`gestion_stock` genera una **`entrada`** en `movimientos_stock` (origen `compra`, con `compra_id`) y suma a `stock_actual`. Al **anular** la compra, movimiento inverso.

### `carpetas` — árbol de organización del gestor documental (feature 019)
Nodo del árbol de carpetas del tenant, autorreferenciado. Sin permisos por carpeta ni versionado
(todos los usuarios aprobados del tenant ven y gestionan todo el espacio).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice; `BelongsToTenant` |
| parent_id | fk → carpetas | nullable = raíz |
| nombre | varchar(255) | único por `(tenant_id, parent_id)` sobre no borrados (FR-005) |
| softDeletes, timestamps | | |

Índice `(tenant_id, parent_id)`. Borrar una carpeta borra en cascada (subcarpetas + archivos,
registros soft-deleted + ficheros físicos eliminados) tras confirmación reforzada (FR-018). Mover
carpetas (cambiar `parent_id`, por drag&drop en el explorador o `PUT /archivos/carpetas/{id}`) está
soportado, validando que el destino no sea la propia carpeta ni uno de sus descendientes
(`Carpeta::descendientesIds()`, evita ciclos en el árbol).

### `archivos` — documentos del gestor documental (feature 019)
Documento subido por un usuario del tenant, opcionalmente dentro de una carpeta.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | índice; `BelongsToTenant` |
| carpeta_id | fk → carpetas | nullable = raíz |
| nombre | varchar(255) | nombre visible, renombrable sin tocar el fichero físico (FR-020) |
| nombre_original | varchar(255) | nombre del fichero tal como se subió |
| ruta | varchar(255) | path en el disco privado `documentos`: `tenants/{tenant_id}/documentos/{uuid}.{ext}` |
| mime | varchar(150) | MIME real detectado en el servidor (no solo por extensión) |
| extension | varchar(20) | para ícono/preview |
| tamano | unsignedBigInteger | bytes |
| subido_por | fk → users | nullable, `nullOnDelete` |
| softDeletes, timestamps | | `created_at` = fecha de subida |

Índice `(tenant_id, carpeta_id)`. El nombre visible **no** es único dentro de una carpeta (a
diferencia de `carpetas`). Lista blanca de tipos permitidos y límite de tamaño configurables
(`App\Support\ArchivosTenant`, ver clave `archivos.limite_mb` más abajo). Los binarios nunca se
sirven por URL pública: `archivos.descargar`/`archivos.preview` resuelven el modelo manualmente
bajo el tenant activo (ver Decisión 6 de `docs/01-arquitectura.md`).

### `configuraciones` — valores de configuración
Almacén clave-valor por tenant para parámetros ajustables sin tocar código (textos legales del pie de factura, IVA/IRPF por defecto, datos de contacto para el PDF, integraciones, etc.). Un valor puede ser global del SaaS si `tenant_id` es nulo.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | **nullable** → nulo = valor global del SaaS |
| clave | varchar(100) | ej. `factura.pie_legal`, `iva.defecto`, `serie.reset_anual` |
| valor | text | valor serializado |
| tipo | enum | `string`, `integer`, `boolean`, `decimal`, `json` (cómo castear `valor`) |
| grupo | varchar(50) | agrupación para la UI: `facturacion`, `verifactu`, `email`, `general`… |
| descripcion | varchar | etiqueta legible para la pantalla de ajustes |
| timestamps | | |

Índice único: `(tenant_id, clave)`.

**Resolución de valores:** buscar primero la clave con el `tenant_id` activo; si no existe, caer al valor global (`tenant_id` nulo) como default. Conviene cachear el conjunto por tenant.

**Ejemplos de claves:**

| clave | grupo | ejemplo de valor |
|-------|-------|------------------|
| `impuesto.regimen` | facturacion | `iva` / `igic` / `ipsi` |
| `impuesto.tipo_defecto` | facturacion | `21` (IVA) · `7` (IGIC) · según régimen |
| `irpf.defecto` | facturacion | `15` |
| `serie.reset_anual` | facturacion | `true` |
| `factura.pie_legal` | facturacion | texto de protección de datos / condiciones |
| `factura.dias_vencimiento` | facturacion | `30` |
| `factura.simplificada_tope_ampliado` | facturacion | `false` (tope 400 €) / `true` (sector con tope ampliado, 3.000 €). Default en `App\Support\TopeSimplificada` |
| `articulo.precio_incluye_iva` | facturacion | `false` (precios sin IVA) / `true` (IVA incluido, retail) |
| `verifactu.activo` | verifactu | `false` |
| `verifactu.entorno` | verifactu | `pruebas` / `produccion` |
| `logs.retencion_dias` | seguridad | `730` (2 años, referencia RD 1720/2007); plazo de retención del registro de accesos antes de purgar |
| `email.smtp_host` | email | `smtp.hostinger.com` (default en `EmailTenant::DEFAULT_SMTP_HOST`, `''`) |
| `email.smtp_port` | email | `465` (default en `EmailTenant::DEFAULT_SMTP_PORT`) |
| `email.smtp_encryption` | email | `ssl` / `tls` (default en `EmailTenant::DEFAULT_SMTP_ENCRYPTION`) |
| `email.smtp_usuario` | email | `facturas@empresa.com` (default en `EmailTenant::DEFAULT_SMTP_USUARIO`, `''`) |
| `email.smtp_password` | email | **cifrada** con `Crypt::encryptString`; nunca en claro ni devuelta al front (default `''`) |
| `email.remitente` | email | `facturas@empresa.com` (default en `EmailTenant::DEFAULT_REMITENTE`, `''`) |
| `email.remitente_nombre` | email | `Empresa Demo SL` (default en `EmailTenant::DEFAULT_REMITENTE_NOMBRE`, `''`) |
| `email.responder_a` | email | opcional; `Reply-To` del correo de factura (default en `EmailTenant::DEFAULT_RESPONDER_A`, `''`) |
| `apariencia.color_primario` | apariencia | `#1D69D6` (default en `AparienciaTenant::DEFAULT_PRIMARIO`) |
| `apariencia.color_secundario` | apariencia | `#1F2025` (default en `AparienciaTenant::DEFAULT_SECUNDARIO`) |
| `apariencia.color_topbar` | apariencia | `#FFFFFF` (default en `AparienciaTenant::DEFAULT_TOPBAR`) |
| `apariencia.facebook_url` | apariencia | `` (vacío, default en `AparienciaTenant::DEFAULT_FACEBOOK`) |
| `apariencia.instagram_url` | apariencia | `` (vacío, default en `AparienciaTenant::DEFAULT_INSTAGRAM`) |
| `apariencia.titulo_login` | apariencia | `Iniciar sesión` (default en `AparienciaTenant::DEFAULT_TITULO_LOGIN`) |
| `archivos.limite_mb` | archivos | `10` (default en `App\Support\ArchivosTenant::DEFAULT_LIMITE_MB`) — tamaño máximo por archivo subido en el gestor documental |
| `general.zona_horaria` | general | `Europe/Madrid` (default en `App\Support\ConfigTenant::DEFAULT_ZONA_HORARIA`) — zona horaria **global del tenant**; catálogo fijo de 3 zonas (Madrid / Canarias / Argentina) en `ConfigTenant::ZONAS_HORARIAS_DISPONIBLES`. Se edita en la tab **General** de configuración |
| `leads.retencion_dias` | crm | `1095` (3 años, default en `App\Support\ConfigCrm::DEFAULT_RETENCION_DIAS`) — plazo antes de purgar leads descartados/no convertidos (comando `leads:purgar`, RGPD) |
| `leads.asignacion_estrategia` | crm | `manual` / `round_robin` (default en `ConfigCrm::estrategiaAsignacion`) — estrategia de asignación de leads nuevos |
| `leads.asignacion_comerciales` | crm | `[]` (json de ids de `users`) — comerciales del reparto round-robin |
| `leads.asignacion_ultimo_indice` | crm | `0` — puntero interno del round-robin (`App\Services\AsignadorLeads`, bloqueo transaccional); no editable en UI |
| `presupuesto.dias_validez` | crm | `30` (default en `ConfigCrm::DEFAULT_DIAS_VALIDEZ_PRESUPUESTO`) — validez por defecto de un presupuesto nuevo |

> **Zona horaria (convención transversal).** Todos los timestamps se **guardan y calculan en UTC**
> (`config('app.timezone')`, Principio III: hora de servidor). `general.zona_horaria` **no** cambia
> esa fuente de verdad: solo controla en qué hora local se **muestran** los datetimes a las personas,
> y aplica a **toda** la aplicación de ese tenant (fichajes, logs de actividad, campañas, stock…),
> no solo a fichajes. Para convertir en la capa de presentación usar la macro Carbon
> `->enZonaTenant()` (registrada en `AppServiceProvider`, resuelve el tenant activo) o
> `ConfigTenant::paraMostrar($fecha, $tenantId)` fuera de contexto de tenant. **Solo aplicar a
> datetimes reales**: los campos `date` puros (p. ej. `fecha_expedicion` de una factura) no llevan
> hora y convertirlos desplazaría el día. Cuando el usuario **edita** una hora local (corrección de
> fichaje), el input se interpreta en la zona del tenant y se reconvierte a UTC antes de guardar.

> Alternativa/complemento: parámetros de facturación muy usados (IVA/IRPF por defecto, recargo) también viven como columnas en `tenants` para acceso directo; `configuraciones` cubre el resto de ajustes flexibles y los globales del SaaS.

#### Cómo agregar un nuevo elemento de configuración

Añadir una clave nueva a `configuraciones` implica siempre estos tres pasos, no solo el registro en base de datos:

1. **Alta en base de datos** — agregar la fila en `database/seeders/ConfiguracionSeeder.php` (clave,
   valor por defecto, `tipo`, `grupo`, `descripcion`), usando `firstOrCreate` por `(tenant_id, clave)`
   como ya hace el seeder para no pisar valores modificados por el usuario en re-seeds.
2. **Registrar el default en el catálogo centralizado** — toda clave de configuración con un valor
   por defecto debe quedar reflejada en la tabla de "Ejemplos de claves" de esta sección (arriba) o,
   si el grupo tiene lógica de resolución propia (colores de apariencia, feature flags, etc.), en la
   clase `Support` correspondiente (p. ej. `App\Support\AparienciaTenant`, que centraliza claves y
   defaults como constantes `CLAVE_*` / `DEFAULT_*`). Esta tabla/clase es la fuente de verdad de
   "qué configuraciones existen y cuál es su default" — **actualizarla es obligatorio al agregar una
   clave nueva**, no opcional.
3. **Input en la vista de configuración** — agregar el campo correspondiente (texto, color picker,
   select, switch, etc.) en la tab de `resources/views/configuracion/` que coincida con el `grupo` de
   la clave (p. ej. `grupo: apariencia` → `_tab_apariencia.blade.php`; si no existe tab para el grupo,
   crearla siguiendo el patrón de `index.blade.php`). Sin este paso el valor existe en BD pero el
   usuario no tiene forma de editarlo.

> **Excepción — valores tipo archivo ligados directo al tenant:** los logos/imágenes (`logo_path`,
> `logo_mini_path`, `login_logo_path`, `login_imagen_path`, `logo_facturacion_path`) NO usan la tabla
> `configuraciones`; son columnas propias de `tenants` (ver tabla arriba), porque son 1:1 con el
> tenant y no necesitan resolución tenant→global. El mismo criterio de "3 pasos" aplica igual: (1)
> columna nueva vía migración + `$fillable`/`getCustomColumns()` en `App\Models\Tenant`, (2) default
> documentado aquí (ruta a un asset de `public/images/`) y usado como fallback donde se renderiza
> (p. ej. `logo_facturacion_path` → `public/images/logardo.png` en `facturas/pdf.blade.php`), (3)
> input de archivo en la tab correspondiente, subida vía `ConfiguracionController::update()`. El
> logo de facturación vive en la tab **Facturación** (`_tab_facturacion.blade.php`), no en
> Apariencia/Marca, porque se usa específicamente en el PDF de factura — no en el resto de la marca
> visual del panel (menú, login).

Los tres pasos son parte del mismo cambio: no dar por cerrada una clave de configuración nueva sin
los tres.

### `plantillas_email` — plantillas reutilizables de email marketing (feature 018)
Asunto + cuerpo HTML reutilizables al crear campañas.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | indexado (`BelongsToTenant`) |
| titulo | varchar(150) | nombre interno |
| asunto | varchar(255) | |
| cuerpo | longtext | HTML |
| activa | boolean | default `true`; solo las activas se ofrecen al crear campaña |
| softDeletes | | eliminar = baja lógica; las campañas ya creadas conservan su copia |
| timestamps | | `updated_at` → "modificado" en el listado |

### `campanas` — campaña de email a clientes (feature 018)
Cabecera de una campaña. Envío síncrono por tandas desde el front (sin colas ni cron, Principio V).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | indexado (`BelongsToTenant`) |
| user_id | fk | nullable; autor (`nullOnDelete`) |
| plantilla_email_id | fk | nullable; plantilla de origen (`nullOnDelete`) |
| asunto | varchar(255) | copiado/editado, independiente de la plantilla |
| cuerpo | longtext | HTML final enviado |
| estado | enum | `borrador`, `en_curso`, `finalizada` |
| total_destinatarios / enviados / fallidos | int unsigned | contadores cache, recalculados desde `campana_destinatarios` |
| enviada_at | datetime | nullable; primera vez que se lanza el envío |
| timestamps | | |

### `campana_destinatarios` — resultado por destinatario (feature 018)
Una fila por cliente incluido; **fuente de verdad** del resultado del envío.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | fk | indexado (derivado de la campaña; se afirma el scope igual) |
| campana_id | fk | `cascadeOnDelete` |
| cliente_id | fk | `cascadeOnDelete` |
| email | varchar(255) | dirección usada (copiada del cliente; nullable si sin email) |
| estado | enum | `pendiente`, `enviado`, `fallido` |
| error | varchar(500) | nullable; motivo del fallo ("Sin email", excepción SMTP) |
| enviado_at | datetime | nullable; marca del intento con resultado ok |
| timestamps | | |

Único `(campana_id, cliente_id)` (dedup). Índice `(campana_id, estado)` (reintento de fallidos).
Reutiliza la infra SMTP del tenant (`TenantMailer`/`EmailTenant`, feature 017) vía el Mailable
`CampanaMail` (cuerpo HTML libre, sin adjunto).

### `logs_actividad` — historial de actividad + registro de accesos (feature 021)
**Auditoría general de uso** (quién hizo qué, sobre qué y cuándo): login/logout y altas/bajas/
modificaciones de clientes, artículos, facturas, configuración y usuarios. Distinta de
`factura_eventos` (huella/encadenamiento Verifactu, solo facturas) — ambas conviven, no se
sustituyen. Append-only: sin edición ni borrado desde la UI.

Además de la auditoría de negocio, esta tabla cumple la función de **registro de accesos** exigible
bajo **RGPD (Art. 32) + LOPDGDD** (referencia técnica: registro de accesos del RD 1720/2007). Para
ello incorpora IP de origen, agente de usuario y resultado del evento, y registra también los
**intentos de login fallidos** (ver `02`/normativa RGPD).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant`, igual patrón que `factura_eventos` |
| usuario_id | fk `users`, nullable | `nullOnDelete()`; **null en login fallido** cuando el email no corresponde a un usuario |
| usuario_nombre | varchar(150) | snapshot del nombre en el momento del evento; sobrevive al borrado del usuario. En login fallido guarda el **email intentado** |
| accion | varchar(20) | enum `login`, `logout`, `alta`, `baja`, `modificacion` |
| resultado | varchar(10) | enum `exito` / `fallo`, default `exito`. Distingue acceso **autorizado o denegado** (requisito del registro de accesos). `fallo` en intentos de login rechazados |
| ip_origen | varchar(45), nullable | IP de origen de la petición (soporta IPv6). Campo clave del registro de accesos |
| user_agent | varchar(255), nullable | navegador/dispositivo de origen (ayuda a investigar accesos indebidos) |
| entidad_tipo | varchar(20), nullable | enum `cliente`, `articulo`, `factura`, `configuracion`, `usuario`; `null` en login/logout |
| entidad_id | unsignedBigInteger, nullable | referencia débil sin FK (apunta a 5 tablas distintas) |
| descripcion | varchar(255) | texto legible, siempre generado en backend |
| ocurrido_at | datetime | |
| timestamps | | |

Índices: `(tenant_id, ocurrido_at)`, `(tenant_id, entidad_tipo)`, `(tenant_id, accion)`,
`(tenant_id, resultado)` (para filtrar accesos denegados). Único punto de escritura:
`App\Services\RegistradorActividad`, invocado desde los controladores de negocio y desde
`LogAuthenticationActivity` (login éxito/fallo, logout). Los super admins sin tenant (dominio
central) no generan fila: el historial es siempre por tenant. Listado accesible desde "Logs" en el
dropdown de usuario, con paginación **server-side real** (a diferencia de usuarios/facturas, que
cargan el listado completo client-side) por crecer sin cota temporal.

**Login fallido:** se registra una fila con `accion=login`, `resultado=fallo`, `usuario_nombre` = email
intentado, `ip_origen`/`user_agent` de la petición y `usuario_id=null` si el email no existe. Es lo
que permite detectar fuerza bruta y accesos no autorizados.

**Retención (RGPD — minimización):** los logs **no** se conservan indefinidamente. El plazo es
**configurable por tenant** (`configuraciones` → `logs.retencion_dias`, default **730 días / 2 años**,
referencia RD 1720/2007). Un comando programado (p. ej. `logs:purgar`) elimina por lotes las filas
cuyo `ocurrido_at` supere el plazo del tenant. Esta purga por antigüedad **no viola** el carácter
append-only: append-only significa que una fila no se **edita** ni se borra selectivamente durante su
vida útil; la eliminación por política de retención es cumplimiento, no manipulación.

> **RGPD — tratamiento:** el propio registro (usuario + IP + actividad) es dato personal. Debe figurar
> en el **Registro de Actividades de Tratamiento (RAT)** del responsable, con acceso restringido y su
> plazo de conservación documentado.

**Navegador y ubicación (solo display, no se persisten):** el listado (`GET /logs`) enriquece cada
fila con dos campos derivados que **no son columnas de la tabla**:
- `navegador` — `App\Support\AgenteUsuario::label()` parsea `user_agent` a "Chrome en Windows".
- `ubicacion` — `App\Support\GeolocalizadorIp::ubicacion()` resuelve `ip_origen` → "Ciudad, País"
  vía una API pública (ip-api.com), cacheada 30 días por IP. Se calcula **al leer**, nunca al
  escribir el evento — así el login/alta/modificación que originó la fila no paga la latencia de
  la llamada externa. IPs privadas/reservadas y fallos de red devuelven `null` sin romper la vista.

---

### `miembros_equipo` — perfil de empleado, 1:1 con un `User` con login (feature 024)
Quien ficha. No es ledger (editable por Admin). Guarda la ubicación de trabajo (perímetro
individual del miembro) y la dirección de casa (solo para calcular la distancia casa-trabajo).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| user_id | fk `users`, único, `cascadeOnDelete` | 1:1 con la cuenta de login que ficha |
| puesto | varchar(120), nullable | cargo/rol laboral |
| trabajo_direccion | varchar(255), nullable | dirección legible del centro |
| trabajo_latitud / trabajo_longitud | decimal(10,7), nullable | centro de trabajo (perímetro de este miembro); nullable — sin ubicación configurada el fichaje se marca `SinUbicacion`, sin alerta |
| distancia_max_metros | unsignedInteger | tolerancia para fichar; más lejos = `Fuera` + alerta |
| casa_direccion | varchar(255), nullable | **dato personal**, purgable tras baja |
| casa_latitud / casa_longitud | decimal(10,7), nullable | **dato personal**, purgable tras baja |
| distancia_casa_trabajo_metros | unsignedInteger, nullable | métrica derivada (Haversine); se conserva tras la purga de casa |
| activo | boolean, default true | inactivo no ficha |
| dado_baja_at | datetime, nullable | marca de baja → dispara la purga de datos de casa |
| timestamps, softDeletes | | conserva histórico ligado a sus fichajes |

Índice `(tenant_id, activo)`.

### `fichajes` — ledger append-only de jornada (feature 024)
Evento inmutable. Único punto de escritura: `App\Services\RegistroFichajes`. Sin rutas de edición/
borrado, sin softDeletes. Única mutación admitida: nulificación de columnas de geo por el comando
de purga (`fichajes:purgar-geo`) — nunca desde la UI.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| miembro_equipo_id | fk `miembros_equipo`, `restrictOnDelete` | quién ficha |
| tipo | varchar(15) | enum `TipoEventoFichaje`: entrada, salida, inicio_pausa, fin_pausa |
| ocurrido_at | datetime | **hora de servidor**, nunca del cliente; en correcciones, la hora corregida |
| resultado_ubicacion | varchar(15), nullable | enum `ResultadoUbicacionFichaje`: dentro, fuera, sin_ubicacion (`null` tras purga geo) |
| distancia_metros | unsignedInteger, nullable | distancia al trabajo del miembro (`null` tras purga geo) |
| precision_metros | unsignedInteger, nullable | precisión reportada por el navegador (`null` tras purga geo) |
| corrige_fichaje_id | fk `fichajes`, nullable, `nullOnDelete` | si es corrección, apunta al original |
| motivo | varchar(255), nullable | obligatorio solo en correcciones |
| registrado_por | fk `users`, nullable, `nullOnDelete` | corrector (Admin); `null` en fichaje propio |
| ip_origen | varchar(45), nullable | registro de acceso |
| user_agent | varchar(255), nullable | registro de acceso |
| timestamps | | |

Índices: `(tenant_id, miembro_equipo_id, ocurrido_at)`, `(tenant_id, ocurrido_at)`,
`corrige_fichaje_id`. **No** se guardan coordenadas crudas del fichaje (minimización RGPD): solo
veredicto + distancia + precisión.

### `alertas` — fichaje fuera de rango + incumplimiento de jornada (feature 024, extendida en 025)
Creada por `RegistroFichajes` (fichaje `Fuera`) o por el comando diario
`jornada:evaluar-cumplimiento` (ausencia/retraso, feature 025). Estado gestionable por Admin; no
se borra.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| miembro_equipo_id | fk `miembros_equipo`, `restrictOnDelete` | a quién |
| fichaje_id | fk `fichajes`, nullable, `restrictOnDelete` | fichaje que la disparó; `null` en alertas de jornada (025) |
| tipo | varchar(30) | enum `TipoAlerta`: `fichaje_fuera_de_rango` (024), `ausencia_jornada`/`retraso_jornada` (025) |
| distancia_metros | unsignedInteger, nullable | distancia del fichaje que superó la tolerancia; `null` en alertas de jornada |
| referencia_fecha | date, nullable | **(025)** día evaluado que originó una alerta de jornada; `null` en las de fichaje fuera de rango. Dedup de idempotencia: `(tenant_id, miembro_equipo_id, tipo, referencia_fecha)` |
| estado | varchar(15), default `nueva` | enum `EstadoAlerta`: nueva, vista, resuelta |
| resuelta_por | fk `users`, nullable, `nullOnDelete` | Admin que la resolvió |
| resuelta_at | datetime, nullable | |
| timestamps | | |

Índice `(tenant_id, estado)`.

**Retención (RGPD — minimización, feature 024):** dos plazos configurables por tenant
(`configuraciones`), independientes del plazo legal de 4 años de la fila de jornada:
- `fichajes.retencion_geo_dias` (default 30) — comando `fichajes:purgar-geo` nulifica
  `resultado_ubicacion`/`distancia_metros`/`precision_metros` de `fichajes` más antiguos que el
  plazo; la fila permanece.
- `fichajes.retencion_casa_dias` (default 30) — comando `miembros:purgar-casa` nulifica
  `casa_direccion`/`casa_latitud`/`casa_longitud` de miembros con `dado_baja_at` anterior al
  plazo; el miembro, su `distancia_casa_trabajo_metros` y sus fichajes se conservan.

Flags adicionales en `configuraciones` (grupo `fichajes`): `fichajes.geofencing_bloqueante` (bool,
default false), `fichajes.registrar_pausas` (bool, default false),
`fichajes.tolerancia_retraso_min` (integer, default 5) y `fichajes.tolerancia_exceso_min`
(integer, default 15) — estos dos últimos de la feature 025, usados por el informe de
cumplimiento.

---

### `horarios` — plantilla de cuadrante reutilizable por tenant (feature 025)
Catálogo del tenant (como `bancos`/`unidades`), independiente de los miembros: editar sus tramos
afecta a todos los miembros que la tengan asignada. Horas previstas (día/semana) son **derivadas**
de sus tramos, no una columna (`Horario::horasPrevistasDia()`/`horasPrevistasSemana()`).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| nombre | varchar(120) | único por `(tenant_id, nombre)` |
| activo | boolean, default true | inactivo no se ofrece para asignar |
| timestamps, softDeletes | | borrado bloqueado si tiene asignaciones (`asignaciones_horario`) |

Índice `(tenant_id, activo)`.

### `horario_tramos` — tramo de trabajo de un horario (feature 025)
Varios tramos el mismo `(horario_id, dia_semana)` = turno partido; ningún tramo ese día = día
libre (0 horas previstas). No cruza medianoche (fuera de alcance v1).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| horario_id | fk `horarios`, `cascadeOnDelete` | |
| dia_semana | unsignedTinyInteger | 1=lunes…7=domingo (ISO-8601, `Carbon::dayOfWeekIso`) |
| hora_inicio / hora_fin | time | `hora_fin > hora_inicio`; sin solape con otro tramo del mismo día (validado en `HorarioRequest`, no en BD) |
| timestamps | | |

Índice `(tenant_id, horario_id, dia_semana)`.

### `asignaciones_horario` — horario aplicable a un miembro con vigencia (feature 025)
Vínculo miembro↔horario tipo "slowly changing dimension": conserva histórico completo. El
horario aplicable de un miembro en una fecha F es la fila con
`vigente_desde <= F AND (vigente_hasta IS NULL OR vigente_hasta >= F)`
(`App\Support\ResolutorHorario`). Al asignar un horario nuevo, `App\Support\AsignadorHorario`
cierra automáticamente la asignación abierta anterior (`vigente_hasta = nueva.vigente_desde − 1
día`) en la misma transacción; rechaza solapes contra rangos ya cerrados.

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| miembro_equipo_id | fk `miembros_equipo`, `cascadeOnDelete` | quién |
| horario_id | fk `horarios`, `restrictOnDelete` | qué horario (impide borrar horario asignado) |
| vigente_desde | date | inicio de vigencia (inclusive) |
| vigente_hasta | date, nullable | fin de vigencia (inclusive); `null` = abierta/vigente |
| timestamps | | |

Índices: `(tenant_id, miembro_equipo_id, vigente_desde)`, `(tenant_id, horario_id)`.

**Cumplimiento (feature 025, derivado, no persistido):** `App\Support\Cumplimiento\ServicioCumplimiento`
cruza el horario vigente de cada día con los `fichajes` reales (vía `InformeJornada::eventosEfectivos`,
que ya aplica las correcciones del ledger) y produce un `ResultadoDia` por día: horas previstas,
horas trabajadas, incidencia (fichaje incompleto, FR-015a) y veredicto (`VeredictoCumplimiento`:
libre, ausencia, retraso, parcial, cumplido, exceso). El informe (`GET /jornada`) lo calcula **al
vuelo**; el comando diario `jornada:evaluar-cumplimiento` evalúa el día anterior de cada miembro y
persiste únicamente las **alertas** de ausencia/retraso (no una tabla de resultados).

---

## Módulo CRM — leads, oportunidades, presupuestos (feature 028)

Embudo comercial previo a la factura: **Lead → Oportunidad → Presupuesto → conversión a factura**.
El presupuesto reutiliza `App\Services\CalculadoraFactura` (no duplica el motor de cálculo) y no es
documento fiscal: numeración propia, sin Verifactu ni serie de factura.

```
tenants ──< leads ──< lead_notas
users   ──(asignado_a)── leads
leads   ──(opcional)── clientes           (convertido_a_cliente_id)
tenants ──< oportunidades
oportunidades ──(lead_id XOR cliente_id)── leads / clientes
tenants ──< presupuestos ──< presupuesto_lineas
oportunidades ──(opcional)──< presupuestos
presupuestos ──(opcional)── facturas       (convertido_a_factura_id)
articulos ──(opcional)── presupuesto_lineas
```

### `leads` — contacto potencial (aún no cliente)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| nombre | varchar(150) | requerido |
| empresa | varchar(150), nullable | |
| email / telefono | varchar, nullable | al menos uno de los dos requerido; dedup por tenant (FR-004, rechazo sin fusión) |
| estado | varchar(15) | enum `EstadoLead`: `nuevo`, `contactado`, `cualificado`, `descartado`, `convertido` |
| origen | varchar(15) | enum `OrigenLead`: `manual`, `importacion` |
| asignado_a | fk `users`, nullable, `nullOnDelete` | `null` = bandeja "sin asignar" |
| convertido_a_cliente_id | fk `clientes`, nullable, `nullOnDelete` | trazabilidad al convertir |
| motivo_descarte | varchar, nullable | |
| timestamps, softDeletes | | |

Índices: `(tenant_id, estado)`, `(tenant_id, asignado_a)`, `(tenant_id, email)`, `(tenant_id, telefono)`.
Retención RGPD: leads `descartado`/no convertidos se purgan pasado `leads.retencion_dias` (comando
`leads:purgar`, patrón `RetencionLogsTenant`/feature 021).

### `lead_notas` — actividad comercial sobre un lead

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| lead_id | fk `leads`, `cascadeOnDelete` | |
| user_id | fk `users`, nullable, `nullOnDelete` | autor |
| tipo | varchar(15) | `nota`, `llamada`, `email`, `reunion`, `sistema` |
| contenido | varchar(500) | |
| timestamps | | |

Índice `(tenant_id, lead_id)`.

### `oportunidades` — posible venta en curso

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| titulo | varchar(150) | |
| lead_id / cliente_id | fk, nullable, `nullOnDelete` | **XOR**: exactamente uno no nulo (validado en el Request) |
| etapa | varchar(15) | enum `EtapaOportunidad`: `nueva`, `en_negociacion`, `ganada`, `perdida` (`ganada`/`perdida` terminales) |
| importe_estimado | decimal(12,2), nullable | |
| asignado_a | fk `users`, nullable, `nullOnDelete` | |
| motivo_perdida | varchar, nullable | requerido al pasar a `perdida` |
| cerrada_at | datetime, nullable | fecha de cierre |
| timestamps, softDeletes | | |

Índices: `(tenant_id, etapa)`, `(tenant_id, lead_id)`, `(tenant_id, cliente_id)`, `(tenant_id, asignado_a)`.
Al ganar una oportunidad con `lead_id`, `App\Services\ConversorLeadCliente` convierte el lead en
cliente (FR-012). **Nota de modelo Eloquent**: `Oportunidad` fija `$table = 'oportunidades'`
explícito — el inflector de Laravel no pluraliza bien "Oportunidad" en español (daría
`oportunidads`).

### `presupuestos` — oferta comercial (documento NO fiscal)

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| numero | varchar(20) | numeración propia `P-{año}-{n}`, **no** consume `series.proximo_numero` ni participa de Verifactu |
| oportunidad_id / cliente_id / lead_id | fk, nullable, `nullOnDelete` | receptor cliente o lead |
| estado | varchar(12) | enum `EstadoPresupuesto`: `borrador`→`enviado`→`aceptado`→`facturado` (terminal); `rechazado`/`caducado` |
| receptor_* (nombre, nif, direccion, cp, ciudad, provincia, pais) | | snapshot congelado, mismo patrón que `facturas.cliente_*` |
| fecha_emision / fecha_validez / fecha_envio | date / date / datetime | |
| regimen_impositivo, aplica_recargo | | del tenant/cliente al crear |
| base_total, cuota_impuesto_total, cuota_recargo_total, irpf_porcentaje, irpf_cuota, total | decimal | calculados por `CalculadoraFactura` (Principio III) |
| convertido_a_factura_id | fk `facturas`, nullable, `nullOnDelete` | factura borrador creada al convertir |
| timestamps, softDeletes | | |

Índices: `(tenant_id, estado)`, `(tenant_id, oportunidad_id)`, `(tenant_id, cliente_id)`,
`unique(tenant_id, numero)`. Solo editable en `borrador` (FR-018); `facturado` es terminal y de
solo lectura (FR-022).

### `presupuesto_lineas` — detalle de la oferta

Espejo de `factura_lineas` en las columnas de importe, sin los campos fiscales de Verifactu
(`calificacion_operacion`, `causa_exencion`, `mencion_legal`).

| Campo | Tipo | Notas |
|-------|------|-------|
| id | bigint PK | |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` |
| presupuesto_id | fk `presupuestos`, `cascadeOnDelete` | |
| articulo_id | fk `articulos`, nullable, `nullOnDelete` | |
| concepto, unidad, cantidad, precio_unitario, descuento_porcentaje, base, tipo_impositivo, cuota_impuesto, tipo_recargo, cuota_recargo, orden | | mismas columnas que `factura_lineas` |
| timestamps | | |

Al convertir a factura, cada línea se copia a `factura_lineas` con sus importes **congelados** (no
releídos del catálogo).

---

## Notas de implementación
- **Totales:** calcular siempre en el backend a partir de las líneas; nunca confiar en el cliente. Guardar los totales desnormalizados en `facturas` para rendimiento de listados y para congelar el importe al emitir.
- **Global scope de tenant:** aplicar en un `TenantScope` sobre un `BaseModel`; todas las consultas filtran por `tenant_id` automáticamente. Cubrir con tests para evitar fugas entre tenants.
- **Numeración:** asignar `numero` dentro de una transacción con bloqueo (evitar huecos/duplicados en concurrencia).
- **Verifactu:** el cálculo de huella y encadenamiento se hace al **emitir** (pasar de borrador a emitida), en un servicio dedicado; a partir de ahí la factura es inmutable.
