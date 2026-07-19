<?php

namespace App\Excel\Definiciones;

use App\Enums\EntidadLogActividad;
use App\Enums\TipoArticulo;
use App\Excel\ColumnaExcel;
use App\Excel\Concerns\NormalizaValores;
use App\Excel\Concerns\OrdenaPorIds;
use App\Excel\DefinicionExportable;
use App\Excel\DefinicionImportable;
use App\Excel\FormatoCelda;
use App\Http\Requests\StoreArticuloRequest;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Artículos: exportable + importable (data-model.md §3.2).
 */
class DefinicionArticulos implements DefinicionExportable, DefinicionImportable
{
    use NormalizaValores, OrdenaPorIds;

    public function modulo(): string
    {
        return 'articulos';
    }

    public function etiquetaModulo(): string
    {
        return 'artículos';
    }

    public function permiso(): string
    {
        return 'ver-articulos';
    }

    public function entidadLog(): EntidadLogActividad
    {
        return EntidadLogActividad::Articulo;
    }

    public function columnas(): array
    {
        return [
            new ColumnaExcel(
                clave: 'tipo',
                etiqueta: 'Tipo',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->tipo === TipoArticulo::Producto ? 'Producto' : 'Servicio',
                importar: function (mixed $valor): string {
                    $texto = mb_strtolower(trim((string) ($valor ?? '')));

                    return $texto === 'servicio' ? TipoArticulo::Servicio->value : TipoArticulo::Producto->value;
                },
                ejemplo: 'Producto',
            ),
            new ColumnaExcel(
                clave: 'sku',
                etiqueta: 'SKU',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->sku,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'SKU-0001',
            ),
            new ColumnaExcel(
                clave: 'nombre',
                etiqueta: 'Nombre',
                obligatoria: true,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->nombre,
                importar: fn (mixed $v) => trim((string) ($v ?? '')),
                ejemplo: 'Silla de oficina',
            ),
            new ColumnaExcel(
                clave: 'descripcion',
                etiqueta: 'Descripción',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->descripcion,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: null,
            ),
            new ColumnaExcel(
                clave: 'unidad',
                etiqueta: 'Unidad',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->unidad,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: 'ud',
            ),
            new ColumnaExcel(
                clave: 'categoria',
                etiqueta: 'Categoría',
                obligatoria: false,
                formato: FormatoCelda::Texto,
                exportar: fn (Articulo $a) => $a->categoria?->nombre,
                importar: fn (mixed $v) => self::texto($v),
                ejemplo: null,
            ),
            new ColumnaExcel(
                clave: 'precio',
                etiqueta: 'Precio',
                obligatoria: true,
                formato: FormatoCelda::Importe,
                exportar: fn (Articulo $a) => (float) $a->precio,
                importar: fn (mixed $v) => is_numeric($v) ? (float) $v : $v,
                ejemplo: '19.90',
            ),
            new ColumnaExcel(
                clave: 'tipo_impositivo',
                etiqueta: 'Tipo impositivo (%)',
                obligatoria: true,
                formato: FormatoCelda::Numero,
                exportar: fn (Articulo $a) => (float) $a->tipo_impositivo,
                importar: fn (mixed $v) => is_numeric($v) ? (float) $v : $v,
                ejemplo: '21',
            ),
            new ColumnaExcel(
                clave: 'gestion_stock',
                etiqueta: 'Gestión de stock',
                obligatoria: false,
                formato: FormatoCelda::Booleano,
                exportar: fn (Articulo $a) => $a->gestion_stock ? 'Sí' : 'No',
                importar: fn (mixed $v) => self::booleano($v),
                ejemplo: 'No',
            ),
            new ColumnaExcel(
                clave: 'stock_actual',
                etiqueta: 'Stock actual',
                obligatoria: false,
                formato: FormatoCelda::Numero,
                exportar: fn (Articulo $a) => $a->stock_actual !== null ? (float) $a->stock_actual : null,
                importar: fn (mixed $v) => is_numeric($v) ? (float) $v : $v,
                ejemplo: null,
            ),
            new ColumnaExcel(
                clave: 'stock_minimo',
                etiqueta: 'Stock mínimo',
                obligatoria: false,
                formato: FormatoCelda::Numero,
                exportar: fn (Articulo $a) => $a->stock_minimo !== null ? (float) $a->stock_minimo : null,
                importar: fn (mixed $v) => is_numeric($v) ? (float) $v : $v,
                ejemplo: null,
            ),
            new ColumnaExcel(
                clave: 'aplica_recargo_equivalencia',
                etiqueta: 'Recargo de equivalencia',
                obligatoria: false,
                formato: FormatoCelda::Booleano,
                exportar: fn (Articulo $a) => $a->aplica_recargo_equivalencia ? 'Sí' : 'No',
                importar: fn (mixed $v) => self::booleano($v),
                ejemplo: 'No',
            ),
            new ColumnaExcel(
                clave: 'activo',
                etiqueta: 'Activo',
                obligatoria: false,
                formato: FormatoCelda::Booleano,
                exportar: fn (Articulo $a) => $a->activo ? 'Sí' : 'No',
                importar: fn (mixed $v) => self::texto($v) === null ? true : self::booleano($v),
                ejemplo: 'Sí',
            ),
        ];
    }

    public function consultaExportacion(array $ids): Builder
    {
        return $this->ordenarPorIds(Articulo::with('categoria:id,nombre')->whereIn('id', $ids), $ids);
    }

    public function validador(array $fila): ValidatorContract
    {
        $request = StoreArticuloRequest::create('/', 'POST', $fila);
        $rules = $request->rules();

        // El fichero trae el nombre de la categoría, no su id interno (data-model.md §3.2): la
        // regla `categoria_id` de StoreArticuloRequest no aplica tal cual, se sustituye por una
        // que valida el nombre dentro del tenant. Si no existe, se rechaza la fila — nunca se crea
        // la categoría al vuelo (edge case explícito del spec).
        unset($rules['categoria_id']);
        $rules['categoria'] = [
            'nullable',
            Rule::exists('categorias_articulo', 'nombre')->where('tenant_id', tenant()->getTenantKey()),
        ];
        $rules['activo'] = ['boolean'];

        $mensajes = [...$request->messages(), 'categoria.exists' => 'La categoría indicada no existe.'];
        $atributos = [...$request->attributes(), 'categoria' => 'categoría'];

        return Validator::make($fila, $rules, $mensajes, $atributos);
    }

    public function crear(array $datos, int $tenantId): Model
    {
        $categoriaId = null;

        if (! empty($datos['categoria'])) {
            $categoriaId = CategoriaArticulo::where('tenant_id', $tenantId)
                ->where('nombre', $datos['categoria'])
                ->value('id');
        }

        return Articulo::create([
            ...$datos,
            'categoria_id' => $categoriaId,
            'tenant_id' => $tenantId,
        ]);
    }

    public function camposUnicos(): array
    {
        return [];
    }
}
