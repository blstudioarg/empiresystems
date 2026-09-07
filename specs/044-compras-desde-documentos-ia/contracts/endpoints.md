# Contrato HTTP — Compras desde documentos

Todas las rutas viven dentro del grupo existente `Route::middleware('can:ver-compras')` de
`routes/web.php`. Todas responden **JSON** (`Accept: application/json`) y requieren el token CSRF: en
las que envían `FormData` viaja en el body; en las que no, **header `X-CSRF-TOKEN` explícito** (guía
de front, "CSRF en peticiones AJAX sin formulario").

> **Orden de declaración en `routes/web.php`**: estas rutas deben registrarse **antes** de
> `GET /compras/{compra}`, o Laravel resolvería `/compras/documentos` como si `documentos` fuese un
> id de compra.

---

## 1. `POST /compras/documentos` → `compras.documentos.subir`

Sube el lote. **No llama al modelo.** Valida y guarda cada fichero como temporal.

**Request** (`multipart/form-data`): `archivos[]` — 1..10 ficheros, `pdf|jpg|jpeg|png|webp`, ≤ 10 MB
cada uno, ≤ 10 páginas si es PDF.

**200 OK**

```json
{
  "documentos": [
    { "token": "7c9e…", "archivo_nombre": "factura-marzo.pdf", "formato": "pdf" },
    { "token": "1a2b…", "archivo_nombre": "albaran.jpg",       "formato": "imagen" }
  ],
  "rechazados": [
    { "archivo_nombre": "catalogo.pdf", "motivo": "El PDF supera el máximo de 10 páginas." }
  ]
}
```

Un lote donde **todos** los ficheros se rechazan responde **422** con el mismo cuerpo.

**422** — validación (`errors.archivos`), o IA no configurada:

```json
{ "message": "El asistente IA no está configurado para esta empresa. Configúralo en Configuración › Asistente IA.", "codigo": "ia_no_configurada" }
```

**403** — sin permiso `ver-compras`.

---

## 2. `POST /compras/documentos/{token}/interpretar` → `compras.documentos.interpretar`

Interpreta **un** documento. Exactamente **una** llamada al modelo. El navegador la invoca en serie,
un token detrás de otro.

**Request**: sin body. Header `X-CSRF-TOKEN` obligatorio.

**200 OK** — propuesta conforme a [`propuesta-ui.schema.json`](./propuesta-ui.schema.json).

**422** — el documento no es interpretable como compra (FR-009):

```json
{
  "message": "No se pudo interpretar «recibo-parking.jpg» como un documento de compra.",
  "codigo": "no_es_documento_compra",
  "archivo_nombre": "recibo-parking.jpg",
  "motivo": "El documento parece un recibo de aparcamiento, sin líneas de artículos ni emisor identificable."
}
```

**422 / 429 / 500 / 502** — fallo del proveedor de IA (FR-008). `codigo` ∈ `clave_invalida` (422),
`limite_excedido` (429), `servicio_no_disponible` (502), `interno` (500). `detalle` **solo** se
incluye si el usuario tiene `ver-configuracion`:

```json
{ "message": "Se alcanzó el límite de uso del servicio de IA. Probá de nuevo en unos minutos.", "codigo": "limite_excedido", "detalle": null }
```

**404** — token inexistente, caducado (> 24 h) o **de otro tenant**. Deliberadamente indistinguibles:
un token ajeno no debe poder confirmarse como existente (Principio I).

---

## 3. `POST /compras/documentos/{token}/crear` → `compras.documentos.crear`

Crea la compra desde la propuesta **editada por el usuario**.

**Request** (`application/json`) — ver [`propuesta-ui.schema.json`](./propuesta-ui.schema.json),
sección `PropuestaConfirmada`. Los importes que lleguen (`base`, `cuota_impuesto`, `totales`) se
**ignoran**; el servidor recalcula (Principio III).

```json
{
  "proveedor_id": 12,
  "crear_proveedor": false,
  "proveedor_nuevo": null,
  "numero_documento": "F-2026/1183",
  "fecha": "2026-03-14",
  "notas": null,
  "confirmar_duplicado": false,
  "lineas": [
    { "articulo_id": 44, "concepto": "Tornillo M6 20mm", "unidad": "ud", "cantidad": 500, "precio_unitario": 0.12, "tipo_impositivo": 21 },
    { "articulo_id": null, "concepto": "Portes", "unidad": null, "cantidad": 1, "precio_unitario": 18.5, "tipo_impositivo": 21 }
  ]
}
```

**201 Created**

```json
{
  "message": "Compra creada correctamente desde el documento.",
  "id": 187,
  "show_url": "https://…/compras/187",
  "totales": { "base_total": "78.50", "cuota_impuesto_total": "16.49", "total": "94.99" }
}
```

**409 Conflict** — posible duplicado detectado y `confirmar_duplicado` no venía a `true` (FR-031).
**No bloquea**: el navegador muestra el aviso y reintenta con `confirmar_duplicado: true` si el
usuario acepta.

```json
{
  "message": "Ya existe una compra con el mismo proveedor, número y fecha.",
  "codigo": "posible_duplicado",
  "compra_existente": { "id": 155, "show_url": "https://…/compras/155", "fecha": "2026-03-14", "total": "94.99" }
}
```

**422** — validación estándar de Laravel (`errors`), incluidos `proveedor_id` de otro tenant y
`lineas.*.articulo_id` de otro tenant.

**404** — token inexistente, caducado o de otro tenant.

**Efectos en éxito**: proveedor (si procede) + compra + líneas creados en **una transacción**;
fichero movido del temporal al disco `documentos`; entrada en el log de actividad.

---

## 4. `DELETE /compras/documentos/{token}` → `compras.documentos.descartar`

Descarta la propuesta. Borra el fichero temporal. **200 OK** `{ "message": "Documento descartado." }`.
Idempotente: un token ya borrado responde 200 igualmente. Nunca toca la base de datos.

---

## 5. `GET /compras/{compra}/documento` → `compras.documentos.descargar`

Descarga el documento original de una compra creada por esta vía (FR-030).

**200 OK** — binario, `Content-Type` según el formato, `Content-Disposition: attachment`.

**404** — la compra no tiene `origen = documento`, no tiene `archivo_recibido_path`, el fichero ya no
existe, o la compra es de otro tenant (el scope global ya lo garantiza).

---

## Resumen de códigos

| Código | HTTP | Significado | Acción de la UI |
|---|---|---|---|
| `ia_no_configurada` | 422 | El tenant no tiene clave de IA | Mensaje con la ruta a Configuración; el botón ya venía deshabilitado |
| `no_es_documento_compra` | 422 | El modelo no reconoció una compra | Marcar el documento como fallido en la cola y **seguir** con el siguiente |
| `clave_invalida` | 422 | Clave rechazada por el proveedor | Toast de error; abortar la cola |
| `limite_excedido` | 429 | Cuota agotada | Toast de error; abortar la cola |
| `servicio_no_disponible` | 502 | Fallo de transporte | Toast de error; ofrecer reintentar ese documento |
| `interno` | 500 | Cualquier otro fallo | Toast de error; marcar fallido y seguir |
| `posible_duplicado` | 409 | Ya existe una compra igual | Aviso con enlace; reintento opcional con `confirmar_duplicado` |
