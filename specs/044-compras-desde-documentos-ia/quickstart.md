# Quickstart — Validación end-to-end

**Feature**: `044-compras-desde-documentos-ia`

Guía para comprobar que la feature funciona de verdad. No contiene código de implementación: eso
vive en `tasks.md` y en el código. Detalles de forma en [`contracts/`](./contracts/) y
[`data-model.md`](./data-model.md).

---

## Prerequisitos

1. **Rama correcta**. La base es `feat/041-plano-sala-servicio`, **no `main`**:

   ```powershell
   git fetch origin
   git switch -c 044-compras-desde-documentos-ia origin/feat/041-plano-sala-servicio
   ```

2. **Dependencias y migraciones**. Esta feature no añade ninguna de las dos, pero la rama base trae
   5 migraciones que `main` no tiene:

   ```powershell
   php artisan migrate        # NUNCA migrate:fresh — ver CLAUDE.md, política de datos de demo
   ```

3. **Acceso local**: credenciales en `ACCESOS.local.md` (raíz, gitignored). Si tras un reset falta
   el usuario: `php artisan db:seed --class=AccesoPersonalSeeder` (idempotente).

4. **Clave de IA del tenant**: Configuración › Asistente IA. Es la misma clave de OpenAI que usa el
   chat (feature 030); no hay configuración nueva. Sin ella, el botón debe aparecer **deshabilitado
   con explicación** — ese es en sí el primer caso a validar (escenario 6).

5. **Documentos de prueba**. Hacen falta cuatro, y conviene dejarlos fuera del repo
   (`storage/app/pruebas-044/`, o el scratchpad):
   - `factura-proveedor-conocido.pdf` — proveedor **ya existente** en el tenant, con su NIF.
   - `factura-proveedor-nuevo.pdf` — NIF que **no** está en el catálogo.
   - `albaran-foto.jpg` — foto de móvil, para validar la rama de imagen.
   - `recibo-parking.jpg` — **no** es un documento de compra: valida el descarte.

---

## Puesta en marcha

```powershell
php artisan serve          # o el entorno habitual del proyecto
npm run dev                # solo si se tocan assets compilados; el JS de esta feature es plano
```

Entrar como usuario **con** permiso `ver-compras` e ir a **Compras**.

---

## Escenarios de validación

Cada escenario indica qué requisito del spec cierra. El criterio general: **nada se persiste hasta
que se pulsa "Crear compra"**.

### 1. Camino feliz, proveedor conocido (US1, US3 — FR-001..FR-015, FR-026..FR-030)

1. Pulsar **Importar documento** → sube `factura-proveedor-conocido.pdf`.
2. **Comprobar durante la espera**: el botón queda deshabilitado con spinner y texto de carga; se ve
   el progreso "1 de 1". (Si el botón sigue pulsable, falla la guía "Estado de carga en botones".)
3. **Comprobar en la propuesta**: proveedor **preseleccionado** e indicado como reconocido por NIF;
   número, fecha y líneas rellenos; las líneas cuyo SKU coincide traen artículo asignado y marca de
   "moverá stock"; los totales mostrados son los **recalculados**, no necesariamente el total
   impreso del documento (si difieren, debe verse el aviso).
4. **Antes de confirmar**: en otra pestaña, comprobar que **no existe** aún la compra en el listado.
5. Pulsar **Crear compra** → toast de éxito, el modal avanza al resumen y el listado se recarga
   **sin recargar la página** (métricas de la cabecera incluidas).
6. Abrir la compra: estado **borrador**, origen "Documento (IA)", y el enlace de **descarga del
   documento original** funciona.
7. Pulsar **Confirmar** en la compra: el stock de los artículos de las líneas emparejadas sube.
   Pulsar **Anular**: vuelve a bajar. (FR-026: sin lógica de stock nueva.)

### 2. Edición de la propuesta (US1 — FR-013, FR-014, FR-027)

Con una propuesta en pantalla: cambiar la fecha, editar el concepto de una línea, cambiar una
cantidad, **añadir** una línea y **eliminar** otra. Crear.

- La compra creada refleja **lo editado**, no lo propuesto por el modelo.
- Los importes persistidos cuadran con las líneas finales.
- **Prueba de Principio III**: interceptar la petición de creación (DevTools › Network › Edit and
  resend) y falsear `base`/`total`. La compra creada debe ignorar esos valores por completo.

### 3. Proveedor nuevo (US2 — FR-017..FR-021)

1. Subir `factura-proveedor-nuevo.pdf`.
2. La propuesta muestra **sin coincidencia** y ofrece crear el proveedor con los datos leídos,
   editables. **Crear compra** debe estar bloqueado mientras no se resuelva el proveedor.
3. **Comprobar la interacción de alta inline**: el check nace deshabilitado en vacío; **Enter**
   confirma y **Escape** descarta; **hacer clic fuera del campo NO crea nada** (guía "Alta inline:
   confirmación explícita, nunca por `blur`").
4. Corregir un dato leído, confirmar el alta y crear la compra → proveedor y compra existen ambos.
5. **Prueba de atomicidad (FR-021)**: repetir con una línea inválida (cantidad 0) para forzar el
   fallo de validación. Tras el error, comprobar en el catálogo que **no se ha creado** el proveedor.
6. **Prueba de descarte (SC-006)**: volver a subir el mismo documento y pulsar **Descartar** tras ver
   la oferta de alta. El catálogo de proveedores no debe haber cambiado.

### 4. Lote con fallo parcial (US4 — FR-033..FR-035)

Subir de una vez: `factura-proveedor-conocido.pdf`, `albaran-foto.jpg` y `recibo-parking.jpg`.

- El progreso avanza documento a documento; la cola indica "n de N".
- El recibo de párking se marca como fallido **identificándolo por su nombre de archivo**, y la cola
  **continúa** con los demás.
- Crear las dos propuestas válidas. El resumen final indica cuántas se crearon, cuántas se
  descartaron y cuál falló y por qué.
- **Prueba de abandono**: repetir el lote y cerrar el modal a mitad. Las compras ya creadas siguen
  ahí; las propuestas pendientes desaparecen sin dejar rastro.

### 5. Duplicado (US5 — FR-031)

Volver a subir `factura-proveedor-conocido.pdf` (ya creada en el escenario 1).

- La propuesta muestra el aviso de **posible duplicado** con enlace a la compra existente.
- El aviso **no bloquea**: aceptar debe crear la compra igualmente.
- Descartar no crea nada.

### 6. Degradación sin clave de IA (FR-007, FR-013 de research)

Borrar la clave en Configuración › Asistente IA y recargar Compras.

- El botón **Importar documento** aparece deshabilitado, explicando dónde configurarla.
- Llamar al endpoint a mano (curl/DevTools) responde **422** con `codigo: "ia_no_configurada"` y un
  mensaje accionable, nunca una excepción.

### 7. Límites de entrada (FR-004)

- Un fichero de >10 MB, un `.docx`, un PDF de >10 páginas y un lote de 11 ficheros deben rechazarse
  **antes** de llamar al modelo, indicando el límite concreto y el archivo afectado.
- Verificar en los logs / en el consumo del proveedor que esos casos **no** gastaron llamada.

### 8. Aislamiento multi-tenant (FR-036, SC-007 — Principio I)

Con dos tenants (A y B):

- Generar una propuesta en A, copiar su `token`, e intentar `interpretar` y `crear` desde una sesión
  de B → **404** en ambos casos (no 403: un token ajeno no debe confirmarse como existente).
- Enviar en la creación un `proveedor_id` y un `lineas.*.articulo_id` de B desde A → **422** de
  validación, nunca una compra creada con datos cruzados.

### 9. Retención y purga (FR-038 — Principio II/RGPD)

1. Subir un documento y **no** crear la compra.
2. Comprobar que existe el temporal en `storage/app/compras-documentos/`.
3. Envejecerlo (tocar su fecha de modificación a >24 h) y ejecutar:

   ```powershell
   php artisan compras-documentos:purgar
   ```

4. El fichero desaparece. El de una compra **creada** (ya en el disco `documentos`) **no** se toca.
5. Comprobar que el comando está en el `withSchedule` de `bootstrap/app.php` con `->daily()`.

---

## Suite automatizada

```powershell
php artisan test --filter=CompraDocumento    # Feature: subida, interpretación, creación, aislamiento
php artisan test --filter=Emparejador        # Unit: proveedor y artículo
php artisan test --filter=ProponedorCompra   # Unit: recálculo, campos ilegibles, régimen impositivo
php artisan test                             # suite completa: nada de la rama base debe romperse
```

**Ningún test llama a la API real**: `InterpretadorDocumentoCompra` se sustituye por un doble que
devuelve lecturas fijas. Un test que necesite red es un test mal escrito.

**Regresión obligatoria de la rama base**: la suite completa incluye las features 040-043 (POS y
Cobros). Debe quedar verde **sin tocar ni un test existente**.

---

## Checklist de cierre (no es opcional)

- [ ] `resources/views/ayuda/compras.blade.php` describe el flujo nuevo (FR-040).
- [ ] `resources/ia/conocimiento/compras.md` **creado** y ensamblado por `ConocimientoAsistente`
      (FR-041). Verificación viva: preguntarle al asistente "¿cómo cargo una compra desde un PDF?"
      y comprobar que responde con el flujo real.
- [ ] `docs/04-front-guidelines.md` tiene la sección nueva de "cola de propuestas en modal" (FR-042).
- [ ] `docs/03-modelo-datos.md` revisado: el origen `documento` de `compras` queda anotado.
- [ ] El JS nuevo se carga con `@assetv(...)`, no con `asset()`.
