# Implementation Plan: Importación conversacional con el asistente

**Branch**: `046-importacion-conversacional-asistente` | **Date**: 2026-09-07 | **Spec**: [spec.md](./spec.md)

## Summary

El asistente pasa a poder recibir material (Excel/CSV, PDF, imagen, texto), analizarlo, decir qué
falta, corregirlo conversando con la persona y confirmar la importación en una sola tarjeta.

La pieza clave del diseño es que **no se construye una segunda vía de importación**: se extrae de
`ImportadorExcel` una costura que trabaja sobre filas en vez de sobre fichero, y a partir de ahí todo
—Excel y documentos interpretados por igual— pasa por las mismas validaciones (`validador()`, que
delega en el FormRequest del alta manual) y la misma escritura (`crear()`, que fuerza `tenant_id`).

Encima, el asistente aporta lo nuevo: subida de material, interpretación de lo no estructurado
(patrón de la 044), un borrador de filas corregible y las tools que dejan al modelo conversar sobre
un análisis real. Se cierra con el estado vacío del panel mostrando sugerencias por categoría.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12

**Primary Dependencies**: `maatwebsite/excel` (ya presente), `openai-php/client` (ya presente),
`stancl/tenancy`

**Storage**: sin tablas nuevas — el borrador vive en el almacén de ficheros de importación existente

**Testing**: PHPUnit. Interpretación probada sin red sustituyendo el punto que toca al proveedor

**Target Platform**: hosting compartido cPanel/Hostinger (Principio V)

**Project Type**: aplicación web Laravel monolítica (Blade + JS vanilla)

**Performance Goals**: análisis de un fichero de 50 filas percibido como inmediato; la interpretación
de un documento añade la latencia del proveedor y debe avisarse con el indicador existente

**Constraints**: sin colas ni workers (Principio V); límites de la 031 (5 MB, 2.000 filas) vigentes;
el asistente no puede inventar valores ausentes (FR-009)

**Scale/Scope**: 3 módulos importables, 2 endpoints nuevos, 3 tools del asistente, 1 servicio de
interpretación, 1 borrador, refactor de costura en `ImportadorExcel`, y el estado vacío del panel

## Constitution Check

*GATE: revisado antes de Phase 0 y tras Phase 1. Constitución v1.2.0.*

| Principio | Cumplimiento | Cómo |
|---|---|---|
| **I. Aislamiento multi-tenant (NON-NEGOTIABLE)** | ✅ | El material se acota a empresa **y** persona (mismo criterio que el historial de la 045). `crear()` fuerza `tenant_id` ignorando lo que traiga el material. Tests con ≥2 tenants escritos antes de implementar. Un token ajeno responde 404, no 403. |
| **II. Cumplimiento normativo (RGPD/LOPDGDD)** | ✅ | El material aportado son datos personales de terceros. Se reutiliza `AlmacenImportaciones` + `importaciones:purgar`, que ya existen y que la constitución obliga a reutilizar. Minimización: el documento original se descarta en cuanto deja de hacer falta; al cliente solo viajan los campos a importar, nunca el fichero. |
| **III. Integridad financiera server-side** | ✅ (no aplica) | No se importan facturas ni albaranes —quedan fuera por diseño, y lo están **por construcción**: no implementan el contrato importable—. No se calcula ningún importe. |
| **IV. Test-first en lógica crítica (NON-NEGOTIABLE)** | ✅ | Aislamiento entra en el alcance obligatorio. Se aplica el mismo rigor a la aplicación de correcciones sobre el borrador: corregir la fila equivocada es un error silencioso y caro. |
| **V. Simplicidad y hosting compartido** | ✅ | Sin tablas nuevas, sin colas, sin dependencias nuevas. La interpretación manda el documento tal cual al proveedor porque el hosting no tiene Imagick ni Ghostscript (research D3, heredado de la 044). |

**Resultado**: sin violaciones. "Complexity Tracking" queda vacío y se elimina.

**Riesgo a vigilar en revisión**: que la feature acabe teniendo su propio camino de validación. Si en
la revisión aparece una regla de negocio escrita dos veces, el planteamiento se rompió.

## Project Structure

### Documentation (this feature)

```text
specs/046-importacion-conversacional-asistente/
├── plan.md · spec.md · research.md · data-model.md · quickstart.md
├── contracts/endpoints.md
├── checklists/requirements.md
└── tasks.md            # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Excel/
│   └── BorradorImportacion.php              # NUEVO — filas de trabajo + correcciones
├── Http/Controllers/
│   ├── AsistenteMaterialController.php      # NUEVO — subida de material
│   └── AsistenteChatController.php          # MODIFICADO — material del turno
├── Ia/Tools/
│   ├── AnalizarMaterialImportable.php       # NUEVO — lectura
│   ├── CorregirFilasImportables.php         # NUEVO — lectura (muta el borrador, no la BD)
│   └── ImportarMaterial.php                 # NUEVO — escritura, con confirmación
├── Ia/
│   ├── CatalogoTools.php                    # MODIFICADO — alta de las tools nuevas
│   └── SugerenciasAsistente.php             # NUEVO — catálogo por categoría, filtrado por permisos
├── Services/
│   ├── ImportadorExcel.php                  # MODIFICADO — costura por filas (research D2)
│   ├── InterpretadorMaterialImportable.php  # NUEVO — único punto que toca al proveedor
│   └── AlmacenImportaciones.php             # MODIFICADO — guardar/leer el borrador
└── Support/
    └── MaterialImportable.php               # NUEVO — tipos admitidos y límites

config/importacion.php                       # NUEVO — topes del material interpretado (research D9)
public/js/asistente-chat.js                  # MODIFICADO — clip, sugerencias, estado del análisis
resources/views/partials/asistente-chat.blade.php  # MODIFICADO — clip, chips de sugerencias
routes/web.php                               # MODIFICADO

docs/01-arquitectura.md · docs/03-modelo-datos.md · docs/04-front-guidelines.md
resources/ia/conocimiento/importacion-exportacion.md · asistente.md
resources/views/ayuda/importar-exportar.blade.php

tests/
├── Feature/Asistente/
│   ├── MaterialAislamientoTest.php          # NUEVO — Principio I + IV, test-first
│   ├── ImportacionConversacionalTest.php    # NUEVO — flujo completo
│   └── SugerenciasPanelTest.php             # NUEVO
└── Unit/
    ├── BorradorImportacionTest.php          # NUEVO — correcciones, test-first
    └── InterpretadorMaterialTest.php        # NUEVO — sin red
```

**Structure Decision**: se respeta la estructura existente. Lo de importación va a `app/Excel` y
`app/Services` junto a lo de la 031; lo del asistente a `app/Ia`. No aparece ninguna capa nueva.

## Fases

**Phase 0 — Research**: completada en [research.md](./research.md). Diez decisiones (D1–D10), sin
`NEEDS CLARIFICATION`: las tres de producto se cerraron con el usuario antes del spec, y la que el
checklist dejó abierta (límites del material interpretado) se resuelve en D9.

**Phase 1 — Diseño y contratos**: completada. [data-model.md](./data-model.md) describe el borrador y
por qué no hay tablas nuevas; [contracts/endpoints.md](./contracts/endpoints.md) fija la subida de
material y las tools; [quickstart.md](./quickstart.md) cómo validarlo.

**Constitution re-check tras Phase 1**: sin cambios. El diseño no introdujo tablas, colas ni
dependencias, y la retención se apoya en un mecanismo que ya existe.

**Phase 2 — Tareas**: las genera `/speckit-tasks`. Orden esperado: tests de aislamiento primero,
después la costura por filas en el importador (sin la cual nada de lo demás encaja), luego subida e
interpretación, después las tools y la conversación, y al final sugerencias y documentación.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El asistente inventa un dato ausente y contamina el maestro de clientes | Prohibición explícita en el prompt, `null` permitido en el schema, y un test que verifica que un campo ilegible llega vacío y no relleno. Es el riesgo más serio de la feature. |
| Acabar con dos caminos de validación | La costura por filas se extrae del importador existente; las tools no validan nada por su cuenta. Si en revisión aparece una regla escrita dos veces, el planteamiento falló. |
| Coste inesperado para la empresa al interpretar documentos grandes | Tope de páginas por importación en `config/`, avisado en la conversación. Los ficheros estructurados no consumen proveedor. |
| El refactor de `ImportadorExcel` rompe la pantalla de importación existente | Los tests de la 031 son la red: deben seguir en verde sin tocarlos. Si alguno exige cambios, el refactor cambió comportamiento. |
| Material abandonado acumulando datos personales | `importaciones:purgar`, que ya corre a diario, cubre el borrador por estar en el mismo almacén. |
