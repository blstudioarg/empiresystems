# Contrato — Informes comerciales (033)

Interfaz HTTP de la sección. Todas las rutas viven bajo `tenant.context` + `auth` y exigen
`ver-informes-comerciales`.

---

## `GET /informes-comerciales`

Pantalla del informe. Acepta los mismos parámetros que la variante JSON.

- **200** con la vista renderizada.
- **403** si el usuario no tiene `ver-informes-comerciales`, o si no tiene ninguno de
  `ver-leads` / `ver-oportunidades` / `ver-presupuestos` (FR-025).

### Parámetros de consulta

| Parámetro | Tipo | Notas |
|---|---|---|
| `preset` | `mes` \| `trimestre` \| `anio` \| `personalizado` | Default `mes` |
| `desde`, `hasta` | `Y-m-d` | Obligatorios si `preset=personalizado` |
| `canal_id` | entero \| `sin_especificar` | Filtro por canal (FR-015). Forma corta de la columna `leads.canal_captacion_id` |
| `comercial_id` | entero | Ignorado si el usuario no tiene `ver-informes-equipo` (FR-024) |
| `fase` | string | Estado de lead, etapa de oportunidad o estado de presupuesto |
| `comparar` | booleano | Activa la comparativa con el ejercicio anterior (FR-018) |

**Rango inválido**: se sigue el contrato ya establecido por el dashboard — **nunca 422**. Un rango
mal formado cae a mes en curso y se avisa por flash/toast. Se reutiliza el patrón de
`DashboardFiltroRequest` (`failedValidation()` que no lanza).

**Filtros desconocidos**: un `canal_id` inexistente o de otro tenant se trata como filtro sin
resultados, no como error. Un `fase` desconocido se ignora.

---

## `GET /informes-comerciales` con `Accept: application/json`

Recarga parcial sin refrescar la página (research D8). Mismo control de acceso y mismos parámetros.

**200** con:

```json
{
  "html": "<bloques del informe renderizados>",
  "periodo": { "preset": "mes", "desde": "2026-07-01", "hasta": "2026-07-19", "etiqueta": "01/07/2026 - 19/07/2026" },
  "alcance": { "tipo": "tenant", "bloques_visibles": ["leads", "oportunidades", "presupuestos"] },
  "graficos": {
    "evolucion": [ { "etiqueta": "01 jul", "leads": 4, "oportunidades": 1, "presupuestos": 0 } ],
    "leads_por_estado": [ { "estado": "nuevo", "etiqueta": "Nuevo", "cantidad": 12 } ],
    "oportunidades_por_etapa": [ { "etapa": "nueva", "etiqueta": "Nueva", "cantidad": 3, "importe": 12500.00 } ],
    "presupuestos_por_estado": [ { "estado": "enviado", "etiqueta": "Enviado", "cantidad": 5, "importe": 9800.00 } ],
    "comparativa": null
  },
  "aviso": null
}
```

**Invariantes de la respuesta**:

- El bloque de negocio cerrado del embudo (FR-030) solo aparece si el usuario tiene
  `ver-presupuestos`, porque se deriva de presupuestos convertidos; **no** requiere
  `ver-facturas`, ya que no expone facturas individuales sino un agregado del embudo.
- Un ratio sin datos se serializa como `null`, nunca como `0` (FR-005).
- Los bloques no visibles por permisos **no aparecen** en `graficos` ni en `html` (FR-022).
- Los importes llegan ya redondeados a 2 decimales; el cliente no recalcula nada (FR-010).
- Con `comparar=1`, `graficos.comparativa` trae la serie del ejercicio anterior **alineada por
  índice de bucket**, no por fecha absoluta (FR-021), y las variaciones no calculables llegan como
  `null` (FR-020).

---

## `POST /informes-comerciales/exportar`

Descarga del informe en `.xlsx` (FR-027). Acepta los mismos parámetros de filtro que la vista.

- **200** con el fichero (`Content-Type` de xlsx), nombre `informe-comercial-{Y-m-d}.xlsx`.
- **403** mismo criterio que la vista.

**Garantías**:

- El alcance del fichero es idéntico al de la pantalla para ese usuario (FR-028); los filtros se
  resuelven de nuevo en el servidor, no se confía en nada precalculado por el cliente.
- Un periodo sin actividad produce un fichero válido con los indicadores a cero (FR-029).
- La exportación se registra en el log de actividad, igual que el resto de exportaciones.

---

## Rutas de administración del catálogo de canales

Bajo `ver-configuracion` (el catálogo es configuración del tenant), siguiendo el patrón CRUD ya
usado por otros catálogos como unidades o categorías.

| Ruta | Efecto |
|---|---|
| `POST /canales-captacion` | Alta. `nombre` obligatorio, único por tenant |
| `PUT/PATCH /canales-captacion/{canal}` | Edición de nombre, orden y estado `activo` |
| `DELETE /canales-captacion/{canal}` | Borra si no tiene leads; si los tiene, **desactiva** y lo informa (FR-013) |

El listado se sirve dentro de la pantalla de configuración existente, no como sección propia del
sidebar (no consume una entrada de menú ni, por tanto, un permiso nuevo de catálogo).

---

## Cambios en contratos existentes

- **Alta y edición de lead**: nuevo campo opcional `canal_captacion_id`. Debe pertenecer al tenant.
  En el alta debe además estar `activo`; al editar se admite conservar un canal ya desactivado.
- **Importación de leads**: nueva columna opcional de canal, resuelta por nombre. Un nombre
  desconocido no aborta la importación: la fila entra sin canal y se reporta el motivo.
- **Exportación de leads**: se añade la columna de canal a la definición existente.

Ningún contrato existente cambia de forma incompatible: todos los campos nuevos son opcionales.
