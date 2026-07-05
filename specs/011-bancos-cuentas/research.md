# Research — Bancos y cuentas bancarias del tenant

Sin `NEEDS CLARIFICATION` pendientes tras `/speckit-clarify`. Se documentan las decisiones técnicas
que resuelven las dependencias y patrones de integración.

## D1 — Ubicación de `bancos`: central vs. tenant

- **Decisión**: `bancos` es tabla **central** (sin `tenant_id`), catálogo global de solo lectura
  sembrado por seeder. Modelo `App\Models\Banco` sin trait `BelongsToTenant`, análogo a
  `App\Models\Provincia`.
- **Rationale**: el listado de entidades bancarias no varía por tenant (docs/03-modelo-datos.md);
  añadir `tenant_id` sería duplicación innecesaria y contradiría el patrón ya establecido de
  catálogos de referencia (provincias/localidades). El Principio I aplica a tablas de **negocio**;
  un catálogo global compartido de solo lectura es la excepción prevista en la constitución.
- **Alternativas descartadas**: (a) `bancos` por tenant → complejidad y datos duplicados sin valor;
  (b) enum PHP en código → no editable/sembrable, incómodo para ampliar la lista.

## D2 — Validación de IBAN

- **Decisión**: validar el IBAN con una **regla custom** `App\Rules\IbanValido` que comprueba
  estructura ISO 13616 y dígito de control (mod-97), normalizando a mayúsculas y sin espacios antes
  de guardar. Laravel 12 no incluye una regla `iban` nativa.
- **Rationale**: cumple FR-003/SC-002 (rechazar 100% de IBAN con formato inválido) sin dependencias
  externas (Principio V). El algoritmo mod-97 es estándar y autocontenido.
- **Alternativas descartadas**: (a) paquete externo de validación IBAN → dependencia innecesaria
  para un solo uso; (b) validar contra API bancaria → fuera de alcance (spec: no se valida contra
  banco real).
- **Normalización**: almacenar el IBAN sin espacios en mayúsculas; mostrar con separación por
  grupos de 4 en la UI/PDF es cosmético y opcional.

## D3 — Resolución de modelo tenant-scoped en el controlador

- **Decisión**: `CuentaBancariaController` NO type-hinta `CuentaBancaria` en la firma de
  `update`/`destroy`/`restore`; recibe el id como `string` y resuelve con
  `CuentaBancaria::withTrashed()->findOrFail($id)` (o sin `withTrashed` donde no aplique) en el
  cuerpo del método.
- **Rationale**: `SubstituteBindings` corre antes de que `tenant.context` fije el scope, por lo que
  el binding implícito puede resolver un modelo de otro tenant ([[project-tenant-route-binding]],
  confirmado en 002-clientes-crm). Resolver en el cuerpo garantiza `TenantScope` activo.
- **Alternativas descartadas**: binding implícito con `scopeBindings()` → frágil y ya descartado
  como patrón del proyecto.

## D4 — Baja lógica y reactivación

- **Decisión**: `cuentas_bancarias` usa **doble mecanismo** ya documentado: `activa` (boolean) +
  `softDeletes`. "Desactivar" desde la UI hace `activa = false` + `delete()` (soft). "Reactivar"
  hace `restore()` + `activa = true`. El selector de facturas solo lista cuentas con
  `activa = true` y no soft-deleted.
- **Rationale**: coherente con docs/03-modelo-datos.md ("soft delete / desactivación (`activa =
  false` + `deleted_at`), nunca borrado físico"), preserva integridad de facturas que referencian la
  cuenta (aunque además esté el snapshot). El listado de configuración muestra también las inactivas
  (con `withTrashed`) para poder reactivarlas.
- **Alternativas descartadas**: solo `activa` sin softDeletes → pierde el patrón de baja lógica del
  resto del proyecto; solo softDeletes sin `activa` → no permite "pausar" una cuenta sin borrarla.

## D5 — Integración con facturas (snapshot)

- **Decisión**: reutilizar el patrón `cliente_*` de `facturas`. Añadir `cuenta_bancaria_id`
  (fk nullable) + `cuenta_bancaria_banco`, `cuenta_bancaria_iban`, `cuenta_bancaria_titular`
  (snapshot). `FacturaController@create/edit` pasan las cuentas activas del tenant; `@store/@update`
  componen el snapshot en backend a partir de la cuenta elegida SOLO si `forma_pago =
  transferencia`. El PDF muestra el bloque bancario si hay snapshot.
- **Rationale**: patrón ya probado y coherente (Principio III: snapshot compuesto server-side;
  Principio II: inmutable al emitir). El `cuenta_bancaria_id` queda como referencia informativa; la
  fuente mostrada es el snapshot, no la relación viva.
- **Alternativas descartadas**: mostrar datos vía relación viva a `cuentas_bancarias` → rompería la
  inmutabilidad si la cuenta se edita tras emitir la factura.

## D6 — Semilla de bancos

- **Decisión**: `BancoSeeder` con lista curada de las principales entidades operando en España
  (p. ej. Banco Santander, BBVA, CaixaBank, Banco Sabadell, Bankinter, ING, Unicaja, Abanca,
  Kutxabank, Ibercaja, Cajamar, Openbank, EVO Banco, Deutsche Bank España, etc.), usando
  `firstOrCreate` por `nombre` para ser idempotente en re-seeds. Registrado en `DatabaseSeeder`.
- **Rationale**: suficiente para el uso real y mantenible (respuesta de clarificación). Idempotencia
  evita duplicados al re-sembrar, mismo criterio que `ConfiguracionSeeder`.
- **Alternativas descartadas**: catálogo exhaustivo por código de entidad del Banco de España →
  descartado en clarificación por peso/mantenimiento.
