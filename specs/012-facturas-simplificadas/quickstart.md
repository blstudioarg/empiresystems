# Quickstart / Validación — POS facturas simplificadas

Guía para validar la feature de punta a punta. No incluye implementación (eso va en `tasks.md`).

## Prerrequisitos

- Entorno Laravel del proyecto funcionando (MySQL/MariaDB, tenant demo sembrado).
- Migraciones y seeders al día:
  ```powershell
  php artisan migrate
  php artisan db:seed
  ```
  Tras la feature, el seeder crea la **serie "S"** (simplificada) y la clave
  `factura.simplificada_tope_ampliado` (default `false`) para el tenant demo.

## Ejecutar la suite crítica (test-first)

```powershell
php artisan test --filter=Pos
```
Debe cubrir y pasar:
- `PosTopeSimplificadaTest`: emitir 400 € con tope base pasa; 401 € se bloquea; con flag ampliado
  3.000 € pasa y 3.001 € se bloquea (inclusivo en el límite).
- `PosTicketEmisionTest`: ticket simple sin receptor se emite con número `S-<año>-0001`, estado
  `emitida`, evento `emitida` registrado, e inmutable; ticket cualificado persiste receptor y sigue
  `tipo = simplificada`.
- `PosListadoSeparadoTest`: `GET /pos` (JSON) sólo devuelve simplificadas; `GET /facturas` (JSON)
  excluye simplificadas.
- `PosTenantIsolationTest`: dos tenants; la numeración "S" y el listado POS no se cruzan.

## Validación manual (navegador / tablet)

1. **Menú POS**: en el sidebar aparece el dropdown **POS** con "Facturas simplificadas" y "Crear
   ticket".
2. **Crear ticket simple**: añadir 1–2 líneas, ver el total actualizarse, emitir sin datos de
   receptor → aparece en el listado POS con número `S-...`, y **no** aparece en el listado de Facturas.
3. **Tope (bloqueo duro)**: intentar emitir un ticket cuyo total supere el tope del tenant → se
   rechaza con toast/error indicando que use factura ordinaria; no se crea nada.
4. **Sector ampliado**: en Configuración → Facturación, activar "sector con tope ampliado (3.000 €)";
   ahora un ticket de, p. ej., 500 € se emite.
5. **Cualificada**: crear un ticket rellenando NIF + domicilio del receptor → se emite como
   simplificada (no ordinaria) y el detalle/PDF muestra receptor y desglose de cuota.
6. **PDF 80 mm / A4**: desde un ticket emitido, descargar en formato "ticket 80 mm" (rollo estrecho) y
   en "A4"; ambos con el contenido mínimo de la simplificada.
7. **Inmutabilidad**: intentar editar/eliminar un ticket emitido → rechazado.
8. **Aislamiento**: repetir en un segundo tenant y verificar que numeración y listados son
   independientes.

## Criterios de aceptación cubiertos
Mapea con `spec.md`: US1 (pasos 2), US2 (pasos 3–4), US3 (pasos 1–2, 8), US4 (paso 5), US5 (paso 6),
inmutabilidad (paso 7). Success Criteria SC-001..SC-008 verificados por la suite `--filter=Pos` + la
validación manual.
