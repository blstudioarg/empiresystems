# Contrato HTTP — Leads

**Feature**: 028 | Rutas en `routes/web.php`, protegidas por permiso `ver-leads` (spatie, feature
027) y global scope de tenant. Respuestas HTML (Blade) salvo el endpoint de datatable/JSON.

| Método | Ruta | Nombre | Permiso | Descripción |
|--------|------|--------|---------|-------------|
| GET | `/leads` | `leads.index` | ver-leads | Listado (con filtro "mis leads" vs todos según rol; bandeja "sin asignar") |
| GET | `/leads/crear` | `leads.create` | ver-leads | Formulario de alta manual |
| POST | `/leads` | `leads.store` | ver-leads | Alta manual; rechaza duplicado por email/teléfono (FR-004) |
| GET | `/leads/{lead}` | `leads.show` | ver-leads | Ficha del lead + notas + oportunidades |
| GET | `/leads/{lead}/editar` | `leads.edit` | ver-leads | Formulario de edición |
| PUT | `/leads/{lead}` | `leads.update` | ver-leads | Actualiza datos/estado/responsable |
| DELETE | `/leads/{lead}` | `leads.destroy` | ver-leads | Baja lógica (softDelete) |
| POST | `/leads/{lead}/notas` | `leads.notas.store` | ver-leads | Añade nota/actividad |
| POST | `/leads/{lead}/convertir` | `leads.convertir` | ver-leads | Convierte lead → cliente (o al ganar oportunidad) |
| GET | `/leads/importar` | `leads.importar.form` | ver-leads | Pantalla de importación por fichero |
| POST | `/leads/importar` | `leads.importar` | ver-leads | Sube CSV/Excel; devuelve resumen importados + rechazadas |

## POST `/leads` (alta manual)

**Request** (`StoreLeadRequest`):
- `nombre` (requerido, string ≤150)
- `email` (nullable, email) — requerido si no hay `telefono`
- `telefono` (nullable, string ≤30) — requerido si no hay `email`
- `empresa` (nullable, string ≤150)
- `asignado_a` (nullable, exists users del tenant) — si vacío, aplica la estrategia vigente
- `notas` (nullable)

**Respuestas**:
- `302` → `leads.show` con flash `success` si se crea.
- `302 back` con error de validación `duplicado` si email/teléfono ya existe en el tenant (FR-004).

## POST `/leads/importar`

**Request** (`ImportarLeadsRequest`):
- `fichero` (requerido, mimes: csv,txt,xlsx, máx configurable)

**Respuesta** (`302 back` con flash estructurado): resumen
`{ importados: N, rechazadas: [{fila, motivo}] }` mostrado en la vista (FR-003). Motivos posibles:
`obligatorio_ausente`, `formato_invalido`, `duplicado`. Las filas válidas se crean aunque haya
inválidas (nunca importación parcial silenciosa).

## POST `/leads/{lead}/convertir`

Convierte el lead en `cliente` (`ConversorLeadCliente`): crea el `Cliente` con los datos del lead,
fija `leads.convertido_a_cliente_id` y `estado = convertido`. Si ya existe un cliente con el mismo
NIF, responde advertencia sin duplicar (edge case spec). Idempotente: un lead ya convertido no se
reconvierte.
