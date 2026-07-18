<?php

namespace App\Ia\Tools;

use App\Models\Cliente;

/**
 * Tool de lectura: busca clientes del tenant activo (US2). Opera bajo el TenantScope del request;
 * no acepta tenant_id (contrato de seguridad #1).
 */
class BuscarClientes extends ToolAsistente
{
    public function nombre(): string
    {
        return 'buscar_clientes';
    }

    public function descripcion(): string
    {
        return 'Busca clientes del negocio por nombre, razón social, NIF o email. Úsala cuando el usuario pregunte por uno o varios clientes concretos o quiera un listado acotado.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => [
                    'type' => 'string',
                    'description' => 'Texto a buscar en nombre, razón social, NIF o email. Vacío para traer los más recientes.',
                ],
            ],
            'required' => [],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-clientes';
    }

    public function esLectura(): bool
    {
        return true;
    }

    public function ejecutar(array $parametros): array
    {
        $texto = trim((string) ($parametros['texto'] ?? ''));
        $limite = (int) config('ia.max_resultados_tool', 10);

        $query = Cliente::query()->latest('id');

        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('razon_social', 'like', "%{$texto}%")
                    ->orWhere('nif', 'like', "%{$texto}%")
                    ->orWhere('email', 'like', "%{$texto}%");
            });
        }

        $clientes = $query->limit($limite)->get(['id', 'nombre', 'razon_social', 'nif', 'email', 'telefono', 'ciudad']);

        return [
            'total' => $clientes->count(),
            'clientes' => $clientes->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->razon_social ?: $c->nombre,
                'nif' => $c->nif,
                'email' => $c->email,
                'telefono' => $c->telefono,
                'ciudad' => $c->ciudad,
            ])->all(),
        ];
    }
}
