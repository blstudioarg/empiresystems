<?php

namespace App\Ia\Tools;

use App\Models\Articulo;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: edita un artículo existente (US3).
 */
class EditarArticulo extends ToolAsistente
{
    public function nombre(): string
    {
        return 'editar_articulo';
    }

    public function descripcion(): string
    {
        return 'Edita nombre, precio, tipo impositivo o unidad de un artículo existente. Requiere confirmación del usuario.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'nombre' => ['type' => 'string'],
                'precio' => ['type' => 'number'],
                'tipo_impositivo' => ['type' => 'number'],
                'unidad' => ['type' => 'string'],
            ],
            'required' => ['id'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-articulos';
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $articulo = Articulo::query()->find($parametros['id'] ?? 0);

        if ($articulo === null) {
            throw ValidationException::withMessages(['id' => 'No existe un artículo con ese ID.']);
        }

        $cambios = array_intersect_key($parametros, array_flip(['nombre', 'precio', 'tipo_impositivo', 'unidad']));
        $cambios = array_filter($cambios, fn ($v) => $v !== null && $v !== '');

        if ($cambios === []) {
            throw ValidationException::withMessages(['articulo' => 'No indicaste ningún cambio.']);
        }

        $listado = implode(', ', array_keys($cambios));
        $resumen = "Editar artículo «{$articulo->nombre}» (#{$articulo->id}): actualizar {$listado}";

        return ['resumen' => $resumen, 'parametros' => ['id' => $articulo->id] + $cambios];
    }

    public function ejecutar(array $parametros): array
    {
        $articulo = Articulo::query()->findOrFail($parametros['id']);
        $articulo->update(array_intersect_key($parametros, array_flip(['nombre', 'precio', 'tipo_impositivo', 'unidad'])));

        return [
            'id' => $articulo->id,
            'mensaje' => 'Artículo actualizado correctamente.',
            'url' => route('articulos.index'),
            'descripcion' => "Editó el artículo «{$articulo->nombre}» (#{$articulo->id})",
        ];
    }
}
