<?php

namespace App\Ia\Tools;

use App\Models\Cliente;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: edita datos de contacto de un cliente existente (US3).
 */
class EditarCliente extends ToolAsistente
{
    public function nombre(): string
    {
        return 'editar_cliente';
    }

    public function descripcion(): string
    {
        return 'Edita los datos de contacto de un cliente existente (email, teléfono, ciudad, nombre, razón social). Requiere confirmación del usuario.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID del cliente a editar.'],
                'nombre' => ['type' => 'string'],
                'razon_social' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'telefono' => ['type' => 'string'],
                'ciudad' => ['type' => 'string'],
            ],
            'required' => ['id'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-clientes';
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $cliente = Cliente::query()->find($parametros['id'] ?? 0);

        if ($cliente === null) {
            throw ValidationException::withMessages(['id' => 'No existe un cliente con ese ID.']);
        }

        $cambios = array_intersect_key($parametros, array_flip(['nombre', 'razon_social', 'email', 'telefono', 'ciudad']));
        $cambios = array_filter($cambios, fn ($v) => $v !== null && $v !== '');

        if ($cambios === []) {
            throw ValidationException::withMessages(['cliente' => 'No indicaste ningún cambio.']);
        }

        $etiqueta = $cliente->razon_social ?: $cliente->nombre;
        $listado = implode(', ', array_keys($cambios));
        $resumen = "Editar cliente «{$etiqueta}» (#{$cliente->id}): actualizar {$listado}";

        return ['resumen' => $resumen, 'parametros' => ['id' => $cliente->id] + $cambios];
    }

    public function ejecutar(array $parametros): array
    {
        $cliente = Cliente::query()->findOrFail($parametros['id']);
        $cliente->update(array_intersect_key($parametros, array_flip(['nombre', 'razon_social', 'email', 'telefono', 'ciudad'])));

        return [
            'id' => $cliente->id,
            'mensaje' => 'Cliente actualizado correctamente.',
            'url' => route('clientes.index'),
            'descripcion' => "Editó el cliente «{$cliente->nombre}» (#{$cliente->id})",
        ];
    }
}
