# Implementation Plan: POS con mesas y opciones de artículo (hostelería)

**Branch**: `038-pos-mesas-opciones` | **Date**: 2026-08-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/038-pos-mesas-opciones/spec.md`

## Summary

Convertir el POS actual —que solo emite tickets de venta directa— en un TPV de hostelería capaz de
mantener cuentas por mesa, modificar platos con opciones y cobrar por partes; **sin cambiar nada
para los tenants que no lo activen**.

El enfoque técnico se apoya en tres decisiones que evitan tocar lo que ya está bajo auditoría
fiscal:

1. **Módulo apagado por defecto** mediante flags clave/valor por tenant (patrón `ConfigFichajes`),
   con doble control de acceso: permiso del usuario **y** middleware de módulo activo.
2. **La cuenta abierta es una entidad propia**, sin número ni serie. Al cobrar, un servicio nuevo
   `CobradorCuenta` traduce "estas unidades" al array de líneas que `RegistroTicket` ya espera y lo
   invoca sin modificar su contrato — de modo que numeración, inmutabilidad, tope legal y Verifactu
   se heredan intactos en vez de duplicarse.
3. **El suplemento de zona sube el precio unitario de cada línea** antes de llegar al motor de
   cálculo, con lo que hereda automáticamente el tipo impositivo de su artículo y no hace falta
   ninguna regla de reparto entre tipos.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (single-database), `spatie/laravel-permission`,
Bootstrap 5 + template NexaDash, jQuery + DataTables, Select2, toastr, DomPDF

**Storage**: MySQL/MariaDB. 10 tablas nuevas con prefijo `pos_`; **ninguna columna nueva en tablas
existentes**; configuración en la tabla `configuraciones` ya existente

**Testing**: PHPUnit (Feature tests sobre HTTP), factories por modelo

**Target Platform**: aplicación web servida desde hosting compartido cPanel. **Tablet apaisada es
el dispositivo de referencia del POS**, no el escritorio

**Project Type**: aplicación web Laravel monolítica (Blade + JS vanilla/jQuery, sin build step)

**Performance Goals**: la Sala se pinta en < 2 s con 20 cuentas abiertas (SC-001) — obliga a
resolver el estado de todas las mesas en una consulta, sin N+1

**Constraints**: sin VPS ni extensiones especiales (Principio V); sin build step de JS; los importes
se calculan siempre en servidor; `movimientos_stock` es append-only

**Scale/Scope**: 7 historias, 64 requisitos, 10 tablas, 3 pantallas nuevas (Sala, Opciones, pestaña
de Configuración) + adaptación de 2 existentes (crear ticket, artículos)

**Añadido tras la primera versión del plan**: FR-063 (importe entregado y cambio a devolver) y
FR-064 (contexto de mesa en la cabecera del modal de cobro), aportados por el usuario desde la
pantalla de cobro de GoTPV. Ambos son **solo interfaz sobre el modal de cobro ya existente**: no
tocan el modelo de datos, ni el motor de cálculo, ni el documento emitido. Por eso no alteran
ninguna decisión de este plan y se resuelven en la fase de pulido.

## Constitution Check

*GATE: revisado antes de Phase 0 y de nuevo tras Phase 1. Resultado: **PASA**, sin violaciones que
justificar.*

### I. Aislamiento Multi-Tenant (NON-NEGOTIABLE) — ✅

Las 10 tablas llevan `tenant_id` indexado bajo `TenantScope`. Ningún modelo se resuelve por binding
implícito: el controller busca bajo el scope (memoria `project_tenant_route_binding`). Tests con 2
tenants afirmando que ni zona, ni mesa, ni cuenta, ni opción del tenant A son accesibles desde el B,
tampoco por identificador directo (FR-054).

**Punto de atención propio de esta feature**: las factories del proyecto declaran
`tenant_id => Tenant::factory()`, lo que crea tenants fantasma si un seeder de demo no fuerza el
tenant activo (memoria `project_factory_tenant_id_pitfall`). Aplica a los seeders de demo de mesas y
opciones.

### II. Cumplimiento Normativo España-First — ✅

- **Numeración correlativa sin huecos**: garantizada estructuralmente. `pos_cuentas` no tiene
  `numero` ni `serie_id`; el número solo lo asigna `EmisorFacturas` al emitir. Abrir y anular
  cuentas no puede dejar huecos porque nunca reservan número. Test explícito.
- **Inmutabilidad**: cada cobro (total o parcial) produce una factura emitida inmutable. Anular una
  cuenta parcialmente cobrada solo afecta a lo pendiente; lo emitido se corrige por rectificativa,
  como cualquier factura.
- **Régimen impositivo parametrizado**: el suplemento de zona no toca tipos impositivos, solo
  precios unitarios; el régimen sigue viniendo de `tenant()->regimen_impositivo` vía el motor
  existente. Test bajo IVA, IGIC e IPSI para dejarlo demostrado y no solo afirmado.
- **Verifactu**: sin cambios. Se emite por el mismo camino.
- **RGPD/LOPDGDD**: la feature no introduce datos personales nuevos. El receptor opcional de la
  cuenta es el mismo dato que ya guarda un ticket, con su retención ya cubierta. **No requiere
  mecanismo de purga nuevo** (verificación exigida por el punto 5 del Development Workflow).

### III. Integridad Financiera Server-Side — ✅

Todos los importes (suplemento de opción, suplemento de zona, bases, cuotas, total) se calculan en
servidor. El cliente envía qué se eligió, nunca cuánto cuesta. La numeración se asigna con bloqueo
dentro de transacción, dentro de `EmisorFacturas`, que no se modifica. El cobro completo —emisión,
marcado de unidades saldadas, `pos_cobros` y movimientos de stock— va en una sola transacción.

### IV. Test-First en Lógica Crítica (NON-NEGOTIABLE) — ✅

Se escriben antes de implementar, en rojo primero, para: aislamiento multi-tenant, cálculo del
suplemento de zona con tipos impositivos mezclados, numeración correlativa bajo cobros parciales, e
invariante de cobro parcial (suma de `pos_cobro_lineas` = `cantidad_saldada`, imposibilidad de doble
cobro). El resto (UI, pantallas de configuración) sigue el flujo flexible.

### V. Simplicidad y Compatibilidad con Hosting Compartido — ✅

Sin dependencias nuevas, sin extensiones, sin build step. El plano drag & drop queda fuera por YAGNI
(decisión explícita del usuario, registrada en el spec). El bloqueo de concurrencia es optimista con
un entero, no un sistema de locks con expiración. El acceso a cuentas no se restringe por camarero.

**Ninguna desviación que justificar. La sección Complexity Tracking queda vacía a propósito.**

## Project Structure

### Documentation (this feature)

```text
specs/038-pos-mesas-opciones/
├── plan.md              # Este archivo
├── research.md          # Phase 0 — 10 decisiones técnicas
├── data-model.md        # Phase 1 — 10 tablas + índices
├── quickstart.md        # Phase 1 — guía de validación
├── contracts/
│   └── rutas.md         # Phase 1 — endpoints, códigos de estado, permisos
├── checklists/
│   └── requirements.md  # Validación del spec
└── tasks.md             # Phase 2 — lo genera /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── PosController.php                  # MODIFICAR: ?cuenta=, tiene_opciones
│   │   ├── Pos/SalaController.php             # NUEVO
│   │   ├── Pos/CuentaController.php           # NUEVO (store/show/update/anular/transferir/unir/cobrar)
│   │   ├── Pos/OpcionController.php           # NUEVO
│   │   ├── Pos/OpcionGrupoController.php      # NUEVO
│   │   ├── Pos/ArticuloOpcionController.php   # NUEVO (index/sync por artículo)
│   │   └── Configuracion/PosZonaController.php, PosMesaController.php   # NUEVOS
│   ├── Middleware/ModuloHosteleriaActivo.php  # NUEVO
│   └── Requests/                              # FormRequests de cuenta, cobro, opción, zona, mesa
├── Models/
│   ├── PosZona.php, PosMesa.php, PosCuenta.php, PosCuentaLinea.php,
│   ├── PosCuentaLineaOpcion.php, PosCobro.php, PosCobroLinea.php,
│   └── PosOpcion.php, PosOpcionGrupo.php      # NUEVOS (todos sobre BaseModel → TenantScope)
├── Services/
│   ├── CobradorCuenta.php                     # NUEVO — traduce unidades → RegistroTicket
│   ├── TransferidorCuenta.php                 # NUEVO — transferir y unir
│   └── RegistroTicket.php                     # SIN CAMBIOS (se invoca, no se toca)
└── Support/
    ├── ConfigPos.php                          # NUEVO — flags del módulo (clon de ConfigFichajes)
    ├── CatalogoPermisos.php                   # MODIFICAR: ver-pos-sala, ver-pos-opciones
    └── CatalogoMenu.php                       # MODIFICAR: entradas Sala y Opciones

database/migrations/                           # 10 migraciones, ninguna destructiva

resources/views/
├── pos/
│   ├── sala.blade.php                         # NUEVO
│   ├── opciones/index.blade.php               # NUEVO
│   └── create.blade.php                       # MODIFICAR (chip de mesa, mini-grid, modales)
├── configuracion/
│   ├── _tab_pos.blade.php                     # NUEVO
│   └── index.blade.php                        # MODIFICAR (registrar la pestaña)
├── articulos/index.blade.php                  # MODIFICAR (asignar opciones)
└── ayuda/
    ├── pos-sala.blade.php, pos-opciones.blade.php   # NUEVOS
    └── pos-crear.blade.php                    # MODIFICAR

public/js/
├── pos-form.js                                # PARTIR primero (735 líneas hoy)
├── pos-catalogo.js, pos-ticket.js, pos-cobro.js     # NUEVOS (extraídos)
├── pos-opciones.js, pos-cuenta.js                   # NUEVOS (funcionalidad)
└── plugins-init/pos-sala.init.js, pos-opciones-datatable.init.js,
    configuracion-pos.init.js                        # NUEVOS

tests/Feature/Pos/                             # aislamiento, numeración, cálculo, cobro parcial,
                                               # concurrencia, módulo apagado
```

**Structure Decision**: se mantiene la estructura monolítica Laravel del proyecto. Los controllers
nuevos se agrupan en un subnamespace `Pos\` porque son nueve piezas de un mismo módulo desactivable;
el resto del proyecto ya usa esa agrupación (`SuperAdmin\`, `Configuracion\`). Los modelos van al
directorio plano `app/Models` como todos los demás, con prefijo `Pos` en el nombre de clase.

## Convenciones de front aplicables (`docs/04-front-guidelines.md`)

Citadas por pantalla, según exige la REGLA DE ORO de `CLAUDE.md`. Un plan que no las refleja produce
trabajo que hay que rehacer entero.

| Pantalla | Secciones que aplican |
|---|---|
| **Sala** (nueva) | "Catálogo del POS (TPV)" — las pestañas de zona reutilizan `.pos-filtro` (52px, badge de conteo, activo con el primario del tenant), **no** se diseña un selector nuevo · "Fondo de la app y elevación de las superficies" para las tarjetas de mesa · "Colores de los lordicon" · toastr para avisos |
| **Opciones** (nueva) | "Listados: SIEMPRE DataTable" · "CRUD simple: alta/edición en modal + AJAX" (un solo modal para alta y edición, datos en `data-*`, sin rutas `create`/`edit`) · "Columna Acciones de los listados" · "DataTable: botones Anterior/Siguiente verticales" (el override es obligatorio en cada tabla nueva, memoria `feedback_datatable_pagination_css`) · "Cabecera de las DataTables en color de marca" · "Confirmación de acciones irreversibles" al borrar |
| **Asignar opciones a un artículo** | "Select dinámico con CRUD inline" — clonar `<x-categoria-select>` de punta a punta **incluido su CSS renombrando el scope**, que es el gotcha documentado · "Sub-listado editable embebido en un modal de edición" — la lista de opciones asignadas no cabe en `data-*`, va por endpoint aparte |
| **Configuración → POS** (nueva pestaña) | "Tamaño de formularios: todo sm por defecto" · "Padding del content-body vs padding interno de las cards" · patrón de las pestañas existentes (`_tab_fichajes.blade.php` es el análogo más cercano: flags + umbrales) |
| **Crear ticket** (modificar) | La geometría sticky de las dos columnas y su calibración `.row`/`.card` **no se toca** (está comentada en la propia vista con el motivo) · botonera de 3 franjas, las acciones nuevas entran en la franja central · modal de opciones reutiliza la familia visual de `.pos-metodo` · "Modales: siempre centrados verticalmente" · "Estado de carga en botones (AJAX/fetch)" · "Notificaciones" (toastr, nunca alerts ad-hoc) · "CSRF en peticiones AJAX sin formulario" |
| **Todas** | "Ayuda contextual" — guía por pantalla en `resources/views/ayuda/` · "Nueva entrada de menú ⇒ nuevo permiso (obligatorio)" — los 5 pasos en orden, incluido re-correr `PermisosSeeder` |

**Orden de CSS**: el `@stack('styles')` de cada vista se inyecta **antes** de `css/style.css`. Si
algo nuevo se ve roto sin error en consola, sospechar de esto antes que del JS.

## Orden de entrega recomendado

Responde al riesgo señalado en el checklist del spec y al punto 1 del encargo. Detalle y motivos en
[research.md](research.md) D9.

| Tanda | Contenido | Por qué aquí |
|---|---|---|
| **0** | Partir `pos-form.js` en módulos, sin añadir nada | Refactor aislado y verificable; hacerlo después obligaría a rehacer el trabajo nuevo |
| **1** | Configuración del módulo + permisos + menú + middleware (US1) | Todo lo demás se esconde detrás de esto. Entregable y sin riesgo: con el flag apagado no cambia nada |
| **2** | Zonas, mesas, Sala, cuentas abiertas (US2) — **con `cantidad_saldada` ya en el esquema** | El núcleo. Ya es un TPV de bar usable |
| **3** | Opciones: catálogo, asignación y venta (US3 + US4) | Independiente del cobro; aporta valor propio |
| **4** | Transferir y unir (US6) | Pequeño, se apoya en la tanda 2 |
| **5** | Cobro dividido por selección de líneas (US5) | **El más caro.** Requiere la tanda 2 estable: toca estado de cuenta, importe de mesa, tope, anulación y transferencias a la vez |
| **6** | Suplemento por zona (US7) | El de mayor impacto fiscal; se aísla al final para probarlo sin ruido |

**La columna `cantidad_saldada` y las tablas `pos_cobros`/`pos_cobro_lineas` se crean en la tanda 2,
aunque no se usen hasta la 5.** Añadirlas después obligaría a migrar datos reales y revalidar toda
la numeración; ahora son dos columnas y una tabla.

**Si hubiera que recortar por tiempo, la tanda 5 es el corte natural**: se pospone sin tocar ni una
migración y sin dejar nada a medias.

## Complexity Tracking

*Sin entradas: el Constitution Check pasa sin violaciones.*
