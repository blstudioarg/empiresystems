# Quickstart: validar el plano de sala arrastrable

## Prerrequisitos

- Tenant con módulo de hostelería activo (`pos.hosteleria_activo`) y al menos una zona con 2-3
  mesas (ver `ACCESOS.local.md` para credenciales de desarrollo — tenant "Empire Demo").
- Migración de esta feature aplicada (`php artisan migrate`), con el backfill de `fila`/`columna`
  ya corrido sobre las mesas de demo existentes.

## Escenario 1 — Reordenar y guardar (User Story 1)

1. Entrar a `Sala` (`/pos/sala`) con un usuario con permiso `ver-configuracion`.
2. Pulsar "Editar plano". El lienzo de la zona activa muestra la grilla de puntos y cada mesa
   gana un asa de arrastre.
3. Arrastrar una mesa a una celda vacía distinta.
4. Verificar que el cambio se ve en pantalla pero que recargar la página (sin guardar) revierte a
   la disposición anterior.
5. Repetir el arrastre y pulsar "Guardar plano". Verificar el toast de confirmación.
6. Recargar la página: la mesa debe seguir en la nueva posición.

**Resultado esperado**: SC-001, SC-003, SC-005 del spec.

## Escenario 2 — Forma y tamaño (User Story 2)

1. En modo edición, abrir el selector de forma/tamaño de una mesa (mismo modo edición, sin salir
   de Sala).
2. Cambiar forma a "barra" y tamaño a "grande".
3. Verificar que el lienzo refleja el cambio de inmediato, sin recargar.
4. Guardar plano y recargar: la forma/tamaño deben persistir.

**Resultado esperado**: FR-003, FR-004.

## Escenario 3 — Reacomodo sin solapamiento (User Story 3)

1. En modo edición, arrastrar una mesa y soltarla exactamente sobre la celda de otra mesa ya
   colocada.
2. Verificar que la mesa que estaba ahí se desplaza a la celda libre visualmente más cercana (no
   desaparece, no queda oculta).
3. Llenar deliberadamente toda la rejilla de una zona pequeña (o simular con pocas celdas
   disponibles) e intentar el mismo solapamiento sin celdas libres restantes: verificar que la
   mesa arrastrada vuelve a su posición anterior con un aviso, y que "Guardar plano" no queda
   habilitado con un estado inconsistente.

**Resultado esperado**: FR-005, FR-006, SC-002.

## Escenario 4 — Independencia entre zonas (User Story 4)

1. Editar y guardar el plano de la zona "Comedor".
2. Cambiar a la pestaña "Terraza" sin guardar cambios pendientes de "Comedor" (si los hubiera).
3. Verificar que "Terraza" muestra su propia disposición, sin ningún rastro de lo editado en
   "Comedor".

**Resultado esperado**: FR-010.

## Escenario 5 — Conflicto de guardado concurrente (Edge Case)

1. Abrir la Sala en dos pestañas/dispositivos con el mismo tenant, ambas en modo edición de la
   misma zona.
2. Guardar el plano desde la primera pestaña.
3. Intentar guardar desde la segunda pestaña (que sigue con el `version` anterior).
4. Verificar que la segunda pestaña recibe un aviso de conflicto (409) y no sobrescribe
   silenciosamente el guardado de la primera.

**Resultado esperado**: FR-011.

## Validación automatizada

Ver `tests/Feature/Pos/PlanoSalaTest.php` (a crear en fase de implementación) para la cobertura
equivalente a los escenarios 1, 3 y 5 vía HTTP, incluyendo el test de aislamiento de tenant
exigido por el Principio I (dos tenants con zonas/mesas propias, verificar que el guardado de uno
no es visible ni afectable desde el otro).
