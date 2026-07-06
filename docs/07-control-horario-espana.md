# Control horario y fichajes con geolocalización — normativa vigente (investigación, jul-2026)

> Base regulatoria que el módulo de **fichajes / registro de jornada** debe soportar. Fechas,
> plazos y umbrales verificados en julio de 2026; **la normativa está en plena transición**
> (ver §1.1), así que **revisar antes de cada release** y antes de dar por cerrada cualquier
> decisión de diseño que dependa del texto final del Real Decreto.
>
> Este documento es la fuente de verdad normativa del feature de fichajes, igual que
> `02-facturacion-espana.md` lo es para facturación. Por el Principio II de la constitución
> (España-First, RGPD/LOPDGDD incluido), **cualquier cambio normativo se refleja primero aquí,
> antes que en el código.**

---

## 1. Registro de jornada obligatorio

### 1.1. Estado normativo (¡en transición — leer antes de implementar!)

Hay que distinguir **dos capas** que hoy conviven:

1. **Base vigente y consolidada:** el registro **diario** de jornada de toda la plantilla es
   **obligatorio desde el 12 de mayo de 2019** (RDL 8/2019, que añadió el art. 34.9 del Estatuto
   de los Trabajadores). Esto **ya es ley** y no depende del decreto nuevo: registro diario con
   hora de inicio y fin, conservación de los registros **4 años** y puesta a disposición de la
   persona trabajadora, sus representantes y la Inspección de Trabajo.

2. **Reforma pendiente (registro horario *digital*):** un nuevo Real Decreto endurecería la capa
   anterior exigiendo que el registro sea **digital, inalterable y accesible en remoto** por la
   Inspección (prohibiendo papel y Excel). **A julio de 2026 este RD NO está publicado en el BOE.**
   El **Consejo de Estado emitió dictamen crítico el 23 de marzo de 2026** (problemas de impacto
   económico, encaje jurídico y protección de datos), por lo que **la fecha de entrada en vigor y
   la letra fina están en el aire.** Se ha manejado una entrada con plazo de adaptación corto
   (~20 días desde publicación) y sanciones agravadas, pero **no debe darse por firme.**

> **Decisión de proyecto (congelar aquí):** diseñamos el módulo para cumplir **ya** la capa (1) —
> que es obligación legal firme — y para estar **preparados** para la capa (2) sin rehacer el
> modelo (inalterabilidad, exportación verificable, trazabilidad de correcciones), igual que con
> Verifactu se modeló la huella/QR desde el día uno aunque el envío a la AEAT se active después.
> **No** implementamos el envío automático API-REST a la Inspección hasta que el RD esté publicado
> y su especificación técnica sea conocida (evita construir contra un borrador — Principio V, YAGNI).

### 1.2. Requisitos técnicos que impactan el modelo de datos

De la capa vigente (1) y del sentido del RD en tramitación (2):

- **Registro diario por persona trabajadora** con **hora de inicio y hora de fin** de la jornada
  efectiva. Como mínimo entrada/salida; pausas si el convenio o la empresa lo exigen.
- **Inalterabilidad y trazabilidad:** el registro no se borra ni se edita en silencio. Toda
  corrección deja rastro (quién, cuándo, valor anterior y motivo). → Encaja con el patrón
  **ledger append-only** que el proyecto ya usa en `movimientos_stock` y `factura_eventos`: el
  fichaje es un evento inmutable; una corrección es un **evento nuevo** que referencia al anterior,
  no una edición in-place.
- **Conservación 4 años** y disponibilidad para trabajador, representantes e Inspección.
- **Exportación verificable** (el RD apunta a exportación estructurada; hoy basta con poder
  entregar los registros de forma legible y completa).
- **Sellado temporal** de cada fichaje (timestamp de servidor, no del cliente — Principio III:
  la hora de referencia la fija el backend, el cliente no es fuente de verdad).

> **Implicación de diseño:** tabla `fichajes` (o `registros_jornada`) **append-only** con
> `tenant_id` (Principio I), timestamp de servidor, tipo de evento (entrada / salida / pausa_inicio
> / pausa_fin) y correcciones modeladas como eventos nuevos enlazados, nunca como UPDATE. Un
> informe de jornada por empleado/periodo se **calcula en backend** a partir de los eventos.

### 1.3. Sanciones

- Régimen vigente (LISOS, infracción grave por incumplir el registro): de **751 € a 7.500 €** por
  infracción, graduada por gravedad, nº de personas afectadas y reincidencia.
- La reforma pendiente ha manejado agravar el importe hasta el entorno de **10.000 € por persona
  trabajadora afectada** — **pendiente de confirmación** con el texto final publicado.

---

## 2. Geolocalización en el fichaje (RGPD / LOPDGDD)

La ubicación es **dato personal**; capturarla en el fichaje es un tratamiento sujeto al RGPD
(Reglamento UE 2016/679) y a la **LOPDGDD (LO 3/2018)**, en particular su **art. 90**
(geolocalización en el ámbito laboral). Esto entra de lleno en el Principio II de la constitución.

### 2.1. Cuándo es lícita (criterio AEPD)

- **Lícita solo como verificación PUNTUAL en el momento del fichaje.** Se captura la posición
  **al fichar entrada/salida**, no de forma continua.
- **Prohibido el rastreo continuo / en segundo plano.** El seguimiento continuado solo se admite
  cuando el desplazamiento es **parte esencial del trabajo** (reparto, transporte, asistencia
  técnica itinerante) y, aun así, con fuertes garantías — **fuera del alcance de este MVP.**
- **Principio de minimización:** se recoge lo imprescindible. Para control horario, idealmente
  basta con **"dentro / fuera del perímetro autorizado"** (un booleano) en lugar de guardar
  coordenadas crudas; si se guardan coordenadas, solo las del **instante del fichaje**, nunca un
  histórico de posición.
- **Proporcionalidad:** la geolocalización debe ser adecuada a la finalidad. Si el mismo control
  se logra con un medio menos invasivo, se prefiere ese.

### 2.2. Obligaciones previas (no negociables antes de activar)

- **Información previa y por escrito** a la persona trabajadora **y** a la representación legal:
  qué datos se recogen, para qué, base jurídica, plazo de conservación y derechos (acceso,
  rectificación, supresión, oposición).
- **Evaluación de Impacto (EIPD/DPIA):** la geolocalización de personas trabajadoras se considera
  tratamiento de **alto riesgo**, por lo que la EIPD es **obligatoria** antes de poner el sistema
  en marcha.
- **Política interna de uso:** dejar claro que la geo **solo** se usa en tiempo de trabajo y solo
  para verificar el fichaje.
- **Biometría prohibida** como método de fichaje (huella dactilar, reconocimiento facial), salvo
  casos excepcionales muy justificados. → **No** implementamos biometría.

### 2.3. Retención y purga (patrón obligatorio del proyecto)

Los datos de geolocalización y de jornada son datos personales y caen bajo el patrón de
retención/purga del Principio II:

- **Registro de jornada:** conservación legal **4 años** (art. 34.9 ET). No se purga antes.
- **Dato de geolocalización asociado:** aplicar minimización — plazo de conservación **configurable
  por tenant** y **purga periódica**, reutilizando `App\Support\RetencionLogsTenant` + un comando
  tipo `logs:purgar` programado vía `bootstrap/app.php` → `withSchedule` (patrón de la feature
  021), **sin inventar un mecanismo nuevo.** Si se opta por guardar solo el booleano
  dentro/fuera, el problema de retención de la coordenada cruda desaparece.

> **Implicación de diseño:** separar conceptualmente **(a)** el registro de jornada (retención 4
> años, obligatorio) de **(b)** el dato de geo del fichaje (minimizado, retención corta y
> configurable, purgable). No mezclar ambos plazos en la misma columna.

---

## 3. Encaje con la arquitectura y hosting compartido

El módulo es viable en **hosting compartido (cPanel/Hostinger)** sin necesidad de VPS
(Principio V), porque la parte costosa no vive en el servidor:

- **La posición se captura en el cliente** con la Geolocation API del navegador
  (`navigator.geolocation`). El servidor solo recibe lat/long. **No** hace falta PostGIS ni
  extensiones espaciales de MySQL. **Requiere HTTPS** (ya disponible en el hosting).
- **El geofencing (validar radio autorizado) es aritmética trivial** (fórmula de Haversine en
  PHP/SQL). A escala pyme no requiere índices espaciales. La validación **se hace en backend**
  (Principio III): el cliente nunca es fuente de verdad de "estaba dentro del perímetro".
- **Sin tiempo real ni workers persistentes:** un fichaje es un `POST` puntual. No hay websockets
  ni colas siempre-activas (que es justo lo que el hosting compartido no permite).
- **La purga RGPD y los informes programados** usan el **scheduler de Laravel** sobre el **cron**
  de cPanel — el mismo mecanismo `withSchedule` ya en uso para `logs:purgar`.
- **Multi-tenant (Principio I):** `fichajes`, `miembros_equipo` (cada uno guarda su propio
  perímetro de trabajo — no hay tabla compartida `ubicaciones_trabajo`, decisión revisada durante
  el diseño, ver `specs/024-control-horario-fichajes/research.md` D10) y `alertas` llevan
  `tenant_id` indexado y pasan por `BelongsToTenant`. Tests de no-fuga entre tenants (Principio IV).

**Dónde el hosting compartido *sí* pesaría (y cómo evitarlo):** fotos de fichaje o mapas
renderizados en servidor (CPU/memoria) → si se quiere foto, redimensionar en cliente; nada de
procesamiento de imágenes server-side. Y **no** guardar rastro continuo de posición (sería
volumen de escritura alto **y** ilegal, §2.1).

---

## 4. Decisiones abiertas (para `/speckit-clarify`)

Estas son las decisiones que **deben resolverse antes de planificar** el feature; su respuesta
cambia el modelo de datos y el alcance:

1. **¿Qué se guarda del fichaje geolocalizado?**
   (a) solo booleano dentro/fuera del perímetro + id de ubicación *(máxima minimización,
   recomendado)*; (b) coordenadas del instante del fichaje; (c) coordenadas + precisión + distancia
   al perímetro. Impacta directamente en retención (§2.3).

2. **¿El geofencing es bloqueante o informativo?**
   ¿Se **impide** fichar fuera del radio, o se **permite** y se marca "fuera de ubicación" para
   revisión? (Relevante para teletrabajo / trabajo de campo.)

3. **¿Alcance de ubicaciones:** un solo centro por tenant, o múltiples `ubicaciones_trabajo` con
   su radio configurable?**

4. **¿Se soporta fichaje remoto / teletrabajo** (sin perímetro, o con perímetro "domicilio")?

5. **¿Se registran pausas** (inicio/fin de descanso) o solo entrada/salida de jornada?

6. **¿Quién es "empleado"?** ¿Cada `user` del tenant ficha, o hay una entidad `empleado` separada
   (con posible relación a `user`)? ¿Los fichan ellos mismos o un responsable?

7. **Plazo de retención del dato de geo** (§2.3): valor por defecto configurable por tenant.

8. **¿Corrección de fichajes:** quién puede corregir (rol), y se materializa como evento nuevo
   enlazado (recomendado, §1.2) con motivo obligatorio?

9. **¿Portal de la persona trabajadora** para consultar/descargar sus propios registros (derecho
   del art. 34.9 ET), o solo vista de administración por ahora?

10. **EIPD e información previa (§2.2):** ¿el software debe **generar/gestionar** el consentimiento
    informado y dejar constancia de la entrega, o eso queda como proceso externo del tenant? (Si
    lo gestiona el software, hace falta modelarlo.)

---

## 5. Fuentes

- **Registro de jornada (base vigente):** RDL 8/2019, de 8 de marzo (art. 34.9 del Estatuto de los
  Trabajadores). Conservación 4 años; disposición a trabajador, representantes e Inspección.
- **Reforma registro horario digital 2026:** en tramitación; **dictamen del Consejo de Estado de
  23-mar-2026** (crítico). Sin publicación en BOE a jul-2026. Requisitos manejados: inalterabilidad,
  acceso remoto de la Inspección (API-REST), prohibición de papel/Excel, sanciones agravadas.
- **Geolocalización laboral:** **art. 90 LOPDGDD (LO 3/2018)** + criterios de la **AEPD**
  (información previa, minimización, proporcionalidad, EIPD obligatoria, licitud solo puntual).
- **RGPD:** Reglamento (UE) 2016/679 (minimización, alto riesgo → EIPD, derechos del interesado).
- **Sanciones:** baremo LISOS (grave: 751 €–7.500 €); agravamiento a ~10.000 €/persona en la
  reforma pendiente (sin confirmar).
- **Biometría:** prohibida como método de fichaje salvo excepción (criterio AEPD 2026).

> Nota: los importes, plazos y el estado del RD deben **re-verificarse contra BOE/AEPD** antes de
> cada release, dado que el marco está en transición (§1.1).
