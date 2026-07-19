# Contrato — Remisión al web service de la AEAT

**Fase**: 1 · **Feature**: 032 · Ver [research.md](../research.md) R3/R6.

## Interfaz

```
RemisorVerifactu::remitir(Factura $factura): ResultadoRemision
```

**Precondiciones**: la factura tiene registro sellado (`huella` y `registro_xml` no nulos).

**Transporte**: sobre SOAP al endpoint de la AEAT correspondiente a `verifactu.entorno`,
autenticado con **TLS mutuo** usando el certificado PKCS#12 del tenant
(`CertificadoTenant::paraFirmar($tenantId)`).

## Resultado

```
ResultadoRemision {
    estado: VerifactuEstado   // enviada | error
    codigo: ?string           // código devuelto por la AEAT
    descripcion: ?string      // descripción del error/acuse
    csv: ?string              // código seguro de verificación del acuse, si lo hay
    tipoError: ?string        // 'rechazo' | 'transporte'
}
```

**Mapeo de respuestas**:

| Respuesta AEAT | Estado resultante | Reintentable |
|----------------|-------------------|--------------|
| Aceptado correctamente | `enviada` | — (terminal) |
| Aceptado con errores que no afectan al registro | `enviada` (con aviso en el evento) | — |
| Rechazado (error funcional del registro) | `error` (`tipoError = rechazo`) | Sí, pero reintentar no lo arregla solo: requiere corregir la causa |
| Timeout / TLS / 5xx / red | `error` (`tipoError = transporte`) | Sí, es el caso típico del reintento automático |
| Certificado ausente o inválido | `error` | Sí, tras configurar el certificado (FR-018: no bloquea emitir) |

Cada resultado escribe un `FacturaEvento` (`verifactu_enviado` o `verifactu_error`) con su detalle.

## Invariante crítica (FR-013/FR-014)

La remisión **nunca** toca `huella`, `huella_anterior`, `registro_xml` ni `registrada_at`.
Solo cambia `verifactu_estado` y añade eventos. Un rechazo de la AEAT **no** deshace el registro
local ni la emisión.

## Orquestación asíncrona

```
Jobs\RemitirRegistroVerifactu (queued)
```

Despachado con `DB::afterCommit()` desde el flujo de emisión, para que solo se envíe lo confirmado en
base de datos. Reintentos:

- **Automático**: comando `verifactu:reintentar`, programado en `bootstrap/app.php` → `withSchedule`,
  reencola las facturas en `verifactu_estado = error`. Con tope de intentos para no insistir
  indefinidamente sobre un rechazo funcional.
- **Manual**: acción del usuario sobre la factura (`VerifactuController`).

Ambas vías reenvían el **mismo registro sellado**, sin recalcular huella ni crear eslabón nuevo.

## Testing

El transporte se **mockea** en los tests (nunca se llama a la AEAT real). Se cubren: aceptación,
rechazo funcional, fallo de transporte, y que el reintento preserva huella/`huella_anterior` intactas
y no añade eslabones a la cadena.
