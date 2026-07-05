# Facturación en España — normativa vigente (investigación, jul-2026)

> Base regulatoria que el modelo de datos debe soportar. Fechas y porcentajes verificados en julio de 2026; **revisar antes de cada release** porque la normativa está en plena transición.

## 1. Verifactu / Sistemas Informáticos de Facturación (SIF)

**Norma:** Real Decreto 1007/2023, desarrollado por RD 254/2025 y Orden HAC/1177/2024.

**Qué obliga:** todo software de facturación (SIF) debe garantizar la **inalterabilidad, trazabilidad y verificación** de las facturas emitidas.

**Requisitos técnicos que impactan el modelo de datos:**
- **Huella / hash (SHA-256)** por cada registro de facturación.
- **Encadenamiento:** el hash de cada registro incluye el hash del registro anterior → cadena inalterable. Modificar/borrar una factura intermedia rompe la cadena.
- **Registro de eventos** (log de operaciones del sistema).
- **Código QR** en cada factura + texto **"VERI*FACTU"** cuando se remite a la AEAT.
- **Exportación en XML** según Orden HAC/1177/2024.
- Campos mínimos del registro: NIF emisor, NIF receptor (si aplica), nº factura, fecha, base imponible, tipo y cuota de IVA, hash del registro, hash del registro anterior.

**Plazos de obligatoriedad (actualizados; aplazados a 2027):**
- **1 de enero de 2027** — contribuyentes del Impuesto sobre Sociedades (empresas/pymes).
- **1 de julio de 2027** — autónomos y resto.

**Sanción por software no homologado:** hasta **50.000 €** por ejercicio.

> **Implicación de diseño:** el modelo de facturas incluye desde el día uno los campos de huella, hash anterior, QR y estado Verifactu, aunque la emisión efectiva a la AEAT se active más adelante.

## 2. Factura electrónica B2B obligatoria (Ley Crea y Crece)

**Norma:** Ley Crea y Crece + **Real Decreto 238/2026** (BOE 31-mar-2026), que modifica el Reglamento de facturación (RD 1619/2012).

**Calendario:**
- Orden ministerial en vigor: **1 de octubre de 2026**.
- **Octubre 2027** — empresas con facturación anual > 8 M€.
- **Octubre 2028** — resto de empresas, pymes y autónomos.

**Novedad clave:** la factura pasa a ser un instrumento de **seguimiento del ciclo comercial**. Hay que **reportar el estado** de cada factura (aceptada, rechazada, pagada) en un máximo de **4 días hábiles**.

> **Implicación de diseño:** las facturas necesitan un **estado de ciclo B2B** (aceptada/rechazada/pagada) con su fecha, además del estado interno.

## 3. Tipos de factura

| Tipo | Cuándo | Particularidades |
|------|--------|------------------|
| **Completa / ordinaria** | Estándar entre empresas/profesionales, o cuando se supera el límite de simplificada | Requiere identificar al cliente (NIF, nombre, dirección) |
| **Simplificada** (antiguo "ticket") | Importe total ≤ **400 €** (IVA incl.); o ≤ **3.000 €** (IVA incl.) en sectores específicos (venta al por menor, hostelería/restauración, transporte de personas, peluquerías, aparcamiento, etc. — ver 3.1); o cuando deba expedirse una **rectificativa** (esta última vía no exige tope de importe) | No requiere datos del cliente salvo que este exija factura completa ("**simplificada cualificada**", ver 3.1) |
| **Rectificativa** | Corregir una factura ya emitida | **Serie propia** (prefijo "R"/"RE"), numeración correlativa sin huecos, debe indicar su condición, el **motivo**, y la **referencia** a la(s) factura(s) rectificada(s). Modalidad por sustitución o por diferencias |

### 3.1 Factura simplificada — contenido y variante "cualificada"

**Contenido mínimo** (RD 1619/2012, art. 7.1), más reducido que la completa:
- Número y, en su caso, serie (correlativo, sin huecos).
- Fecha de expedición y fecha de la operación si es distinta.
- NIF e identificación del **emisor** (el receptor NO es obligatorio en la variante simple).
- Identificación del bien entregado o servicio prestado.
- Tipo impositivo (puede añadirse "IVA incluido"); si hay varios tipos en la misma factura, desglosar base imponible por cada uno.
- Contraprestación total (importe final a pagar).
- Mención "régimen especial del criterio de caja" si aplica (régimen fuera de alcance del modelo actual).
- Si es una rectificativa en formato simplificado: referencia a la factura rectificada y detalle de lo modificado.

**Simplificada "cualificada"**: cuando el destinatario es un empresario/profesional que quiere deducirse el impuesto, o un particular que exige factura para ejercer un derecho tributario, la simplificada debe incorporar además el **NIF y domicilio del destinatario** y la **cuota repercutida** (desglose del IVA, no solo "IVA incluido"). Es decir: sigue siendo tipo "simplificada" (no pasa a ordinaria), pero con más datos del receptor — en la práctica, los mismos campos de receptor que ya existen en el modelo para la ordinaria, simplemente opcionales/vacíos cuando no se piden.

> **Implicación de diseño:** el modelo de `facturas` ya tiene `cliente_id` y el snapshot `cliente_*` como **nullable** (pensado desde el inicio para simplificada). No hace falta ninguna columna nueva para soportar la variante cualificada: si el usuario completa el receptor en una factura `tipo = simplificada`, es cualificada; si lo deja vacío, es la variante simple. La validación de importe (≤ 400 €/3.000 € según sector) y la lista de sectores con tope ampliado quedan como reglas de negocio a implementar en la feature correspondiente, no como columnas.

## 4. Campos obligatorios de la factura completa
- Número y, en su caso, **serie** (correlativo, sin huecos).
- **Fecha de expedición** y, si difiere, **fecha de operación**.
- **Emisor:** NIF, nombre/razón social, domicilio.
- **Receptor:** NIF, nombre/razón social, domicilio.
- **Descripción** de la operación.
- **Base imponible** por cada tipo impositivo.
- **Tipo de IVA** aplicado y **cuota** resultante.
- **Total**.
- Menciones especiales cuando apliquen (inversión del sujeto pasivo, exención, régimen especial, etc.).

## 5. Impuestos y retenciones

### IVA (tipos vigentes 2026)
- **21 %** — general.
- **10 %** — reducido (hostelería, transporte de viajeros, vivienda, alimentos en general…).
- **4 %** — superreducido (pan, leche, huevos, frutas/verduras, libros, medicamentos…).
- **0 % / exento** — determinadas operaciones (seguros, servicios financieros, etc.).

### Recargo de equivalencia (ventas a minorista en ese régimen — se suma al IVA)
- **5,2 %** con IVA 21 %.
- **1,4 %** con IVA 10 %.
- **0,5 %** con IVA 4 %.

### IRPF (retención en facturas de autónomos/profesionales)
- Retención habitual **15 %** (o **7 %** para nuevos autónomos los primeros años).
- Se **resta** del total de la factura.

### Regímenes territoriales del impuesto indirecto
El impuesto indirecto **no es siempre IVA** según dónde tribute el emisor:

- **Península y Baleares → IVA** (21 / 10 / 4 / 0).
- **Canarias → IGIC** (Impuesto General Indirecto Canario). Tipos vigentes 2026: **0 %**, **3 %** (reducido), **7 %** (general), **9,5 %** (incrementado), **15 %** (especial incrementado) y **20 %** (tabaco rubio). El recargo de equivalencia del IVA no aplica; Canarias tiene su propio régimen para minoristas.
- **Ceuta y Melilla → IPSI** (Impuesto sobre la Producción, los Servicios y la Importación), con tipos propios de cada ciudad autónoma.

> **Implicación de diseño:** el desglose de impuestos debe ser **por tipo impositivo** y **agnóstico al régimen**. Una factura mezcla varios tipos, pero todos del mismo régimen (IVA *o* IGIC *o* IPSI). Se guarda un `regimen_impositivo` a nivel de tenant (por defecto) y de factura (congelado al emitir). El IRPF y el recargo se tratan aparte, a nivel de factura.

## 6. Series y numeración
- Numeración **correlativa dentro de cada serie**, **sin huecos**.
- Se pueden usar varias series (p. ej. por año, por tipo, por punto de venta).
- Las **rectificativas** llevan **serie separada** obligatoriamente.

---

## Fuentes
- Verifactu / plazos: [b2brouter](https://www.b2brouter.net/es/verifactu-obligatorio-autonomos/), [AEAT — nota ampliación de plazo](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html), [autonomosyemprendedor](https://www.autonomosyemprendedor.es/articulo/autonomos/nuevos-plazos-verifactu-2027-que-autonomos-van-tener-que-cambiar-programas-facturacion/20251230143941047321.html)
- Verifactu técnica (hash/QR/XML): [AEAT — FAQ huella/hash](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/huella-hash.html), [AEAT — FAQ sistemas Verifactu](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html)
- Factura electrónica B2B / RD 238/2026: [BOE-A-2026-7295](https://www.boe.es/diario_boe/txt.php?id=BOE-A-2026-7295), [AEAT — facturación electrónica obligatoria](https://sede.agenciatributaria.gob.es/Sede/todas-noticias/2026/marzo/31/facturacion-electronica-obligatoria.html), [BBVA](https://www.bbva.com/es/es/empresas/factura-electronica-b2b-y-ley-crea-y-crece-calendario-requisitos-y-retos/)
- Tipos de factura / campos: [AEAT — contenido de las facturas](https://sede.agenciatributaria.gob.es/Sede/iva/facturacion-registro/facturacion-iva/contenido-facturas.html), [tukonta — factura simplificada](https://tukonta.com/asesoramiento/factura-simplificada/), [AEAT — facturas rectificativas](https://sede.agenciatributaria.gob.es/Sede/iva/facturacion-registro/facturacion-iva/facturas-rectificativas.html)
- Factura simplificada (supuestos, contenido, cualificada): [AEAT — Manual actividades económicas 5.10.6 Facturas simplificadas](https://sede.agenciatributaria.gob.es/Sede/ayuda/manuales-videos-folletos/manuales-practicos/folleto-actividades-economicas/5-impuesto-sobre-valor-anadido/5_10-facturas/5_10_6-facturas-simplificadas.html)
- IVA / recargo / IRPF: [AEAT — tipos impositivos IVA](https://sede.agenciatributaria.gob.es/Sede/iva/calculo-iva-repercutido-clientes/tipos-impositivos-iva.html)
- IGIC Canarias 2026: [guiafiscal — IGIC 2026](https://guiafiscal.es/iva/igic-canarias-2026/), [KPMG — cambios tipos IGIC 2026](https://assets.kpmg.com/content/dam/kpmgsites/es/pdf/2026/01/tax-alert-cambios-tipos-igic-2026.pdf.coredownload.inline.pdf)
- Numeración/series: [AEAT — recomendaciones numeración](https://sede.agenciatributaria.gob.es/Sede/iva/facturacion-registro/facturacion-iva.html)
