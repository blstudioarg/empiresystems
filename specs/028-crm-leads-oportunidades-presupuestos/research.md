# Research: Gestión de Clientes CRM — Leads, Oportunidades y Presupuestos

**Feature**: 028 | **Fecha**: 2026-07-08 | **Fase**: 0

Resolución de las incógnitas técnicas del plan. Las decisiones de producto ya se cerraron en
`spec.md` (sección Clarifications); aquí se resuelven las de implementación.

---

## D1 — Reutilización del motor de cálculo de facturas para presupuestos

**Decisión**: Invocar `App\Services\CalculadoraFactura::calcular($regimen, $aplicaRecargo,
$irpfPorcentaje, $lineas)` desde un servicio nuevo `RegistroPresupuesto`, exactamente igual que
`FacturaController` lo usa. La calculadora ya recibe un array plano de líneas
(`{cantidad, precioUnitario, descuentoPorcentaje, tipoImpositivo}`) y devuelve
`ResultadoCalculoFactura` con líneas calculadas, desglose de impuestos y totales.

**Rationale**: El motor es agnóstico a "factura": no toca la BD, no asigna numeración, no genera
Verifactu; es cálculo puro. Reutilizarlo garantiza que el total de un presupuesto sea idéntico al
céntimo al de la factura equivalente (SC-003) sin mantener dos implementaciones. Cumple el mandato
explícito de la spec de no duplicar el motor.

**Alternativas descartadas**:
- *Copiar la lógica de cálculo en un `CalculadoraPresupuesto`*: viola DRY y el requisito de la spec;
  arriesga divergencia de redondeos entre presupuesto y factura.
- *Calcular en el front y persistir*: viola Principio III (cliente como fuente de verdad de importes).

**Implicación**: `presupuesto_lineas` replica las columnas de importe de `factura_lineas`
(`base`, `tipo_impositivo`, `cuota_impuesto`, `tipo_recargo`, `cuota_recargo`). El régimen
impositivo y el recargo se toman del tenant/cliente al crear el presupuesto (mismo criterio que una
factura borrador), pero **no se congelan** hasta convertir a factura (un presupuesto en borrador es
editable, FR-018).

---

## D2 — Parseo de la importación CSV/Excel de leads

**Decisión**: Usar `maatwebsite/excel` (Laravel Excel) si ya está en el proyecto; si no, preferir
`league/csv` para CSV y aceptar `.xlsx` vía PhpSpreadsheet solo si aporta. Encapsular todo en
`App\Services\ImportadorLeads`, que devuelve un resultado `{importados: int, rechazadas:
array<{fila, motivo}>}` sin abortar por filas inválidas (FR-003).

**Rationale**: El requisito oficial del Kit Digital pide "importación por fichero". CSV cubre el
caso base; Excel (.xlsx) es el formato que más usan las pymes al exportar desde su gestor previo.
Un servicio dedicado mantiene el controlador fino y permite testear el parseo aislado (filas
válidas, inválidas, duplicadas, codificación).

**Alternativas descartadas**:
- *Solo CSV*: deja fuera el .xlsx que la mayoría de pymes exporta directamente; el doc pide "fichero"
  genérico.
- *Importación asíncrona por colas*: viola Principio V (hosting compartido, sin workers). El volumen
  objetivo (≤500 filas, SC-001) es procesable síncrono dentro del request.

**Verificar en implementación**: si `maatwebsite/excel` no está instalado, evaluar su peso vs.
`league/csv` + PhpSpreadsheet. Registrar la elección final en `tasks.md`.

---

## D3 — Reglas de asignación (round-robin) sin estado de carrera

**Decisión**: `App\Services\AsignadorLeads` con dos estrategias (`EstrategiaAsignacion`: `manual`,
`round_robin`). Para round-robin, mantener un puntero por tenant (columna en la config de la regla
o `configuraciones` clave `leads.asignacion_ultimo_indice`) y asignar dentro de una transacción con
bloqueo, igual que la numeración de facturas, para que una importación concurrente no repita
comercial.

**Rationale**: El reparto equitativo requiere recordar a quién le tocó el último lead. Reutilizar el
patrón de bloqueo transaccional ya probado en `NumeradorFacturas` evita duplicar/omitir asignaciones
bajo concurrencia. Manual = el usuario elige el responsable en el alta.

**Alternativas descartadas**:
- *Aleatorio puro*: no garantiza equidad (SC exige reparto ~equitativo).
- *Menor carga de trabajo / por zona*: fuera de alcance por clarificación (Q2 → A).

**Edge case cubierto**: si el conjunto de comerciales de la regla queda vacío (todos de baja/rol
retirado), el lead queda `asignado_a = null` y visible en la bandeja "sin asignar" (FR-006).

---

## D4 — Conversión presupuesto → factura (estado borrador)

**Decisión**: `App\Services\ConversorPresupuestoFactura::convertir(Presupuesto $p): Factura` crea un
`Factura` en estado `borrador` copiando las líneas e importes **congelados en el presupuesto** (no
releídos del catálogo), enlaza la factura al presupuesto (`presupuesto_id` en `facturas` o
`factura_id` en `presupuestos`), marca el presupuesto como `facturado`, todo en una transacción. La
emisión posterior (numeración correlativa + Verifactu + stock) la hace el flujo existente
`EmisorFacturas::emitir()` cuando el usuario lo decide.

**Rationale**: Cumple la clarificación Q4 (factura nace borrador). La numeración correlativa no se
"gasta" hasta la emisión real, evitando huecos si el usuario revisa y descarta (Principios II/III).
El mapeo cabecera+líneas reutiliza la misma forma que `FacturaController::store` (ver
`app/Http/Controllers/FacturaController.php:486-544`), sin duplicar el motor de cálculo.

**Alternativas descartadas**:
- *Emitir directamente en la conversión*: descartado por Q4; arriesga numeración consumida sobre una
  factura que aún se puede querer ajustar.
- *Presupuesto que "se transforma" en factura in-place (misma fila)*: rompe la separación
  fiscal/no-fiscal y complica la trazabilidad; se prefieren dos entidades enlazadas.

**Guardas**: un presupuesto solo se convierte una vez (estado `facturado` es terminal, FR-022);
la comprobación se hace dentro de la transacción para evitar doble facturación concurrente (SC-005).
Los importes de la factura se copian del presupuesto aunque el artículo haya cambiado de precio o
se haya dado de baja (edge case de la spec).

---

## D5 — Retención/purga de leads (RGPD, Principio II)

**Decisión**: Reutilizar el patrón de la feature 021: clave `leads.retencion_dias` en
`configuraciones` (default a definir, p. ej. 1095 días / 3 años para leads descartados/no
convertidos) + un comando `leads:purgar` programado vía `bootstrap/app.php → withSchedule`, con un
`App\Support\RetencionLeadsTenant` análogo a `RetencionLogsTenant`.

**Rationale**: Un lead es un contacto personal (nombre, email, teléfono) sujeto a minimización
RGPD/LOPDGDD (Principio II). La constitución obliga a reutilizar el patrón existente en vez de
inventar uno. Solo se purgan leads **descartados/no convertidos** superado el plazo; un lead
convertido a cliente ya vive como `cliente` con su propia retención.

**Alternativas descartadas**:
- *No purgar leads*: viola Principio II (minimización, sin conservación indefinida injustificada).
- *Mecanismo de retención nuevo por feature*: prohibido por la constitución (Additional Constraints).

**Implicación de documentación**: añadir la clave `leads.retencion_dias` a la tabla de claves de
`docs/03-modelo-datos.md` y las tablas nuevas al modelo de datos (parte del cierre de la feature).

---

## D6 — Integración con permisos y sidebar (feature 027)

**Decisión**: Añadir 3 permisos al catálogo `App\Support\CatalogoPermisos`: `ver-leads`,
`ver-oportunidades`, `ver-presupuestos` (módulo "CRM"). Re-correr el seeder de permisos. Proteger
las rutas con `can:`/middleware de spatie igual que el resto de secciones. La visibilidad "mis
leads vs. todos" (FR-026) se resuelve en el controlador según el rol/permiso del usuario.

**Rationale**: La feature 027 ya estableció que cada sección del sidebar = un permiso, con el
catálogo centralizado como fuente de verdad. Seguir ese patrón es obligatorio (docs/04, FR-013 de
027) y evita reinventar el control de acceso.

**Alternativas descartadas**:
- *Un único permiso `ver-crm`*: menos granular que el patrón establecido (una vista = un permiso);
  impediría, p. ej., dar presupuestos sin dar leads.

---

## Resumen de decisiones

| ID | Decisión | Reutiliza |
|----|----------|-----------|
| D1 | Cálculo de presupuesto vía `CalculadoraFactura` | Motor de facturación |
| D2 | Importación síncrona en `ImportadorLeads` (CSV + xlsx) | — (dep. nueva a confirmar) |
| D3 | `AsignadorLeads` round-robin con bloqueo transaccional | Patrón `NumeradorFacturas` |
| D4 | Conversión a `Factura` borrador, emisión con flujo existente | `EmisorFacturas`, `store` mapping |
| D5 | Purga de leads descartados | Patrón `RetencionLogsTenant` (021) |
| D6 | 3 permisos nuevos módulo CRM | `CatalogoPermisos` (027) |

Sin `NEEDS CLARIFICATION` pendientes. Listo para Fase 1.
