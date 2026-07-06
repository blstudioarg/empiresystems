# Data Model — Facturae emisión y recepción (Fase 1)

Solo se documentan los cambios/entidades de esta feature. El modelo base de `facturas`,
`factura_lineas`, `factura_impuestos`, `compras`, `compra_lineas`, `proveedores` y `configuraciones`
ya existe. Alineado con `docs/03-modelo-datos.md` (que ya describe estas columnas).

## 1. `factura_lineas` — columnas nuevas (menciones especiales)

Migración `ALTER TABLE` (nullable/con default para no romper líneas existentes). Ver
`docs/02-facturacion-espana.md` §6.

| Columna | Tipo | Notas |
|---------|------|-------|
| `calificacion_operacion` | enum (`S1`,`S2`,`N1`,`N2`) | Default `S1` (sujeta, sin ISP). `S2` = sujeta con inversión del sujeto pasivo. `N1`/`N2` = no sujeta. Tipado por `App\Enums\CalificacionOperacion`. |
| `causa_exencion` | enum (`E1`..`E6`), nullable | Solo cuando la línea es exenta. Mapea al artículo LIVA (E1=art.20, E2=art.21, E3=art.22, E4=arts.23/24, E5=art.25, E6=otra). Tipado por `App\Enums\CausaExencion`. |
| `mencion_legal` | varchar(255), nullable | Texto de la mención para el PDF/Facturae (p. ej. "Inversión del sujeto pasivo, art. 84.Uno.2º LIVA"). Autogenerable desde calificación/causa. |

**Reglas de validación / invariantes**:
- Con `calificacion_operacion = S2` (ISP): `cuota_impuesto` de la línea es 0, pero la operación
  **está sujeta** (no exenta) → `causa_exencion` debe ser `null`.
- Con `causa_exencion` no nula (exenta): `cuota_impuesto` es 0 y `calificacion_operacion` refleja la
  exención (no `S1`/`S2`). E5 requiere NIF-IVA del cliente (verificable en VIES) para justificarse.
- Una factura puede **mezclar** líneas sujetas (S1), con ISP (S2) y exentas (E1–E6); el desglose
  `factura_impuestos` se agrega respetando esa mezcla.
- Estas columnas se **congelan** al emitir junto con el resto de la línea (factura emitida inmutable,
  Principio II).

## 2. `compras` — columnas nuevas (recepción electrónica)

Migración `ALTER TABLE`. Ver `docs/03-modelo-datos.md` (tabla `compras`) y
`docs/06-kit-digital.md`.

| Columna | Tipo | Notas |
|---------|------|-------|
| `origen` | enum (`manual`,`facturae`,`otro`) | Default `manual`. `facturae` = creada al importar un XML recibido. Tipado por `App\Enums\OrigenCompra`. |
| `formato_recepcion` | varchar(20), nullable | `facturae`, `ubl`, `cii`… cuando `origen != manual`. En v1 solo `facturae`. |
| `archivo_recibido_path` | varchar, nullable | Ruta (disco privado `documentos`) del XML recibido del proveedor. Se conserva. |
| `estado_b2b` | enum (`recibida`,`aceptada`,`rechazada`,`pagada`), nullable | Ciclo comercial del lado receptor. `null` para compras manuales que no lo usan. Tipado por `App\Enums\EstadoB2b`. |
| `estado_b2b_fecha` | datetime, nullable | Fecha del último cambio de `estado_b2b` (reportable en 4 días hábiles). |

**Índice nuevo**: `(tenant_id, estado_b2b)` para listar/filtrar por estado (ya previsto en docs).

**Reglas de validación / invariantes**:
- `origen=facturae` ⇒ `formato_recepcion` y `archivo_recibido_path` no nulos; `estado_b2b` inicia en
  `recibida` con su fecha.
- Cambiar `estado_b2b` actualiza `estado_b2b_fecha` a `now()`.
- La compra importada reutiliza el ciclo `borrador → confirmada/anulada` existente (una factura
  recibida es una factura de compra); `estado_b2b` es ortogonal al `estado` de stock/compra.
- Detección de duplicado antes de crear: mismo `tenant_id` + `proveedor_id` (por NIF) +
  `numero_documento` + `fecha`.

## 3. Certificado del emisor (tenant) — no es tabla nueva

Se modela reutilizando `configuraciones` (grupo `certificado`) + disco privado, gestionado por
`App\Support\CertificadoTenant`. No se crea tabla dedicada (patrón `EmailTenant`).

| Clave (`configuraciones`, grupo `certificado`) | Contenido |
|-----------------------------------------------|-----------|
| `certificado.archivo_path` | Ruta del `.p12`/`.pfx` en disco privado `documentos` (por tenant). |
| `certificado.password` | Contraseña del `.p12`, **cifrada** con `Crypt` (nunca en claro, nunca al front). |
| `certificado.titular` | Titular/CN leído del certificado (display). |
| `certificado.caduca_at` | Fecha de caducidad (para avisar/bloquear). |

**Reglas**:
- Uno vigente por tenant. Aislado por `tenant_id` (Principio I).
- Al configurarlo se valida: la password abre el `.p12`, hay clave privada usable, no está caducado.
- Un tenant sin certificado válido (ausente/caducado) **no puede** generar Facturae (FR-007).
- El `.p12` y la password son **datos sensibles**; el XML firmado y los XML recibidos contienen
  datos personales → sujetos a retención/purga por tenant (Principio II).

## 4. Archivos Facturae emitidos — asociación

El XML firmado emitido se conserva por factura. Se reutiliza el patrón de documento asociado (disco
privado `documentos`). Asociación 1:1 lógica con la factura (misma factura ⇒ mismo archivo
recuperable; regenerar reutiliza/actualiza el mismo destino). Registrado como `FacturaEvento`
(`tipo_evento = 'facturae_generado'` / `'envio_facturae'`) para trazabilidad, coherente con
`factura_eventos` existente.

## Entidades y relaciones (resumen)

- `Factura 1—N FacturaLinea` (con menciones) `—` `FacturaImpuesto` (desglose): fuente del Facturae
  emitido.
- `Tenant 1—1 Certificado` (vía `configuraciones`): material de firma.
- `Proveedor 1—N Compra` (con `origen=facturae`, XML recibido, `estado_b2b`): destino de la
  recepción.
