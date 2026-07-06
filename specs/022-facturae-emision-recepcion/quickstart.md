# Quickstart — Validación de Facturae emisión y recepción

Guía para validar end-to-end que la feature funciona. No incluye código de implementación (eso va
en `tasks.md` / la fase de implementación). Referencias: [spec.md](./spec.md),
[data-model.md](./data-model.md), [contracts/http.md](./contracts/http.md),
[research.md](./research.md).

## Prerrequisitos

- Dependencia instalada: `composer require josemmo/facturae-php` (ver R1).
- Migraciones aplicadas: columnas nuevas en `factura_lineas` y `compras` (ver data-model §1–§2).
- Extensiones PHP `openssl`, `dom`, `libxml` disponibles (estándar).
- Un **certificado de prueba** `.p12` con su contraseña (puede ser autofirmado para los tests de
  generación/firma; la validación de esquema no depende de la cadena de confianza).
- Fixtures de test: al menos un XML **Facturae 3.2.2 de proveedor** válido, uno inválido (esquema
  roto) y uno válido con firma no verificable, bajo `tests/Fixtures/facturae/`.

## Escenario 1 — Configurar el certificado del tenant (FR-010/011/012)

1. Autenticarse en un tenant. Ir a Configuración → Certificado.
2. Subir el `.p12` con su contraseña correcta.
3. **Esperado**: se guarda; se muestran titular y fecha de caducidad; la password no vuelve al
   front en claro. Subir con password incorrecta o un `.p12` caducado → error claro, no se guarda.

## Escenario 2 — Emitir y descargar Facturae firmado (US1, FR-001..007)

1. Con una factura en estado `emitida` (ordinaria, varios tipos de IVA).
2. `GET /facturas/{factura}/facturae` (botón "Descargar Facturae").
3. **Esperado**: descarga un XML que (a) **valida contra el esquema Facturae 3.2.2**, (b) refleja
   cabecera, líneas, desglose por tipo y totales de la factura, (c) tiene firma XAdES-EPES
   verificable con el certificado del tenant.
4. Repetir la descarga → devuelve el **mismo** archivo conservado (no se regenera).
5. Con un tenant **sin** certificado → la exportación se bloquea con aviso (FR-007).

## Escenario 3 — Menciones especiales en el XML (US1, FR-004, SC-003)

1. Factura con una línea **ISP** (`calificacion_operacion=S2`, cuota 0) y una línea **exenta
   intracomunitaria** (`causa_exencion=E5`, art. 25 LIVA).
2. Exportar a Facturae.
3. **Esperado**: el XML marca la línea ISP como sujeta sin repercutir cuota, con su mención legal;
   la línea E5 como exenta con su causa/artículo; el desglose agrega correctamente la mezcla.
4. Repetir con régimen **IGIC** (Canarias) → el XML refleja IGIC, no IVA (agnóstico al régimen).

## Escenario 4 — Envío automático por email (US1, FR-006a)

1. Con SMTP del tenant configurado (feature 017) y un cliente con email.
2. `POST /facturas/{factura}/facturae` (generar y enviar).
3. **Esperado**: se envía el email con **XML + PDF** adjuntos; se registra `FacturaEvento`
   `envio_facturae`. Si el cliente no tiene email o el SMTP falla → el XML queda generado/conservado
   y se avisa que el envío quedó pendiente (no se pierde la generación).

## Escenario 5 — Importar Facturae de proveedor (US2, FR-013..018)

1. `POST /compras/importar-facturae` con un XML de proveedor válido.
2. **Esperado**: se crea una `compra` con `origen=facturae`, líneas/importes volcados, proveedor
   asociado por NIF, `archivo_recibido_path` guardado, `estado_b2b=recibida`.
3. Importar un archivo inválido (esquema roto o importes incoherentes) → **rechazo** sin crear
   compra, mensaje claro.
4. Importar un XML válido con **firma no verificable** → se crea la compra con **aviso visible** de
   firma (FR-016a).
5. Reimportar el mismo XML → aviso de **posible duplicado**, no se crea otra compra (FR-018).
6. Importar un XML de un proveedor **no registrado** → se ofrece crear el proveedor con los datos
   del XML antes de completar (FR-015).
7. Desde el detalle de la compra, descargar el **XML original** recibido (FR-017).

## Escenario 6 — Estado de ciclo B2B (US3, FR-019/020)

1. Sobre una compra `origen=facturae` en estado `recibida`, `PATCH /compras/{compra}/estado-b2b`
   con `aceptada`.
2. **Esperado**: `estado_b2b=aceptada`, `estado_b2b_fecha=now()`.
3. En `GET /compras`, filtrar por estado B2B y ver la compra en el estado correcto.

## Escenario 7 — Validación fiscal y VIES (US4, FR-021/022)

1. Editar un cliente con un NIF de dígito de control incorrecto e intentar exportar su factura a
   Facturae → **bloqueo** con aviso de NIF inválido.
2. Para una entrega intracomunitaria (E5), lanzar la verificación **VIES** del NIF-IVA →
   resultado válido/ inválido; con VIES **indisponible**, se informa sin bloquear irreversiblemente.

## Aislamiento multi-tenant (Principio I) — obligatorio en tests

- Crear ≥2 tenants. Verificar que el tenant A **no** puede: firmar con el certificado de B, importar
  hacia una compra de B, ni descargar el XML (emitido o recibido) de B. 0 fugas.

## Comandos de validación

```bash
# Ejecutar la suite de la feature
php artisan test --filter=Facturae
php artisan test --filter=CompraEstadoB2b
php artisan test --filter=CertificadoTenant
php artisan test --filter=ValidadorIdentificacionFiscal
php artisan test --filter=VerificadorVies
```

Criterios de aceptación medibles → ver Success Criteria en [spec.md](./spec.md) (SC-001..SC-007).
