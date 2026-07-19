# Contrato — `RegistroVerifactu` (servicio de sellado)

**Fase**: 1 · **Feature**: 032 · Ver [plan.md](../plan.md), [research.md](../research.md) R1/R2.

Único punto del sistema donde se calcula huella y encadenamiento (Principio III).

## Interfaz

```
RegistroVerifactu::registrar(Factura $factura): Factura
```

**Precondiciones**:
- `$factura->estado === EstadoFactura::Emitida` (se invoca dentro del acto de emisión, después de
  asignar número).
- `VerifactuTenant::activo($factura->tenant_id) === true`. Si es `false`, el servicio **no se invoca**
  (responsabilidad del llamador, `EmisorFacturas`).
- Se ejecuta **dentro de una transacción** ya abierta por el llamador.
- El NIF del emisor (tenant) es válido — si no, lanza `VerifactuNoRegistrableException` (FR-018).

**Postcondiciones** (todas o ninguna, por la transacción del llamador):
- `huella` ← SHA-256 hex del registro.
- `huella_anterior` ← huella del último eslabón del tenant (vacía si es el primero).
- `registro_xml` ← XML del registro de alta.
- `qr_contenido` ← URL de cotejo AEAT compuesta.
- `verifactu_estado` ← `registrada`.
- `verifactu_entorno` ← entorno configurado del tenant.
- `registrada_at` ← momento del sellado.
- Se crea un `FacturaEvento` `verifactu_alta` con la huella.

**Garantías**:
- El último eslabón se lee con bloqueo pesimista filtrando por `tenant_id` (R2), de modo que dos
  emisiones concurrentes del mismo tenant no obtienen la misma `huella_anterior`.
- Nunca modifica una factura ya registrada (idempotencia defensiva: si `huella` ya existe, lanza
  `VerifactuNoRegistrableException` en vez de re-sellar).

**Errores**:

| Excepción | Cuándo |
|-----------|--------|
| `VerifactuNoRegistrableException` | Factura no emitida, NIF del emisor inválido, o factura ya registrada. |
| `CadenaVerifactuRotaException` | El entorno configurado no coincide con el de la cadena existente del tenant (FR-020). |

## Anulación

```
RegistroVerifactu::registrarAnulacion(Factura $factura, string $motivo): Factura
```

Mismo mecanismo de encadenamiento, con el conjunto de campos del registro de anulación (research R1)
y evento `verifactu_anulacion`.

## Helper puro asociado

```
HuellaVerifactu::alta(array $campos, string $huellaAnterior): string
HuellaVerifactu::anulacion(array $campos, string $huellaAnterior): string
```

Sin dependencias de base de datos ni de Eloquent: recibe primitivos y devuelve el hash hex. Es lo que
se testea contra los **vectores oficiales de la AEAT** (`tests/Unit/HuellaVerifactuTest.php`).
