# Dynamic Select Component Pattern

Guía para crear componentes de select dinámicos reutilizables (p. ej. unidades, clientes, provincias) con CRUD inline vía modal.

## Patrón general

Cada select dinámico se compone de 4 capas:

1. **Componente Blade** (`resources/views/components/X-select.blade.php`) — markup + assets compartidos (`@once`)
2. **CSS** (`public/css/X-select.css`) — estilos (cargado por el componente vía `@push('styles')`)
3. **Módulo JS** (`public/js/components/X-select.js`) — lógica multi-instancia reutilizable
4. **Tabla + Modelo + Controller** — backend (tenant-scoped si aplica)

## Caso base: `<x-unidad-select>`

### 1. Backend

**Tabla + Migración** (`database/migrations/2026_07_03_130001_create_unidades_table.php`):
```php
Schema::create('unidades', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('nombre', 20);
    $table->timestamps();
    $table->unique(['tenant_id', 'nombre']);
});
```

**Modelo** (`app/Models/Unidad.php`):
```php
class Unidad extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id', 'nombre'];
}
```

**Controller** (`app/Http/Controllers/UnidadController.php`):
- `index()` → JSON `[{id, nombre}, ...]`
- `store(Request)` → crea, retorna `{message, unidad: {id, nombre}}`
- `update(Request, id)` → actualiza, retorna lo mismo
- `destroy(id)` → elimina, retorna `{message}`

**Rutas** (`routes/web.php`):
```php
Route::resource('unidades', UnidadController::class)->only(['index', 'store', 'update', 'destroy']);
```

---

### 2. CSS

**`public/css/unidad-select.css`**:
- `.unidad-control .select2-container { flex: 1 1 auto; width: 1% !important; }`
- `.unidad-control .select2-container--default .select2-selection--single { ... }`
  - `height: auto; min-height: 0;` (no fijar alto fijo)
  - `padding: 0.25rem 0.75rem; font-size: 0.76563rem; line-height: 1.5;`
  - `display: flex; align-items: center;` (centra verticalmente)
  - `border-top-right-radius: 0; border-bottom-right-radius: 0;` (esquinas derechas cuadradas para botones)
- `.unidad-control .select2-container--default .select2-selection--single .select2-selection__rendered { ... }`
  - `padding: 0; margin: 0; min-height: 0;`
  - `line-height: 1.5; font-size: 0.76563rem;`
- `.unidad-control .select2-container--default .select2-selection--single .select2-selection__arrow { top: 0; height: 100%; }`

Propósito: igualar exactamente la altura y fuente de `.form-control` para alineación visual.

---

### 3. Componente Blade

**`resources/views/components/unidad-select.blade.php`**:

```blade
@props([
    'name' => 'unidad',
    'id' => null,
])
@php $fieldId = $id ?? $name; @endphp

<div class="input-group unidad-control">
    <select name="{{ $name }}" id="{{ $fieldId }}" 
            class="form-control unidad-select" {{ $attributes }}></select>
    <button type="button" class="btn btn-success btn-unidad-add" 
            title="Nueva unidad"><i class="fas fa-plus"></i></button>
    <button type="button" class="btn btn-primary btn-unidad-edit" 
            title="Renombrar"><i class="fas fa-pen"></i></button>
    <button type="button" class="btn btn-danger btn-unidad-delete" 
            title="Eliminar"><i class="fas fa-trash"></i></button>
</div>
<div class="invalid-feedback d-block" data-error-for="{{ $name }}"></div>

@once
    @push('styles')
        <link href="{{ asset('vendor/select2/css/select2.min.css') }}" rel="stylesheet">
        <link href="{{ asset('css/unidad-select.css') }}" rel="stylesheet">
    @endpush

    @push('scripts')
        <!-- Modal compartido para todas las instancias de unidad-select -->
        <div class="modal fade" id="unidadModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form id="unidad-form">
                        <div class="modal-header">
                            <h5 class="modal-title" id="unidadModalLabel">Nueva unidad</h5>
                            <button type="button" class="btn-close" 
                                    data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="unidad_nombre" class="form-label">Nombre</label>
                            <input type="text" id="unidad_nombre" class="form-control" 
                                   maxlength="20" placeholder="ud, hora, kg...">
                            <div class="invalid-feedback d-block" 
                                 data-error-for="unidad_nombre"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger light" 
                                    data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            window.unidadSelectConfig = {
                indexUrl: @json(route('unidades.index')),
                storeUrl: @json(route('unidades.store')),
                updateUrlTemplate: @json(route('unidades.update', '__ID__')),
                destroyUrlTemplate: @json(route('unidades.destroy', '__ID__')),
                csrf: @json(csrf_token()),
            };
        </script>
        <script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/components/unidad-select.js') }}"></script>
    @endpush
@endonce
```

**Notas**:
- `@once` → el modal y los assets (`window.unidadSelectConfig`, los `<script>`) se emiten **una sola vez** aunque haya múltiples componentes en la página.
- El `<select class="unidad-select">` se inicializa automáticamente por el JS.
- Props: `name` (nombre del campo), `id` (HTML id; por defecto `$name`).

---

### 4. Módulo JS

**`public/js/components/unidad-select.js`**:

Estructura general (pseudocódigo):

```javascript
(function ($) {
    var config = window.unidadSelectConfig || {};
    var instances = [];
    var active = null;  // instancia activa (la que abrió el modal)
    
    // refs al modal compartido
    var $modal, modalObj, $form, $input, $error, $label;
    
    class UnidadInstance {
        init() {
            // Inicializa Select2, bindea botones (add/edit/delete)
            this.$select.select2({...});
            this.$control.on('click', '.btn-unidad-add', () => this.openModal('create'));
            this.$control.on('click', '.btn-unidad-edit', () => this.openModal('edit'));
            this.$control.on('click', '.btn-unidad-delete', () => this.eliminar());
            this.reload();
        }
        
        reload(selectNombre) {
            // GET /unidades → puebla nombreToId + repuebla <select>
            // Conserva la selección actual (o força una si es la instancia activa)
        }
        
        setValue(nombre) {
            // Selecciona por nombre; si no existe pero hay datos heredados, añade opción temporal
        }
        
        openModal(mode) {
            // Setea this.editingId, prellenado, label; abre el modal
            active = this;
            modalObj.show();
        }
        
        eliminar() {
            // confirmDelete → DELETE /unidades/{id} → reload()
        }
    }
    
    function reloadAll(selectNombreActiva) {
        // Recarga TODOS los selects en paralelo
        // Solo la instancia activa fuerza una selección concreta
        return $.when.apply($, instances.map(i => 
            i.reload(i === active ? selectNombreActiva : undefined)
        ));
    }
    
    function save() {
        // POST /unidades (o PUT si editing)
        // En .done: close modal, reloadAll(), toast
    }
    
    $(function () {
        // 1. Cachea referencias al modal + forma
        $modal = $('#unidadModal');
        $form = $('#unidad-form');
        $input = $('#unidad_nombre');
        $error = $form.find('[data-error-for="unidad_nombre"]');
        $label = $('#unidadModalLabel');
        modalObj = bootstrap.Modal.getOrCreateInstance($modal[0]);
        
        // 2. Bindea eventos del modal
        $form.on('submit', e => { e.preventDefault(); save(); });
        $modal.on('shown.bs.modal', () => $input.focus());
        $modal.on('hidden.bs.modal', () => {
            // Restaura modal-open si hay otro modal abierto debajo
            if ($('.modal.show').length) {
                document.body.classList.add('modal-open');
            }
        });
        
        // 3. Instancia todos los .unidad-select de la página
        $('.unidad-select').each(function () {
            let instance = new UnidadInstance($(this));
            instances.push(instance);
            instance.init();
        });
    });
    
    window.UnidadSelect = {
        get: (idOrEl) => {
            // Devuelve instancia por id o elemento DOM
            let el = typeof idOrEl === 'string' ? 
                document.getElementById(idOrEl) : idOrEl;
            return instances.find(i => i.$select[0] === el) || null;
        },
        instances: instances,
    };
})(jQuery);
```

---

## Adaptar para una tabla diferente (p. ej. Cliente)

### Paso 1: Backend (igual que Unidad)
1. Crea migración `unidades_table.php` → tabla `clientes` (o renombra)
2. Crea modelo `Cliente.php` con `BelongsToTenant`
3. Crea `ClienteController.php` con los 4 métodos (index/store/update/destroy)
4. Registra rutas en `web.php`

### Paso 2: CSS
Copia `public/css/unidad-select.css` → `public/css/cliente-select.css` (el contenido es idéntico, solo cambia el nombre de clase `.cliente-control` en lugar de `.unidad-control`).

### Paso 3: Componente Blade
Copia `resources/views/components/unidad-select.blade.php` → `resources/views/components/cliente-select.blade.php`.

**Cambios**:
- Reemplaza `unidad` → `cliente` en:
  - ID del modal (`#clienteModal`)
  - Campos del form (`#cliente_nombre`, `data-error-for="cliente_nombre"`)
  - Config global (`window.clienteSelectConfig`)
  - Rutas (`route('clientes.*')`)
  - Class del select (`.cliente-select`)
  - Class del container (`.cliente-control`)

### Paso 4: Módulo JS
Copia `public/js/components/unidad-select.js` → `public/js/components/cliente-select.js`.

**Cambios**:
- Reemplaza `unidadSelect` → `clienteSelect` en:
  - Variable de config (`window.clienteSelectConfig`)
  - Selector del modal (`$modal = $('#clienteModal')`)
  - Selector del form/input (`$form = $('#cliente-form')`, etc.)
  - Clase del select (`.cliente-select`)
  - Función global (`window.ClienteSelect = {...}`)

---

## Uso en vistas

### Una sola instancia
```blade
<div class="col-md-6">
    <label for="cliente" class="form-label">Cliente</label>
    <x-cliente-select name="cliente" id="cliente" />
</div>
```

### Múltiples instancias (p. ej. líneas de factura)
```blade
@foreach ($factura->lineas as $index => $linea)
    <div class="row">
        <div class="col-md-4">
            <x-cliente-select name="lineas[{{ $index }}][cliente_id]" />
        </div>
    </div>
@endforeach
```

Todas comparten el modal `#clienteModal` y la config `window.clienteSelectConfig` (emitidos una sola vez por `@once`). El JS automáticamente:
1. Crea una instancia por `<select class="cliente-select">` encontrado en el DOM
2. Mantiene un array `window.ClienteSelect.instances`
3. Cuando se guarda en el modal, recarga todos los selects en paralelo

---

## API pública

```javascript
// Obtener instancia por ID o elemento
let instance = window.UnidadSelect.get('unidad');
let instance = window.UnidadSelect.get(domElement);

// Métodos de instancia
instance.setValue('kg');        // Selecciona por nombre
instance.clear();               // Limpia la selección
instance.reload();              // Recarga desde backend
instance.openModal('create');   // Abre modal (uso interno)

// Acceso directo a todas las instancias
window.UnidadSelect.instances    // Array de UnidadInstance
```

---

## Variante por FK: guardar el `id` en vez del `nombre` (`<x-categoria-select>`)

`<x-unidad-select>` guarda el **nombre** como valor del `<option>` (el artículo persiste
`unidad` como texto libre). Cuando el select alimenta una **relación real** (FK), el valor del
`<option>` debe ser el **id** del catálogo. Caso vivo: `<x-categoria-select>` →
`articulos.categoria_id` (fk → `categorias_articulo`).

Diferencias respecto al caso base (mismo esqueleto Blade/CSS/JS):

- **Migración**: además de la tabla `categorias_articulo` (id + nombre, único por tenant), se
  añade `categoria_id` nullable en `articulos` con `constrained()->nullOnDelete()`.
- **JS** (`categoria-select.js`): la `<option>` se construye con `new Option(nombre, id)` y el
  módulo mantiene `idToNombre` (en vez de `nombreToId`). `currentId()`/`setValue(id)` operan sobre
  el id; no hay soporte de "datos heredados de texto libre" (una FK siempre apunta a un id válido
  o es `null`).
- **Request**: `categoria_id` se valida con `Rule::exists('categorias_articulo','id')->where('tenant_id', ...)`
  en vez de `Rule::unique`.
- **Listado JSON del padre**: expone `categoria_id` (para preseleccionar el select al editar) y,
  si hace falta mostrarlo, `categoria_nombre` vía eager load (`with('categoria:id,nombre')`).

Cuándo usar cada variante: **por nombre** para atributos-etiqueta que pueden ser texto libre
(unidad); **por id** cuando el dato es una relación de negocio con integridad referencial.

---

## Características

✅ **Multi-instancia**: un modal compartido, varias instancias independientes  
✅ **Sincronización**: todas las instancias se recargan simultáneamente tras CRUD  
✅ **Datos heredados**: soporta valores de texto libre (no en catálogo)  
✅ **Tenant-scoped**: cada tenant ve su propio catálogo (vía `BelongsToTenant`)  
✅ **Modales apilados**: funciona dentro de otro modal (p. ej. modal de artículo)  
✅ **Accesible**: soporte para Select2, validación inline, mensajes de error  
✅ **Reutilizable**: patrones idénticos para cualquier tabla simple (id + nombre)

---

## Limitaciones y futuros mejoras

- **Campos adicionales**: si `Cliente` tuviera más campos (email, teléfono), el modal actual solo soporta `nombre`. Solución: pasar el nombre del componente al JS y cargar templates dinámicos.
- **Validación custom**: ahora valida `nombre` único por tenant. Para reglas más complejas, pasar reglas via config.
- **Paginación**: el `reload()` carga todo; para catálogos muy grandes, usar AJAX paginado + búsqueda.

