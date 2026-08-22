# Quickstart — Validación del módulo de Cobros

Guía para comprobar que la feature funciona de punta a punta. No contiene implementación.

## Prerrequisitos

- Entorno local en marcha (`php artisan serve` o el host habitual) y BD con datos.
- Credenciales del tenant de desarrollo: **`ACCESOS.local.md`** (raíz del repo, gitignored).
  No inventar ni resetear contraseñas; ese archivo ya las tiene.
- **No correr `migrate:fresh` ni nada que trunque tablas** (regla de `CLAUDE.md`). Esta feature no
  trae migraciones de esquema, así que no hace falta ninguna.

## Puesta a punto tras implementar

```bash
# 1. Sembrar el permiso nuevo y resincronizar el rol Administrador de cada tenant
php artisan db:seed --class=PermisosSeeder

# 2. Ejecutar la migración de datos que reparte 'ver-cobros' a los roles personalizados
#    que ya tenían 'ver-facturas' (research D7). Revisar antes qué hace: no es destructiva.
php artisan migrate

# 3. Limpiar caché de rutas/vistas si el entorno las cachea
php artisan optimize:clear
```

## Suite automática

```bash
php artisan test --filter=ConsultaCobrosParidad   # espejo SQL ≡ métodos del modelo (Principio IV)
php artisan test --filter=CobrosAislamiento       # 2 tenants, sin fuga (Principio I)
php artisan test --filter=CobrosModulo            # permiso, filtros, orden, paginación, métricas
php artisan test --filter=CatalogoPermisos        # contabilidad del catálogo
php artisan test --filter=RutasPermisos           # mapa de rutas con permiso

# SC-008: la suite existente de facturas y pagos debe seguir verde SIN tocarla
php artisan test --filter=Factura
php artisan test --filter=Pago
```

Si algún test de facturas o pagos hay que **modificar** para que pase, la extracción del modal
(research D6) cambió comportamiento y está mal hecha: revertir y rehacerla a comportamiento
constante.

## Escenarios manuales

Datos de partida recomendados en el tenant de demo (crear a mano desde la app, sin seeders
destructivos): una factura emitida sin cobros, una con un cobro parcial, una cobrada del todo,
una vencida con saldo, una sin `fecha_vencimiento` y una rectificada por diferencias.

| # | Historia | Pasos | Resultado esperado |
|---|---|---|---|
| 1 | US1 | Entrar a **Facturas → Cobros** | 4 cards con pendiente, cobrado del periodo, vencido y nº de facturas pendientes; debajo el listado. Las cifras cuadran con las facturas creadas. La card de vencido va en rojo y con rail rojo. |
| 2 | US1 / FR-008 | Mirar los títulos de las cards | "Cobrado" muestra la etiqueta de criterio *evento (fecha del cobro)*; "Pendiente" y "Vencido", *instantánea a hoy*. |
| 3 | US1 / FR-007 | Cambiar el rango a Trimestre y a un rango personalizado | Solo cambia "Cobrado en el periodo"; pendiente, vencido y el contador **no** cambian. Sin recarga de página. |
| 4 | US2 | Filtrar por estado "Pendiente", luego por un cliente, luego marcar "solo vencidas" | Los tres filtros se acumulan; el contador de registros del DataTable baja de forma coherente. |
| 5 | US2 / FR-012 | Ordenar por "Saldo pendiente" y por "Días de retraso" | Orden correcto en ambos sentidos; las facturas sin vencimiento quedan al final, no arriba con un 0. |
| 6 | US2 / FR-013 | Buscar el número de una factura y luego el nombre de un cliente | Ambas búsquedas devuelven la fila esperada. |
| 7 | US3 | En una factura pendiente: Acciones → Registrar cobro | El modal se abre con la fecha de hoy y el importe prerrellenado al saldo pendiente. El botón muestra spinner mientras guarda. |
| 8 | US3 | Registrar un cobro parcial | Toast de éxito, la fila pasa a **Parcial**, el saldo baja, y las cards se actualizan solas. |
| 9 | US3 / FR-019 | Intentar un importe mayor que el saldo, y luego 0 | Error 422 con mensaje claro bajo el campo; **no** se crea ningún cobro (comprobar en el historial). |
| 10 | US3 / FR-021 | Mirar el dropdown de una factura ya cobrada | No aparece "Registrar cobro". |
| 11 | US4 | Acciones → Cobros | Historial con fecha, método, referencia, importe y estado, del más reciente al más antiguo. |
| 12 | US4 / FR-024 | Anular un cobro vigente | Pide confirmación explícita; al confirmar, saldo y estado se recalculan, el cobro queda visible como **Anulado** y sin acción de anular. Cancelar no cambia nada. |
| 13 | US5 | Acciones → Ver factura, con filtros y en la página 2 | El PDF se abre en modal (no en pestaña nueva); al cerrarlo, filtros, orden y página siguen intactos. Reabrirlo en otra factura no muestra un instante el PDF anterior. |
| 14 | US6 / FR-029 | Sidebar → "Ayuda de esta pantalla" | Guía específica de Cobros (no genérica ni de otra pantalla). |
| 15 | US6 / FR-030 | Preguntar al asistente IA "¿cómo registro un cobro?" | Responde describiendo el módulo de Cobros. |
| 16 | FR-016 | Buscar en el listado un borrador y una factura rectificativa | No aparecen. La **original rectificada** sí aparece, con su importe efectivo y el aviso de contexto en el modal. |
| 17 | FR-002 | Con un rol sin `ver-cobros`: mirar el menú y entrar a `/cobros` por URL | No hay entrada de menú; la URL da 403. |
| 18 | SC-006 | Con dos tenants con facturas, entrar con cada uno | Cada uno ve solo lo suyo, en cards y en listado. |
| 19 | D7 | Con un rol personalizado que tenía `ver-facturas` antes del deploy | Sigue pudiendo registrar y anular cobros (la migración de datos le dio `ver-cobros`). |
| 20 | SC-005 | Comparar el saldo de una factura en Cobros y en el módulo de Facturas | Idéntico al céntimo. |

## Revisión visual

Antes de dar por cerrada la feature, comprobar contra `docs/04-front-guidelines.md`:

- Los botones "Anterior"/"Siguiente" del DataTable **no** salen con las letras apiladas en vertical
  (si salen así, falta el override CSS por id de tabla).
- La cabecera de la tabla se ve con el color de marca y texto legible (no letra blanca sobre card
  blanca).
- La columna de acciones es **un dropdown**, no botones sueltos.
- Los lordicon de las cards no están recortados dentro de una caja de color.
- Ningún importe muestra ceros de relleno tipo `710.0000`.
