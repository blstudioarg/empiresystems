# Contrato de datos — home del panel de Super Admin

Forma exacta del array que devuelve `App\Services\EstadisticasTenants::resumen()` y que
`SuperAdmin\PanelController@index` pasa a la vista como `datos`. Es el contrato que verifican los
tests de US2/US3.

```php
[
    'totales' => [
        'tenants'    => 12,   // int, todos los tenants
        'activos'    => 9,    // int
        'inactivos'  => 3,    // int  (activos + inactivos == tenants)
        'altas_mes'  => 2,    // int, alta dentro del mes natural en curso
        'usuarios'   => 47,   // int, usuarios con tenant_id no nulo (el super admin no cuenta)
    ],

    // Exactamente 12 elementos, del mes más antiguo al actual. Meses sin altas => valor 0.
    'serie_altas' => [
        ['etiqueta' => 'ago 25', 'valor' => 0],
        // …
        ['etiqueta' => 'jul 26', 'valor' => 2],
    ],

    // Máximo 5, orden: created_at descendente.
    'ultimos_tenants' => [
        [
            'id'          => 12,
            'nombre'      => 'BL Studio',
            'dominio'     => 'blstudio.localhost',   // string|null si no tiene dominio registrado
            'activo'      => true,
            'alta'        => '21/07/2026',           // ya formateada para la vista
            'gestion_url' => 'http://localhost/super_admin/tenants',
        ],
    ],

    // Máximo 5, orden: documentos desc, luego usuarios desc.
    'ranking_tamano' => [
        ['id' => 3, 'nombre' => 'Empire Demo', 'usuarios' => 8, 'documentos' => 154],
    ],

    // Sin límite fijo; vacío si ningún tenant cumple criterios.
    'atencion' => [
        [
            'id'          => 7,
            'nombre'      => 'Tenant Parado',
            'motivos'     => ['desactivado', 'sin_actividad'],   // 1..3 motivos
            'gestion_url' => 'http://localhost/super_admin/tenants',
        ],
    ],
]
```

## Motivos de atención

| Clave | Criterio | Etiqueta en la vista |
|---|---|---|
| `desactivado` | `tenants.activo = false` | "Desactivado" |
| `sin_usuarios` | ningún `users` del tenant con `estado = aprobado` y `activo = true` | "Sin usuarios que puedan entrar" |
| `sin_actividad` | ningún `logs_actividad` del tenant en los últimos 30 días | "Sin actividad en 30 días" |

## Invariantes verificables por test

- `totales.activos + totales.inactivos === totales.tenants`.
- `count(serie_altas) === 12` siempre, incluso sin ningún tenant.
- `array_sum(column(serie_altas, 'valor'))` = número de tenants creados en los últimos 12 meses.
- Con cero tenants: todos los totales a 0, `serie_altas` con 12 ceros, las tres listas vacías → la
  vista renderiza el estado vacío de FR-015 (con acción "Crear el primer tenant").
- El array **no contiene** ningún dato de negocio de un tenant más allá de recuentos (FR-019).

## Contrato de vista (`resources/views/super_admin/panel/index.blade.php`)

- Extiende `layouts.app` (mismo layout que `super_admin/tenants/index.blade.php`).
- 5 tarjetas de métricas siguiendo "Icono flotante en cards informativas": `<x-lordicon>` dentro de
  un `<div>` **sin clases**, `size="50"`, `trigger="hover"`, `target=".card"`.
- Gráfico: `<canvas id="chart-altas-tenants">` + `public/js/plugins-init/super-admin-panel.init.js`,
  que lee la serie desde `window.panelSuperAdminState` (mismo patrón `window.<algo>State` que el
  resto de inits del proyecto) y crea un `new Chart(...)` de barras.
- Widgets de resumen: `<table class="table table-borderless mb-0">` + `@foreach`, como
  `partials/dashboard-contenido.blade.php` (excepción documentada a la regla de DataTable).
- Declara `@section('ayuda-titulo', 'Panel de Super Admin')` y
  `@section('ayuda') @include('ayuda.super-admin-panel') @endsection`.
- Cada fila de `ultimos_tenants` / `atencion` enlaza a `gestion_url` (≤2 clics, SC-007).
