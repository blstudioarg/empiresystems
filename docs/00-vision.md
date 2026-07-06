# Empire Systems — Visión del producto

## Qué es
SaaS de **facturación para España**, reconstrucción desde cero del CRM actual de Empire Systems con nueva tecnología.

## Stack
- **Backend:** Laravel 12
- **Frontend:** template de admin panel (lo aplica Federico, fuera de alcance por ahora)
- **Base de datos:** MySQL/MariaDB

## Modelo SaaS
- Multi-tenant: varias empresas (tenants) usan la misma aplicación.
- Un **Super Admin** da de alta nuevos clientes del SaaS desde el propio software.
- **Arquitectura de datos elegida:** base de datos **compartida** con columna `tenant_id` (ver `01-arquitectura.md` para la justificación).

## Alcance funcional inicial (MVP)
El corazón del producto es **facturar cumpliendo la normativa española vigente**:
- Gestión de clientes.
- Emisión de facturas (ordinaria, simplificada, rectificativa).
- Cálculo correcto de IVA, IRPF y recargo de equivalencia.
- Series y numeración correlativa sin huecos.
- Preparado para **Verifactu** (registro con huella/hash encadenado y QR) desde el diseño.
- Preparado para **factura electrónica B2B** (Ley Crea y Crece / RD 238/2026).

## Alcance ampliado — Control de stock (incorporado al MVP)
El producto incorpora **control de inventario con trazabilidad completa**, imprescindible
para gestionar el stock más allá de un contador suelto:
- Gestión de **proveedores** (de quién se compra el stock).
- **Compras / facturas de proveedor** que, al confirmarse, generan entradas de stock.
- **Kardex de movimientos de stock** (`movimientos_stock`) append-only: entradas (compras),
  salidas (emisión de facturas), ajustes manuales (inventario, roturas, mermas) y
  devoluciones, con trazabilidad y auditoría del stock resultante.
- Alertas de **stock mínimo** para reposición.

> Ver `docs/03-modelo-datos.md` (secciones `proveedores`, `compras`, `compra_lineas`,
> `movimientos_stock`) para el modelo de datos de esta fase.

## Alcance ampliado — Gestor documental (incorporado al MVP)
El producto incluye un **espacio de archivos por tenant** tipo "Drive": carpetas anidadas y
archivos ligeros (PDF, imágenes, ofimática, texto/csv), sin permisos por carpeta ni versionado —
todos los usuarios aprobados del tenant ven y gestionan todo el espacio. Subir/descargar/
renombrar/mover/borrar (cascada con confirmación reforzada) y previsualización inline de PDF e
imágenes. Almacenamiento en disco privado particionado por tenant, nunca accesible por URL
pública (ver Decisión 6 de `docs/01-arquitectura.md`).

> Ver `docs/03-modelo-datos.md` (secciones `carpetas`, `archivos`) para el modelo de datos de esta
> fase.

## Alcance ampliado — Control horario y fichajes (incorporado al MVP)
El producto incorpora el **registro de jornada** exigido por el art. 34.9 ET: cada persona
trabajadora (perfil **miembro de equipo**, 1:1 con su cuenta de login) ficha entrada/salida —y
pausas si el tenant las activa— desde una pantalla con mapa que valida su posición contra el
perímetro de su propio centro de trabajo (Haversine, sin coordenadas crudas). El fichaje es un
**ledger append-only** (`fichajes`): la hora la fija siempre el servidor, y las correcciones son
eventos nuevos enlazados, nunca ediciones. Un fichaje fuera del perímetro genera una **alerta**
para administración. Administración consulta/exporta el informe de jornada de la plantilla;
cada persona trabajadora tiene su propio portal ("Mi jornada"). Datos de geolocalización y la
dirección de casa del miembro (usada solo para calcular la distancia casa-trabajo) se minimizan
con retención corta configurable y purga periódica, separada del plazo legal de 4 años del
registro de jornada. Sin biometría, sin rastreo continuo de posición, sin envío automático a la
Inspección de Trabajo (pendiente de que se publique la especificación técnica del RD de registro
digital, ver `docs/07-control-horario-espana.md`).

> Ver `docs/03-modelo-datos.md` (secciones `miembros_equipo`, `fichajes`, `alertas`) para el
> modelo de datos de esta fase, y `docs/07-control-horario-espana.md` para la normativa aplicable.

### Horario planificado y cumplimiento de jornada
Sobre el registro real (`fichajes`) se añade una capa de horario **planificado**: administración
define plantillas de cuadrante reutilizables por tenant (`horarios` + `horario_tramos`, con
turnos partidos y jornada variable por día) y las asigna a cada miembro con vigencia temporal
(`asignaciones_horario`), conservando el histórico. Cada persona ve su turno esperado en "Mi
jornada"; el informe de administración cruza previsto vs. real y clasifica cada día (retraso,
ausencia, cumplimiento parcial, exceso), calculado siempre al vuelo. Un comando diario evalúa el
día anterior y genera alertas de ausencia/retraso sobre la misma bandeja de `alertas`. No
sustituye ni reescribe el ledger de fichajes; solo lo contrasta contra lo planificado. Fuera de
alcance: festivos, vacaciones/ausencias justificadas y gestión formal de horas extra.

> Ver `docs/03-modelo-datos.md` (secciones `horarios`, `horario_tramos`, `asignaciones_horario`)
> para el modelo de datos de esta capa.

### Calendario de fichajes y horarios
Administración dispone además de un **calendario** (`/calendario`, mismo permiso que el informe
de jornada) que proyecta esos mismos datos sin tablas ni escrituras nuevas: vista mensual con el
veredicto de cumplimiento de cada día pasado (color + etiqueta, idéntico al informe; hoy y futuro
solo muestran la previsión), vistas semana/día con los tramos previstos superpuestos a los
intervalos realmente trabajados, filtro por miembro y una vista agregada de equipo con recuentos
de incumplimientos por día y drill-down al calendario individual. Desde el detalle de un día se
puede corregir un fichaje o asignar un horario reutilizando los flujos existentes (el ledger y
sus validaciones no cambian); todo el cálculo ocurre en backend por rango visible, siempre al
vuelo.

## Fuera de alcance por ahora
- Diseño del frontend (template externo).
- Pasarela de cobro de las suscripciones del SaaS (se define más adelante).
