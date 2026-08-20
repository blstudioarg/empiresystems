# Quickstart / validación: mesas redimensionables por celdas

**Feature**: 040-pos-mesas-redimensionables | **Fecha**: 2026-08-19

Guion para comprobar que la feature funciona de punta a punta. No contiene código de
implementación: eso vive en `tasks.md` y en el propio cambio.

## Prerrequisitos

- Entorno local en marcha con el módulo de hostelería del POS **activado** en Configuración → POS.
- Credenciales de desarrollo: ver `ACCESOS.local.md` (raíz del repo, gitignored). No inventar ni
  resetear contraseñas.
- Al menos una zona con **3-4 mesas**, incluyendo una de forma `rectangular` y una `barra`, para
  poder observar la conversión.
- Usuario con permiso de configuración del POS (el editor de plano lo exige; un camarero sin él ve
  la Sala en solo lectura).

> ⚠️ Aplicar la migración con `php artisan migrate`. **Nunca** `migrate:fresh` ni `migrate:refresh`:
> hay datos de demo con valor de presentación (regla del proyecto).

## 1. Conversión de los planos existentes

```bash
php artisan migrate
```

Comprobar, antes y después, el plano de una zona que tenga una barra o una rectangular:

- [ ] Ninguna mesa ha desaparecido (SC-003).
- [ ] Las mesas `rectangular` ocupan ahora 2 celdas de ancho y las `barra` 3, salvo donde no cupieran.
- [ ] Ninguna mesa se sale de la rejilla ni pisa a otra (SC-002) — verificable a ojo en el plano y
      por consulta:

```bash
php artisan tinker
# Por cada zona, cargar sus mesas y comprobar los invariantes G2 y G3 de data-model.md.
# Debe salir vacío: ninguna pareja solapada, ninguna fuera de 8x6.
```

- [ ] La columna `tamano` ya no existe en `pos_mesas`.

## 2. Redimensionar (User Story 1)

Sala del POS → seleccionar una zona → **Editar plano**.

- [ ] Arrastrar el borde derecho de una mesa con hueco a su derecha: crece **una celda entera de
      golpe**, encajada en la rejilla, sin quedar a medio camino.
- [ ] Seguir arrastrando: crece celda a celda.
- [ ] Arrastrar hacia dentro: encoge celda a celda y **se detiene en 1×1** (no desaparece ni se
      invierte).
- [ ] Arrastrar el borde **superior** o **izquierdo**: la mesa crece hacia ese lado y su celda de
      origen se desplaza en consecuencia.
- [ ] Salir del modo edición **sin guardar**: la mesa vuelve a su tamaño anterior.
- [ ] Redimensionar, **Guardar plano**, recargar (F5) y volver a entrar en **Editar plano**: el
      tamaño persiste. (Fuera del modo edición la Sala muestra su rejilla de tarjetas, que no dibuja
      el plano y no cambia con esta feature.)
- [ ] Ya **no** aparece ningún selector de tamaño pequeña/mediana/grande en el panel de la mesa; sí
      sigue el de forma.

## 3. Bloqueo por colisión y por rejilla (User Story 2)

- [ ] Dos mesas en celdas contiguas: agrandar una contra la otra ⇒ el borde **se detiene** y no la
      invade. La mesa vecina **no se mueve**.
- [ ] La mesa bloqueada muestra un resalte de rechazo (sombra), y **el color de su borde no cambia**
      — el borde sigue indicando su estado libre/ocupada/olvidada (FR-009).
- [ ] Una mesa apoyada en el límite derecho de la rejilla no puede crecer hacia fuera.
- [ ] Una mesa bloqueada a la derecha **sí** puede crecer hacia arriba si ahí hay hueco, **y
      también al arrastrar la esquina** (el clamp es por eje, no global).
- [ ] Mover una mesa de 2 celdas de ancho a un sitio donde su segunda celda caería sobre otra: se
      aplica el reacomodo habitual; si no hay hueco del tamaño necesario, aparece un toast
      explicando que no hay espacio suficiente y el movimiento se cancela.

## 4. Sillas (User Story 3)

- [ ] Una mesa de 3 celdas muestra visiblemente más sillas que la misma forma en 1 celda (SC-006).
- [ ] Las sillas se recalculan **al soltar el borde**, sin necesidad de guardar.
- [ ] Una `barra` alargada muestra las sillas solo en su lado largo.

## 5. Táctil (FR-019, SC-005)

Con una tablet real o con emulación táctil del navegador (DevTools → Device toolbar):

- [ ] **Primero, medir la línea base**: ¿funciona el arrastre de **posición** (asa circular) por
      táctil *antes* de tocar nada? Anotar el resultado — si ya estaba roto, es un defecto
      preexistente de la feature 039 que este cambio arregla de paso, y hay que decirlo, no
      presentarlo como si siempre hubiera funcionado.
- [ ] Tras el shim: se puede agarrar el borde con el dedo y redimensionar.
- [ ] Al arrastrar un borde, la página **no** hace scroll bajo el dedo.
- [ ] Se acierta el borde al primer intento sin activar por error el arrastre de posición (el asa
      circular y las asas de borde no se pisan).

## 6. Barrera de servidor (FR-013, Principio III)

Con las herramientas de red del navegador, reenviar un guardado manipulado:

- [ ] Un rectángulo que se sale de la rejilla ⇒ `422`, y el plano queda **exactamente** como estaba.
- [ ] Dos mesas solapadas en el payload ⇒ `422`, sin cambios parciales (SC-004).
- [ ] `ancho_celdas: 0` ⇒ `422`.
- [ ] Guardar con una `version` desactualizada ⇒ `409` con el aviso de conflicto de siempre.

## 7. Tests automáticos

```bash
php artisan test --filter=Plano
php artisan test --filter=AislamientoMesas
```

- [ ] `PlanoRectangulosTest` — solapamiento, límites, mínimo 1×1, rechazo atómico.
- [ ] `PlanoConversionTest` — la migración no deja solapes ni mesas fuera de rejilla, ni pierde mesas.
- [ ] `AislamientoMesasTest` — un tenant no puede redimensionar mesas de otro (Principio I).
- [ ] `PlanoSalaTest` — los casos existentes siguen en verde con la geometría nueva.

## 8. Documentación (obligatorio antes de cerrar)

- [ ] `resources/views/ayuda/pos-sala.blade.php` — describe el redimensionado por arrastre y ya no
      menciona el tamaño pequeña/mediana/grande (líneas ~26-30 del archivo actual).
- [ ] `resources/ia/conocimiento/pos-hosteleria.md` — sección "Plano de sala arrastrable"
      actualizada (hoy dice literalmente "su **tamaño** (pequeña, mediana, grande)").
- [ ] `docs/03-modelo-datos.md` — columnas nuevas y `tamano` retirada.
- [ ] `docs/04-front-guidelines.md` — convención reutilizable: "el feedback de bloqueo en un elemento
      cuyo borde ya comunica estado va por sombra, nunca por color de borde".
