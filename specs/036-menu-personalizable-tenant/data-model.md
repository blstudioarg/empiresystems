# Data Model — Menú lateral personalizable por tenant

**Feature**: 036-menu-personalizable-tenant | **Fecha**: 2026-07-26

**Sin migración**: esta feature no crea ni altera ninguna tabla. Usa la tabla `configuraciones` ya
existente (ver research.md, D1).

---

## 1. Catálogo del menú (`App\Support\CatalogoMenu`) — código, no datos

Constante de clase con la definición de los elementos del menú del tenant. Es la **fuente de
verdad** de qué existe, con qué nombre por defecto, con qué icono, hacia dónde apunta y qué permiso
lo gobierna. No se edita desde la aplicación (FR-002).

### Campos por elemento

| Campo | Tipo | Descripción |
|---|---|---|
| `clave` | string (kebab-case) | **Identificador estable** del elemento. Nunca cambia, ni siquiera si cambia su etiqueta por defecto. Es la clave de la personalización. |
| `etiqueta` | string | Nombre por defecto mostrado si el tenant no lo personalizó (FR-007). |
| `icono` | string\|null | Nombre del lordicon. Solo en elementos de primer nivel. **No personalizable** (FR-003). |
| `ruta` | string\|null | Nombre de la ruta Laravel destino. `null` en un grupo que solo despliega hijos. **No personalizable** (FR-003). |
| `permiso` | string\|null | Clave del permiso `ver-*` que gobierna su visibilidad. |
| `hijos` | lista de elementos | Entradas del grupo. Vacía en las entradas de primer nivel con ruta propia. |

El **orden por defecto** es el orden posicional dentro del array (y dentro de `hijos`); no hay campo
numérico explícito.

### Contenido inicial (derivado de `resources/views/partials/sidebar.blade.php`)

| # | Clave (grupo) | Etiqueta por defecto | Permiso | Claves de sus entradas (en orden) |
|---|---|---|---|---|
| 1 | `inicio` | Inicio | `ver-dashboard` | — (enlace directo a `dashboard`) |
| 2 | `control-fichaje` | Control de fichaje | — (derivado) | `fichar`, `mi-jornada`, `jornada`, `calendario`, `miembros`, `horarios`, `alertas` |
| 3 | `clientes` | Clientes | — (derivado) | `cartera-clientes` |
| 4 | `crm` | CRM | — (derivado) | `leads`, `oportunidades`, `presupuestos`, `albaranes`, `informes-comerciales` |
| 5 | `stock` | Stock | — (derivado) | `catalogo`, `kardex`, `proveedores`, `compras` |
| 6 | `facturas` | Facturas | — (derivado) | `facturas-listado`, `facturas-crear` |
| 7 | `pos` | POS | — (derivado) | `pos-listado`, `pos-crear` |
| 8 | `archivos` | Archivos | `ver-archivos` | — (enlace directo a `archivos.index`) |
| 9 | `marketing` | Marketing | — (derivado) | `campanas`, `campanas-crear`, `plantillas-email` |
| 10 | `usuarios` | Usuarios | — (derivado) | `usuarios-listado`, `roles` |

**Total**: 10 elementos de primer nivel + **26** entradas de segundo nivel = 36 elementos
(recuento verificado sobre `sidebar.blade.php`, no estimado).

**"— (derivado)"**: un grupo con hijos **no declara permiso propio**; su visibilidad se deriva de si
alguna de sus entradas es visible para el usuario (research.md, D2, regla 2). Esto reproduce
exactamente el `@canany([...])` actual sin duplicar la lista.

Cada entrada declara su permiso `ver-*` y su ruta, con las mismas parejas que hoy están escritas a
mano en el sidebar (p. ej. `fichar` → `ver-fichar` / `fichajes.index`; `facturas-crear` →
`ver-facturas-crear` / `facturas.create`; `roles` → `ver-roles` / `roles.index`).

### Invariantes

- **INV-1**: las `clave` son únicas en todo el catálogo (grupos y entradas comparten espacio de
  nombres, porque la personalización las indexa en un único mapa).
- **INV-2**: toda `ruta` declarada debe existir; el bucle del sidebar omite el elemento si
  `Route::has()` es falso (research.md, D6.3), lo que hace segura la entrada `roles`.
- **INV-3**: todo permiso declarado debe existir en `App\Support\CatalogoPermisos::PERMISOS`. Un
  test lo verifica para que un renombrado de permiso no deje un elemento invisible en silencio.
- **INV-4**: la jerarquía tiene exactamente **dos niveles**. Un hijo no puede tener hijos.

---

## 2. Personalización del tenant — fila en `configuraciones`

| Columna | Valor |
|---|---|
| `tenant_id` | id del tenant (aísla; Principio I) |
| `clave` | `menu.personalizacion` |
| `grupo` | `menu` |
| `tipo` | `json` |
| `valor` | documento JSON descrito abajo |

**Ausencia de la fila = tenant sin personalizar** (FR-016: restaurar equivale a borrarla).

### Formato del `valor`

```json
{
  "etiquetas": {
    "clientes": "Pacientes",
    "cartera-clientes": "Mis pacientes",
    "pos-listado": "Tickets"
  },
  "orden": {
    "_raiz": ["facturas", "clientes", "inicio", "crm"],
    "control-fichaje": ["alertas", "fichar", "mi-jornada"]
  }
}
```

- `etiquetas`: mapa `clave del elemento → nombre visible`. **Solo contiene lo que difiere del
  catálogo** (research.md, D4).
- `orden`: mapa `nivel → lista ordenada de claves`. La clave especial `_raiz` es el nivel de los
  grupos; el resto son claves de grupo con el orden de sus entradas. Solo se guardan los niveles cuyo
  orden difiere del catálogo.
- Ambas listas pueden ser **parciales**: nunca se asume que contengan todo el catálogo.

---

## 3. Reglas de fusión (`MenuTenant::estructura($tenantId)`)

Produce la estructura final que consume el sidebar. Algoritmo, por nivel:

1. Partir de la lista de elementos del catálogo para ese nivel, en su orden por defecto.
2. Ordenarlos según `orden[nivel]`: primero los que aparecen ahí, en ese orden; después, los que
   **no** aparecen, conservando su orden relativo del catálogo (FR-017: elemento nuevo → al final).
3. Descartar las claves de `orden[nivel]` que no existan en el catálogo (FR-018).
4. Para cada elemento, su nombre visible es `etiquetas[clave]` si existe y no está vacío; si no, la
   `etiqueta` del catálogo (FR-007).
5. Recursión al nivel de hijos con la misma regla.

**Determinista y total**: para cualquier JSON guardado (incluso corrupto, vacío o con claves
inventadas) devuelve siempre una estructura válida que contiene exactamente los elementos del
catálogo. No lanza excepción por datos guardados (FR-018).

**Nota importante**: la fusión **no filtra por permisos**. El filtrado por `@can` ocurre en el
render del sidebar (research.md, D2). Esto es deliberado: así la **tab de configuración** puede
mostrar el menú completo a quien administra, aunque su propio rol no vea todas las secciones — de lo
contrario, un administrador sin permiso sobre POS no podría reordenar POS para su equipo.

---

## 4. Reglas de validación al guardar (`ActualizarMenuRequest`)

| Campo recibido | Regla |
|---|---|
| `etiquetas` | array, opcional |
| `etiquetas.*` | `required`, `string`, `max:40`, sin ser solo espacios (FR-005) |
| clave de `etiquetas` | debe existir en el catálogo; si no, se descarta silenciosamente (D4) |
| `orden` | array, opcional |
| `orden.*` | array de strings |
| `orden.*.*` | `string`; claves desconocidas se descartan (D4) |

- El error de validación se devuelve **por clave de elemento** para poder pintarlo junto a su input
  (FR-005, patrón `data-error-for` del proyecto).
- La etiqueta se guarda **recortada de espacios** y se muestra siempre escapada por Blade, como
  texto plano (FR-006).
- Nombres duplicados entre elementos **se permiten**; no hay regla de unicidad (edge case del spec).
- Las claves de `orden` que no correspondan a un grupo con hijos (ni a `_raiz`) se descartan.

---

## 5. Superficie de la API de `App\Support\MenuTenant`

| Método | Propósito |
|---|---|
| `estructura(int $tenantId): array` | Catálogo + personalización fusionados (sección 3). Memoizado por request. |
| `guardar(int $tenantId, array $etiquetas, array $orden): void` | Normaliza contra el catálogo (D4), descarta lo igual al valor por defecto, y hace `updateOrCreate` de la fila. |
| `restaurar(int $tenantId): void` | Borra la fila y limpia la memoización (FR-015/FR-016). |

Mismo estilo estático que `ConfigCrm`, `ConfigTenant` y `AparienciaTenant`, con los que convive en
`app/Support/`.
</content>
