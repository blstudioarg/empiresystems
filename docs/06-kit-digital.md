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

- **Facturas en formato estructurado**, al menos **Facturae**, para tratamiento automatizado. ⚠️ *Gap.*
- **Facturas ilimitadas.**
- **Clientes ilimitados.**
- **Productos/servicios ilimitados** en el catálogo.
- **Control de vencimiento** de las facturas. ✅ *(ya tenemos `fecha_vencimiento`)*
- **Envío y recepción** de facturas electrónicas, **al menos por email**. ✅ *Envío implementado (spec 017: SMTP por tenant, `FacturaController@enviar`).* ⚠️ ***Recepción** aún no existe.*
- **Almacenamiento** mínimo **1 GB** (segmentos I–III) / 10 GB (IV–V) para el historial.
- **Copias de seguridad**, con posibilidad de **periodicidad diaria**. ⚠️ *A definir/documentar.*
- **Verifactu**: **imprescindible** para operar en España. ⚠️ *Gap — sin spec todavía.*
- **Emisión de facturas verificables** (físico y digital), verificables por el comprador ante la AEAT
  (QR). ⚠️ *Depende de Verifactu.*

### Implicaciones / gaps para homologar esta categoría
1. **Facturae** (XML estructurado) — formato **distinto** del registro XML de Verifactu; hay que
   generarlo/exportarlo. **Nuevo requisito, sin implementar.**
2. **Verifactu real** — deja de ser "para 2027" y pasa a ser **requisito de homologación ya**.
3. **Recepción de facturas** — hoy la app solo **emite** (el **envío** por email ya está, spec 017); la categoría exige enviar **y recibir**. La recepción es el gap.
4. **Backups diarios** — capacidad a documentar (aunque sea a nivel de infraestructura/hosting).

---

## Categoría 2 — Gestión de Clientes (CRM)
*(aplicable si además homologamos el lado CRM — bono según segmento; parametrización 40 h seg. I / 30 h seg. II–III)*

**Requisitos funcionales mínimos:**

- **Gestión de clientes**: alta desde oportunidad de negocio, datos, simular compra/contratación. ✅ *(parcial: clientes ✔)*
- **Gestión de Leads**: alta manual o **importación por fichero** + reglas de asignación. ⚠️ *Gap.*
- **Gestión de oportunidades**: ofertas y **presupuestos** al lead/cliente. ⚠️ *Gap.*
- **Alertas** de clientes en **formato gráfico**. ⚠️ *(parcial: hay dashboard 015)*
- **Gestión documental** de la actividad comercial. ✅ *(spec 019 gestor documental)*
- **Diseño responsive**. ✅ *(template NexaDash)*
- **Integración con plataformas**: **APIs o Web Services**. ⚠️ *Gap.*

### Implicaciones / gaps para homologar esta categoría
1. **Leads** (con importación por fichero y reglas de asignación) — no existe.
2. **Oportunidades + presupuestos** — no existe (sería el paso previo a la factura).
3. **Alertas gráficas** de clientes — ampliar el dashboard.
4. **API / Web Services** — exponer API para consolidar datos.

---

## Decisión pendiente
¿Homologamos **solo Factura Electrónica**, **solo Gestión de Clientes**, o **ambas**? Define el
roadmap: la vía factura prioriza **Facturae + Verifactu + recepción/envío**; la vía CRM prioriza
**leads + oportunidades/presupuestos + API**.

---

## Fuentes
- Categoría CRM (guía justificación oficial): [Red.es — PDK Gestión de Clientes](https://portal.gestion.sedepkd.red.gob.es/portal/common/help/justificaciones/PDK_G_Gestion_de_Clientes.pdf), [Acelera Pyme — Gestión de clientes](https://www.acelerapyme.gob.es/en/kit-digital/gestion-clientes)
- Categoría Factura Electrónica (guía justificación oficial): [Red.es — PDK Factura Electrónica](https://portal.gestion.sedepkd.red.gob.es/portal/common/help/justificaciones/PDK_G_Factura_Electronica.pdf), [Acelera Pyme — Factura electrónica](https://www.acelerapyme.gob.es/en/kit-digital/factura-electronica)
- Convocatoria / bases: [Sede Red.es — convocatoria segmento III](https://sede.red.gob.es/es/procedimientos/convocatoria-de-ayudas-destinadas-la-digitalizacion-de-empresas-del-segmento-iii), [Orden TDF/39/2026 (AECIM)](https://acelerapyme-aecim.com/ayuda-digitalizacion/kit-digital-2026-ayudas-para-la-digitalizacion-de-pymes-y-autonomos-orden-tdf-39-2026-modificacion-de-bases-reguladoras/)
