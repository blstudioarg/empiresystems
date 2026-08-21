# Quickstart: validación de la feature 042

**Feature**: 042-plano-sala-forma-medidas | **Fecha**: 2026-08-21

Cómo comprobar que el lienzo por zona funciona de verdad. Los escenarios automatizados cubren los
invariantes; los manuales cubren el gesto, que es donde esta feature se gana o se pierde.

## Prerrequisitos

- Módulo de hostelería activo en el tenant de pruebas (Configuración → POS).
- Al menos una zona con dos o tres mesas colocadas en el plano.
- Un segundo usuario **sin** el permiso de configuración del plano pero **con** acceso a la Sala
  (para el escenario 6). Credenciales de desarrollo: ver `ACCESOS.local.md`.
- Migración aplicada:

  ```powershell
  php artisan migrate
  ```

  > La migración solo **añade** columnas con default. No requiere `migrate:fresh` (prohibido sin
  > confirmación explícita, ver `CLAUDE.md`).

## Validación automatizada

```powershell
php artisan test --filter="PlanoLienzoZona|AislamientoPlanoLienzo|SalaPayloadPlano|PlanoRectangulos|PlanoSala"
```

Debe quedar todo en verde, incluidos los tests de las features 039/040/041: la feature no cambia
ningún comportamiento anterior, solo el valor contra el que se valida.

Cobertura esperada de `PlanoLienzoZonaTest`:

| Caso | Espera |
|------|--------|
| Guardar con `columnas`/`filas` válidas | 200 y zona actualizada |
| Guardar con una mesa sobre una celda inactiva (G4) | 422, ningún cambio persistido |
| Guardar recortando la zona entera (G5) | 422 |
| Guardar con medidas fuera de 4-24 | 422 |
| Guardar reduciendo medidas con una mesa fuera (G2) | 422 nombrando la mesa |
| Guardar reduciendo medidas **y** moviendo esa mesa en la misma petición | 200 (se valida la geometría propuesta, no la guardada) |
| `celdas_inactivas` con claves fuera de la rejilla propuesta | 200, con esas claves descartadas al persistir |
| `celdas_inactivas` con duplicados y desordenadas | 200, persistido normalizado y ordenado |
| `version` desactualizada | 409, ningún cambio |

`AislamientoPlanoLienzoTest`: dos tenants, cada uno con su zona; el tenant A no puede leer ni guardar
el lienzo de la zona de B (Principio I).

## Validación manual

### 1. Medidas por zona (US1)

1. Sala → modo edición del plano de una zona.
2. Subir las columnas a 12 → el lienzo se ensancha en el acto y las mesas no se mueven.
3. Guardar, recargar (F5) → las medidas se conservan.
4. Cambiar a otra zona → conserva **sus** medidas, no las de la anterior (regresión de D4: la
   geometría ya no es global).
5. Volver a la primera, cambiar medidas y **cancelar** la edición → vuelve a las medidas guardadas.

### 2. Recortar la planta (US2)

1. Activar el modo de recorte.
2. Arrastrar el dedo/puntero sobre un bloque de celdas de una esquina en **un solo gesto** → todas se
   marcan como fuera de la sala.
3. Salir del modo de recorte, guardar y recargar → la zona se dibuja en L; las celdas recortadas se
   ven como vacío, claramente distintas de una celda de suelo libre.
4. Intentar arrastrar una mesa sobre una celda recortada → no la ocupa, igual que si hubiera otra
   mesa.
5. Agrandar una mesa hacia una celda recortada → el borde no avanza.

### 3. Nada se pierde (US3)

1. Colocar una mesa en la última columna.
2. Intentar reducir las columnas por debajo de esa posición → se rechaza indicando **cuántas mesas**
   quedarían fuera; el lienzo no cambia.
3. Intentar recortar una celda ocupada → la celda no cambia y la mesa que lo impide hace el gesto de
   rechazo (sombra + micro-desplazamiento), **sin** cambiar de color de borde.
4. Recorrer con el gesto de recorte varias celdas ocupadas seguidas → **un solo** toast al soltar,
   con el conteo, no uno por celda.
5. Mover las mesas y repetir → ahora sí se completa.

### 4. Migración conservadora (FR-015 / SC-007)

Antes de migrar, anotar el plano de una zona (medidas y posiciones). Tras migrar: mismas medidas
(8×6), planta completa, todas las mesas en su sitio.

### 5. Atomicidad (SC-005)

Con las herramientas del navegador, enviar un guardado manipulado (una mesa fuera de la rejilla
declarada). Respuesta 422 y, al recargar, el plano **exactamente** como estaba: ni medidas, ni
recorte, ni mesas, ni `version` cambiados.

### 6. El camarero ve la misma sala (US4 / SC-006)

Con el usuario **sin** permiso de configuración: abrir la Sala en vista de plano. El contorno
(medidas y forma) coincide con el del editor. Comprobar además que no aparece ningún control de
medidas ni de recorte (FR-018).

### 7. Rendimiento y tablet

1. Poner una zona a 24×24 y activar el modo de recorte → la interacción sigue siendo fluida.
2. Desactivar el modo de recorte → la capa de celdas se desmonta (verificable en el inspector: el
   lienzo vuelve a tener solo mesas y las celdas inactivas).
3. En tablet (o emulación táctil): el primer gesto de recorte **no** mueve ninguna mesa; y con el
   modo de recorte apagado, arrastrar una mesa funciona como siempre.
4. Con una zona más ancha que la pantalla: todas las mesas siguen siendo alcanzables mediante
   desplazamiento, sin scroll en dos ejes simultáneos.

### 8. Mesa nueva en una zona recortada

Crear una mesa desde el panel de gestión en una zona con las primeras celdas recortadas → aparece en
la primera celda **de suelo** libre, nunca en un hueco recortado (D3).

## Documentación (obligatoria antes de cerrar)

- [ ] `docs/04-front-guidelines.md`: convención nueva sobre el modo de gesto explícito para pintar
      sobre un lienzo que ya tiene arrastre (D6).
- [ ] `docs/03-modelo-datos.md`: las tres columnas nuevas de `pos_zonas`.
- [ ] `resources/views/ayuda/pos-sala.blade.php`: cómo ajustar medidas y recortar la planta (FR-016).
- [ ] `resources/ia/conocimiento/pos-hosteleria.md`: lienzo por zona (FR-017).
