# Implementation Plan: Traducción del POS al chino

**Branch**: `050-traduccion-pos` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/050-traduccion-pos/spec.md`

## Summary

El tenant elige el idioma del POS (español por defecto o chino simplificado) en Configuración →
POS. Los textos propios del POS se marcan con el traductor estándar de Laravel usando el **texto en
español como clave** (`__()` en Blade/PHP, `__t()` en JS), así que el español no cambia y cambiar un
texto genera una clave nueva sola. Para chino, un `TranslationLoader` propio sirve las traducciones
desde base de datos: una tabla central con la traducción automática de cada texto (DeepL, con
glosario de términos del POS) y otra por tenant con sus correcciones manuales, que prevalecen. Un
comando del deploy extrae los textos del ámbito POS y traduce los pendientes; como respaldo, lo que
falte se traduce **después** de enviar la respuesta, nunca dentro de ella. Si la API falla, se ve en
español. El ticket y la precuenta se imprimen bilingües con una fuente que incluye caracteres
chinos. El menú solo traduce el grupo POS.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12; JS vanilla del POS (sin build step)

**Primary Dependencies**: traductor de Laravel (`Illuminate\Translation`), cliente `Http` de Laravel
contra la API REST de DeepL (sin SDK), `barryvdh/laravel-dompdf` (ya usado). Fuente **Noto Sans SC**
(OFL) añadida al repo. Sin paquetes Composer nuevos.

**Storage**: MySQL/MariaDB — 2 tablas nuevas (`traducciones` central, `traduccion_correcciones`
por tenant) + 1 clave de configuración (`pos.idioma`).

**Testing**: PHPUnit, `Http::fake()` para DeepL (ningún test llama a la API real), ≥ 2 tenants para
aislamiento de correcciones.

**Target Platform**: hosting compartido cPanel (empiresass.gestionley.com), PHP-FPM; tablet en
horizontal.

**Project Type**: aplicación web monolítica Laravel (Blade + JS por vista)

**Performance Goals**: pantallas del POS en chino tan rápidas como en español (una consulta extra
por request para el diccionario; la API nunca en el camino de la respuesta).

**Constraints**: sin colas ni workers (Principio V); el deploy no puede fallar por la API; plan
gratuito de DeepL (1.000.000 caracteres/mes); PDFs con subsetting de fuente.

**Scale/Scope**: unos 30 archivos del POS (≈ 10 vistas, 6 guías de ayuda, 4 plantillas PDF, ≈ 15
JS, controladores/servicios/requests del POS); estimación: 400–700 textos, < 60.000 caracteres.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cumplimiento | Estado |
|---|---|---|
| **I. Aislamiento multi-tenant** | El idioma es una clave por tenant en `configuraciones`. `traduccion_correcciones` lleva `tenant_id` + `BelongsToTenant`; el loader solo mezcla las del tenant activo. Tests con 2 tenants: la corrección de A no la ve B, ni se lista, ni se edita/borra por hash desde B. `traducciones` **no** lleva `tenant_id` a propósito: son textos de la aplicación, no datos de ningún tenant (justificación en Complexity Tracking). | ✅ |
| **II. Cumplimiento normativo** | RD 1619/2012 art. 12.2 (factura en cualquier lengua; la AEAT puede pedir traducción al castellano) se documenta en `docs/02-facturacion-espana.md` **antes** del código. Ticket y precuenta bilingües: el castellano está siempre en el documento. Ni numeración, ni importes, ni QR, ni registro Verifactu cambian (FR-021); el partial `verifactu-qr` no se toca. RGPD: solo `corregida_por` (mismo tipo que `abierta_por`); a DeepL solo viajan textos de la interfaz, **nunca** datos del negocio ni personales (los datos no pasan por `__()`). | ✅ |
| **III. Integridad financiera server-side** | Ningún importe pasa por la traducción; `bilingue()` y `__()` solo tocan etiquetas. Tests: el total, número y QR de un ticket en chino son idénticos al mismo ticket en español. | ✅ |
| **IV. Test-first en lógica crítica** | Aplica al aislamiento de correcciones y a "no altera importes/numeración/Verifactu": esos tests se escriben antes y fallan primero. El resto (UI, extractor, cliente DeepL) con tests de feature normales. | ✅ |
| **V. Simplicidad / hosting compartido** | Sin colas (respaldo en `terminating`), sin SDK, sin caché externa; extracción por regex acotada a un ámbito declarado. Única pieza "pesada": la fuente CJK (archivo estático + subsetting). | ✅ |

**Re-check post-diseño**: sin violaciones. Una justificación en Complexity Tracking (tabla central).

**Revisión obligatoria (Development Workflow)**: (1) scope de tenant en correcciones ✅, (2) tests de
aislamiento y de "no altera importes" planificados ✅, (3) inmutabilidad de facturas intacta ✅,
(4) sin complejidad fuera de alcance (solo POS, solo `es`/`zh`, sin idioma por usuario) ✅, (5) sin
tabla nueva de datos personales que exija purga (`corregida_por` es configuración del tenant) ✅.

## Documentación leída y convenciones aplicadas (regla de oro, CLAUDE.md)

Leídas antes de la spec: `.specify/memory/constitution.md`, `docs/00-vision.md`,
`docs/01-arquitectura.md` (Decisiones 9 y 10, Infraestructura), `docs/04-front-guidelines.md`
(índice completo y las secciones de la tabla). Normativa verificada: RD 1619/2012 art. 12 (BOE).

| Sección de `docs/04-front-guidelines.md` | Qué exige | Dónde se aplica |
|---|---|---|
| Ayuda contextual | contenido por archivo `ayuda/<slug>`, modal global | las 6 guías `ayuda/pos*.blade.php` se marcan con `__()` (bloques HTML, research D8); el texto «Ayuda de esta pantalla» del sidebar se traduce como entrada del grupo POS (FR-009) |
| Notificaciones | siempre toastr / `showToast` | los mensajes de los JS del POS pasan por `__t()`; los del servidor por `__()` |
| Listados: SIEMPRE DataTable | DataTable para listados | listado de tickets, cierres, opciones: su objeto `language` pasa por `__t()`; la nueva sección «Traducciones del POS» es una DataTable |
| DataTable: botones Anterior/Siguiente | override de ancho por vista | la DataTable nueva lo lleva; se verifica que con textos chinos los existentes no se rompen |
| CRUD simple: alta/edición en modal + AJAX | edición en modal | corregir una traducción (research D10) |
| Confirmación de acciones irreversibles | `confirmDelete` | «Restaurar automática» (borra la corrección) |
| Estado de carga en botones / Botones con markup interno | `withButtonLoading` | guardar corrección, cambiar idioma |
| Modales: siempre centrados | `modal-dialog-centered` | modal de corrección |
| Bloque QR normativo en PDF | partial único, no duplicar | el partial `verifactu-qr` no se modifica |
| «Ver» un documento… en modal | sin cambios de estructura | ticket/precuenta siguen igual, solo bilingües |
| Assets propios: siempre `@assetv` | versionado por mtime | `traduccion.js` (`__t`) |
| Peso y cacheo de assets | no inflar páginas | diccionario JS solo en vistas del POS y solo si el idioma no es español |
| Botonera del POS / chip de mesa / tarjetas de mesa | sin cambios de estructura | se valida que los textos en chino no desbordan (edge case) |
| Nueva entrada de menú ⇒ nuevo permiso | — | **no aplica**: la sección de traducciones vive en Configuración → POS, sin entrada de menú |

## Project Structure

### Documentation (this feature)

```text
specs/050-traduccion-pos/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── traducciones.md
├── checklists/
│   └── requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
config/traduccion.php                                    # idiomas, proveedor, glosario, no_traducir, ámbitos
database/migrations/
├── 2026_10_06_100000_create_traducciones_table.php
└── 2026_10_06_100100_create_traduccion_correcciones_table.php

app/
├── Models/Traduccion.php, TraduccionCorreccion.php      # central / tenant
├── Support/ConfigPos.php                                # + idioma()
├── Traduccion/
│   ├── ProveedorTraduccion.php                          # interfaz
│   ├── TraductorDeepl.php                               # Http + glosario + tag_handling
│   ├── CargadorTraducciones.php                         # TranslationLoader (BD + correcciones del tenant)
│   ├── ExtractorClaves.php                              # regex por ámbito
│   ├── MemoriaTraducciones.php                          # pendientes, traducir lote, respaldo terminating
│   └── Bilingue.php                                     # helper bilingue()
├── Console/Commands/SincronizarTraducciones.php         # traducciones:sincronizar
├── Http/Middleware/IdiomaPos.php
├── Http/Controllers/Configuracion/
│   ├── PosConfiguracionController.php                   # + idioma
│   └── TraduccionPosController.php                      # index / update / destroy
└── Providers/AppServiceProvider.php                     # registra loader, missing keys, terminating

resources/fonts/NotoSansSC-Regular.ttf, NotoSansSC-Bold.ttf
public/js/traduccion.js                                  # __t()
routes/web.php                                           # middleware idioma.pos en grupos POS + rutas de traducciones

# Marcado de textos (ámbito pos)
resources/views/pos/*.blade.php, pos/opciones/index.blade.php
resources/views/ayuda/pos*.blade.php
resources/views/facturas/ticket-80mm.blade.php, facturas/pdf.blade.php (solo simplificada), pos/precuenta-80mm.blade.php
resources/views/caja/informe-80mm.blade.php, caja/informe-a4.blade.php
resources/views/partials/sidebar.blade.php (botón de ayuda) + app/Support/MenuTenant.php (grupo POS)
resources/views/configuracion/_tab_pos.blade.php         # selector de idioma + sección Traducciones (en español)
public/js/pos-*.js, public/js/plugins-init/pos-*.js, configuracion-pos.init.js
app/Http/Controllers/PosController.php, Pos/*Controller.php, Http/Requests/*Pos*/*Caja*,
app/Services/CobradorCuenta.php, TransferidorCuenta.php, PrecuentaCuenta.php, AperturaCaja.php,
CierreCaja.php, MovimientosCaja.php, Exceptions de caja/ticket, Middleware ModuloHosteleriaActivo

tests/Feature/Traduccion/
├── CargadorTraduccionesTest.php        # español intacto; chino desde BD; corrección prevalece
├── AislamientoCorreccionesTest.php     # 2 tenants (TEST-FIRST)
├── SincronizarTraduccionesTest.php     # extracción, lotes, idempotencia, fallo/cupo, variables
├── RespaldoPrimerUsoTest.php           # clave nueva → pendiente tras la respuesta
├── IdiomaPosConfiguracionTest.php      # guardar idioma, sin módulo de hostelería
├── CorreccionTraduccionTest.php        # contrato index/update/destroy, saneado HTML, variables
├── PosEnChinoTest.php                  # pantallas del POS, menú, ayuda, mensajes del servidor
└── DocumentosBilinguesTest.php         # bilingüe + importes/número/QR idénticos (TEST-FIRST)
```

**Structure Decision**: monolito Laravel existente. El mecanismo de traducción vive en
`app/Traduccion/` porque no es del POS (servirá al resto de la app); lo específico del POS es el
ámbito declarado en `config/traduccion.php`, el middleware y el marcado de sus archivos.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Marcar textos es mecánico y extenso; se escapa alguno | test que recorre cada pantalla del POS en chino con un diccionario falso «⟦texto⟧» y busca texto español visible; respaldo de primer uso |
| Fuente CJK pesada en dompdf (memoria/tiempo la primera vez) | subsetting solo en esos PDFs; pre-generar métricas en el deploy; medir en el hosting (quickstart 6) |
| Textos chinos más cortos/largos rompen chips y botones | validación visual en el quickstart; reglas `white-space`/`flex-wrap` ya aplicadas en la 049 |
| Translator cachea lo cargado por instancia: en tests con 2 tenants en el mismo método puede mezclar | separar los escenarios multi-tenant en métodos distintos (memoria `feedback_test_session_permission_singleton`) o `app('translator')->setLoaded([])` |
| Glosario v2 de DeepL no admitiera `es→zh` | verificarlo al empezar (research D6); si no, aplicar los términos del glosario localmente antes/después de traducir |

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Tabla `traducciones` sin `tenant_id` (Principio I habla de "tabla de negocio") | Guarda textos de la propia aplicación ("Cobrar"), no datos de ningún tenant; compartirla es lo que hace que cada texto se traduzca una sola vez (SC-009) | Una copia por tenant multiplicaría llamadas y cupo por número de tenants y no aislaría nada sensible. Lo que sí es del tenant (idioma y correcciones) lleva `tenant_id` |
