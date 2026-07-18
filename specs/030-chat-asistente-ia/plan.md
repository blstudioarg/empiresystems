# Implementation Plan: Chat flotante con asistente IA

**Branch**: `030-chat-asistente-ia` | **Date**: 2026-07-16 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/030-chat-asistente-ia/spec.md`

## Summary

Widget de chat flotante en todas las pantallas del área de tenant, conectado a la API de OpenAI con tool use. Responde dudas de funcionamiento (base de conocimiento modular en markdown), consulta datos de negocio y crea/edita entidades (clientes, artículos, presupuestos, facturas **solo borrador**) mediante tools filtradas por los permisos del usuario y ejecutadas bajo el `TenantScope`. Las escrituras usan un flujo de dos fases con confirmación explícita del usuario, y las acciones prohibidas no existen como tools (bloqueo estructural en servidor). La API key es por tenant, cifrada en `configuraciones` (grupo `ia`), seteable desde Configuración. Respuestas por streaming (SSE).

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12

**Primary Dependencies**: `openai-php/client` (SDK oficial PHP de OpenAI — dependencia nueva), `stancl/tenancy`, `spatie/laravel-permission` (existentes)

**Storage**: MySQL/MariaDB. Sin tablas nuevas: la API key vive en `configuraciones` (grupo `ia`, valor cifrado con `Crypt`, patrón `EmailTenant`); la conversación vive en la sesión de Laravel (efímera, no persistida entre logins)

**Testing**: PHPUnit 11 (`tests/Feature`, `tests/Unit`), cliente OpenAI mockeado/fakeado en tests

**Target Platform**: Web (hosting compartido cPanel/Hostinger; también corre en Railway/Docker)

**Project Type**: Web app Laravel monolítica (Blade + JS vanilla, patrón existente del proyecto)

**Performance Goals**: Primer token visible < 5s en condiciones normales; respuesta de funcionamiento completa < 30s (SC-001)

**Constraints**: Sin colas ni workers (Principio V) — llamada a OpenAI síncrona dentro del request con streaming SSE (`response()->stream()`); timeout del request compatible con hosting compartido; truncado de conversación en servidor para acotar contexto/costo

**Scale/Scope**: 50–80 tenants, uso interactivo puntual; una conversación activa por usuario; ~10–14 tools iniciales; base de conocimiento ~1 archivo md por módulo funcional (~20)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento |
|---|---|
| **I. Aislamiento multi-tenant** | Todas las tools operan vía modelos Eloquent bajo `TenantScope` en el contexto del request (tenant resuelto por dominio). Ninguna tool recibe ni acepta `tenant_id` como parámetro. Tests de aislamiento con ≥2 tenants obligatorios (SC-003). |
| **II. Cumplimiento normativo** | El asistente jamás emite facturas: no existe tool de emisión (bloqueo estructural). Las facturas creadas quedan en `borrador` sin número de serie definitivo. Datos personales: la conversación es efímera (sesión, se destruye al logout/expiración) → no requiere retención/purga nueva; el envío de datos del tenant a la API de OpenAI se documenta como consideración de privacidad bajo responsabilidad del tenant (clave propia). La API key se registra en `logs_actividad` al configurarse (patrón feature 021). |
| **III. Integridad financiera server-side** | Las tools de creación de factura/presupuesto delegan en los servicios existentes (`CalculadoraFactura`, `RegistroPresupuesto`); el modelo solo aporta artículos/cantidades, nunca importes ni totales. |
| **IV. Test-first en lógica crítica** | Test-first para: aislamiento de tenant en tools, filtrado por permisos, bloqueo de acciones prohibidas (incluida evasión), flujo de confirmación de escrituras, y cifrado/enmascarado de la API key. UI del widget con flujo flexible. |
| **V. Simplicidad / hosting compartido** | Sin colas, sin websockets, sin Redis: SSE sobre HTTP plano dentro del request. Una sola dependencia nueva (SDK oficial). Sin tablas nuevas. Conversación en sesión estándar de Laravel. |

**Resultado**: PASS (sin violaciones que justificar).

## Project Structure

### Documentation (this feature)

```text
specs/030-chat-asistente-ia/
├── plan.md              # Este archivo
├── research.md          # Fase 0
├── data-model.md        # Fase 1
├── quickstart.md        # Fase 1
├── contracts/           # Fase 1 (endpoints del chat + contrato de tools)
└── tasks.md             # Fase 2 (/speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   ├── AsistenteChatController.php      # POST mensaje (SSE), confirmar/cancelar acción, reset conversación
│   └── ConfiguracionController.php      # (existente) + sección "Asistente IA" (guardar/borrar API key)
├── Services/
│   └── AsistenteIa.php                  # Orquestador: cliente OpenAI, loop de tool use, streaming, truncado
├── Support/
│   └── IaTenant.php                     # Config del tenant (clave ia.api_key cifrada), patrón EmailTenant
└── Ia/
    ├── CatalogoTools.php                # Registro de tools; filtra por permisos del usuario
    ├── ConversacionAsistente.php        # Historial en sesión: append, truncado, acción pendiente
    ├── ConocimientoAsistente.php        # Ensambla system prompt desde resources/ia/conocimiento/*.md
    └── Tools/
        ├── ToolAsistente.php            # Contrato base: nombre, descripción, schema, permiso requerido, esLectura()
        ├── BuscarClientes.php           # lectura (ver-clientes)
        ├── BuscarArticulos.php          # lectura (ver-articulos)
        ├── BuscarFacturas.php           # lectura (ver-facturas)
        ├── BuscarPresupuestos.php       # lectura (ver-presupuestos)
        ├── ResumenDatosNegocio.php      # lectura agregada (dashboard) (ver-dashboard)
        ├── CrearCliente.php             # escritura (ver-clientes)
        ├── EditarCliente.php            # escritura (ver-clientes)
        ├── CrearArticulo.php            # escritura (ver-articulos)
        ├── EditarArticulo.php           # escritura (ver-articulos)
        ├── CrearPresupuesto.php         # escritura (ver-presupuestos)
        ├── CrearFacturaBorrador.php     # escritura (ver-facturas) — SOLO borrador
        └── EditarFacturaBorrador.php    # escritura (ver-facturas) — SOLO facturas en borrador

resources/
├── ia/conocimiento/                     # Base de conocimiento modular (1 md por módulo)
│   ├── 00-general.md                    # Qué es la app, navegación, convenciones
│   ├── clientes.md, facturas.md, ...    # Uno por sección funcional
│   └── (se añade uno por cada feature nueva — regla FR-013)
└── views/
    ├── partials/asistente-chat.blade.php   # Widget flotante (incluido en layouts/app.blade.php)
    └── configuracion/                       # (existente) + tab/sección Asistente IA

public/js/
└── asistente-chat.js                    # UI del widget: SSE, render de mensajes, botones de confirmación

routes/web.php                           # Rutas del chat (auth + tenant) y de configuración IA

config/ia.php                            # Modelo fijo del sistema, max_tokens, límite de truncado

tests/
├── Feature/Asistente/
│   ├── ChatEndpointTest.php             # auth, disponibilidad con/sin clave, visibilidad widget
│   ├── ToolsAislamientoTest.php         # 2 tenants, cero fuga (test-first)
│   ├── ToolsPermisosTest.php            # filtrado por rol (test-first)
│   ├── AccionesProhibidasTest.php       # no existen tools de emitir/pagar/borrar; evasión (test-first)
│   ├── ConfirmacionEscrituraTest.php    # flujo 2 fases: propuesta → confirmar/cancelar (test-first)
│   └── ConfiguracionIaTest.php          # guardar cifrada, enmascarado, borrar clave (test-first)
└── Unit/
    ├── ConversacionAsistenteTest.php    # truncado, acción pendiente
    └── ConocimientoAsistenteTest.php    # ensamblado modular del system prompt
```

**Structure Decision**: monolito Laravel existente. La lógica nueva se concentra en `app/Ia/` (dominio del asistente) + un servicio orquestador en `app/Services/` (convención del proyecto). El conocimiento vive en `resources/ia/conocimiento/` como markdown plano para que agregarlo por feature sea trivial (FR-012/FR-013, SC-007).

## Diseño clave (resumen — detalle en research.md)

1. **Bloqueo estructural de acciones prohibidas**: no hay "lista negra" que el modelo pueda evadir — simplemente no existen tools para emitir, pagar, borrar ni configurar. El servidor solo ejecuta tools del catálogo filtrado por permisos. (FR-006, SC-002)
2. **Escrituras en dos fases**: una tool de escritura no escribe; devuelve una *propuesta* que el backend guarda como acción pendiente en sesión y el widget renderiza con botones Confirmar/Cancelar. Solo el endpoint de confirmación (request separado, CSRF, usuario autenticado) ejecuta la escritura vía los servicios existentes. La confirmación es del usuario, no del modelo. (FR-007, SC-004)
3. **Conversación en sesión de Laravel**: sobrevive a la navegación, muere con la sesión; truncado por número de mensajes/tokens estimados en servidor. (FR-014, clarificación de truncado)
4. **Streaming SSE** dentro del request (`response()->stream()`), sin infraestructura extra. (FR-016, Principio V)
5. **API key por tenant**: `configuraciones` grupo `ia`, cifrada con `Crypt`, support `IaTenant` (espejo de `EmailTenant`); enmascarada en la vista; modelo fijo en `config/ia.php`.

## Complexity Tracking

Sin violaciones de la constitución que justificar.
