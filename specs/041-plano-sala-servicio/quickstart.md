# Quickstart / validación: vista de plano en la Sala en modo servicio

**Feature**: 041-plano-sala-servicio | **Fecha**: 2026-08-20

Guion para comprobar que la feature funciona de punta a punta. No contiene código de
implementación: eso vive en `tasks.md` y en el propio cambio.

## Prerrequisitos

- Entorno local con el módulo de hostelería del POS **activado** en Configuración → POS.
- Credenciales de desarrollo: `ACCESOS.local.md` (raíz del repo, gitignored). No inventar ni
  resetear contraseñas.
- Una zona con **al menos 4 mesas** ya colocadas en el plano, con tamaños distintos (una de 1×1 y
  una de 2 o más celdas) para poder juzgar la legibilidad del texto.
- **Dos usuarios**: uno con permiso de configuración y otro **sin él** (solo `ver-pos-sala`). El
  segundo es imprescindible: es el destinatario real de la feature.

> Esta feature **no lleva migración**. Si algo pide migrar, es que se ha colado un cambio de
> esquema que el plan no contempla.

---

## 1. La vista existe y no rompe lo anterior

1. Abrir la Sala. **Esperado**: se ve como siempre, en vista de tarjetas — la feature no cambia la
   vista por defecto de nadie (FR-019).
2. Localizar el selector de vista en la cabecera, junto a "Actualizar". **Esperado**: dos opciones
   (tarjetas / plano), con tarjetas marcada como activa.
3. Con el usuario **con** permiso de configuración: el botón "Editar plano" sigue estando y sigue
   funcionando igual que antes (FR-020).

## 2. Ver la sala como plano y abrir cuentas (US1)

1. Cambiar a vista de plano **sin tocar "Editar plano"**. **Esperado**: el lienzo con las mesas en
   su posición, forma y tamaño reales.
2. Comprobar que **no hay ningún control de edición**: ni asa circular de arrastre, ni asas en los
   bordes, ni botón "Guardar plano" (FR-015).
3. Intentar arrastrar una mesa por su centro y por su borde. **Esperado**: no se mueve ni cambia de
   tamaño (SC-007).
4. Tocar una mesa **libre**. **Esperado**: se abre el TPV con esa mesa preseleccionada, igual que
   al tocar su tarjeta (FR-013).
5. Volver a la Sala, tocar una mesa **ocupada**. **Esperado**: se retoma su cuenta (FR-014).

## 3. El estado se lee de un vistazo (US2)

1. Preparar tres mesas: una libre, una con cuenta abierta reciente y una por encima del umbral de
   "olvidada" (Configuración → POS).
2. En vista de plano, comprobar que las tres se distinguen **sin leer el texto** (SC-002), con el
   mismo código de color que sus tarjetas (FR-006).
3. Comprobar que la mesa ocupada muestra el importe **pendiente de cobro** y el tiempo abierta
   (FR-007). Contrastar el importe con el de su tarjeta: deben coincidir.
4. Comprobar la mesa de **1×1**: nombre, importe y tiempo caben dentro del contorno, sin
   desbordarlo ni taparse entre sí (FR-009).
5. Cobrar esa cuenta desde otra pestaña y pulsar "Actualizar" en la Sala. **Esperado**: la mesa pasa
   a libre en el plano sin recargar la página ni volver a elegir la vista (FR-016, SC-005).
6. Comparar las métricas de cabecera (total / libres / ocupadas / olvidadas) con lo dibujado, en las
   dos vistas. **Esperado**: coinciden (FR-018, SC-006).

## 4. La preferencia se recuerda (US3)

1. Con la vista de plano activa, ir a otra pantalla y volver a la Sala. **Esperado**: sigue en vista
   de plano (SC-003).
2. Cerrar sesión y entrar con **otro usuario en el mismo navegador**. **Esperado**: ese usuario
   tiene su propia preferencia, no hereda la del primero (FR-003).
3. Volver al primer usuario. **Esperado**: su preferencia sigue siendo la que dejó.

## 5. Los casos borde, que son donde esto se rompe

1. **Filtro "Todas"**: estando en tarjetas con "Todas" activo, cambiar a plano. **Esperado**: se
   selecciona una zona concreta y **el filtro lo refleja**; "Todas" aparece deshabilitado mientras
   dure la vista de plano (FR-010). No debe quedarse un lienzo ambiguo o vacío.
2. **Mesa sin sitio en la rejilla**: crear mesas hasta llenar una zona (48) y crear una más. En
   vista de plano, esa mesa aparece en la franja bajo el lienzo, con su estado, y **se puede tocar
   para abrirle cuenta** (FR-011, SC-004). Es el caso que decide si la feature es segura de usar en
   un turno real.
3. **Zona sin mesas colocadas**: seleccionar una zona vacía. **Esperado**: un mensaje que explique
   la situación, no un lienzo vacío sin más (FR-012).
4. **Pantalla de tablet**: abrir la vista de plano en tablet (o emulación). **Esperado**: el ancho
   del lienzo cabe; como mucho hay scroll vertical, **nunca** scroll en los dos ejes a la vez
   (FR-021).
5. **Importe largo**: dejar una cuenta con un importe de cuatro cifras en una mesa de 1×1.
   **Esperado**: no desborda ni tapa el nombre.

## 6. Permisos (FR-004 / FR-020)

1. Entrar con el usuario **sin** permiso de configuración.
2. **Esperado**: ve el selector de vista, puede cambiar a plano, ve el estado y puede abrir cuentas
   tocando las mesas.
3. **Esperado**: **no** ve el botón "Editar plano" ni ningún control que permita alterar el plano.
4. Confirmar en las herramientas de red que la vista de plano **no emite ninguna petición de
   escritura** (I3 de [data-model.md](./data-model.md)).

## 7. Comprobación automática

```bash
php artisan test --filter=Pos
```

**Esperado**: en verde, incluido el test nuevo que fija el contrato del payload de la Sala
(`SalaPayloadPlanoTest`) y los de aislamiento que ya existían.
