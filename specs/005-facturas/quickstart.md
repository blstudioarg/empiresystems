# Quickstart — Validación de la feature Facturas

Guía para validar la feature de punta a punta. No contiene implementación; ver `tasks.md` para el
desglose y `data-model.md`/`contracts/` para el detalle.

## Prerrequisitos
- Features 001–004 aplicadas (tenants, usuarios, clientes, artículos).
- Nueva dependencia instalada: `composer require barryvdh/laravel-dompdf`.
- Migraciones + seeders al día:
  ```bash
  php artisan migrate
  php artisan db:seed        # crea la serie ordinaria por defecto por tenant (SerieSeeder)
  ```

## Tests (deben estar en verde)
```bash
# Lógica crítica (test-first, Principio IV)
php artisan test tests/Unit/CalculadoraFacturaTest.php
php artisan test tests/Unit/NumeradorFacturasTest.php

# CRUD, aislamiento y PDF
php artisan test tests/Feature/FacturaCrudTest.php
php artisan test tests/Feature/FacturaTenantIsolationTest.php
php artisan test tests/Feature/FacturaPdfTest.php
```
Esperado: cálculo de bases/cuotas/desglose/total correcto; numeración correlativa sin huecos ni
duplicados; ninguna fuga entre ≥2 tenants (listado/detalle/PDF/update/destroy); PDF responde y
refleja los datos persistidos.

## Validación manual (UX)
1. Login como usuario de un tenant con al menos un cliente y algún artículo.
2. Ir a **Facturas** (sidebar) → el listado DataTable carga vacío o con borradores existentes.
3. Pulsar **Nueva factura** → navega a la **vista full-page** (no modal).
4. Verificar secciones: datos del emisor (tenant + logo), selector de cliente, fechas (vencimiento
   autocompletado a +30 días), forma de pago, tabla de líneas editables, IRPF/recargo, totales.
5. Añadir una línea desde catálogo (autocompleta concepto/precio/unidad/tipo) y otra libre.
6. Cambiar cantidad/precio/descuento → la **preview** y los totales se actualizan al instante.
7. Introducir IRPF 15% → el total refleja la retención.
8. Guardar → vuelve al listado con toast de éxito; la factura aparece como **"Borrador"** (sin nº).
9. Editar el borrador → los datos se precargan; modificar y guardar.
10. Desde el listado, abrir **PDF** → documento con emisor+logo, cliente, líneas, desglose por
    tipo, IRPF/recargo y total, coincidiendo con lo guardado.
11. Eliminar el borrador → desaparece del listado.

## Comprobaciones de cumplimiento
- Los importes mostrados en el PDF y el listado coinciden con `CalculadoraFactura` (no con el JS).
- `facturas.regimen_impositivo` quedó congelado del tenant; `aplica_recargo` del cliente.
- Un usuario de otro tenant NO puede abrir el PDF ni el editar de esta factura (404/403).
- Los borradores no tienen `numero`; el correlativo se reserva para la emisión (feature futura).

## Notas de diseño front
Aplicar skills de diseño (`frontend-design`) dentro de NexaDash; registrar patrones reutilizables
en `docs/04-front-guidelines.md` (layout de líneas editables, preview de documento, totales sticky).
