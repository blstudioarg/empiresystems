# Data Model: Logs de actividad de usuarios

## `logs_actividad`

Tabla de negocio, append-only (FR-007/FR-008): ninguna ruta de la aplicación actualiza ni borra una
fila una vez creada.

| Columna | Tipo | Notas |
|---|---|---|
| id | bigint autoincrement | PK |
| tenant_id | unsignedBigInteger, indexado | `BelongsToTenant` (Principio I). Igual patrón que `factura_eventos`. |
| usuario_id | foreignId nullable → `users.id`, `nullOnDelete()` | Puede quedar `null` si el usuario responsable se elimina más adelante, o si un intento de login fallido no corresponde a ningún usuario existente. |
| usuario_nombre | string(150) | Snapshot del nombre del usuario en el momento del evento (D4). En un login fallido guarda el **email intentado** en su lugar. |
| accion | string(20) | Enum `AccionLogActividad`: `login`, `logout`, `alta`, `baja`, `modificacion`. |
| resultado | string(10), default `exito` | Enum `ResultadoLogActividad`: `exito` / `fallo`. Distingue acceso autorizado de denegado — requisito del **registro de accesos** (RGPD Art. 32 + LOPDGDD, ref. técnica RD 1720/2007). `fallo` solo se usa hoy en intentos de login rechazados. |
| ip_origen | string(45) nullable | IP de origen de la petición (IPv4/IPv6), capturada automáticamente por `RegistradorActividad` — nunca la envía el cliente. |
| user_agent | string(255) nullable | Agente de usuario/navegador, capturado automáticamente igual que `ip_origen`. |
| entidad_tipo | string(20) nullable | Enum `EntidadLogActividad`: `cliente`, `articulo`, `factura`, `configuracion`, `usuario`. `null` para `login`/`logout` (no hay entidad de negocio afectada). |
| entidad_id | unsignedBigInteger nullable | Referencia débil (sin FK: apunta a 5 tablas distintas según `entidad_tipo`) al registro afectado. `null` para `login`/`logout` o cuando la entidad ya no aplica (p. ej. configuración, que es un singleton por tenant). |
| descripcion | string(255) | Texto legible ya formado en el momento de crear la fila (p. ej. "Creó el cliente Acme S.L.", "Inició sesión"). Se decide en el backend, nunca en el cliente. |
| ocurrido_at | dateTime | Momento del evento (puede diferir levemente de `created_at` si en el futuro se procesan eventos en cola; hoy son iguales). |
| created_at / updated_at | timestamp | Convención estándar del proyecto (`docs/03-modelo-datos.md`). `updated_at` nunca cambia tras el insert. |

**Retención (RGPD — minimización):** las filas no se conservan indefinidamente. Plazo configurable
por tenant (`configuraciones` → `logs.retencion_dias`, default 730 días/2 años — ver
`App\Support\RetencionLogsTenant`). El comando `logs:purgar` (programado a diario vía
`bootstrap/app.php` → `withSchedule`) borra por lotes de 500 filas las que superan el plazo del
tenant. La purga por antigüedad no viola el carácter append-only (ninguna fila se edita ni se
borra selectivamente durante su vida útil).

**Índices**:
- `[tenant_id, ocurrido_at]` — orden cronológico por tenant (consulta principal del listado).
- `[tenant_id, entidad_tipo]` — soporта filtros/búsquedas futuras por tipo de entidad.
- `[tenant_id, accion]`

**Validaciones / invariantes**:
- Toda fila pertenece a un único tenant (`tenant_id` nunca nulo).
- `descripcion` nunca vacía.
- Si `entidad_tipo` es `null`, `entidad_id` también debe ser `null` (y viceversa no es estricto:
  `configuracion` puede no necesitar `entidad_id` al ser un singleton por tenant).
- No hay endpoint de actualización ni borrado sobre este modelo (FR-007).

## Enums

### `App\Enums\AccionLogActividad`

| Valor | Label (ES) |
|---|---|
| `login` | Inicio de sesión |
| `logout` | Cierre de sesión |
| `alta` | Alta |
| `baja` | Baja |
| `modificacion` | Modificación |

### `App\Enums\EntidadLogActividad`

| Valor | Label (ES) |
|---|---|
| `cliente` | Cliente |
| `articulo` | Artículo |
| `factura` | Factura |
| `configuracion` | Configuración |
| `usuario` | Usuario |

## Relaciones

- `LogActividad belongsTo Tenant` (vía `BelongsToTenant`, igual que `FacturaEvento`).
- `LogActividad belongsTo User` (`usuario_id`, nullable) — únicamente informativa; la UI no
  depende de esta relación para mostrar el nombre (usa el snapshot `usuario_nombre`).
- No hay relación Eloquent hacia la entidad afectada (`entidad_tipo`/`entidad_id`): es deliberadamente
  débil porque apunta a 5 modelos distintos y solo se usa para mostrar/filtrar, no para navegar.

## Servicio: `App\Services\RegistradorActividad`

Único punto de escritura de la tabla (D3). Dos métodos:

```php
public function registrar(
    User $usuario,
    AccionLogActividad $accion,
    ?EntidadLogActividad $entidadTipo,
    ?int $entidadId,
    string $descripcion,
    ResultadoLogActividad $resultado = ResultadoLogActividad::Exito,
): LogActividad

public function registrarIntentoFallido(
    int $tenantId,
    string $emailIntentado,
    string $descripcion,
): LogActividad
```

- Toma `tenant_id` de `$usuario->tenant_id` (igual que `EmisorFacturas` toma `$factura->tenant_id`
  explícitamente, en vez de depender solo del scope implícito). `registrarIntentoFallido` toma un
  `tenantId` explícito en vez de un `User` porque en un login fallido puede no existir ningún
  usuario correspondiente al email intentado.
- `ip_origen`/`user_agent` se capturan automáticamente de `request()` dentro del servicio en ambos
  métodos — nunca los decide ni los envía el llamador ni el cliente.
- `ocurrido_at` se fija a `now()` dentro del servicio (nunca lo decide el llamador ni el cliente).
- No lanza excepciones de negocio: si falla la escritura del log, es un error de infraestructura
  (deja que se propague como cualquier otro fallo de base de datos); no debe usarse para bloquear
  la acción principal que la originó (p. ej. si falla el registro del log, la creación del cliente
  ya ocurrió y no debe revertirse solo por eso). Se llama **después** de que la operación principal
  del controlador se confirma con éxito.

## Puntos de instrumentación (quién llama al servicio)

| Origen | Acción | `accion` | `entidad_tipo` | `descripcion` (ejemplo) |
|---|---|---|---|---|
| `LogAuthenticationActivity::handleLogin` | login exitoso | `login` | `null` | "Inició sesión" |
| `LogAuthenticationActivity::handleLogout` | logout | `logout` | `null` | "Cerró sesión" |
| `LogAuthenticationActivity::handleFailed` | login fallido (credenciales inválidas) | `login` (`resultado=fallo`) | `null` | "Intento de inicio de sesión fallido para {email}" |
| `ClienteController@store` | alta cliente | `alta` | `cliente` | "Creó el cliente {nombre}" |
| `ClienteController@update` | modificación cliente | `modificacion` | `cliente` | "Modificó el cliente {nombre}" |
| `ClienteController@destroy` | baja cliente (soft delete) | `baja` | `cliente` | "Eliminó el cliente {nombre}" |
| `ArticuloController@store/update/destroy` | ídem sobre artículo | `alta`/`modificacion`/`baja` | `articulo` | "Creó/Modificó/Eliminó el artículo {nombre}" |
| `ConfiguracionController@update` (apariencia) | modificación config | `modificacion` | `configuracion` | "Actualizó la apariencia del sistema" |
| `ConfiguracionController@updateFacturacion` | modificación config | `modificacion` | `configuracion` | "Actualizó los datos de facturación" |
| `ConfiguracionController@updateEmail` | modificación config | `modificacion` | `configuracion` | "Actualizó la configuración de email" |
| `ConfiguracionController@updateArchivos` | modificación config | `modificacion` | `configuracion` | "Actualizó la configuración de archivos" |
| `Auth\RegisterController@store` | alta usuario (pendiente) | `alta` | `usuario` | "Se registró {nombre} (pendiente de aprobación)" |
| `UsuarioController@aprobar` | alta efectiva de usuario | `alta` | `usuario` | "Aprobó al usuario {nombre}" |
| `UsuarioController@rechazar` | baja de usuario | `baja` | `usuario` | "Rechazó al usuario {nombre}" |
| `FacturaController@store` | alta factura (borrador) | `alta` | `factura` | "Creó la factura en borrador {identificador}" |
| `FacturaController@update` | modificación factura (borrador) | `modificacion` | `factura` | "Modificó la factura en borrador {identificador}" |
| `FacturaController@destroy` | baja factura (borrador) | `baja` | `factura` | "Eliminó la factura en borrador {identificador}" |
| `FacturaController@emitir` | modificación relevante | `modificacion` | `factura` | "Emitió la factura {numero}" |
| `FacturaController@rectificar` | modificación relevante | `modificacion` | `factura` | "Rectificó la factura {numero} mediante {numero_rectificativa}" |

Nota: los eventos de emisión/rectificación de facturas son un registro de auditoría general
("quién hizo qué"), complementario al log específico de Verifactu ya existente en
`factura_eventos` (huella/encadenamiento) — no lo reemplazan ni duplican su propósito normativo.
