# Implementation Plan: Emisión y recepción de facturas en formato Facturae

**Branch**: `022-facturae-emision-recepcion` | **Date**: 2026-07-05 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/022-facturae-emision-recepcion/spec.md`

## Summary

Añadir a cada tenant la capacidad de (1) **emitir** cualquier factura ya emitida como un XML
**Facturae 3.2.2** firmado con **XAdES-EPES** usando el certificado PKCS#12 del propio tenant,
conservarlo como archivo asociado a la factura y **enviarlo automáticamente por email** al cliente
reutilizando el flujo de envío existente (feature 017); y (2) **recibir** un Facturae de proveedor
importándolo a una `compra` (`origen=facturae`), guardando el XML recibido y permitiendo marcar el
**estado de ciclo B2B**. Se apoya en `josemmo/facturae-php` (PHP puro, MIT, sin dependencias
nativas más allá de `openssl`/`dom` — compatible con hosting compartido, Principio V) para generar,
firmar, validar e importar Facturae. Prerrequisito descubierto: las columnas de menciones
especiales en `factura_lineas` y las de recepción en `compras` están documentadas pero **aún no
existen en el esquema**; esta feature las crea vía migración.

## Technical Context

**Language/Version**: PHP 8.2, Laravel 12

**Primary Dependencies**: `josemmo/facturae-php` (generación/firma/validación/importación Facturae,
MIT, PHP puro); `barryvdh/laravel-dompdf` (ya presente, para el PDF del email); extensiones PHP
estándar `openssl`, `dom`, `libxml` (disponibles en el hosting objetivo). Envío de email vía
`TenantMailer` + `App\Mail\FacturaMail` existentes (feature 017). Almacenamiento de ficheros vía
`App\Services\AlmacenArchivos` + disco privado `documentos` existente.

**Storage**: MySQL/MariaDB. Nuevas columnas en `factura_lineas` (menciones) y `compras` (recepción)
vía migraciones. Certificado del tenant: archivo `.p12`/`.pfx` en disco privado `documentos`
(particionado por tenant) + contraseña **cifrada** en `configuraciones` (patrón `EmailTenant`/`Crypt`).
XML Facturae emitido/recibido: disco privado `documentos`.

**Testing**: PHPUnit (Pest-style feature/unit tests como el resto del repo). `Http::preventStrayRequests()`
ya activo en `tests/TestCase.php`; VIES se mockea con `Http::fake()`.

**Target Platform**: Hosting compartido tipo cPanel/Hostinger (Principio V).

**Project Type**: Aplicación web Laravel multi-tenant (single-database, `stancl/tenancy`).

**Performance Goals**: Generación/firma de un Facturae de tamaño típico (≤ ~100 líneas) percibida
como inmediata por el usuario (< ~3 s incluida la firma). VIES y envío de email no bloquean la
respuesta si están indisponibles (se degradan con aviso).

**Constraints**: Sin dependencias que requieran VPS/extensiones nativas exóticas (Principio V). La
firma y todo cálculo ocurren en backend (Principio III). Aislamiento por tenant en certificados,
XML y compras (Principio I). Los XML contienen datos personales → retención/purga (Principio II).

**Scale/Scope**: Escala de un SaaS de facturación pyme; volumen por tenant de decenas a cientos de
facturas/mes. Alcance: 4 historias (P1 emisión+firma+envío, P2 recepción, P3 estado B2B, P3
validación fiscal/VIES).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Aislamiento Multi-Tenant (NON-NEGOTIABLE)**: PASS. El certificado, los XML emitidos/recibidos
  y las compras importadas llevan `tenant_id` y pasan por el scope de tenant. Se añaden tests de
  aislamiento (un tenant no firma con el certificado de otro, no importa hacia otro, no descarga el
  XML de otro). Las columnas nuevas viven en tablas de negocio ya cubiertas por `TenantScope`.
- **II. Cumplimiento Normativo España-First**: PASS. Formato Facturae 3.2.2 y firma XAdES-EPES según
  `docs/02-facturacion-espana.md` §2; menciones especiales (S1/S2/N1/N2, E1–E6, mención legal)
  según §6; impuesto **agnóstico al régimen** (IVA/IGIC/IPSI congelado en la factura). Los XML
  contienen datos personales → sujetos al patrón de retención/purga (`RetencionLogsTenant`/comando
  programado) como exige el Principio II. Facturae es independiente de Verifactu (no se toca el
  encadenamiento). Cualquier cambio normativo se refleja primero en `docs/02`/`docs/03` (ya hecho).
- **III. Integridad Financiera Server-Side**: PASS. La generación, el mapeo de importes/desglose y
  la firma ocurren exclusivamente en un servicio backend a partir de la factura ya emitida
  (inmutable); el cliente nunca aporta importes. No se recalculan totales: se leen de la factura.
- **IV. Test-First en Lógica Crítica (NON-NEGOTIABLE)**: PASS con compromiso. Áreas críticas aquí:
  aislamiento de tenant y **mapeo fiel factura→Facturae** (importes/desglose/menciones) y
  parseo Facturae→compra. Estas se escriben test-first (fixtures de XML de referencia, aserción de
  validez de esquema y de equivalencia de importes). La validación NIF/CIF/NIE (dígito de control)
  es lógica pura y se escribe test-first.
- **V. Simplicidad y Compatibilidad con Hosting Compartido**: PASS. `josemmo/facturae-php` es PHP
  puro (solo `openssl`/`dom`), sin VPS ni servicio externo de firma; VIES se consume por HTTP (sin
  depender de la extensión `soap`). No se introduce infraestructura dedicada. Se reutilizan
  `TenantMailer`, `AlmacenArchivos`, `Configuracion` en vez de crear mecanismos nuevos.

**Resultado**: PASS. Sin violaciones que justificar → Complexity Tracking vacío.

## Project Structure

### Documentation (this feature)

```text
specs/022-facturae-emision-recepcion/
├── plan.md              # Este archivo
├── spec.md              # Especificación (con Clarifications)
├── research.md          # Fase 0 — decisiones técnicas
├── data-model.md        # Fase 1 — columnas nuevas y entidad certificado
├── quickstart.md        # Fase 1 — guía de validación end-to-end
├── contracts/
│   └── http.md          # Fase 1 — endpoints HTTP de la feature
└── checklists/
    └── requirements.md  # Checklist de calidad del spec
```

### Source Code (repository root)

```text
app/
├── Services/
│   ├── GeneradorFacturae.php        # factura emitida → XML Facturae 3.2.2 firmado (XAdES-EPES)
│   ├── ImportadorFacturae.php       # XML Facturae recibido → datos de compra (+ validación firma)
│   └── EnvioFacturae.php            # orquesta generar + guardar + enviar email (reusa TenantMailer/FacturaMail)
├── Support/
│   ├── CertificadoTenant.php        # lee/guarda .p12 (disco) + password (Crypt en Configuracion); metadatos/caducidad
│   ├── ValidadorIdentificacionFiscal.php  # NIF/CIF/NIE formato + dígito de control (lógica pura)
│   └── VerificadorVies.php          # consulta VIES por HTTP; null/aviso si indisponible
├── Http/Controllers/
│   ├── FacturaeController.php       # exportar/descargar/reenviar Facturae de una factura
│   ├── CompraFacturaeController.php # importar XML de proveedor; cambiar estado_b2b
│   └── ConfiguracionController.php  # (existente) + subida/validación del certificado del tenant
├── Mail/
│   └── FacturaMail.php              # (existente) — se adjunta también el XML Facturae
└── Enums/
    ├── CalificacionOperacion.php    # S1/S2/N1/N2
    ├── CausaExencion.php            # E1..E6
    ├── OrigenCompra.php             # manual/facturae/otro
    └── EstadoB2b.php                # recibida/aceptada/rechazada/pagada

database/migrations/
├── ****_add_menciones_to_factura_lineas_table.php   # calificacion_operacion, causa_exencion, mencion_legal
└── ****_add_recepcion_facturae_to_compras_table.php # origen, formato_recepcion, archivo_recibido_path, estado_b2b, estado_b2b_fecha

resources/views/
├── facturas/           # (existente) botón "Descargar/Enviar Facturae" en el detalle
├── compras/            # (existente) importar XML + selector de estado_b2b + aviso de firma
└── configuracion/      # (existente) formulario del certificado del tenant

tests/
├── Feature/
│   ├── FacturaeGeneracionTest.php   # validez esquema, firma, mapeo importes/menciones, aislamiento, envío email
│   ├── FacturaeRecepcionTest.php    # import → compra, proveedor inexistente, rechazo inválido, aviso firma, duplicado
│   ├── CompraEstadoB2bTest.php      # transición estado + fecha, filtrado
│   └── CertificadoTenantTest.php    # subida/validación/caducidad/aislamiento
└── Unit/
    ├── ValidadorIdentificacionFiscalTest.php  # NIF/NIE/CIF válidos e inválidos
    └── VerificadorViesTest.php      # Http::fake — válido/ inválido/ indisponible
```

**Structure Decision**: Se mantiene la estructura Laravel existente del repo (servicios en
`app/Services`, helpers/valor en `app/Support`, enums en `app/Enums`, controladores REST por
recurso). No se introducen carpetas ni capas nuevas. La generación/importación/firma vive en
servicios dedicados y testeables; los controladores solo orquestan. Los enums de menciones se
crean para tipar las columnas nuevas (consistente con `EstadoFactura`/`EstadoCompra`).

## Complexity Tracking

> Sin violaciones de la constitución que justificar. Sección intencionalmente vacía.
