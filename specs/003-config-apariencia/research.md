# Research — Configuración del tenant (Apariencia / Marca)

Fase 0. Resolución de decisiones técnicas y NEEDS CLARIFICATION.

## 1. Aplicar colores por tenant sobre el template NexaDash

**Decision**: Inyectar en `<head>` (después de `style.css`) un bloque `<style>` que redefine las
variables CSS del template para el tenant activo: `--primary`, `--primary-hover`, `--rgba-primary-1`
… `--rgba-primary-9`, `--secondary`, y una variable nueva `--topbar-bg` para el fondo de la topbar.

**Rationale**: El template ya está construido sobre variables CSS (`fill="var(--primary)"`,
`background: var(--rgba-primary-1)`, etc.). Sobrescribir esas variables propaga el color a toda la UI
sin tocar reglas individuales. Las variantes `--rgba-primary-N` están hardcodeadas en `style.css` con
el color por defecto (`rgba(29,105,214,α)`); si solo se cambia `--primary`, los fondos translúcidos
seguirían azules. Por eso el override las recalcula a partir del color primario elegido (misma alpha
0.1–0.9). El color primario se convierte de HEX a componentes RGB en backend para generar las rgba.

**Alternatives considered**:
- *Usar el motor `dzSettingsOptions` del template (15 colores predefinidos)*: rechazado — solo admite
  una paleta fija, no un color arbitrario elegido por el tenant.
- *Compilar un CSS por tenant*: rechazado — sobreingeniería y complica el hosting compartido; el bloque
  inline es trivial y cacheable con la apariencia del tenant.

**Fondo de la topbar**: `.header`/`.nav-header` no usan hoy una variable dedicada; se añade
`--topbar-bg` en el override y una regla mínima en una hoja propia (o en el mismo `<style>`) que
aplique `background: var(--topbar-bg)` al contenedor de la topbar. Si el tenant no configuró nada, no
se emite override y queda el estilo por defecto del template.

## 2. Almacenamiento de los valores

**Decision**: **Colores** en la tabla `configuraciones` (clave-valor por tenant, grupo `apariencia`),
con claves `apariencia.color_primario`, `apariencia.color_secundario`, `apariencia.color_topbar`.
**Logo** como columna `tenants.logo_path` + fichero en disco `public`.

**Rationale**: Es exactamente lo previsto en `docs/03-modelo-datos.md` (tabla `configuraciones` con
índice único `(tenant_id, clave)` y `tenants.logo_path` "para el PDF" — reutilizable también para el
menú). Evita añadir columnas ad-hoc y deja la puerta abierta a las futuras tabs de configuración, que
usarán la misma tabla con otros grupos.

**Alternatives considered**:
- *Columnas de color directas en `tenants`*: rechazado — contradice el diseño documentado y no escala
  a las decenas de ajustes futuros que motivaron la tabla clave-valor.
- *Logo también en `configuraciones`*: rechazado para el binario; la ruta del logo encaja mejor como
  columna del tenant (ya documentada) y el fichero vive en disco, no en BD.

## 3. Resolución y caché de la apariencia por request

**Decision**: Un helper/objeto `AparienciaTenant` lee las 3 claves de color del tenant activo (una
query, con `remember` en caché por `tenant_id`) y expone: colores efectivos (con fallback a los
defaults del template) y el bloque de variables ya calculado. Se comparte con las vistas vía View
Composer sobre el layout. El logo se toma de `tenant()->logo_path`.

**Rationale**: Una sola consulta cacheada por tenant evita penalizar cada página. El fallback a
defaults mantiene la UI intacta para tenants sin configurar. Invalida la caché al guardar.

**Alternatives considered**: *Leer clave por clave sin caché* — rechazado por N queries por request.

## 4. Vista previa del logo antes de guardar

**Decision**: JS propio (`configuracion-apariencia.init.js`) que, al elegir fichero en el `input
type=file`, usa `FileReader` para pintar la imagen en un `<img>` de preview. Sin subir nada hasta
guardar.

**Rationale**: Es el patrón estándar y ligero; el template no trae preview, así que se añade a mano
(tal como pidió el usuario). No requiere dependencias.

**Alternatives considered**: *Plugin dropzone/uploader* — rechazado por peso y complejidad innecesaria
para un único fichero.

## 5. Color picker

**Decision**: Vendorizar `jquery-asColorPicker` desde el banco del template
(`template/.../public/vendor/jquery-asColorPicker/` + `js/plugins-init/jquery-asColorPicker.init.js`)
a `public/vendor/` y `public/js/plugins-init/`. Inputs con clase `.as_colorpicker` en modo simple.

**Rationale**: Ya está en el repositorio (banco del template), es jQuery (stack actual del front del
template) y da el HEX directamente. Cumple Principio V (nada nuevo pesado).

**Alternatives considered**: *`<input type="color">` nativo* — viable y aún más simple, pero el
usuario pidió explícitamente el color picker del template; se mantiene asColorPicker. (Fallback
aceptable si diera problemas de integración.)

## 6. Validación de colores y logo (backend)

**Decision**: `UpdateAparienciaRequest`:
- Colores: `required`-ish opcional, formato HEX `#RRGGBB` (regex), si vienen.
- Logo: `nullable|image|mimes:png,jpg,jpeg,svg,webp|max:1024` (KB) — límite de tamaño configurable.
- Acción de "restablecer": una bandera que borra las claves de color del tenant y el `logo_path`
  (y opcionalmente el fichero anterior).

**Rationale**: Validación server-side (coherente con el proyecto). SVG se admite pero con cuidado (ver
Riesgos). El límite 1 MB es razonable para un logo.

**Alternatives considered**: *Aceptar cualquier color-string CSS* — rechazado; HEX acotado es más
fácil de validar y de convertir a RGB para las variantes rgba.

## 7. Permisos de acceso (resuelve FR-011)

**Decision**: Cualquier usuario **autenticado** del tenant puede ver y editar la apariencia de su
propio tenant (sin distinción de rol). Rutas bajo el grupo `['auth','tenant.context']` existente. El
aislamiento entre tenants (Principio I) se mantiene siempre.

**Rationale**: Decisión explícita del usuario en la fase de especificación. Simplifica la UI (sin
estados de solo-lectura) y no añade lógica de rol.

## Riesgos / notas

- **SVG como logo**: un SVG puede contener scripts. Mitigación: servirlo como imagen estática desde el
  disco público (no inline en el DOM del panel), y/o restringir a PNG/JPG/WEBP si se prefiere evitar
  el riesgo. Se deja PNG/JPG/WEBP como conjunto seguro por defecto y SVG como opción a confirmar en
  tasks.
- **storage:link**: la subida de logo necesita el symlink `public/storage`. Documentado en quickstart;
  en hosting compartido se crea una vez.
- **Super admin (tenant_id null)**: no tiene tenant, así que la pantalla de apariencia por tenant no
  aplica a su contexto central; queda fuera de alcance de esta feature (no rompe: sin tenant no se
  emiten overrides).
