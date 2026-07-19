# Data Model — Verifactu (feature 032)

**Fase**: 1 · **Fecha**: 2026-07-19 · **Plan**: [plan.md](./plan.md)

> La mayor parte del modelo **ya existe**: las columnas Verifactu se reservaron en la migración
> original de `facturas` (`2026_07_03_130001_create_facturas_table.php`) y `factura_eventos` existe
> desde la feature 005. Esta feature las **pone en uso** y añade una sola columna nueva.

---

## 1. `facturas` — columnas Verifactu (ya existentes, ahora en uso)

| Campo | Tipo | Estado | Notas |
|-------|------|--------|-------|
| `huella` | `varchar(64)` nullable | ya existe | SHA-256 hex del registro. `null` mientras la factura es borrador o si se emitió con el flag apagado. |
| `huella_anterior` | `varchar(64)` nullable | ya existe | Huella del registro anterior **del mismo tenant**. `null`/vacío en el primer eslabón de la cadena. |
| `qr_contenido` | `text` nullable | ya existe | URL de cotejo AEAT ya compuesta (lo que codifica el QR). Se guarda para no recomponerla en cada render del PDF y para que sea auditable. |
| `verifactu_estado` | `varchar` default `'pendiente'` | ya existe | Se pasa a enum `VerifactuEstado` en el modelo. |
| `registro_xml` | `longtext` nullable | ya existe | XML del registro (Orden HAC/1177/2024) tal cual se remitió. |
| `registrada_at` | `datetime` nullable | ya existe | Marca temporal del sellado local (no la del acuse de la AEAT). |

### Columna nueva (única migración de esta feature)

| Campo | Tipo | Notas |
|-------|------|-------|
| `verifactu_entorno` | `varchar(12)` nullable | `pruebas` \| `produccion`. Marca en qué entorno se generó el registro (FR-020). `null` en facturas emitidas sin Verifactu. |

### Índice nuevo

`(tenant_id, id)` filtrado sobre facturas con `huella` no nula — sirve para localizar el **último
eslabón de la cadena del tenant** de forma barata y con bloqueo. Se implementa como índice compuesto
`(tenant_id, registrada_at, id)`; MySQL/MariaDB no soporta índices parciales, así que el filtro
`whereNotNull('huella')` va en la query, no en el índice.

---

## 2. `factura_eventos` — uso Verifactu (tabla ya existente)

Append-only (nunca se edita ni se borra — constitución, *Additional Constraints*). Esta feature añade
tipos de evento nuevos y **empieza a poblar la columna `huella`**, que hasta ahora quedaba siempre
`null`.

| `tipo_evento` | Cuándo | `detalle` (JSON) | `huella` |
|---------------|--------|------------------|----------|
| `verifactu_alta` | Se sella el registro al emitir | `{ huella, huella_anterior, entorno }` | huella del registro |
| `verifactu_anulacion` | Se genera el registro de anulación | `{ huella, huella_anterior, motivo }` | huella del registro de anulación |
| `verifactu_enviado` | La AEAT acepta el registro | `{ csv?, acuse, intento }` | `null` (no encadena) |
| `verifactu_error` | Rechazo o fallo de transporte | `{ codigo, descripcion, intento, tipo: 'rechazo'\|'transporte' }` | `null` (no encadena) |

> Los eventos de **envío** no participan del encadenamiento: la cadena la forman los registros
> (alta/anulación), no los intentos de comunicación. Esto ya estaba anticipado en `docs/03`:
> "`huella` … `null` en eventos que no participan del encadenamiento Verifactu".

---

## 3. Estados y transiciones (`verifactu_estado`)

```
                 (flag apagado)
   [ sin registro: huella = null, estado = pendiente ]  ← estado terminal para esas facturas

                 (flag encendido, al emitir)
   pendiente ──sellado local OK──▶ registrada
                                       │
                                       ├── envío aceptado ──▶ enviada   (terminal)
                                       │
                                       └── rechazo / fallo ─▶ error
                                                               │
                                                               └── reintento ──▶ registrada → (enviada | error)
```

**Invariantes de estado**:
- `registrada`, `enviada` y `error` implican **siempre** `huella` no nula y `registrada_at` no nula.
- Pasar a `error` **nunca** modifica `huella` ni `huella_anterior` (FR-013/FR-014).
- No existe transición desde `enviada` hacia atrás: una vez aceptado por la AEAT, es terminal.
- Una factura emitida con el flag apagado se queda en `pendiente` con `huella = null` para siempre;
  encender el flag después **no** la registra retroactivamente (R5).

---

## 4. Entidad conceptual: cadena Verifactu del tenant

No es una tabla: es la secuencia de facturas del tenant con `huella` no nula, ordenada por el
momento de sellado, enlazada por `huella_anterior → huella`.

**Invariantes de la cadena** (lo que verifican los tests de `CadenaVerifactuTest`):

1. **Enlace**: para todo eslabón N > 1, `huella_anterior(N) === huella(N−1)` dentro del mismo tenant.
2. **Origen**: el primer eslabón del tenant tiene `huella_anterior` vacía.
3. **Unicidad de sucesor**: ninguna huella puede aparecer como `huella_anterior` de dos registros
   distintos (sin bifurcaciones). Garantizado por el bloqueo pesimista (R2).
4. **Aislamiento** (Principio I): ninguna `huella` de un tenant aparece como `huella_anterior` en
   otro tenant. Las cadenas son totalmente independientes.
5. **Inmutabilidad**: una vez escrita, ni `huella` ni `huella_anterior` se modifican jamás. Las
   facturas emitidas ya son inmutables por regla vigente.
6. **Homogeneidad de entorno**: todos los eslabones de una misma cadena comparten
   `verifactu_entorno` (FR-020).

---

## 5. Configuración por tenant (`configuraciones`, grupo `verifactu`)

| Clave | Tipo | Default | Notas |
|-------|------|---------|-------|
| `verifactu.activo` | bool | `false` | Flag único todo-o-nada (FR-000). Default `false` para no alterar el comportamiento de ningún tenant existente al desplegar. |
| `verifactu.entorno` | string | `pruebas` | `pruebas` \| `produccion`. No modificable si el tenant ya tiene cadena iniciada (FR-020). |

Se accede vía `App\Support\VerifactuTenant`, siguiendo el patrón de `ConfigTenant` /
`CertificadoTenant` / `EmailTenant` (clave/valor sobre `configuraciones` filtrado por `tenant_id`).

**Dependencia**: el **certificado** del tenant no se duplica aquí — se reutiliza el ya gestionado por
`App\Support\CertificadoTenant` (grupo `certificado`), introducido por la feature 022 (Facturae).

---

## 6. Datos que alimentan el registro (solo lectura)

El registro **no calcula nada nuevo**: lee valores ya congelados en la factura emitida
(Principio III). Origen de cada campo del registro:

| Campo del registro | Origen |
|--------------------|--------|
| NIF emisor | `tenants.nif` (datos fiscales del tenant) |
| NIF receptor | `facturas.cliente_nif` (nullable: simplificada variante simple) |
| Serie + número | `facturas.numero_completo` / `serie_id` + `numero` |
| Fecha expedición | `facturas.fecha_expedicion` |
| Tipo de factura | `facturas.tipo` + `es_rectificativa` (+ `tipo_rectificacion`) |
| Referencia a rectificada | `facturas.factura_rectificada_id` → su `numero_completo` y fecha |
| Base / cuota por tipo | `factura_impuestos` (desglose ya calculado) |
| Régimen impositivo | `facturas.regimen_impositivo` (IVA/IGIC/IPSI — agnóstico, Principio II) |
| Calificación operación | `factura_lineas.calificacion_operacion` (S1/S2/N1/N2) agregada por desglose |
| Causa de exención | `factura_lineas.causa_exencion` (E1–E6) |
| Importe total | `facturas.total` |
| Huella anterior | último eslabón de la cadena del tenant |

---

## 7. Resumen de cambios de esquema

- **1 migración nueva**: añadir `verifactu_entorno` a `facturas` + índice `(tenant_id, registrada_at, id)`.
- **0 tablas nuevas**: todo lo demás reutiliza `facturas`, `factura_eventos` y `configuraciones`.
- **Modelo `Factura`**: añadir `verifactu_estado` y `verifactu_entorno` a `casts()` (enums) y a
  `$fillable` donde corresponda; añadir helpers de lectura (¿tiene registro?, ¿es reintentable?).

> **Actualización obligatoria de `docs/03-modelo-datos.md`** al cerrar la feature: la tabla `facturas`
> gana `verifactu_entorno`, y la sección `factura_eventos` debe listar los nuevos `tipo_evento`.
