# Contrato: Importación

**Feature**: 031-import-export-excel

**Módulos válidos**: `clientes`, `articulos`, `proveedores`. **Únicamente estos tres.**

Cualquier otro módulo devuelve `404`, incluidos `facturas`, `albaranes` y `leads`. Y no lo hace
por una lista negra: lo hace porque `RegistroDefiniciones` solo resuelve rutas de importación para
definiciones que implementan el contrato importable, y las de facturas y albaranes **no lo
implementan** (FR-010, ver D7). No hay ninguna comprobación que se pueda olvidar de añadir.

**Autorización**: todas las rutas viven dentro del grupo `can:ver-{modulo}` existente. Sin permiso
→ `403` (FR-020).

---

## 1. `GET /importar/{modulo}`

Muestra la pantalla de importación: zona de subida, enlace a la plantilla e instrucciones.

**Respuesta**: `200` con la vista `excel.importar`.

---

## 2. `GET /importar/{modulo}/plantilla`

Descarga una plantilla `.xlsx` vacía (FR-023).

**Respuesta**: `200`, descarga binaria,
`Content-Disposition: attachment; filename="plantilla-clientes.xlsx"`.

Contenido: fila de cabecera con las etiquetas de la definición **más una fila de ejemplo** con los
valores `ejemplo` de cada columna.

**Garantía FR-024**: las cabeceras proceden de la misma lista `columnas()` que usa la exportación,
así que coinciden por construcción, no por mantenimiento manual.

---

## 3. `POST /importar/{modulo}/previsualizar`

Analiza el fichero **sin escribir ningún dato** (FR-012, FR-014).

### Petición

`multipart/form-data`:

| Campo | Reglas |
|---|---|
| `fichero` | `required`, `file`, `mimes:xlsx,xls,csv,txt`, `max:5120` (5 MB) |

### Proceso

1. Guardar el fichero en `storage/app/private/importaciones/{uuid}` y generar el `token`.
2. Leer las cabeceras. Si faltan columnas obligatorias → **rechazo del fichero entero** (`422`,
   FR-018), indicando cuáles se esperaban. Nada se importa y el fichero se borra.
3. Si el número de filas supera **2.000** → `422` indicando el límite (FR-019). El fichero se
   borra.
4. Recorrer las filas: normalizar cada valor y validarlas con las reglas del `FormRequest` del
   alta manual (D3), acumulando los identificadores ya vistos para detectar duplicados **dentro
   del propio fichero**.

### Respuesta — 200

```json
{
  "token": "9f2c1e40-...",
  "modulo": "clientes",
  "total_filas": 120,
  "validas": 117,
  "rechazadas": [
    { "fila": 14, "motivo": "El NIF no es válido." },
    { "fila": 51, "motivo": "Ya existe un cliente con el NIF B12345678." },
    { "fila": 52, "motivo": "El NIF B12345678 está repetido en el fichero (fila 51)." }
  ],
  "muestra": [ { "Tipo": "Empresa", "Nombre": "Acme SL", "…": "…" } ]
}
```

`muestra` contiene las 10 primeras filas válidas ya normalizadas, para que el usuario verifique de
un vistazo que las columnas se han interpretado como esperaba antes de confirmar.

### Errores

| Código | Caso |
|---|---|
| `403` | Sin permiso sobre el módulo. |
| `404` | Módulo no importable (facturas, albaranes, leads, o inexistente). |
| `422` | Fichero ausente, tipo no admitido, mayor de 5 MB, cabeceras insuficientes (FR-018), o más de 2.000 filas (FR-019). |
| `422` | Fichero vacío, corrupto, protegido con contraseña o ilegible → mensaje explicativo, **nunca un 500** (SC-008). |

**Invariante verificable (SC-006)**: tras cualquier respuesta de este endpoint, el recuento de
registros del módulo en base de datos es idéntico al de antes de la petición.

---

## 4. `POST /importar/{modulo}/confirmar`

Ejecuta la importación (FR-014: solo aquí se escribe).

### Petición

| Campo | Reglas |
|---|---|
| `token` | `required`, `string`, `uuid` |

### Proceso

1. Recuperar el fichero por `token`. Si no existe o tiene más de 24 h → `422` pidiendo volver a
   subirlo (edge case "previsualización caducada").
2. **Volver a analizar y validar el fichero entero desde cero.** No se reutiliza el veredicto de
   la previsualización: entre previsualizar y confirmar, otro usuario del tenant puede haber
   creado un registro con el mismo NIF. La previsualización es informativa; **la confirmación es
   la autoritativa** (D2).
3. Persistir las filas válidas, **forzando `tenant_id = tenant()->id`** e ignorando por completo
   cualquier columna del fichero que pretenda fijar un tenant (FR-015).
4. Las filas inválidas **no abortan** el proceso: se importan las válidas y se reportan las
   rechazadas (FR-016), igual que ya hace `ImportadorLeads`.
5. Borrar el fichero.
6. Registrar la actividad (FR-021).

### Respuesta — 302

Redirección a la pantalla de importación con el resumen en sesión, mostrado por toast + panel de
resultado (FR-022):

```
importados: 117
rechazadas: [ { fila, motivo }, … ]
```

Si hay rechazos, se ofrece un enlace de descarga del detalle en `.xlsx` para corregir y reintentar
solo esas filas.

### Errores

| Código | Caso |
|---|---|
| `403` | Sin permiso sobre el módulo. |
| `404` | Módulo no importable. |
| `422` | Token ausente, desconocido o caducado. |

---

## 5. `GET /importar/{modulo}/rechazos/{token}`

Descarga el detalle de filas rechazadas de la última importación confirmada (FR-022), en `.xlsx`
con dos columnas: `Fila` y `Motivo`.

---

## Garantías transversales

| Garantía | Cómo se cumple |
|---|---|
| **FR-015 / Principio I** — el tenant nunca viene del fichero | `crear()` fija `tenant_id` desde `tenant()->id` del usuario autenticado. La columna `tenant_id` del fichero, si existe, no está en `columnas()` y por tanto **no se lee**. Test obligatorio: importar un fichero con una columna `tenant_id` de otro tenant y afirmar que los registros creados son del tenant activo. |
| **FR-017** — mismas validaciones que el alta manual | Se ejecutan las `rules()` del `FormRequest` existente (`StoreClienteRequest`, `StoreArticuloRequest`, `StoreProveedorRequest`), no un juego de reglas paralelo (D3). |
| **FR-010 / Principio III** — nada financiero es importable | Facturas, albaranes, pagos y movimientos de stock no implementan el contrato importable. La ruta ni siquiera se registra para ellos. |
| **Principio II (RGPD)** — el fichero no se conserva | Almacenamiento privado (nunca URL pública), borrado tras confirmar o cancelar, y comando `importaciones:purgar` a 24 h enganchado en `bootstrap/app.php` → `withSchedule`, siguiendo el patrón obligatorio de `logs:purgar`. |
| **SC-008** — ningún fallo interno | Todo error de lectura (fichero corrupto, vacío, protegido, tipo falseado) se captura y se traduce a un `422` con mensaje en español. |
