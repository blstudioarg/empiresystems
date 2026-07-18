# Data Model — 030 Chat flotante con asistente IA

**Sin migraciones nuevas.** La feature reutiliza estructuras existentes y estado efímero de sesión.

## 1. Configuración de IA del tenant (tabla `configuraciones`, existente)

Filas nuevas en el grupo `ia` (patrón `EmailTenant`/feature 017):

| Clave | Valor | Cifrado | Default |
|---|---|---|---|
| `ia.api_key` | API key de OpenAI del tenant | Sí (`Crypt::encryptString`) | `''` (no configurada) |

- Scope: `tenant_id` (global scope existente en `Configuracion`).
- Acceso solo vía `App\Support\IaTenant` (nunca lectura directa en controladores/vistas).
- `IaTenant::configurada(): bool` — clave no vacía.
- `IaTenant::apiKeyEnmascarada(): string` — `sk-ant-…XXXX` (últimos 4), para la vista.
- Guardar/quitar la clave registra evento en `logs_actividad` (sin incluir el valor).

## 2. Conversación del asistente (sesión de Laravel, efímera)

Clave de sesión `asistente.conversacion`:

```php
[
  'mensajes' => [            // formato Chat Completions de OpenAI (roles user/assistant/tool, con tool_calls)
    ['role' => 'user', 'content' => '...'],
    ['role' => 'assistant', 'content' => [...]],
  ],
  'accion_pendiente' => [    // null si no hay propuesta abierta; máx. 1 a la vez
    'id' => 'uuid',
    'tool' => 'crear_cliente',
    'parametros' => [...],   // ya validados por la tool al proponer
    'resumen' => 'Crear cliente "Textiles Sur" con NIF B12345678',
    'creada_en' => 'iso8601',
  ],
]
```

Reglas:
- **Truncado** (clarificación): al superar `config('ia.max_mensajes')` se descartan los turnos más antiguos, preservando pares `tool_use`/`tool_result` completos (nunca un `tool_result` huérfano).
- **Acción pendiente**: se invalida al confirmar, cancelar, enviar un mensaje nuevo o reiniciar la conversación. Si el modelo propone una segunda escritura habiendo ya una pendiente, la nueva se rechaza con tool_result explicativo (no reemplaza ni encola). Confirmar re-verifica permiso del usuario y estado de la entidad antes de ejecutar.
- **Ciclo de vida**: muere con la sesión (logout/expiración). Sin retención adicional (RGPD: efímero por diseño).

## 3. Catálogo de tools (código, no datos)

Cada tool implementa `ToolAsistente`:

| Método | Significado |
|---|---|
| `nombre(): string` | Identificador para la API (`buscar_clientes`) |
| `descripcion(): string` | Descripción para el modelo (incluye cuándo usarla) |
| `schema(): array` | JSON Schema de parámetros |
| `permisoRequerido(): string` | Clave del `CatalogoPermisos` (feature 027) |
| `esLectura(): bool` | `true` = ejecuta directo; `false` = genera acción pendiente |
| `ejecutar(array $parametros): array` | Lectura: resultado. Escritura: solo se llama desde el endpoint de confirmación |
| `proponer(array $parametros): array` | Solo escrituras: valida y devuelve `{resumen, parametros_normalizados}` |

Catálogo inicial (§Project Structure del plan): 5 lecturas + 7 escrituras. Restricciones de negocio dentro de cada tool:
- `CrearFacturaBorrador` / `EditarFacturaBorrador`: estado `borrador` forzado; importes vía `CalculadoraFactura`; `EditarFacturaBorrador` aborta si la factura no está en borrador.
- `CrearPresupuesto`: importes vía `RegistroPresupuesto`/calculadora existente.
- Búsquedas: paginadas (top N configurable), campos mínimos necesarios (minimización D11).

## 4. Base de conocimiento (`resources/ia/conocimiento/*.md`, archivos versionados)

- `00-general.md` + un `.md` por módulo funcional.
- Formato libre markdown; encabezado H1 = nombre del módulo.
- `ConocimientoAsistente::systemPrompt(User $user)`: reglas fijas del asistente + concatenación determinista (orden alfabético) de los `.md` + contexto del usuario (nombre, lista de secciones con permiso).
- Invariante SC-007: añadir feature = añadir archivo, sin editar los existentes.

## 5. Configuración de instalación (`config/ia.php`)

| Clave | Default | Uso |
|---|---|---|
| `modelo` | `gpt-4o-mini` (env `IA_MODELO`) | Modelo fijo del sistema (D2) |
| `max_tokens` | `4096` | Límite de salida por respuesta |
| `max_mensajes` | `30` | Umbral de truncado de conversación |
| `max_resultados_tool` | `10` | Paginación de búsquedas (minimización) |
| `max_iteraciones_tools` | `8` | Tope del loop de tool use por mensaje |
