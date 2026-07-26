# Data Model — Reporting Comercial CRM (033)

La feature es mayoritariamente de **lectura** sobre entidades existentes. Los únicos cambios de
esquema son los mínimos para que la segmentación por canal (FR-011..FR-014) y el ciclo comercial
(FR-004) sean posibles.

---

## 1. Tabla nueva: `canales_captacion`

Catálogo por tenant del canal comercial de procedencia de un lead (research D3).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | BIGINT autoincrement | |
| `tenant_id` | UNSIGNED BIGINT, indexado | Principio I |
| `nombre` | VARCHAR(80) | Único por tenant |
| `activo` | BOOLEAN, default `true` | Desactivación en vez de borrado (FR-013) |
| `orden` | SMALLINT, default 0 | Orden de presentación en selectores |
| `created_at` / `updated_at` | TIMESTAMP | |

**Índices**: `unique(tenant_id, nombre)`, `index(tenant_id, activo)`.

**Reglas**:

- `nombre` obligatorio, único por tenant (comparación insensible a mayúsculas y espacios sobrantes)
  para evitar segmentos duplicados tipo "Web" / "web ".
- Un canal **no se borra** si tiene leads asociados: se desactiva. Un canal sin leads sí puede
  borrarse.
- Un canal desactivado no aparece en los selectores de alta/importación de leads, pero sí en los
  filtros y desgloses de informes (FR-013).
- Sin borrado lógico (`softDeletes`): el booleano `activo` es suficiente y más simple.

**Siembra por defecto** (research D3): Web, Recomendación, Feria/Evento, Campaña de email, Llamada
entrante, Redes sociales, Otro. Se crean (a) al provisionar un tenant nuevo, junto al resto de
provisión inicial del tenant, y (b) para los tenants ya existentes, desde la propia migración.

---

## 2. Cambios sobre `leads`

| Columna | Tipo | Notas |
|---|---|---|
| `canal_captacion_id` | FK nullable → `canales_captacion`, `nullOnDelete` | FR-011. `NULL` = "Sin especificar" (FR-014). El parámetro HTTP equivalente se llama `canal_id` (forma corta); la columna conserva el nombre largo |
| `convertido_at` | DATETIME nullable | Research D2. Se puebla al convertir el lead |

**Índices nuevos**: `index(tenant_id, canal_captacion_id)`, `index(tenant_id, created_at)`.

**Reglas**:

- `canal_captacion_id` es **opcional** en el alta manual y en la importación: un lead sin canal es
  válido y se agrupa como "Sin especificar". No se fuerza a rellenar retroactivamente.
- Al asignar canal debe validarse que pertenece al tenant activo y que está `activo` (en el alta;
  al editar un lead antiguo se admite conservar un canal ya desactivado).
- `convertido_at` lo escribe **exclusivamente** `App\Services\ConversorLeadCliente` al convertir,
  junto a `convertido_a_cliente_id` y el cambio de estado. Nunca es editable por el usuario.
- Leads convertidos antes de esta feature: `convertido_at` queda `NULL`; cuentan como convertidos
  pero no aportan al promedio de duración de ciclo (research D2).
- **No se modifica** `origen` (`OrigenLead`): sigue registrando manual/importación.

**Importación de leads**: la definición de importación existente admite una columna opcional de
canal, resuelta por nombre contra el catálogo del tenant. Un nombre desconocido **no aborta la
importación**: la fila se acepta sin canal y se reporta el motivo, coherente con el comportamiento
de rechazos parciales ya establecido en la feature 028/031.

---

## 3. Índices añadidos sobre tablas existentes

Solo índices; ninguna columna ni regla de negocio cambia (research D6).

| Tabla | Índice | Motivo |
|---|---|---|
| `leads` | `(tenant_id, created_at)` | Cohorte de captación |
| `leads` | `(tenant_id, canal_captacion_id)` | Segmentación por canal |
| `oportunidades` | `(tenant_id, cerrada_at)` | Ganadas/perdidas por evento |
| `presupuestos` | `(tenant_id, fecha_emision)` | Cohorte de presupuestos |

---

## 4. Entidad calculada: `InformeComercial`

**No se persiste**. Es el resultado en memoria de aplicar `(periodo, filtros, alcance)` sobre los
datos existentes. Estructura lógica:

```text
InformeComercial
├── periodo            { desde, hasta, preset, etiqueta }
├── alcance            { tipo: propio|tenant, comercial_id?, bloques_visibles[] }
├── filtros            { canal_id?, comercial_id?, fase? }
├── indicadores        { leads_captados, leads_convertidos, oportunidades_creadas,
│                        oportunidades_ganadas, oportunidades_perdidas,
│                        oportunidades_abiertas, importe_pipeline,
│                        presupuestos_emitidos, importe_presupuestado,
│                        presupuestos_aceptados, importe_aceptado,
│                        facturas_del_embudo, importe_facturado_embudo }
├── ratios             { conversion_lead_cliente, oportunidades_ganadas,
│                        aceptacion_presupuestos, conversion_presupuesto_factura,
│                        importe_medio_ganada,
│                        ciclo_medio_lead_dias, ciclo_medio_oportunidad_dias }
├── fases              { leads_por_estado[], oportunidades_por_etapa[],
│                        presupuestos_por_estado[] }
├── evolucion          [ { etiqueta, leads, oportunidades, presupuestos } ]
├── top_articulos      [ { articulo_id, nombre, importe, unidades } ]
└── comparativa?       { periodo, indicadores, ratios, evolucion, variaciones }
```

**Reglas de cálculo**:

- Cada indicador se adscribe al periodo según la tabla de criterios de research D1, que es la fuente
  de verdad; la UI muestra ese criterio junto al indicador (FR-006).
- **Negocio cerrado del embudo** (FR-030/FR-031): cuenta únicamente facturas alcanzables desde un
  presupuesto del periodo, es decir, presupuestos con `fecha_emision` en rango cuyo
  `convertido_a_factura_id` no es nulo. Se adscribe por **cohorte de `fecha_emision` del
  presupuesto**, igual que el resto de indicadores de presupuesto, para que
  `conversion_presupuesto_factura` tenga numerador y denominador de la misma población. Las facturas
  de alta directa y las simplificadas de punto de venta **nunca** entran aquí: no son alcanzables
  desde un presupuesto, así que la exclusión es estructural y no requiere un filtro aparte.
- Un ratio con denominador cero se representa como **`null`** ("sin datos"), nunca `0` (FR-005). La
  capa de presentación es la única responsable de traducir `null` a texto.
- Los ratios se calculan siempre sobre una única población (research D1), de modo que el numerador
  nunca exceda al denominador.
- Todos los recuentos e importes se resuelven con agregación en base de datos (`COUNT`/`SUM` +
  `GROUP BY`), no trayendo colecciones a memoria (research D6, SC-007).
- Los importes se redondean a 2 decimales en el servidor (Principio III).
- Registros con borrado lógico quedan excluidos automáticamente por el comportamiento por defecto de
  los modelos; no se usa `withTrashed()` en ningún punto del informe.
- El `TenantScope` aplica a todas las consultas; en ningún punto se usa `withoutGlobalScopes()`
  (Principio I).

**Alcance del usuario** (research D4):

- Sin `ver-informes-equipo`: se fuerza `asignado_a = usuario autenticado` sobre leads y
  oportunidades, y se descarta cualquier `comercial_id` recibido en la petición.
- Los presupuestos se acotan por el comercial de su oportunidad asociada; un presupuesto sin
  oportunidad queda fuera del alcance restringido.
- `bloques_visibles` se deriva de los permisos de módulo (`ver-leads`, `ver-oportunidades`,
  `ver-presupuestos`); un bloque sin permiso no se calcula ni se envía al cliente (FR-022).

---

## 5. Permisos nuevos

Se añaden a `App\Support\CatalogoPermisos` (research D4):

| Clave | Etiqueta | Módulo |
|---|---|---|
| `ver-informes-comerciales` | Informes comerciales | CRM |
| `ver-informes-equipo` | Informes de todo el equipo | CRM |

Tras añadirlos hay que re-ejecutar el seeder de permisos, según el procedimiento ya establecido por
la feature 027. `ver-informes-comerciales` entra en el rol "Usuario" base; `ver-informes-equipo`
**no** (queda reservado a perfiles de responsable, coherente con la exclusión de permisos de gestión
del rol base).

---

## 6. Retención y datos personales

La feature **no introduce datos personales nuevos** (Principio II): `canal_captacion_id` es un dato
comercial y `convertido_at` una marca temporal de un hecho de negocio. Ambos viven en `leads` y se
purgan con el lead, bajo la retención ya configurada (`ConfigCrm::retencionDias`, feature 028). No
se necesita mecanismo de purga propio.

`canales_captacion` es un catálogo de configuración del tenant, sin datos personales.
