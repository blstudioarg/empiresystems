# Kit Digital — homologación como solución de Agente Digitalizador

> **Contexto estratégico (importante):** este SaaS se comercializa a través del **Kit Digital**. Eso
> significa que no basta con que el software funcione: para que los clientes puedan pagarlo con el
> **bono digital**, Empire Systems debe estar dado de alta como **Agente Digitalizador adherido** y
> la solución debe cumplir los **requisitos mínimos por categoría** que fija Red.es. Estos requisitos
> son **obligaciones de producto**, no opcionales.
>
> Fechas/importes verificados jul-2026; **revisar antes de homologar** porque las bases y catálogos
> se actualizan (última modificación de bases: Orden TDF/39/2026).

## Cómo funciona (resumen)
- El **Agente Digitalizador** (nosotros) presta la solución; el beneficiario (pyme/autónomo) firma un
  **acuerdo bilateral** y la subvención se paga **al agente**, no al beneficiario.
- El beneficiario debe hacer el **test de diagnóstico digital** obligatorio antes de solicitar el bono.
- Importe del bono por segmento: **III (0–2 empleados) 3.000 €** · **II (3–9) 6.000 €** ·
  **I (10–49) 12.000 €**.

---

## Categoría 1 — Gestión de la Facturación y Factura Electrónica
*(la categoría central de este producto — importe de la solución hasta ~2.000 €)*

**Requisitos funcionales mínimos:**

- **Facturas en formato estructurado**, al menos **Facturae**, para tratamiento automatizado. ✅ *(spec 022: `GeneradorFacturae`, `FacturaeController`)*
- **Facturas ilimitadas.** ✅
- **Clientes ilimitados.** ✅
- **Productos/servicios ilimitados** en el catálogo. ✅
- **Control de vencimiento** de las facturas. ✅ *(`fecha_vencimiento` + `VencimientoFactura`)*
- **Envío y recepción** de facturas electrónicas, **al menos por email**. ✅ *Envío (spec 017: SMTP por tenant) + recepción (spec 022: `ImportadorFacturae`, `CompraFacturaeController`, estado de ciclo B2B en `compras`).*
- **Almacenamiento** mínimo **1 GB** (segmentos I–III) / 10 GB (IV–V) para el historial. ⚠️ *A verificar/documentar en el plan de hosting.*
- **Copias de seguridad**, con posibilidad de **periodicidad diaria**. ⚠️ **Gap abierto — a definir/documentar.**
- **Verifactu**: **imprescindible** para operar en España. ✅ *(spec 032: `RegistroVerifactu`, `GeneradorXmlVerifactu`, `RemisorVerifactu` con remisión a AEAT, `HuellaVerifactu` para el encadenamiento)*
- **Emisión de facturas verificables** (físico y digital), verificables por el comprador ante la AEAT
  (QR). ✅ *(spec 032: `QrVerifactu`)*

### Implicaciones / gaps para homologar esta categoría
1. ~~**Facturae** (XML estructurado) — formato distinto del registro XML de Verifactu.~~ ✅ Implementado (spec 022).
2. ~~**Verifactu real** — requisito de homologación ya, no "para 2027".~~ ✅ Implementado (spec 032), con huella, encadenamiento, QR y remisión a AEAT.
3. ~~**Recepción de facturas** — la app solo emitía.~~ ✅ Implementado (spec 022), apoyado en el módulo de compras (spec 014): importación del XML del proveedor a una `compra`, con referencia al archivo recibido y estado de ciclo B2B para los 4 días hábiles que exige la ley.
4. **Backups diarios** — ⚠️ **único gap vivo de esta categoría.** Capacidad a documentar aunque sea a nivel de infraestructura/hosting (ver `docs/05-despliegue-railway.md`). No requiere código de producto, pero **sí requiere evidencia escrita** para justificar.

---

## Categoría 2 — Gestión de Clientes (CRM)
*(aplicable si además homologamos el lado CRM — bono según segmento; parametrización 40 h seg. I / 30 h seg. II–III)*

**Requisitos funcionales mínimos:**

- **Gestión de clientes**: alta desde oportunidad de negocio, datos, simular compra/contratación. ✅ *(clientes ✔ + alta desde lead/oportunidad, spec 028)*
- **Gestión de Leads**: alta manual o **importación por fichero** + reglas de asignación. ✅ *(spec 028: alta manual + importación CSV/Excel con reporte de rechazos, dedup, asignación manual/round-robin)*
- **Gestión de oportunidades**: ofertas y **presupuestos** al lead/cliente. ✅ *(spec 028: pipeline nueva→negociación→ganada/perdida + presupuestos con conversión a factura borrador)*
- **Alertas** de clientes en **formato gráfico**, con **ratios de eficiencia, estado de fases,
  pipeline, segmentación por canal/perfil/fase y comparativas entre ejercicios**. ✅ *(spec 033:
  sección de informes comerciales — indicadores + ratios + gráficos de evolución/fases/top
  artículos, filtros por canal/comercial/fase, comparativa entre ejercicios, alcance por perfil y
  exportación a Excel; se apoya en el dashboard 015 y en el pipeline de oportunidades, spec 028)*
- **Gestión documental** de la actividad comercial. ✅ *(spec 019 gestor documental)*
- **Diseño responsive**. ✅ *(template NexaDash)*
- **Integración con plataformas**: **APIs o Web Services**. 🟡 *Parcial — cubierto por integración
  **entrante** (AEAT/Verifactu, Facturae, VIES, SMTP, proveedores IA); falta API **saliente** propia.
  Ver la sección "Punto 10" más abajo, donde está el análisis completo.*

### Implicaciones / gaps para homologar esta categoría
1. ~~**Leads** (con importación por fichero y reglas de asignación) — no existe.~~ ✅ Implementado (spec 028).
2. ~~**Oportunidades + presupuestos** — no existe (sería el paso previo a la factura).~~ ✅ Implementado (spec 028).
3. ~~**Alertas gráficas** de clientes (ratios, fases, pipeline, comparativas) — la vista pipeline (spec 028) solo cubría parcialmente el recuento/importe por etapa.~~ ✅ Implementado (spec 033: informes comerciales).
4. **API / Web Services** — 🟡 **único gap vivo de esta categoría.** Ver sección siguiente.

---

## Punto 10 — Integración con plataformas (APIs / Web Services)

*Análisis hecho el 2026-07-19 sobre el código real, tras revisar el modelo de compilación de
evidencias. Es el último requisito abierto de la categoría CRM.*

### Lo que la app YA integra (integración entrante — evidenciable hoy, sin escribir código)

| Integración | Dónde vive | Qué es |
|---|---|---|
| **AEAT / Veri*factu** | `App\Services\RemisorVerifactu` (spec 032) | Web service de la Agencia Tributaria |
| **Facturae** emisión + recepción | `EnvioFacturae`, `ImportadorFacturae` (spec 022) | Estándar B2B/B2G con la administración |
| **VIES** | `App\Support\VerificadorVies` | Web service de la Comisión Europea (validación de NIF intracomunitario) |
| **SMTP por tenant** | Configuración de email (spec 017) | Correo transaccional |
| **Proveedores de IA** | Chat asistente (spec 030) | API de LLM |
| Geolocalización IP | `App\Support\GeolocalizadorIp` | API de terceros (control horario) |

Esto es **más sólido que el ejemplo del propio modelo de evidencias oficial**, que justificó su punto
10 con login de Google + MessageBird + SMTP + Facturae. Nosotros ya tenemos AEAT + Facturae + VIES +
SMTP funcionando en producción.

### Lo que falta (integración saliente)

**No existe una API propia que exponga los datos del CRM hacia afuera.** Verificado sobre el código:
no hay `routes/api.php`, ni `laravel/sanctum` ni `passport` ni `socialite` en `composer.json`. El
requisito habla de "las **opciones de integración que ofrece** la solución", y ahí es donde flaquea:
la app integra *hacia adentro* pero no *ofrece* punto de integración a terceros.

### Recomendación (pendiente de decidir)

**Doble vía, en este orden:**

1. **Vía corta (coste cero, desbloquea la homologación)**: justificar el punto 10 con la integración
   entrante ya existente. El requisito es de tipo *genérico* y esto lo cubre. **Acción**: preparar
   las capturas de AEAT/Facturae/VIES/configuración SMTP.
2. **Vía completa (feature 034, aún sin abrir)**: **API REST propia con `laravel/sanctum`**, que
   convierte "cubierto a mínimos" en "cubierto de verdad" y suma valor de producto real (permite al
   cliente conectar su web, Zapier, Power BI). Esbozo:
   - Tokens de API por tenant, gestionables desde Configuración (crear/revocar, con scopes). El
     token hereda el `tenant_id` → el aislamiento del Principio I se respeta sin excepciones.
   - `routes/api.php` con lectura paginada y filtrable sobre lo existente: `/api/v1/clientes`,
     `/leads`, `/facturas`, `/presupuestos`, todo bajo el `TenantScope`.
   - Escritura de alto valor: `POST /api/v1/leads` (que un formulario web externo cree leads
     directamente en el CRM — **encaja con el canal de captación de la spec 033**) y
     `POST /api/v1/clientes`.
   - **Documentación OpenAPI/Swagger** — que es literalmente la captura de "opciones de integración
     que ofrece" que pide el punto 10.

**Alternativas evaluadas y descartadas como solución principal:**

- *OAuth social login* (lo que hizo el ejemplo del PDF) — más barato, pero es autenticación, no
  integración de datos; queda pobre y no aporta valor de producto.
- *Webhooks salientes* — buen complemento, pero por sí solos no son "la API que ofrece la solución".
  Candidato a v2 de la feature 034.
- *Solo documentar lo entrante y no construir nada* — pasaría la justificación, pero deja el gap de
  producto abierto de forma permanente.

---

## Estado consolidado frente al modelo de compilación de evidencias (categoría CRM)

El documento operativo de justificación (`CAP_CUM_FUN`, 1ª convocatoria, v01 30/11/23) exige
evidencias para **10 puntos**. Estado a 2026-07-19:

| # | Punto | Estado | Dónde está |
|---|---|---|---|
| 1 | Usuarios suministrados (mín. 3) | ✅ | `UsuarioController`, `RolController` (spec 027) |
| 2 | Gestión de clientes + simulación de contratación | ✅ | `ClienteController` + presupuestos (spec 028) |
| 3 | Gestión de leads + reglas de asignación | ✅ | spec 028 (`AsignadorLeads`, importación CSV/Excel) |
| 4 | Gestión de oportunidades | ✅ | spec 028 (pipeline + presupuestos) |
| 5 | Acciones o tareas comerciales | 🟡 **Parcial** | Campañas de email (spec 018). **No existe entidad "tarea"** (llamada/seguimiento con fecha y responsable) |
| 6 | Reporting, planificación y seguimiento | ✅ | **spec 033** (informes comerciales) |
| 7 | Alertas | ✅ | Toastr global + indicadores gráficos de spec 033 |
| 8 | Gestión documental | ✅ | spec 019 |
| 9 | Diseño responsive | ✅ | Template NexaDash |
| 10 | Integración con plataformas | 🟡 **Parcial** | Entrante ✅ / API saliente ⚠️ (ver sección anterior) |

**Nota sobre el punto 5**: es el segundo gap más débil después del 10. Hoy se defendería con el
módulo de campañas de email (acción comercial automática), pero no hay un modelo de *tarea
comercial* (llamada, visita, seguimiento con fecha de vencimiento y responsable). Si un auditor
aprieta en "creación de tareas comerciales de forma manual o automática", el argumento es flojo.
**Candidato a feature futura**, sin decidir.

---

## Pendiente de concretar (estado a 2026-07-19)

Lo que queda abierto, por orden de urgencia para homologar:

1. **Punto 10 — API saliente**: decidir entre vía corta (documentar lo entrante) y feature 034
   (API REST con Sanctum). Análisis completo arriba. **Sin decidir.**
2. **Backups diarios (Categoría 1)**: único gap vivo de la vía factura. No requiere código, pero sí
   evidencia documentada del hosting. **Sin documentar.**
3. **Almacenamiento mínimo 1 GB/tenant**: verificar y documentar que el plan de hosting lo cumple.
4. **Punto 5 — tareas comerciales**: valorar si se construye una entidad de tarea comercial o se
   defiende con campañas de email. **Sin decidir.**
5. **Decisión estratégica de fondo**: ¿homologamos **solo Factura Electrónica**, **solo Gestión de
   Clientes**, o **ambas**? Con el estado actual **ambas están al alcance**: la vía factura solo
   necesita documentar backups; la vía CRM solo necesita cerrar el punto 10. Esto ya no es la
   decisión bloqueante que era cuando se escribió este documento.

---

## Fuentes
- **Modelo de compilación de evidencias** (documento operativo de justificación, 1ª convocatoria,
  versión 01 – modificación 30/11/23): plantilla de 10 puntos con el tipo de captura exigido por
  cada uno (*personalizada* = debe verse información identificativa del beneficiario; *genérica* =
  basta con evidenciar que es la herramienta implantada). Los puntos 1 y 2 exigen captura
  **personalizada**; el resto admiten **genérica**.
- Categoría CRM (guía justificación oficial): [Red.es — PDK Gestión de Clientes](https://portal.gestion.sedepkd.red.gob.es/portal/common/help/justificaciones/PDK_G_Gestion_de_Clientes.pdf), [Acelera Pyme — Gestión de clientes](https://www.acelerapyme.gob.es/en/kit-digital/gestion-clientes)
- Categoría Factura Electrónica (guía justificación oficial): [Red.es — PDK Factura Electrónica](https://portal.gestion.sedepkd.red.gob.es/portal/common/help/justificaciones/PDK_G_Factura_Electronica.pdf), [Acelera Pyme — Factura electrónica](https://www.acelerapyme.gob.es/en/kit-digital/factura-electronica)
- Convocatoria / bases: [Sede Red.es — convocatoria segmento III](https://sede.red.gob.es/es/procedimientos/convocatoria-de-ayudas-destinadas-la-digitalizacion-de-empresas-del-segmento-iii), [Orden TDF/39/2026 (AECIM)](https://acelerapyme-aecim.com/ayuda-digitalizacion/kit-digital-2026-ayudas-para-la-digitalizacion-de-pymes-y-autonomos-orden-tdf-39-2026-modificacion-de-bases-reguladoras/)
