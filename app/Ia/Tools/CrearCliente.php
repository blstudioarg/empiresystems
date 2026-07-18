<?php

namespace App\Ia\Tools;

use App\Enums\TipoCliente;
use App\Models\Cliente;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: crea un cliente (US3). Dos fases con confirmación del usuario.
 */
class CrearCliente extends ToolAsistente
{
    public function nombre(): string
    {
        return 'crear_cliente';
    }

    public function descripcion(): string
    {
        return 'Crea un cliente nuevo (empresa o particular). Requiere confirmación del usuario antes de guardarse.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo' => ['type' => 'string', 'enum' => ['empresa', 'particular']],
                'nombre' => ['type' => 'string', 'description' => 'Nombre de contacto o del particular.'],
                'razon_social' => ['type' => 'string', 'description' => 'Razón social (obligatoria si es empresa).'],
                'nif' => ['type' => 'string', 'description' => 'NIF/CIF (obligatorio y único si es empresa).'],
                'email' => ['type' => 'string'],
                'telefono' => ['type' => 'string'],
                'ciudad' => ['type' => 'string'],
            ],
            'required' => ['tipo', 'nombre'],
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
        $tipo = $parametros['tipo'] ?? null;
        $nombre = trim((string) ($parametros['nombre'] ?? ''));

        if (! in_array($tipo, ['empresa', 'particular'], true) || $nombre === '') {
            throw ValidationException::withMessages(['cliente' => 'Faltan datos: tipo y nombre son obligatorios.']);
        }

        if ($tipo === 'empresa') {
            $razon = trim((string) ($parametros['razon_social'] ?? ''));
            $nif = trim((string) ($parametros['nif'] ?? ''));

            if ($razon === '' || $nif === '') {
                throw ValidationException::withMessages(['cliente' => 'Para una empresa hacen falta razón social y NIF.']);
            }

            if (Cliente::query()->where('nif', $nif)->exists()) {
                throw ValidationException::withMessages(['nif' => "Ya existe un cliente con el NIF {$nif}."]);
            }
        }

        $normalizados = [
            'tipo' => $tipo,
            'nombre' => $nombre,
            'razon_social' => $parametros['razon_social'] ?? null,
            'nif' => $parametros['nif'] ?? null,
            'email' => $parametros['email'] ?? null,
            'telefono' => $parametros['telefono'] ?? null,
            'ciudad' => $parametros['ciudad'] ?? null,
        ];

        $etiqueta = $normalizados['razon_social'] ?: $normalizados['nombre'];
        $resumen = "Crear cliente {$tipo} «{$etiqueta}»".($normalizados['nif'] ? " (NIF {$normalizados['nif']})" : '');

        return ['resumen' => $resumen, 'parametros' => $normalizados];
    }

    public function ejecutar(array $parametros): array
    {
        $cliente = Cliente::create([
            'tenant_id' => tenant()->id,
            'tipo' => TipoCliente::from($parametros['tipo']),
            'nombre' => $parametros['nombre'],
            'razon_social' => $parametros['razon_social'] ?? null,
            'nif' => $parametros['nif'] ?? null,
            'email' => $parametros['email'] ?? null,
            'telefono' => $parametros['telefono'] ?? null,
            'ciudad' => $parametros['ciudad'] ?? null,
            'pais' => 'ES',
        ]);

        return [
            'id' => $cliente->id,
            'mensaje' => 'Cliente creado correctamente.',
            'url' => route('clientes.index'),
            'descripcion' => "Creó el cliente «{$cliente->nombre}» (#{$cliente->id})",
        ];
    }
}
