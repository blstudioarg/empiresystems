# Contract: Dashboard con filtro por rango

La carga inicial del dashboard es server-rendered (Blade); el cambio de rango recarga **solo el
contenido por AJAX** (misma ruta con `Accept: application/json`) sin navegar de página. El "contrato"
son (1) los parámetros de consulta que acepta la ruta, (2) sus dos modos de respuesta (HTML / JSON) y
(3) la firma del servicio de estadísticas.

## HTTP — `GET /` (ruta `dashboard`)

### Query parameters

| Param | Requerido | Valores | Notas |
|-------|-----------|---------|-------|
| `preset` | No | `mes` \| `trimestre` \| `anio` \| `personalizado` | Ausente ⇒ `mes` (mes en curso). |
| `desde` | Solo si `preset=personalizado` | Fecha `Y-m-d` | Inicio inclusive. |
| `hasta` | Solo si `preset=personalizado` | Fecha `Y-m-d` | Fin inclusive. |

### Modos de respuesta según `Accept`

- **HTML (carga inicial / recarga directa de la URL)**: renderiza `dashboard.blade.php` completo (barra
  de filtro + `partials/dashboard-contenido.blade.php`). Un rango inválido añade un flash `warning`
  (toastr vía `partials/flash-toastr.blade.php`).
- **JSON (`Accept: application/json`, usado por el filtro AJAX)**: **HTTP 200** con
  `{ html, rango, graficos, aviso }`:
  - `html`: el parcial `partials/dashboard-contenido.blade.php` ya renderizado (todo salvo la barra de
    filtro), para sustituir `#dashboard-contenido` en el cliente.
  - `rango`: eco del rango aplicado (`{ preset, desde, hasta, etiqueta }`) para re-sincronizar la barra.
  - `graficos`: `{ serie_facturacion, comparativo, distribucion_estados }` para reconstruir los charts.
  - `aviso`: mensaje de rango inválido (string) o `null`. En modo JSON **no** se usa flash de sesión; el
    front dispara el toast con `window.showToast('warning', aviso)`.

### Reglas de respuesta (comunes a ambos modos)

- Sin params ⇒ mes en curso (comportamiento equivalente al dashboard actual). **[FR-006]**
- `preset` de "en curso" ⇒ desde el inicio del periodo natural hasta hoy. **[FR-006]**
- `preset=personalizado` con `desde`/`hasta` válidos y `hasta >= desde` ⇒ ese rango. **[FR-008]**
- Rango inválido (fechas mal, `hasta < desde`, preset desconocido) ⇒ **HTTP 200** con el mes en curso y
  un aviso `warning`. Nunca error de página ni 422. **[FR-009, SC-004]**
- El rango aplicado se refleja en la URL (el filtro AJAX usa `history.pushState`; recargar/compartir la
  URL reproduce el rango vía el modo HTML). **[FR-010]**
- Todos los datos scoped al tenant activo. **[FR-018]**

### Ejemplos

```
GET /                                   → mes en curso (HTML)
GET /?preset=trimestre                  → trimestre en curso (inicio de trimestre → hoy)
GET /?preset=anio                       → año en curso (1 ene → hoy)
GET /?preset=personalizado&desde=2026-01-01&hasta=2026-03-31  → Q1 2026
GET /?preset=personalizado&desde=2026-05-01&hasta=2026-04-01  → inválido → mes en curso + aviso
GET /?preset=trimestre  (Accept: application/json)            → { html, rango, graficos, aviso } (filtro AJAX)
```

## Servicio — `DashboardEstadisticas::resumen(RangoFechas $rango): array`

- **Entrada**: un `RangoFechas` (nunca null; el controller siempre construye uno, con fallback a
  `mesEnCurso()`).
- **Salida**: array con las claves documentadas en [data-model.md](../data-model.md) (`rango`, `kpis`,
  `impuestos`, `serie_facturacion`, `comparativo`, `distribucion_estados`, `top_clientes`,
  `alertas_stock`, `facturas_recientes`).
- **Invariantes de cálculo** (verificables por test):
  - `kpis.facturado.valor` = Σ `totalCobrable()` de facturas facturables (no simplificadas, no
    rectificativas, estado facturado) con `fecha_expedicion` en el rango. **[FR-001, FR-002, FR-004b]**
  - Una operación 100 € rectificada por sustitución a 75 € ⇒ aporta **75**, no 175. **[SC-001]**
  - Una operación 100 € rectificada por diferencias (delta −25) ⇒ aporta **75** (neto). **[SC-001]**
  - Borradores, anuladas y simplificadas ⇒ **no** aportan al facturado. **[FR-002, FR-004]**
  - `kpis.ventas_pos` = Σ `total` de simplificadas emitidas en el rango; **no** entra en `facturado`.
  - `kpis.resultado` = `kpis.facturado.valor − kpis.gastos.valor`.
  - `impuestos.repercutido` neteado igual que el facturado; `impuestos.soportado` = Σ
    `cuota_impuesto_total` de compras confirmadas del rango; `impuestos.etiqueta` según régimen del tenant.
  - `top_clientes` ordenado por facturado **neto** del rango, máx. 5.
  - `pendiente_cobro` y `alertas_stock` **no** dependen del rango (estado a hoy). **[FR-012]**
  - Variaciones `variacion_pct` comparan contra `rango.anterior()` (igual duración). **[FR-011]**

## Backwards compatibility

El renombrado de claves de salida (`facturado_mes` → `kpis.facturado`, `serie_facturacion_12_meses` →
`serie_facturacion`, `comparativo_6_meses` → `comparativo`) es un cambio interno consumido solo por
`dashboard.blade.php` / `partials/dashboard-contenido.blade.php` y `DashboardTest`. Todos se actualizan
en esta feature. No hay API pública ni otros consumidores.
