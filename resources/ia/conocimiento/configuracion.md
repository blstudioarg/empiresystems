# Configuración

En Configuración (solo administradores) se ajustan datos generales, apariencia, facturación, email
SMTP, archivos, certificado de firma, fichajes, CRM, el **Asistente IA** (clave de API) y el
**Menú** lateral. El asistente no puede modificar la configuración; si el usuario lo pide, debe
indicarle que lo haga desde esa pantalla.

En la tab **POS** (feature 050) está el **idioma del POS**: español (por defecto) o chino
simplificado, para todo el tenant (no por usuario). Con chino, todas las pantallas del POS (crear
ticket, sala y plano, caja y cierres, opciones de artículo, listado de tickets), sus avisos, los
mensajes de error del POS y sus guías de ayuda se ven en chino; en el menú lateral solo se traduce
el grupo POS y el botón de ayuda. El resto de la app y esta pantalla de Configuración siguen en
español, y los datos del negocio (artículos, mesas, zonas, clientes) nunca se traducen. Con el POS
en chino aparece la sección **Traducciones del POS**: lista los textos con su traducción y su
origen (automática, corregida o pendiente) y permite **corregir** cualquiera (la corrección es
solo de ese tenant, se ve al momento y ninguna actualización la pisa) o **restaurar la
automática**. Una corrección debe conservar las partes `:variable` del texto original.

En la tab **Menú** (feature 036), cada tenant puede renombrar el texto de cualquier sección de su
menú lateral y reordenar arrastrando (grupos entre sí, y entradas dentro de su propio grupo, sin
mezclar niveles). Es puramente de presentación: no crea, oculta ni mueve secciones entre grupos, y
no cambia qué puede ver cada persona (eso lo sigue controlando su rol en Usuarios/Roles). Hay un
botón "Restaurar valores por defecto" que vuelve el menú completo (nombres y orden) al original.
