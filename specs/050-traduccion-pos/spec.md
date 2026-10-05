# Feature Specification: Traducción del POS al chino

**Feature Branch**: `050-traduccion-pos`

**Created**: 2026-10-05

**Status**: Draft

**Input**: User description: "Traducción del módulo POS al chino (feature 050). El tenant elige el idioma del POS (Español / Chino simplificado) desde Configuración → POS; todo el módulo POS (Crear ticket/TPV, Sala y plano, Caja y cierres, Opciones de artículo, listado de tickets, modales, toasts/avisos de los JS, mensajes de error/validación del POS y sus guías de ayuda in-app) se muestra en ese idioma. Traducción automática con una API de terceros (DeepL, cuenta ya creada, clave de plataforma en .env: DEEPL_API_KEY / DEEPL_API_URL, plan gratuito 1M caracteres/mes) con caché persistente: cada texto en español se traduce una sola vez y se guarda; si el texto español cambia, se traduce de nuevo solo; un comando de deploy pre-traduce lo pendiente y, como respaldo, lo no traducido se traduce la primera vez que se ve; si la API falla se muestra en español sin romper el POS. Glosario obligatorio de términos del POS (DeepL tradujo "Precuenta" como 预算 = presupuesto). Las traducciones guardadas se pueden corregir manualmente y una corrección manual no se vuelve a pisar. Fuera de alcance: el asistente IA (experimental), el resto de la app (el mecanismo debe servir para extenderlo después), traducir datos del negocio (artículos, categorías: el cliente los carga en chino). Los PDFs del ticket y la precuenta necesitan una fuente con caracteres chinos (DejaVu Sans no los tiene). El idioma de los documentos fiscales (ticket/factura simplificada) debe verificarse contra la normativa española antes de decidir si se traducen."

## Contexto y encuadre

Un cliente del SaaS es un negocio de hostelería regentado por personas chinas, con personal y
clientela mayoritariamente china. Pidió poder usar la aplicación en chino, sobre todo el POS, que
es donde se trabaja durante todo el turno. Traducir la aplicación entera de una vez es un trabajo
grande; esta feature traduce **el módulo POS** y deja el mecanismo listo para extenderlo después al
resto de la aplicación pantalla a pantalla, sin rehacer nada.

Decisiones ya tomadas con el usuario antes de esta spec:

- **El idioma lo elige el tenant**, en Configuración → POS (no cada usuario): en este negocio todo
  el personal trabaja en chino.
- **La traducción es automática, con una API de terceros** (DeepL), no un trabajo manual de
  traducción. El motivo explícito del usuario: no mantener archivos de traducciones a mano cada vez
  que cambia un texto. Pero no se llama a la API cada vez que alguien abre una pantalla: cada texto
  se traduce **una vez**, se guarda y se reutiliza.
- **La cuenta de DeepL ya existe** (plan gratuito, 1.000.000 de caracteres al mes) y su clave es de
  la plataforma, no de cada tenant. Probada el 2026-10-05: sin glosario tradujo «Precuenta» como
  «预算» («presupuesto»), lo que obliga a fijar los términos propios del POS.
- **El asistente IA queda fuera**: es experimental.

### Encuadre normativo

El Reglamento de facturación (RD 1619/2012, art. 12.2) permite **expedir facturas en cualquier
lengua**; la Administración tributaria puede exigir después una traducción al castellano (u otra
lengua oficial en España) de las facturas expedidas, cuando lo considere necesario para una
comprobación. Por tanto traducir el ticket (factura simplificada) **es legal**, pero obliga a poder
producir su versión en castellano si la AEAT la pide. La precuenta (feature 049) no es un documento
fiscal y no tiene esa restricción. Decisión del usuario (2026-10-05): con el POS en chino, ticket y
precuenta se imprimen **bilingües español/chino**, de modo que el castellano está siempre en el
propio documento y la exigencia de traducción queda cubierta sin generar nada aparte. El cambio se documenta en `docs/02-facturacion-espana.md` antes
del código (Principio II).

### Convenciones de front que condicionan esta spec

Leídas antes de redactar, según la REGLA DE ORO de `CLAUDE.md`:

- `docs/04-front-guidelines.md` § **"Ayuda contextual"** — la guía de cada pantalla vive en su propio
  archivo y se muestra en el modal global; las guías del POS (`pos`, `pos-crear`, `pos-sala`,
  `pos-caja`, `pos-caja-cierres`, `pos-opciones`) se traducen con el mismo mecanismo que la pantalla.
- `docs/04-front-guidelines.md` § **"Notificaciones"** — los avisos son siempre toastr; sus textos,
  que nacen en los JS del POS y en los mensajes del servidor, también se traducen.
- `docs/04-front-guidelines.md` § **"Listados: SIEMPRE DataTable"** y § **"DataTable: botones
  Anterior/Siguiente"** — los listados del POS (tickets, cierres de caja, opciones) son DataTables
  con textos propios del plugin («Buscar», «Mostrar», «Anterior»…): forman parte de lo que se
  traduce, y el override de ancho de esos botones debe seguir funcionando con textos en chino.
- `docs/04-front-guidelines.md` § **"Bloque QR normativo en documentos PDF"** y § **"«Ver» un
  documento… en modal"** — los PDFs del ticket y la precuenta no cambian de estructura; solo
  necesitan poder mostrar caracteres chinos.
- `docs/04-front-guidelines.md` § **"Nueva entrada de menú ⇒ nuevo permiso"** — no aplica a la
  pantalla del POS en sí; aplica a la pantalla de corrección de traducciones si se le da entrada de
  menú (ver FR-016).
- `docs/01-arquitectura.md` **Decisión 10** (módulo opcional por tenant con flags en
  `configuraciones`) — el idioma del POS es un ajuste más de `ConfigPos`, con español por defecto
  por ausencia de fila, sin migración de datos.
- `.specify/memory/constitution.md` — Principio I (aislamiento: el idioma es por tenant; las
  traducciones de textos de la app son compartidas, ver Assumptions), Principio II (idioma de los
  documentos fiscales), Principio III (ningún importe cambia por el idioma), Principio V (sin
  infraestructura nueva: la traducción no requiere colas ni servicios propios).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Usar el POS en chino (Priority: P1)

La encargada del local entra en Configuración → POS y elige «中文 (chino simplificado)» como idioma
del POS. Desde ese momento, todo el personal del local ve el POS en chino: la pantalla de crear
ticket con sus botones y el modal de cobro, la Sala con sus mesas y estados, la Caja, el listado de
tickets, las opciones de artículo, los avisos que aparecen al guardar o cobrar y la ayuda de cada
pantalla. Los importes, los nombres de los platos que cargaron ellos y los números siguen igual.

**Why this priority**: es exactamente lo que pidió el cliente. Sin esto no hay feature.

**Independent Test**: con un tenant en chino, recorrer las pantallas del POS y comprobar que ningún
texto propio de la aplicación aparece en español (salvo los que no tengan traducción todavía, que
se muestran en español sin romper nada), y que un tenant en español no ve ningún cambio.

**Acceptance Scenarios**:

1. **Given** un tenant con el POS en español (el valor por defecto), **When** se abre cualquier
   pantalla del POS, **Then** se ve exactamente igual que antes de esta feature.
2. **Given** un tenant que elige chino en Configuración → POS, **When** cualquier usuario de ese
   tenant abre una pantalla del POS, **Then** los textos de la interfaz (títulos, botones,
   etiquetas, estados de mesa, cabeceras de tabla, mensajes vacíos, modales, avisos) se ven en
   chino.
3. **Given** el POS en chino, **When** se produce un aviso o un error (guardar cuenta, cobrar, caja
   cerrada, precuenta desactualizada, validación de un formulario del POS), **Then** el mensaje
   aparece en chino.
4. **Given** el POS en chino, **When** se abre «Ayuda de esta pantalla» en una pantalla del POS,
   **Then** la guía se muestra en chino.
5. **Given** el POS en chino, **When** se miran importes, nombres de artículos, categorías, mesas y
   zonas, **Then** se muestran tal como los cargó el negocio, sin traducir, y los importes no
   cambian.
6. **Given** dos tenants, uno en chino y otro en español, **When** cada uno usa el POS, **Then**
   cada uno lo ve en su idioma, sin que el ajuste de uno afecte al otro.

---

### User Story 2 - Que los términos del POS se traduzcan siempre bien y siempre igual (Priority: P1)

El personal ve «precuenta», «suplemento de zona», «cuenta aparcada», «caja», «arqueo» o «ticket»
traducidos con la palabra correcta del oficio en chino, y siempre con la misma palabra en todas las
pantallas. Nunca aparece «presupuesto» donde dice «precuenta».

**Why this priority**: una traducción automática sin control usa palabras distintas para lo mismo
o directamente equivocadas (caso real: «Precuenta» → «presupuesto»). En un TPV eso confunde al
personal en pleno servicio y puede llevar a errores al cobrar. Sin esto, la US1 no es usable.

**Independent Test**: comprobar que cada término de la lista fija del POS aparece con su traducción
fijada en todas las pantallas donde aparece.

**Acceptance Scenarios**:

1. **Given** la lista de términos del POS con su traducción fijada, **When** se traduce cualquier
   texto que contiene uno de esos términos, **Then** el término aparece con la traducción fijada.
2. **Given** un término fijado que aparece en varias pantallas, **When** se recorren, **Then** se
   ve siempre igual.

---

### User Story 3 - Cambiar un texto en español sin tener que tocar traducciones (Priority: P1)

El equipo de desarrollo cambia un texto del POS (por ejemplo, un botón pasa de «Cobrar» a «Cobrar
mesa») y lo despliega como cualquier cambio. Nadie edita archivos de traducción: el texto nuevo se
traduce solo, y el antiguo deja de usarse.

**Why this priority**: es la condición que puso el usuario para hacer la feature: no mantener
traducciones a mano.

**Independent Test**: cambiar un texto del POS, desplegar, y comprobar que en un tenant en chino
aparece traducido sin intervención manual.

**Acceptance Scenarios**:

1. **Given** un texto nuevo o modificado en el POS, **When** se hace el despliegue con su paso de
   traducción, **Then** el texto queda traducido antes de que nadie lo vea.
2. **Given** un texto que por algún motivo no se tradujo en el despliegue, **When** un usuario en
   chino lo ve por primera vez, **Then** se traduce en ese momento (o se muestra en español si la
   traducción no está disponible) y las siguientes veces ya sale traducido sin esperar.
3. **Given** que el servicio de traducción no responde o se agotó el cupo, **When** un usuario en
   chino usa el POS, **Then** el POS funciona con normalidad y los textos sin traducir salen en
   español; los ya traducidos siguen en chino.

---

### User Story 4 - Corregir una traducción que quedó mal (Priority: P2)

La encargada ve que un botón quedó traducido de forma rara. Desde la configuración del POS busca
ese texto y escribe la traducción correcta. A partir de ahí todos los usuarios ven la corrección, y
ningún despliegue futuro la vuelve a cambiar.

**Why this priority**: la traducción automática nunca es perfecta. Sin una vía de corrección, cada
error depende de que alguien del equipo lo arregle en código.

**Independent Test**: corregir una traducción, comprobar que se ve en el POS, ejecutar el paso de
traducción del despliegue y comprobar que la corrección sigue intacta.

**Acceptance Scenarios**:

1. **Given** una traducción automática, **When** un usuario con permiso la corrige, **Then** el POS
   muestra la corrección en todas sus pantallas.
2. **Given** una traducción corregida a mano, **When** se vuelve a ejecutar la traducción
   automática, **Then** la corrección se conserva.
3. **Given** una traducción corregida a mano, **When** el texto original en español cambia, **Then**
   el texto nuevo se traduce automáticamente y la corrección anterior deja de aplicarse (era para
   otro texto), sin perderse del historial de quien la hizo.
4. **Given** dos tenants en chino, **When** uno corrige una traducción, **Then** el otro sigue viendo
   la traducción automática.

---

### User Story 5 - Ticket y precuenta bilingües y con caracteres chinos (Priority: P2)

El negocio carga sus platos con nombres en chino. Al imprimir el ticket o la precuenta, los nombres
salen correctamente, no como cuadrados vacíos, y las etiquetas del documento aparecen en español y
en chino, para que lo entienda el cliente de la mesa y siga siendo válido ante la AEAT sin
traducción aparte.

**Why this priority**: mostrar caracteres chinos es independiente de traducir la interfaz: en cuanto
el negocio cargue datos en chino, los documentos tienen que poder mostrarlos, y hoy no pueden. Lo
bilingüe es lo que ve el cliente final del negocio.

**Independent Test**: con el POS en chino, emitir un ticket y una precuenta con un artículo de nombre
chino y comprobar que el nombre se ve y que cada etiqueta aparece en los dos idiomas.

**Acceptance Scenarios**:

1. **Given** un artículo con nombre en chino, **When** se emite su ticket o su precuenta, **Then**
   el nombre se lee correctamente en el PDF, sea cual sea el idioma del POS.
2. **Given** el POS en chino, **When** se emite un ticket o una precuenta, **Then** cada texto propio
   del documento aparece en español y en chino, y los importes, el número, el QR y las menciones
   fiscales son los mismos que tendría en español.
3. **Given** un tenant con el POS en español, **When** se emiten sus documentos, **Then** se ven igual
   que antes.

---

### Edge Cases

- **Texto con partes variables** (por ejemplo «Mesa 4 · 3 comensales», «Cuenta guardada.», «No
  quedan tantas unidades pendientes de «Caña»»): se traduce la plantilla del texto, no cada
  combinación; el nombre de la mesa, del artículo o el importe se insertan después, sin traducir.
- **Cupo mensual agotado o servicio caído durante un despliegue**: el despliegue no falla por eso;
  quedan textos pendientes que se traducen más tarde (en el siguiente intento o al verse).
- **Texto que no debe traducirse** (códigos, siglas fiscales como «IVA», «NIF», «VERI*FACTU»,
  formatos de número y moneda): se mantiene igual en la traducción.
- **Textos largos en chino o en español que rompan el diseño** (botones táctiles, chips, tarjetas de
  mesa): el diseño del POS no se desborda en ninguno de los dos idiomas.
- **Cambiar el idioma con usuarios trabajando**: el cambio se aplica a partir de la siguiente pantalla
  que carguen; no interrumpe un cobro en curso.
- **Un texto que aparece fuera del POS y dentro del POS** (por ejemplo, un aviso genérico): dentro de
  las pantallas del POS se ve traducido; fuera, en español.
- **Volver a español**: el POS vuelve a verse en español de inmediato; las traducciones guardadas no
  se pierden.
- **Tenant nuevo o existente sin tocar la configuración**: el POS está en español.

## Requirements *(mandatory)*

### Functional Requirements

**Configuración del idioma**

- **FR-001**: El sistema DEBE permitir elegir el idioma del POS de cada tenant desde Configuración →
  POS, con dos opciones: español (por defecto) y chino simplificado.
- **FR-002**: El idioma del POS DEBE ser independiente de que el módulo de hostelería esté activo: el
  listado de tickets, crear ticket y la caja existen sin él y también se traducen.
- **FR-003**: Un tenant que no haya elegido idioma DEBE ver el POS en español, sin necesidad de
  migrar ningún dato.

**Alcance de la traducción**

- **FR-004**: Con el idioma del POS en chino, DEBEN mostrarse en chino todos los textos propios de la
  aplicación en las pantallas del POS: listado de tickets (facturas simplificadas), crear ticket
  (TPV, cobro, cliente, precuenta, opciones de artículo, transferir/unir, caja desde el TPV), Sala
  (tarjetas, plano y editor del plano), Caja y cierres de caja (incluido el informe de cierre, que es
  un documento interno y se traduce entero), Opciones de artículo, y la pantalla de módulo inactivo.
- **FR-005**: La traducción DEBE alcanzar también los avisos y confirmaciones que muestran esas
  pantallas, los mensajes de error y de validación que devuelve el servidor en las operaciones del
  POS, los textos de los listados (incluidos los del propio componente de tabla: buscar, paginar,
  sin resultados) y las guías de «Ayuda de esta pantalla» del POS.
- **FR-006**: Los datos del negocio (nombres de artículos, categorías, opciones, mesas, zonas,
  clientes, notas) NO DEBEN traducirse: se muestran tal como se cargaron.
- **FR-007**: Los importes, cantidades y la moneda NO DEBEN cambiar por el idioma.
- **FR-008**: Fuera de las pantallas del POS la aplicación DEBE seguir en español. El asistente IA
  queda fuera de alcance.
- **FR-009**: Con el POS en chino, en el menú lateral DEBEN verse en chino las entradas del grupo POS
  (el propio grupo, Facturas simplificadas, Crear ticket, Caja, Sala, Opciones de artículo) y el
  botón «Ayuda de esta pantalla», en cualquier pantalla. Una entrada cuyo nombre personalizó el
  tenant se muestra tal como la escribió. El resto del menú lateral y la barra superior quedan en
  español en esta entrega.

**Traducción automática**

- **FR-010**: Cada texto en español DEBE traducirse automáticamente con el servicio de traducción una
  sola vez y quedar guardado; las siguientes veces se usa la traducción guardada, sin volver a
  llamar al servicio.
- **FR-011**: Cuando un texto en español cambia, el texto nuevo DEBE traducirse de nuevo sin que nadie
  edite traducciones a mano.
- **FR-012**: El despliegue DEBE poder traducir por adelantado todos los textos del POS pendientes, de
  modo que normalmente ningún usuario vea un texto sin traducir; si quedara alguno, DEBE traducirse
  la primera vez que se muestre.
- **FR-013**: Si el servicio de traducción falla, no responde a tiempo o se agotó el cupo, el POS DEBE
  seguir funcionando y mostrar en español los textos sin traducción guardada. Un fallo de traducción
  nunca impide guardar, cobrar ni cerrar caja.
- **FR-014**: Las partes variables de un texto (nombres, números, importes) DEBEN conservarse
  intactas en la traducción y colocarse donde corresponde en la frase traducida.
- **FR-015**: La traducción DEBE usar una lista fija de términos del POS con su traducción decidida
  (glosario), que se aplica siempre que aparecen. Como mínimo: precuenta, ticket, factura
  simplificada, cuenta, cuenta aparcada, mesa, sala, zona, suplemento de zona, comensales, caja,
  fondo de cambio, arqueo, cierre de caja, cobro, cobro por partes, opción, grupo de opciones,
  reimpresión. Siglas y marcas (IVA, IGIC, IPSI, NIF, VERI*FACTU, TPV) no se traducen.

**Corrección manual**

- **FR-016**: Un usuario con permiso de configuración DEBE poder buscar los textos traducidos del POS
  y corregir su traducción desde Configuración → POS.
- **FR-017**: Una traducción corregida a mano NO DEBE ser sobrescrita por la traducción automática.
- **FR-018**: Una corrección manual DEBE aplicarse **solo al tenant que la hizo**: los demás tenants
  en ese idioma siguen viendo la traducción automática (o su propia corrección). Ningún tenant puede
  ver ni modificar las correcciones de otro.

**Documentos**

- **FR-019**: El ticket (en sus dos formatos), la factura simplificada en A4 y la precuenta DEBEN
  mostrar correctamente caracteres chinos en los datos del negocio (artículos, opciones, cliente,
  notas), cualquiera que sea el idioma del POS. Un documento sin ningún carácter chino (ni en los
  datos ni por ser bilingüe) DEBE verse exactamente igual que antes de esta feature.
- **FR-020**: Con el POS en chino, el ticket (80 mm y A4) y la precuenta DEBEN imprimirse
  **bilingües**: cada texto propio del documento (títulos, etiquetas, leyendas, totales, menciones
  como «IVA incluido» o «Documento no válido como factura») aparece en español y en chino. El español
  está siempre presente, de modo que el documento nunca necesita una traducción aparte si la AEAT la
  pide. Con el POS en español los documentos no cambian.
- **FR-021**: El idioma del POS NO DEBE alterar ningún dato fiscal del ticket: numeración, importes,
  impuestos, QR ni registro Verifactu.

**Documentación**

- **FR-022**: La guía de «Ayuda de esta pantalla» de Configuración y la base de conocimiento de la
  aplicación DEBEN explicar el nuevo ajuste de idioma y cómo corregir traducciones.

### Key Entities

- **Idioma del POS del tenant**: ajuste de configuración del tenant (español por defecto, o chino
  simplificado). Decide en qué idioma ve el POS todo el personal de ese tenant.
- **Traducción guardada**: un texto de la aplicación en español, su idioma de destino y su traducción
  automática. Es compartida por toda la aplicación y es la memoria que evita volver a traducir lo
  mismo.
- **Corrección de traducción del tenant**: la traducción que un tenant escribió a mano para un texto
  concreto, con quién y cuándo la corrigió. Pertenece a ese tenant y prevalece, solo para él, sobre
  la traducción automática.
- **Término fijado (glosario)**: un término del POS en español con su traducción decidida a un
  idioma, que el servicio de traducción respeta siempre.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Con el POS en chino, en un recorrido por todas las pantallas del POS, el 100 % de los
  textos propios de la aplicación aparecen en chino (excepto siglas y marcas definidas como no
  traducibles).
- **SC-002**: Un tenant que no cambia el idioma no nota ninguna diferencia respecto de antes de la
  feature.
- **SC-003**: Abrir cualquier pantalla del POS en chino tarda lo mismo que en español en el uso
  normal: el servicio de traducción no interviene en la carga de pantallas ya traducidas.
- **SC-004**: Cambiar o añadir un texto del POS requiere cero ediciones manuales de traducción.
- **SC-005**: Con el servicio de traducción caído, el 100 % de las operaciones del POS (guardar,
  cobrar, precuenta, abrir y cerrar caja) siguen funcionando.
- **SC-006**: Los términos fijados del glosario se ven con su traducción fijada en el 100 % de sus
  apariciones.
- **SC-007**: Una corrección manual se ve en el POS en cuanto se guarda y sobrevive al 100 % de los
  despliegues posteriores.
- **SC-008**: Un ticket y una precuenta con nombres de artículo en chino se imprimen legibles y, con el
  POS en chino, el 100 % de sus etiquetas aparece en español y en chino.
- **SC-009**: El uso mensual del servicio de traducción se mantiene dentro del plan gratuito
  contratado (1.000.000 de caracteres al mes) en el uso normal, porque cada texto se traduce una
  sola vez.

## Assumptions

- **Idioma por tenant, no por usuario**: lo decidió el usuario. Un tenant con personal mixto queda
  fuera de alcance por ahora; el diseño no debe impedir añadir más adelante un idioma por usuario.
- **Solo dos idiomas**: español y chino simplificado. El mecanismo no debe impedir añadir otros
  idiomas después, pero no se ofrecen ahora.
- **Las traducciones de los textos de la aplicación son de la aplicación, no de un tenant**: el texto
  «Cobrar» se traduce una vez y lo reutilizan todos los tenants en chino. No son datos de negocio de
  ningún tenant, por eso no rompen el aislamiento (Principio I); lo que sí es por tenant es el
  ajuste de idioma y sus correcciones manuales (FR-018), que sí son datos del tenant y llevan su
  aislamiento.
- **Servicio de traducción**: DeepL, con una cuenta y clave de la plataforma ya creadas (plan
  gratuito, 1.000.000 de caracteres al mes); el tenant no configura nada. Admite el par español →
  chino simplificado y glosarios en ese par. El coste para el tenant es cero.
- **Volumen**: todos los textos del POS más sus guías de ayuda suman decenas de miles de caracteres;
  como se traducen una vez, el plan gratuito alcanza con holgura.
- **El glosario lo decide el equipo** con la ayuda del cliente: una lista corta de términos (FR-015)
  con su traducción china validada. Si el cliente no puede validarla, se parte de la traducción
  habitual del sector en chino y se corrige con FR-016.
- **Mensajes genéricos del framework**: los mensajes de validación por defecto que hoy no están
  escritos en el código del POS (los genéricos del framework, que hoy ya se muestran en inglés) quedan
  como están. Se traducen todos los mensajes que el POS escribe en su propio código.
- **Formatos de número y fecha**: se mantienen los actuales (coma decimal, euro, día/mes/año). El
  negocio opera en España con euros.
- **Fuera de alcance**: el resto de la aplicación (facturas ordinarias, clientes, stock, CRM,
  configuración salvo el ajuste nuevo y la corrección de traducciones), el asistente IA, el
  panel del Super Admin, los emails, la traducción de datos del negocio y un idioma por usuario.
- **Los textos del POS se identifican de forma explícita en el código** para poder traducirse; los
  que estén incrustados de forma que no se puedan identificar se adaptan como parte de esta feature.
