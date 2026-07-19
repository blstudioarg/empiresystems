# Implementation Plan: Verifactu — registro y remisión de facturas a la AEAT

**Branch**: `032-verifactu-registro-aeat` | **Date**: 2026-07-19 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/032-verifactu-registro-aeat/spec.md`

## Summary

Activar la maquinaria Verifactu (RD 1007/2023 · Orden HAC/1177/2024) sobre las columnas ya reservadas en `facturas` desde el diseño inicial. Gobernado por un **único flag por tenant** `verifactu.activo` (todo o nada): con el flag encendido, emitir una factura genera de forma atómica su **registro Verifactu** (huella SHA-256 encadenada a la del registro anterior del mismo tenant), su **XML de registro**, su **QR de cotejo AEAT** + texto "VERI*FACTU" en el PDF, y **remite el registro al web service de la AEAT** en el entorno configurado. Un fallo de la AEAT nunca bloquea la emisión: el registro local queda sellado y el envío es reintentable (automático + manual).

Enfoque técnico: un servicio dedicado (`RegistroVerifactu`) invocado desde el flujo de emisión existente (`EmisorFacturas::emitir()`, que ya cubre ordinaria/rectificativa/POS), con el encadenamiento serializado por tenant mediante bloqueo pesimista dentro de la transacción de emisión — mismo patrón ya usado por `NumeradorFacturas` para la numeración sin huecos. El envío a la AEAT se despacha como **job en cola** tras confirmar la transacción, de modo que la latencia y los fallos del servicio externo queden fuera del camino crítico de emitir.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 12

**Primary Dependencies**: `stancl/tenancy` (multi-tenant single-database), `barryvdh/laravel-dompdf` (PDF), `josemmo/facturae-php` ^1.8 (Facturae, ya presente — **no** cubre Verifactu en esta versión), certificado PKCS#12 por tenant vía `App\Support\CertificadoTenant`. **Nuevas**: librería de generación de QR (ver research R4) y cliente para el web service AEAT (ver research R3).

**Storage**: MySQL/MariaDB. Columnas Verifactu **ya existentes** en `facturas` (`huella`, `huella_anterior`, `qr_contenido`, `verifactu_estado`, `registro_xml`, `registrada_at`) — creadas por `2026_07_03_130001_create_facturas_table.php`. Tabla `factura_eventos` ya existente (append-only, con columna `huella`). Configuración por tenant en `configuraciones` (clave/valor por grupo).

**Testing**: Pest/PHPUnit (`php artisan test`). Test-first obligatorio en encadenamiento y aislamiento multi-tenant (Principio IV).

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V) + despliegue Railway (docs/05). Implica: sin dependencias que exijan VPS; la cola debe funcionar con driver `database`.

**Project Type**: Aplicación web monolítica Laravel (backend + Blade).

**Performance Goals**: Emitir una factura no debe degradarse de forma perceptible por Verifactu: el registro local (hash + XML + QR) es síncrono y del orden de milisegundos; la remisión a la AEAT es asíncrona (cola) y no cuenta en el tiempo de emisión.

**Constraints**: El encadenamiento debe ser serializable por tenant (sin huecos ni bifurcaciones bajo concurrencia). La AEAT es una dependencia externa no fiable: nunca puede bloquear ni revertir una emisión. Las facturas emitidas son inmutables.

**Scale/Scope**: Multi-tenant; cada tenant tiene su propia cadena independiente. Alcance: 3 tipos de factura (ordinaria, rectificativa, simplificada/POS), 2 formatos de PDF (A4 y ticket 80 mm), 2 entornos AEAT (pruebas/producción).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Cómo lo cumple este plan | Estado |
|-----------|--------------------------|--------|
| **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)** | La cadena de huellas se calcula y consulta siempre filtrando por `tenant_id`; `Factura` y `FacturaEvento` ya usan `BelongsToTenant`. El bloqueo de encadenamiento se toma **por tenant**. Tests obligatorios con ≥2 tenants demostrando que ninguna huella cruza cadenas. | ✅ PASS |
| **II. Cumplimiento Normativo España-First** | Es el objeto mismo de la feature: huella SHA-256, encadenamiento, QR, estado Verifactu, campos mínimos del registro, calificación de operación (S1/S2/N1/N2) y causa de exención (E1–E6) por desglose. Agnóstico al régimen (IVA/IGIC/IPSI) reutilizando `regimen_impositivo` ya congelado en la factura. Inmutabilidad de emitidas respetada. Fuentes AEAT/BOE citadas en research y volcadas a `docs/02` antes de codificar. | ✅ PASS |
| **III. Integridad Financiera Server-Side** | Todo el cálculo de huella/encadenamiento/XML ocurre en un **único servicio dedicado** (`RegistroVerifactu`) en backend, nunca en cliente ni duplicado. El encadenamiento usa transacción + bloqueo, igual que la numeración de serie. Ningún importe se recalcula: se leen los ya congelados en la factura emitida. | ✅ PASS |
| **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)** | El encadenamiento Verifactu está explícitamente nombrado en el principio. Los tests de huella, encadenamiento, concurrencia y aislamiento se escriben **antes** de la implementación (Red-Green-Refactor) y así se ordenan en `tasks.md`. | ✅ PASS |
| **V. Simplicidad y Compatibilidad con Hosting Compartido** | Sin infraestructura dedicada: cola con driver `database`, reintento vía comando programado en `withSchedule` (patrón ya usado por `logs:purgar`, `facturae:purgar`, etc.). Se reutiliza `CertificadoTenant` en vez de crear una gestión de certificados nueva. No se implementa el modo "No Veri*Factu" ni el ciclo B2B (fuera de alcance, YAGNI). | ✅ PASS |

**Additional Constraints check**: importes `DECIMAL(12,2)` y porcentajes `DECIMAL(5,2)` ya vigentes (no se tocan). Toda regla fiscal nueva cita fuente BOE/AEAT (research.md). `factura_eventos` se mantiene **append-only** como exige la constitución.

**Resultado del gate**: PASS sin violaciones. La sección *Complexity Tracking* queda vacía a propósito.

**Re-evaluación post-Phase 1**: PASS — el diseño no introdujo desviaciones. Las dependencias nuevas (librería QR + cliente del web service AEAT) se justifican en research R3/R4 y no exigen VPS ni extensiones no disponibles en hosting compartido.

## Project Structure

### Documentation (this feature)

```text
specs/032-verifactu-registro-aeat/
├── plan.md              # Este archivo
├── research.md          # Phase 0: algoritmo de huella, XML, endpoints AEAT, QR, cola
├── data-model.md        # Phase 1: campos, estados, transiciones, invariantes de cadena
├── quickstart.md        # Phase 1: cómo validar la feature end-to-end
├── contracts/           # Phase 1: contratos del registro, del QR y del servicio AEAT
│   ├── registro-verifactu.md
│   ├── qr-cotejo.md
│   └── aeat-webservice.md
├── checklists/
│   └── requirements.md  # Checklist de calidad de la spec (en verde)
└── tasks.md             # Phase 2 (/speckit-tasks — no lo crea /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── VerifactuEstado.php              # NUEVO: pendiente|registrada|enviada|error
│   └── EntornoVerifactu.php             # NUEVO: pruebas|produccion
├── Services/
│   ├── RegistroVerifactu.php            # NUEVO: huella + encadenamiento (servicio dedicado, Principio III)
│   ├── GeneradorXmlVerifactu.php        # NUEVO: mapea factura → XML Orden HAC/1177/2024
│   ├── RemisorVerifactu.php             # NUEVO: envío al web service AEAT + parseo de respuesta
│   └── EmisorFacturas.php               # MODIFICADO: invoca RegistroVerifactu dentro de la transacción
├── Support/
│   ├── VerifactuTenant.php              # NUEVO: flag activo + entorno (patrón ConfigTenant/CertificadoTenant)
│   ├── HuellaVerifactu.php              # NUEVO: cálculo SHA-256 según spec AEAT (puro, testeable)
│   └── QrVerifactu.php                  # NUEVO: URL de cotejo + render del QR
├── Jobs/
│   └── RemitirRegistroVerifactu.php     # NUEVO: envío asíncrono, reintentable
├── Console/Commands/
│   └── VerifactuReintentar.php          # NUEVO: reintento automático de envíos en error
├── Exceptions/
│   ├── VerifactuNoRegistrableException.php  # NUEVO
│   └── CadenaVerifactuRotaException.php     # NUEVO
├── Http/Controllers/
│   └── VerifactuController.php          # NUEVO: reintento manual + consulta de estado
└── Models/
    ├── Factura.php                      # MODIFICADO: casts de campos Verifactu + helpers
    └── FacturaEvento.php                # sin cambios estructurales

database/migrations/
└── 2026_07_XX_000000_add_entorno_to_facturas_verifactu.php  # NUEVO: columna `verifactu_entorno` (FR-020)

resources/views/
├── facturas/pdf.blade.php               # MODIFICADO: bloque QR + "VERI*FACTU"
├── facturas/ticket-80mm.blade.php       # MODIFICADO: idem, adaptado a 80 mm
├── partials/verifactu-qr.blade.php      # NUEVO: partial compartido por ambos PDF
├── configuracion/                       # MODIFICADO: sección Verifactu (flag + entorno)
└── ayuda/verifactu.blade.php            # NUEVO: guía in-app (capa 3 de documentación)

resources/ia/conocimiento/
└── verifactu.md                         # NUEVO: base de conocimiento del asistente IA (capa 4)

tests/
├── Unit/HuellaVerifactuTest.php         # Vector de prueba oficial AEAT
├── Feature/RegistroVerifactuTest.php    # Registro al emitir, atomicidad
├── Feature/CadenaVerifactuTest.php      # Encadenamiento, concurrencia, sin huecos
├── Feature/VerifactuAislamientoTenantTest.php  # ≥2 tenants, sin cruce (Principio I)
├── Feature/VerifactuFlagTest.php        # Flag apagado = sin huella/QR/envío
├── Feature/RemisionVerifactuTest.php    # enviada/error, reintento sin nuevo eslabón
└── Feature/VerifactuQrPdfTest.php       # QR + "VERI*FACTU" en A4 y ticket
```

**Structure Decision**: Se mantiene la estructura del monolito Laravel ya vigente (`app/Services` para lógica de negocio, `app/Support` para helpers puros y config por tenant, `app/Jobs` para trabajo asíncrono, Blade en `resources/views`). No se introduce ninguna capa ni módulo nuevo: `RegistroVerifactu` se suma al conjunto de servicios de facturación existentes (`EmisorFacturas`, `NumeradorFacturas`, `CalculadoraFactura`, `GeneradorFacturae`) siguiendo sus mismas convenciones. La separación `HuellaVerifactu` (cálculo puro) vs `RegistroVerifactu` (orquestación con estado) existe para poder testear el algoritmo de hash contra los vectores oficiales de la AEAT sin tocar base de datos.

## Complexity Tracking

> No aplica: el Constitution Check pasó sin violaciones. No se solicita ninguna desviación de los Principios I–V.
