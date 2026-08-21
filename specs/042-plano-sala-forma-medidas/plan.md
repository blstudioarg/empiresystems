# Implementation Plan: Forma y medidas configurables de la zona en el plano de sala

**Branch**: `042-plano-sala-forma-medidas` | **Date**: 2026-08-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/042-plano-sala-forma-medidas/spec.md`

## Summary

Convertir el lienzo del plano de sala —hoy una rejilla fija de 8×6 idéntica para todas las zonas de
todos los tenants— en **un dato de cada zona**: sus medidas (columnas × filas) y qué celdas de ese
rectángulo son realmente suelo, de modo que una sala pueda tener forma de L, un patio interior o el
hueco de un pilar.

**Enfoque técnico**: se conserva íntegro el sistema de coordenadas por celdas (D1). La feature es,
en esencia, **quitar tres constantes**: `PosPlanoCeldas::COLUMNAS`/`::FILAS` en servidor y
`PosPlanoDibujo.COLS`/`ROWS` en cliente, y sustituirlas por la geometría de la zona activa, que viaja
en el payload que ya existe. Encima de eso, dos añadidos: una **capa de celdas** en el lienzo para
poder pintar el recorte (D5) bajo un **modo de recorte explícito** (D6, para que el gesto no se
confunda con arrastrar una mesa en tablet), y dos invariantes nuevos (G4, G5) en la validación
server-side que ya rechaza payloads inválidos de forma atómica (D8). Los defaults de la migración
reproducen el lienzo actual, así que ninguna zona existente cambia y no hay backfill (D10).

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12) en servidor; JavaScript ES5 en cliente (sin build step,
mismo estilo que el resto de `public/js/plugins-init/`).

**Primary Dependencies**: ninguna nueva. jQuery UI (vendorizado) sigue cubriendo arrastre y
redimensionado de mesas; el pintado de celdas usa Pointer Events nativos (D6).

**Storage**: MySQL/MariaDB. Tres columnas nuevas en `pos_zonas` (`columnas`, `filas`,
`celdas_inactivas`). Ninguna tabla nueva.

**Testing**: PHPUnit/Pest sobre SQLite en memoria (`phpunit.xml`), en la línea de
`tests/Feature/Pos/PlanoRectangulosTest.php` y `PlanoSalaTest.php`.

**Target Platform**: navegador de tablet (uso principal del POS) y escritorio.

**Project Type**: aplicación web Laravel con vistas Blade y JS por pantalla, sin SPA.

**Performance Goals**: el lienzo mayor admitido es 24×24 = 576 celdas. La capa de celdas completa
solo se monta mientras el modo de recorte está activo (D5); fuera de él, el DOM del plano crece solo
con las celdas inactivas dibujadas. El repintado al cambiar de zona debe seguir siendo imperceptible.

**Constraints**: sin build step ni dependencias nuevas (Principio V); el servidor revalida toda la
geometría (Principio III); el color del borde de la mesa está reservado a su estado, así que el
rechazo va por sombra + micro-desplazamiento y un solo toast al soltar (D7,
`docs/04-front-guidelines.md`); el módulo de dibujo no puede quedar detrás del guard de permiso del
editor (feature 041).

**Scale/Scope**: una migración, un modelo, dos clases de soporte, dos controladores, una vista Blade,
tres archivos JS, cuatro capas de documentación.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Aplica | Evaluación |
|-----------|--------|------------|
| **I. Aislamiento Multi-Tenant** (NON-NEGOTIABLE) | Sí | Las tres columnas nuevas viven en `pos_zonas`, que ya lleva `tenant_id` y `BelongsToTenant`. La zona del guardado se sigue resolviendo **manualmente** bajo el tenant activo (`PosZona::query()->findOrFail($zona)`), nunca por binding implícito — pitfall ya documentado del proyecto. No se abre ninguna vía de datos nueva: no hay endpoints ni queries nuevas. Se añade un test de aislamiento que verifica que el lienzo de una zona de otro tenant no se puede leer ni guardar. **PASA**. |
| **II. Cumplimiento Normativo España-First** | No | No toca facturación, impuestos, numeración, régimen impositivo ni Verifactu. Tampoco introduce datos personales: la geometría de una sala no identifica a nadie, así que no hay plazo de retención ni purga que definir. **NO APLICA**. |
| **III. Integridad Financiera Server-Side** | Sí (por vigilancia) | Ningún importe cambia. Lo que sí aplica es el corolario del principio: **el cliente no es la única barrera**. El bloqueo al recortar y el rechazo de la reducción ocurren en cliente como previsualización, pero G1-G5 se revalidan íntegramente en servidor sobre la geometría propuesta y rechazan la petición entera (D8, FR-014). **PASA**. |
| **IV. Test-First en Lógica Crítica** (NON-NEGOTIABLE) | Parcial | Las áreas formalmente test-first (aislamiento, impuestos, numeración, Verifactu) no se tocan salvo aislamiento, que sí se cubre y se escribe primero. Además, la validación de geometría es lógica de invariantes con reglas exactas y es donde un bug pierde el plano de un cliente: **los tests de G4/G5 y del rechazo de reducción se escriben antes que la implementación** y deben fallar primero. La UI (pintado, modo de recorte) sigue el flujo flexible que la constitución permite, validada por `quickstart.md`. **PASA**. |
| **V. Simplicidad y Hosting Compartido** | Sí | Cero dependencias nuevas, cero endpoints nuevos, cero permisos nuevos, cero tablas nuevas. La máscara es una columna JSON que nunca se consulta desde SQL (D1), así que no exige funciones JSON del motor y funciona igual en MariaDB de hosting compartido. Se descartó explícitamente la alternativa "polígonos libres" por ser la que sí habría exigido un motor de geometría nuevo. **PASA**. |

**Re-evaluación post-diseño (Phase 1)**: sin cambios. El diseño no introdujo ninguna estructura que
no estuviera contemplada arriba; en particular, la capa de celdas es DOM efímero del editor, no un
sistema de dibujo paralelo (D5). **Sin entradas en Complexity Tracking.**

## Project Structure

### Documentation (this feature)

```text
specs/042-plano-sala-forma-medidas/
├── plan.md              # Este archivo
├── spec.md
├── research.md          # D1-D11
├── data-model.md        # pos_zonas + invariantes G1-G5
├── quickstart.md        # Validación manual y automatizada
├── contracts/
│   └── plano-zona.md    # Payload de la Sala + guardado del plano
├── checklists/
│   └── requirements.md
└── tasks.md             # (/speckit-tasks)
```

### Source Code (repository root)

```text
database/migrations/
└── 2026_08_21_HHMMSS_lienzo_por_zona_en_pos_zonas.php   # NUEVO: columnas, filas, celdas_inactivas

app/
├── Models/
│   └── PosZona.php                                       # fillable + casts de los 3 campos
├── Support/
│   ├── PosPlanoCeldas.php                                # CONSTANTES → geometría de zona (D3)
│   └── PosPlanoReacomodo.php                             # G4 + G5 sobre geometría propuesta (D8)
└── Http/Controllers/Pos/
    ├── SalaController.php                                # geometría en el payload de zonas
    └── PlanoSalaController.php                           # valida + persiste el lienzo en la misma tx

resources/views/
├── pos/sala.blade.php                                    # capa de celdas, controles de medidas, CSS
└── ayuda/pos-sala.blade.php                              # guía in-app (FR-016)

public/js/plugins-init/
├── pos-plano-dibujo.js                                   # geometría por zona, sin globales (D4)
├── pos-sala-plano.init.js                                # modo de recorte, medidas, bloqueos (D6/D7)
└── pos-sala-plano-servicio.init.js                       # lienzo y recorte de la zona activa

resources/ia/conocimiento/
└── pos-hosteleria.md                                     # base de conocimiento del asistente (FR-017)

tests/Feature/Pos/
├── PlanoLienzoZonaTest.php                               # NUEVO: G4, G5, reducción, normalización
├── AislamientoPlanoLienzoTest.php                        # NUEVO: aislamiento del lienzo entre tenants
└── SalaPayloadPlanoTest.php                              # AMPLIADO: fija los campos nuevos
```

**Structure Decision**: aplicación web Laravel monolítica ya existente; la feature se integra en los
archivos del módulo POS listados arriba. No se crean capas, servicios ni directorios nuevos: la
lógica de geometría sigue viviendo en `app/Support/`, donde ya está la de las features 039/040.

## Fases

### Phase 0 — Research

Completada. Once decisiones en [research.md](./research.md): D1 máscara como columna JSON; D2
medidas y límites 4-24; D3 `PosPlanoCeldas` deja de tener constantes globales; D4 la geometría deja
de ser global en cliente; D5 capa de celdas; D6 modo de recorte explícito; D7 feedback de rechazo;
D8 validación server-side con G4/G5; D9 quién produce el conteo de mesas; D10 migración sin backfill;
D11 la vista de tarjetas no cambia.

### Phase 1 — Design & Contracts

Completada: [data-model.md](./data-model.md), [contracts/plano-zona.md](./contracts/plano-zona.md),
[quickstart.md](./quickstart.md).

### Phase 2 — Tasks

Pendiente de `/speckit-tasks`. Orden previsto: migración y modelo → constantes y validación en
servidor (test-first) → payload y controlador → geometría por zona en el cliente → capa de celdas y
modo de recorte → feedback de bloqueo → las cuatro capas de documentación.

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|-----------|
| Quedan usos sueltos de `COLUMNAS`/`FILAS` que siguen asumiendo 8×6 | Renombrar a `COLUMNAS_DEFECTO`/`FILAS_DEFECTO` (D3) hace que todos los usos salten; se revisa cada uno explícitamente en vez de cambiar el valor de una constante con el mismo nombre. |
| El plano de la vista de servicio se queda con las medidas de la zona anterior al cambiar de zona | Las variables CSS se escriben en el lienzo al dibujar cada zona, no una vez en `documentElement` (D4). Cubierto en `quickstart.md`. |
| El primer gesto de recorte mueve una mesa en tablet | Modo de recorte explícito que desactiva arrastre y redimensionado mientras está activo (D6). |
| Un lienzo de 24×24 hace lenta la edición en tablet | La capa de celdas completa solo existe mientras el modo de recorte está activo (D5); fuera de él el DOM no crece. Escenario de rendimiento en `quickstart.md`. |
| Una zona recortada deja a la creación de mesas colocando en un hueco que no es sala | `primeraCeldaLibre` pasa a recibir la zona y a saltarse las celdas inactivas (D3). |
