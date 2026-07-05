# Quickstart / Validación — Bancos y cuentas bancarias

Guía para validar la feature de extremo a extremo. Detalles de modelo en [data-model.md](data-model.md)
y de rutas en [contracts/cuentas-bancarias.md](contracts/cuentas-bancarias.md).

## Prerrequisitos

- Entorno Laravel del proyecto levantado (`php artisan serve` o el entorno habitual).
- Migraciones y seeders aplicados: `php artisan migrate` + `php artisan db:seed` (incluye
  `BancoSeeder`). Verificar que la tabla `bancos` tiene filas.
- Al menos un tenant y un usuario aprobado para iniciar sesión.

## Escenario 1 — Alta de cuenta bancaria (US1)

1. Iniciar sesión y entrar en **Configuración → tab Cuentas bancarias**.
2. Crear una cuenta: alias "Cuenta principal", banco del selector, IBAN válido
   (ej. `ES9121000418450200051332`), titular.
3. **Esperado**: toast de éxito; la cuenta aparece en el listado como *activa*.
4. Reintentar con un IBAN inválido (ej. `ES0000`). **Esperado**: error de validación en el campo
   IBAN; no se crea la cuenta.

## Escenario 2 — Edición y baja lógica (US2)

1. Editar el alias/titular de la cuenta creada. **Esperado**: listado refleja los nuevos datos.
2. Desactivar la cuenta. **Esperado**: pasa a *inactiva* (sigue en el listado), deja de aparecer en
   selectores de factura.
3. Reactivar la cuenta. **Esperado**: vuelve a *activa* y reaparece como seleccionable.

## Escenario 3 — Cuenta en factura por transferencia (US3)

1. Crear factura en borrador con **forma de pago = Transferencia**. **Esperado**: aparece el
   selector de cuenta bancaria con las cuentas activas del tenant.
2. Elegir la cuenta y guardar. Generar el **PDF**. **Esperado**: el PDF muestra banco, IBAN y
   titular de la cuenta.
3. Volver a Configuración y **editar** el IBAN de esa cuenta. Regenerar el PDF de la factura
   anterior. **Esperado**: el PDF sigue mostrando el IBAN **original** (snapshot congelado).
4. Crear una factura con forma de pago distinta de Transferencia. **Esperado**: no se ofrece
   selector de cuenta y el PDF no muestra bloque bancario.

## Escenario 4 — Aislamiento multi-tenant (Principio I / SC-005)

1. Con dos tenants A y B, cada uno con sus cuentas.
2. Como usuario de A, listar cuentas. **Esperado**: solo se ven las de A.
3. Intentar `PUT/DELETE /cuentas-bancarias/{id}` con un id de una cuenta de B. **Esperado**: 404
   (el global scope excluye la cuenta de B).

## Comprobaciones automatizadas

Ejecutar los Feature tests de la feature:

```bash
php artisan test --filter=CuentaBancaria
php artisan test --filter=FacturaCuentaBancariaSnapshot
```

**Esperado**: verde en aislamiento entre tenants, validación de IBAN, CRUD/baja-reactivación y
snapshot inmutable en facturas.
