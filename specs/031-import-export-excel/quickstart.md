# Quickstart: validación de importación y exportación de Excel

**Feature**: 031-import-export-excel

Guía para comprobar que la feature funciona de punta a punta. Referencias de detalle en
[contracts/](./contracts/) y [data-model.md](./data-model.md).

## Prerrequisitos

- Entorno local levantado (`php artisan serve` + Vite si aplica).
- Base de datos migrada con datos de demo: `php artisan migrate:fresh --seed`.
- Acceso a un tenant con un usuario que tenga los permisos `ver-clientes`, `ver-articulos`,
  `ver-proveedores`, `ver-facturas`, `ver-albaranes` y `ver-leads`. Credenciales reproducibles vía
  `AccesoPersonalSeeder` (ver `ACCESOS.local.md`, fuera de git).

## Suite automatizada

```bash
# Todo lo de la feature
php artisan test --filter="ExportacionExcel|ImportacionExcel|DefinicionesExcel|ExcelPermisos|PurgarImportaciones"

# Los dos tests que NO pueden fallar nunca (Principio I)
php artisan test --filter=aislamiento
```

**Orden test-first (Principio IV)**: los tests de aislamiento multi-tenant se escriben y se ven
fallar **antes** de implementar el exportador y el importador. Si al escribirlos pasan a la
primera, están mal escritos.

---

## Escenario 1 — Exportar respetando los filtros (User Story 1, P1)

1. Entrar en **Clientes**. Anotar el total de filas que muestra el listado.
2. Escribir algo en el buscador del DataTable hasta que queden pocas filas (p. ej. 3).
3. Pulsar **Exportar**.
4. Abrir el `.xlsx` descargado.

**Esperado**:

- Contiene **3 filas de datos** + 1 de cabecera, no el total del paso 1 (FR-002, SC-002).
- Cabeceras en español (`Tipo`, `Nombre`, `Razón social`, `NIF`, …) (FR-005).
- El nombre del fichero es `clientes-AAAA-MM-DD.xlsx` (FR-007).
- En la celda de un importe, la barra de fórmulas muestra el **número crudo**, no `1.234,56 €`;
  seleccionar una columna de importes y comprobar que Excel calcula la suma (FR-006, D5).
- En **Registro de actividad** aparece una entrada `Exportación` con el recuento (FR-008).

Repetir con **Facturas** (importes y fechas), **Albaranes**, **Artículos** y **Leads**.

### 1b — Listado vacío

Filtrar hasta que no quede ninguna fila y exportar. **Esperado**: fichero válido solo con
cabecera + toast de aviso. No un error.

### 1c — Aislamiento (crítico)

Con las herramientas de desarrollo, interceptar el `POST /exportar/clientes` y añadir al array
`ids` el id de un cliente de **otro tenant**.

**Esperado**: el fichero descargado **no contiene esa fila** (FR-003, Principio I). El log de
actividad registra el número de filas realmente exportadas, no el de IDs enviados.

---

## Escenario 2 — Importar con previsualización (User Story 2, P2)

Preparar un `.xlsx` de clientes con 10 filas, de las cuales:

- 7 válidas,
- 1 con el NIF mal formado,
- 1 sin nombre,
- 1 con un NIF que ya existe en el tenant.

1. Ir a **Clientes → Importar**, subir el fichero.
2. Revisar la previsualización.

**Esperado**: indica `10 filas leídas, 7 válidas, 3 rechazadas`, con el número de fila y el motivo
de cada rechazo (FR-013). **Comprobar que aún no se ha creado ningún cliente** (FR-014, SC-006):

```bash
php artisan tinker --execute="echo \App\Models\Cliente::count();"
```

3. Confirmar.

**Esperado**: se crean exactamente **7 clientes**; resumen con los 3 rechazos y enlace de descarga
del detalle (FR-016, FR-022); entrada `Alta` en el registro de actividad (FR-021).

### 2b — El tenant nunca viene del fichero

Añadir al fichero una columna `tenant_id` con el id de otro tenant e importar.

**Esperado**: los clientes creados pertenecen al **tenant activo**; la columna se ignora por
completo (FR-015).

### 2c — Cabeceras incorrectas

Subir un fichero cuyas cabeceras no correspondan a ninguna columna esperada.

**Esperado**: `422`, mensaje indicando las cabeceras esperadas, **nada importado** (FR-018).

### 2d — Ficheros problemáticos (SC-008)

Probar uno a uno: un PDF renombrado a `.xlsx`, un fichero vacío, uno protegido con contraseña, y
uno de 2.500 filas.

**Esperado en los cuatro casos**: mensaje explicativo en español. **Ningún error 500.** El de
2.500 filas menciona el límite de 2.000 (FR-019).

### 2e — Permisos

Con un usuario sin `ver-clientes`, intentar `GET /importar/clientes` y `POST /exportar/clientes`.

**Esperado**: `403` en ambos (FR-004, FR-020).

### 2f — Prohibición estructural (FR-010)

```bash
php artisan route:list | grep importar
```

**Esperado**: aparecen rutas de importación **solo** para `clientes`, `articulos` y `proveedores`.
Navegar a `/importar/facturas` devuelve `404`.

Repetir el escenario 2 completo con **Artículos** (incluida una fila con una categoría inexistente
→ rechazo con ese motivo) y **Proveedores**.

---

## Escenario 3 — Plantilla y viaje de ida y vuelta (User Story 3, P3)

1. En **Artículos → Importar**, descargar la plantilla.
2. Comprobar que trae las cabeceras correctas + 1 fila de ejemplo (FR-023).
3. Rellenar 2 filas sin tocar las cabeceras e importar. **Esperado**: 0 rechazos por formato.

### 3b — Ida y vuelta (SC-007)

1. Exportar el listado de **Clientes**.
2. Abrir el fichero, borrar todas las filas menos 2 y cambiarles el NIF y el nombre.
3. Reimportar ese mismo fichero.

**Esperado**: se importan las 2 filas sin ningún rechazo por cabeceras ni por formato. Esto es lo
que verifica que exportación, plantilla e importación comparten una única definición (D7).

---

## Escenario 4 — Volumen y retención

### 4a — Exportación grande (SC-009)

Sembrar ~10.000 clientes en un tenant y exportar el listado completo.

**Esperado**: el fichero se genera completo, sin agotar memoria (`FromQuery` + chunking, D4). La
aplicación sigue respondiendo en otra pestaña durante la generación.

### 4b — Purga de ficheros huérfanos (Principio II)

1. Previsualizar un fichero y **no** confirmar.
2. Comprobar que existe en `storage/app/private/importaciones/`.
3. Ejecutar `php artisan importaciones:purgar`.
4. Con el fichero recién creado (< 24 h): **sigue ahí**.
5. Retrasar su fecha de modificación 25 h y repetir: **desaparece**.
6. Confirmar una importación normal y comprobar que su fichero se borra **de inmediato**, sin
   esperar a la purga.

### 4c — Previsualización caducada

Previsualizar, borrar el fichero a mano, y confirmar con ese token.

**Esperado**: `422` pidiendo volver a subirlo. **Nada importado.**

---

## Checklist de cierre de la feature

Antes de dar el trabajo por terminado, las 4 capas de documentación que exige `CLAUDE.md`:

- [ ] `docs/` — ¿cambió el modelo de datos o la arquitectura? **No** (esta feature no crea tablas);
      confirmar y dejarlo dicho.
- [ ] `docs/04-front-guidelines.md` — el botón "Exportar" en la cabecera de un DataTable **es** una
      convención de UI reutilizable: documentarla.
- [ ] `resources/views/ayuda/importar-exportar.blade.php` — guía transversal nueva (FR-025).
- [ ] `resources/views/ayuda/` — **actualizar las seis guías que ya existen** y que esta feature
      deja desactualizadas: `clientes`, `articulos`, `proveedores`, `facturas`, `albaranes`,
      `leads`. Las seis describen pantallas a las que se les añade un botón. Crear la guía nueva
      **no sustituye** a esto: la regla de oro de `CLAUDE.md` es que una guía desactualizada le
      miente al usuario.
- [ ] `resources/ia/conocimiento/importacion-exportacion.md` — base de conocimiento del asistente
      IA (FR-025, FR-013 de la feature 030). Debe dejar claro qué se puede importar y qué **no**,
      para que el asistente no le prometa al usuario un import de facturas que no existe.
- [ ] `resources/ia/conocimiento/clientes.md` y `articulos.md` — **ya existen** y no mencionan la
      importación/exportación: actualizarlos.
