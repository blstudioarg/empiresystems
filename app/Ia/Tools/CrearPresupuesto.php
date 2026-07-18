<?php

namespace App\Ia\Tools;

use App\Ia\Tools\Concerns\ResuelveLineasDocumento;
use App\Services\RegistroPresupuesto;

/**
 * Tool de escritura: crea un presupuesto en borrador (US3), vía RegistroPresupuesto (importes
 * calculados por el servidor).
 */
class CrearPresupuesto extends ToolAsistente
{
    use ResuelveLineasDocumento;

    public function nombre(): string
    {
        return 'crear_presupuesto';
    }

    public function descripcion(): string
    {
        return 'Crea un presupuesto en borrador para un cliente, con líneas de artículos. Los importes los calcula el sistema. Requiere confirmación del usuario.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cliente_id' => ['type' => 'integer'],
                'lineas' => $this->schemaLineas(),
            ],
            'required' => ['cliente_id', 'lineas'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-presupuestos';
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $cliente = $this->resolverCliente($parametros['cliente_id'] ?? null);
        $lineas = $this->resolverLineas($parametros['lineas'] ?? []);

        $etiqueta = $cliente->razon_social ?: $cliente->nombre;
        $resumen = "Crear presupuesto para «{$etiqueta}»: ".$this->resumenLineas($lineas);

        return ['resumen' => $resumen, 'parametros' => ['cliente_id' => $cliente->id, 'lineas' => $lineas]];
    }

    public function ejecutar(array $parametros): array
    {
        $datos = [
            'cliente_id' => $parametros['cliente_id'],
            'fecha_emision' => now()->toDateString(),
            'lineas' => $parametros['lineas'],
        ];

        $presupuesto = app(RegistroPresupuesto::class)->guardar($datos);

        return [
            'id' => $presupuesto->id,
            'mensaje' => 'Presupuesto creado en borrador.',
            'url' => route('presupuestos.index'),
            'descripcion' => "Creó el presupuesto {$presupuesto->numero} (#{$presupuesto->id})",
        ];
    }
}
