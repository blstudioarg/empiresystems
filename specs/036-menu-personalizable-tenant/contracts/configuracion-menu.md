# Contrato — Endpoints de la tab "Menú" de Configuración

**Feature**: 036-menu-personalizable-tenant

Dos rutas nuevas, registradas en `routes/web.php` **dentro del grupo
`Route::middleware('can:ver-configuracion')` ya existente** (FR-013: el enforcement es el
middleware, no ocultar la tab). No se crea ningún permiso nuevo.

---

## 1. Guardar la personalización

```
PUT|PATCH /configuracion/menu      name: configuracion.menu.update
```

**Autorización**: sesión autenticada de un tenant + `can:ver-configuracion`. El Super Admin no
accede a esta vista (FR-014).

**Petición** (`application/json` o form-encoded, enviada por AJAX desde la tab):

```json
{
  "etiquetas": {
    "clientes": "Pacientes",
    "cartera-clientes": "Mis pacientes"
  },
  "orden": {
    "_raiz": ["facturas", "inicio", "clientes", "..."],
    "control-fichaje": ["alertas", "fichar", "..."]
  }
}
```

Ambas propiedades son opcionales; el servidor normaliza contra el catálogo y **no confía en que la
lista esté completa ni bien formada** (research.md, D4).

**Respuestas**:

| Código | Cuerpo | Cuándo |
|---|---|---|
| `200` | `{ "message": "Menú guardado correctamente." }` | Éxito. El cliente muestra el toast y **no** recarga la página; el sidebar nuevo se ve en la siguiente navegación. |
| `422` | `{ "message": "...", "errors": { "etiquetas.clientes": ["El nombre no puede superar los 40 caracteres."] } }` | Validación (data-model.md §4). La clave del error identifica el elemento para pintarlo junto a su input. |
| `403` | — | Sin `ver-configuracion`. |
| `419` | — | Token CSRF caducado. |

**Efectos**:

- `updateOrCreate` de la fila `menu.personalizacion` del tenant (data-model.md §2).
- Registro en `logs_actividad` vía `RegistradorActividad`, con
  `AccionLogActividad::Modificacion` + `EntidadLogActividad::Configuracion` y el texto
  "Actualizó la personalización del menú lateral" (FR-022), idéntico en forma a `updateCrm`.
- **Sin efecto sobre otros tenants** (FR-012).

**Nota de compatibilidad**: el resto de tabs responden `redirect()` cuando la petición no es JSON.
Esta responde **siempre JSON** porque su formulario es exclusivamente AJAX (no hay submit clásico
que necesite un redirect con flash).

---

## 2. Restaurar los valores por defecto

```
DELETE /configuracion/menu         name: configuracion.menu.restaurar
```

**Autorización**: idéntica al endpoint anterior.

**Petición**: sin cuerpo (solo CSRF).

**Respuestas**:

| Código | Cuerpo | Cuándo |
|---|---|---|
| `200` | `{ "message": "Menú restaurado a los valores por defecto.", "estructura": [ ... ] }` | Éxito. Devuelve la estructura por defecto para que la tab se repinte sin recargar. |
| `403` / `419` | — | Igual que arriba. |

**Efectos**: borra la fila `menu.personalizacion` del tenant (FR-016) y registra la actividad con el
texto "Restauró el menú lateral a los valores por defecto". Es **idempotente**: restaurar un tenant
que nunca personalizó devuelve `200` sin hacer nada.

**Confirmación previa**: la exige el cliente con `window.confirmDelete(...)` y las opciones
`confirmLabel: 'Restaurar'` / `confirmClass: 'btn-primary'` (no es un borrado de registros, no debe
mostrar la papelera roja ni el botón "Eliminar" — ver `docs/04-front-guidelines.md`, sección
"Confirmación de acciones irreversibles"). El servidor **no** depende de esa confirmación.

---

## 3. Contrato de lectura (sin endpoint propio)

La tab **no** tiene endpoint `GET` propio: su estado inicial lo entrega
`ConfiguracionController::show()`, que ya sirve `/configuracion`, añadiendo a la vista la clave
`menuEstructura` con el resultado de `MenuTenant::estructura($tenantId)`. Cada elemento llega a la
vista con:

| Campo | Uso en la tab |
|---|---|
| `clave` | `name` del input y valor arrastrable de la fila |
| `etiqueta` | valor actual del input de nombre |
| `etiqueta_defecto` | texto de apoyo junto al input (FR-020) |
| `hijos` | lista anidada arrastrable |

Es coherente con el resto de tabs, que también reciben sus datos desde `show()` y no por un `GET`
aparte.
</content>
