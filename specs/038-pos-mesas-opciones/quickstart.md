# Phase 1 — Guía de validación: POS con mesas y opciones

Cómo comprobar que la feature funciona de punta a punta. No contiene implementación: eso vive en
`tasks.md`. Para el detalle de tablas ver [data-model.md](data-model.md); para el de endpoints,
[contracts/rutas.md](contracts/rutas.md).

## Prerrequisitos

- Entorno local levantado y credenciales en `ACCESOS.local.md` (no las pidas ni las resetees:
  están ahí).
- **Desplegar esta feature exige dos pasos, en este orden**: `php artisan migrate` (10 tablas
  nuevas, ninguna destructiva) y después `php artisan db:seed --class=PermisosSeeder` (siembra
  `ver-pos-sala`/`ver-pos-opciones` y resincroniza el rol "Administrador" de cada tenant). **Solo
  el rol "Administrador" recibe los permisos nuevos automáticamente**: cualquier otro rol
  personalizado que deba ver Sala/Opciones se lo concede a mano desde `/roles` — es opt-in, no
  retroactivo (mismo patrón que el resto de permisos nuevos, ver `docs/04-front-guidelines.md`
  §"Nueva entrada de menú ⇒ nuevo permiso").
- **No ejecutar `migrate:fresh` ni `migrate:refresh`.** Hay datos de demo con imágenes que ningún
  factory puede recrear. Solo `php artisan migrate`.
- Demo opcional del módulo (zonas, mesas, opciones de ejemplo, módulo activado) en el tenant
  `pruebapos`: `php artisan db:seed --class=DemoPosHosteleriaSeeder`.

## Batería automatizada

```bash
php artisan test --filter=Pos
```

Debe incluir, como mínimo, y en verde:

| Área | Qué prueba | Principio |
|---|---|---|
| Aislamiento | Con 2 tenants, ninguna zona/mesa/cuenta/opción del tenant A es visible ni accesible por id desde el B | I |
| Módulo apagado | Las rutas nuevas responden 403/404 aunque el usuario tenga el permiso | — |
| Numeración | Abrir y anular cuentas no deja huecos en la serie; N cobros parciales dan N números correlativos | II |
| Régimen impositivo | El suplemento de zona calcula igual bajo IVA, IGIC e IPSI (nada asume IVA) | II |
| Cálculo | Ticket con dos tipos impositivos + suplemento de zona: el desglose por tipo suma el total exacto | III/IV |
| Cobro parcial | Suma de `pos_cobro_lineas` = `cantidad_saldada` de cada línea; imposible cobrar dos veces | IV |
| Concurrencia | Guardar con versión obsoleta devuelve 409 y no pisa cambios | — |
| Catálogo de permisos | `CatalogoPermisosTest` y `RutasPermisosTest` reflejan los dos permisos nuevos | — |

> Nota de un tropiezo ya conocido: si algún test necesita dos usuarios con permisos distintos en
> secuencia, **va en métodos separados**. Con `SESSION_DRIVER=array` el `Store` y el
> `PermissionRegistrar` se comparten entre peticiones dentro de un mismo método de test.

> Otra: al afirmar textos de pantalla, usar marcadores de HTML y no texto plano — la ayuda in-app
> se renderiza siempre y puede contener las mismas palabras, haciendo pasar un `assertSee` que
> debería fallar.

## Validación manual

### 1. El módulo apagado no cambia nada (SC-012, FR-002)

Con un tenant recién creado, antes de tocar nada:

- El POS se ve exactamente como antes: catálogo, ticket, botonera de tres franjas.
- No aparecen "Sala" ni "Opciones" en el menú.
- Entrar a `/pos/sala` por URL directa **no** funciona.
- Emitir un ticket de venta directa funciona igual que siempre.

Si algo de esto falla, no seguir: es la garantía de que la feature no rompe a los tenants actuales.

### 2. Activar y configurar (US1)

Configuración → POS → activar el módulo. Crear dos zonas ("Barra", "Comedor") y 4 mesas repartidas.
Comprobar que Sala y Opciones aparecen en el menú y que la sala pinta las mesas por zona con las
pestañas de filtro.

Intentar apagar el módulo con una cuenta abierta: debe negarse indicando cuántas hay (FR-006).

### 3. Dos cuentas a la vez (US2, SC-002)

Abrir la mesa 1, cargar 3 artículos, guardar, volver a Sala. Repetir con la mesa 2. Comprobar:

- Las dos mesas figuran ocupadas con su importe y sus minutos.
- Recuperar la mesa 1 devuelve exactamente lo cargado.
- Cobrar la mesa 1 emite ticket y la libera.
- La cuenta nunca aparece en el listado de tickets antes de cobrarse (FR-017).

### 4. Opciones (US3 y US4, SC-004/SC-005)

Crear el grupo "Punto de cocción" (exactamente 1, obligatorio) y "Extras" (varias, opcional, con
"Extra queso" +1,50 € vinculado a un artículo con stock). Asignarlos a un artículo.

- Tocar un artículo **sin** opciones: se añade en 1 toque, sin modal. Este es el criterio que
  protege al 90 % del catálogo de ganar fricción.
- Tocar el artículo **con** opciones: abre el modal; no deja confirmar sin elegir punto de cocción.
- Elegir "Extra queso": el importe de la línea sube 1,50 €.
- Emitir: el ticket muestra las opciones bajo el plato, **sin línea propia**, y el stock del
  artículo vinculado baja (FR-052).

### 5. Cobro por partes (US5, SC-014)

Cuenta de 6 líneas. Cobrar 2 líneas, luego 3, luego la última. Comprobar:

- Tras cada cobro parcial la mesa sigue ocupada y muestra el **pendiente**.
- Lo ya cobrado no se puede volver a seleccionar.
- Al saldar la última, la cuenta se cierra y la mesa se libera.
- Los 3 tickets tienen números correlativos sin huecos y suman exactamente el consumo.

### 6. Transferir y unir (US6)

Transferir una cuenta a una mesa libre; unir dos mesas ocupadas. Intentar transferir a una mesa
ocupada: debe ofrecer unir, nunca sobrescribir. Con una cuenta parcialmente cobrada, comprobar que
solo se mueve lo pendiente (FR-032).

### 7. Suplemento de zona (US7)

Poner 10 % en una zona y 0 en otra. Cobrar el mismo consumo en cada una:

- Sin suplemento: importe idéntico al de venta directa.
- Con suplemento: cada línea sube un 10 % y el desglose impositivo sigue cuadrando. **Usar un
  ticket con dos tipos impositivos distintos**: es el caso que justifica la decisión de aplicar el
  suplemento por línea y no como línea aparte.

### 8. Vuelto en efectivo (FR-063)

Cobrar 3,50 € en efectivo tecleando 5,00 € como entregado: debe mostrar 1,50 € a devolver. Comprobar
que el importe **cobrado** sigue siendo 3,50 € y que el entregado **no aparece** en el documento
emitido: es una ayuda de caja, no un dato fiscal.

### 9. La vista de crear ticket no se degradó (SC-011)

En tablet apaisada: la botonera sigue teniendo tres franjas, ninguna acción existente cambió de
sitio ni perdió tamaño táctil, y las dos columnas conservan su comportamiento de scroll.

## Antes de dar la feature por cerrada

Las cuatro capas de documentación (regla transversal de `CLAUDE.md`):

- [ ] `docs/03-modelo-datos.md` — las 10 tablas nuevas y el prefijo `pos_` con su motivo
- [ ] `docs/01-arquitectura.md` — el módulo opcional por tenant, si cambia una decisión técnica
- [ ] `docs/04-front-guidelines.md` — convenciones nuevas: tarjeta de mesa, modal de opciones,
      partición de `pos-form.js`
- [ ] `resources/views/ayuda/` — guías nuevas de Sala y Opciones + **actualizar `pos-crear`**
- [ ] `resources/ia/conocimiento/pos.md` — actualizado, y archivos nuevos si procede
