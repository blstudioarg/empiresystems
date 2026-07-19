# Quickstart — validar Verifactu (feature 032)

**Fase**: 1 · **Feature**: 032 · Ver [plan.md](./plan.md)

Guía para comprobar end-to-end que la feature funciona. No contiene implementación: para el detalle
de contratos ver [contracts/](./contracts/) y para el esquema [data-model.md](./data-model.md).

## Prerrequisitos

- Entorno local levantado con un tenant de prueba (ver `ACCESOS.local.md`, seeder
  `AccesoPersonalSeeder`).
- Migraciones al día (`php artisan migrate`).
- Cola procesable: `QUEUE_CONNECTION=database` y un worker (`php artisan queue:work`) o
  `QUEUE_CONNECTION=sync` para validación manual rápida.
- Para el envío real a la AEAT: certificado `.p12` del tenant cargado en Configuración (feature 022)
  y `verifactu.entorno = pruebas`. **Nunca validar contra el entorno de producción.**

## 1. Tests automatizados (la vía principal)

```bash
php artisan test --filter=Verifactu
```

Debe cubrir, en verde:

| Test | Qué demuestra |
|------|---------------|
| `HuellaVerifactuTest` | El hash coincide con el **vector oficial de la AEAT** (no con un valor inventado). |
| `RegistroVerifactuTest` | Emitir sella el registro; si el sellado falla, no queda factura emitida ni eslabón (atomicidad, FR-001). |
| `CadenaVerifactuTest` | Encadenamiento correcto, primer eslabón sin anterior, sin huecos ni bifurcaciones bajo concurrencia. |
| `VerifactuAislamientoTenantTest` | Con ≥2 tenants, ninguna huella cruza cadenas (Principio I). |
| `VerifactuFlagTest` | Con el flag apagado: sin huella, sin QR, sin llamadas a la AEAT. |
| `RemisionVerifactuTest` | Aceptación → `enviada`; rechazo/timeout → `error`; reintento preserva huella y no añade eslabón. |
| `VerifactuQrPdfTest` | QR + "VERI*FACTU" presentes en A4 y en ticket 80 mm. |

## 2. Validación manual en la app

### 2.1 Flag apagado (comportamiento actual intacto)

1. Con `verifactu.activo = false`, emitir una factura ordinaria.
2. **Esperado**: la factura se emite normal; sin QR ni "VERI*FACTU" en el PDF; `huella` vacía; ninguna
   llamada a la AEAT.

### 2.2 Encender el flag y emitir

1. Configuración → Verifactu: activar el flag, entorno `pruebas`.
2. Emitir una factura ordinaria.
3. **Esperado**: la factura queda con huella, `verifactu_estado = registrada` (y `enviada` si el
   worker procesó el envío), y el PDF muestra QR + "VERI*FACTU".
4. Emitir una segunda factura y comprobar que su `huella_anterior` es la `huella` de la primera.

### 2.3 Los tres tipos de documento

Repetir 2.2 con: una **rectificativa** (desde una factura emitida) y un **ticket del POS**
(simplificada). Ambos deben encadenar en la misma cadena del tenant y mostrar el QR — el ticket en su
formato de 80 mm.

### 2.4 Fallo de la AEAT no bloquea emitir

1. Forzar un fallo de envío (certificado ausente, o entorno inalcanzable).
2. Emitir una factura.
3. **Esperado**: la factura **se emite igualmente** y queda `registrada`/`error`, con la huella
   sellada y el QR presente. El error aparece en el historial de la factura.

### 2.5 Reintento

1. Sobre la factura en `error`, usar la acción de reintento manual.
2. **Esperado**: se reenvía el mismo registro; `huella` y `huella_anterior` **no cambian**; el estado
   se actualiza según la respuesta; se añade un evento nuevo sin borrar los anteriores.
3. Verificar también la vía automática ejecutando el comando programado:
   ```bash
   php artisan verifactu:reintentar
   ```

### 2.6 Aislamiento entre tenants

Emitir facturas en dos tenants distintos con el flag activo y comprobar que cada uno mantiene su
propia cadena, sin que ninguna huella aparezca en la cadena del otro.

## 3. Verificación de la cadena

Comprobar la integridad de la cadena de un tenant: recorrer sus facturas con huella ordenadas por
sellado y validar que cada `huella_anterior` coincide con la `huella` previa (invariantes 1–6 de
[data-model.md](./data-model.md) §4).

## 4. Antes de dar la feature por cerrada

Las **cuatro capas** de documentación (CLAUDE.md):

- [ ] `docs/02-facturacion-espana.md` — algoritmo de huella, formato del QR y endpoints, con fuente AEAT/BOE citada.
- [ ] `docs/03-modelo-datos.md` — nueva columna `verifactu_entorno` y nuevos `tipo_evento`.
- [ ] `resources/views/ayuda/verifactu.blade.php` — guía in-app del usuario.
- [ ] `resources/ia/conocimiento/verifactu.md` — base de conocimiento del asistente IA.
