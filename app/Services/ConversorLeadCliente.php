<?php

namespace App\Services;

use App\Enums\EstadoLead;
use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de conversión de un lead a cliente, conservando trazabilidad
 * (`convertido_a_cliente_id`, FR-009). No fusiona con un cliente existente aunque comparta NIF:
 * el controlador debe advertir antes de llamar aquí (ver {@see clienteConNif}).
 */
class ConversorLeadCliente
{
    /**
     * @param  array<string, mixed>  $datosCliente
     */
    public function convertir(Lead $lead, array $datosCliente = []): Cliente
    {
        return DB::transaction(function () use ($lead, $datosCliente) {
            $cliente = Cliente::create([
                'tenant_id' => $lead->tenant_id,
                'tipo' => $datosCliente['tipo'] ?? TipoCliente::Particular->value,
                'nombre' => $datosCliente['nombre'] ?? $lead->nombre,
                'razon_social' => $datosCliente['razon_social'] ?? $lead->empresa,
                'nif' => $datosCliente['nif'] ?? null,
                'direccion' => $datosCliente['direccion'] ?? null,
                'cp' => $datosCliente['cp'] ?? null,
                'ciudad' => $datosCliente['ciudad'] ?? null,
                'provincia' => $datosCliente['provincia'] ?? null,
                'pais' => $datosCliente['pais'] ?? 'ES',
                'email' => $lead->email,
                'telefono' => $lead->telefono,
            ]);

            $lead->update([
                'estado' => EstadoLead::Convertido,
                'convertido_a_cliente_id' => $cliente->id,
            ]);

            return $cliente;
        });
    }

    public function clienteConNif(?string $nif, int $tenantId): ?Cliente
    {
        if (! $nif) {
            return null;
        }

        return Cliente::where('tenant_id', $tenantId)->where('nif', $nif)->first();
    }
}
