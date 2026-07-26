<?php

namespace App\Ia\Tools;

use App\Ia\Tools\Concerns\ResuelveLineasDocumento;
use App\Services\RegistroFacturaBorrador;

/**
 * Tool de escritura: crea una factura EN BORRADOR (US3). Nunca emite (bloqueo estructural).
 * Importes vía RegistroFacturaBorrador → CalculadoraFactura (Principio III).
 */
class CrearFacturaBorrador extends ToolAsistente
{
    use ResuelveLineasDocumento;

    public function nombre(): string
    {
        return 'crear_factura_borrador';
    }

    public function descripcion(): string
    {
        return 'Crea una factura EN BORRADOR para un cliente, con líneas de artículos. No la emite: queda en borrador para que el usuario la revise y emita manualmente. Los importes los calcula el sistema. Requiere confirmación del usuario.';
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
        // Crear-vía-IA es la acción de la vista "Crear factura" (doc 09, Cambio 2).
        // BuscarFacturas/EditarFacturaBorrador siguen con ver-facturas.
        return 'ver-facturas-crear';
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
        $resumen = "Crear factura EN BORRADOR para «{$etiqueta}»: ".$this->resumenLineas($lineas);

        return ['resumen' => $resumen, 'parametros' => ['cliente_id' => $cliente->id, 'lineas' => $lineas]];
    }

    public function ejecutar(array $parametros): array
    {
        $cliente = $this->resolverCliente($parametros['cliente_id']);
        $factura = app(RegistroFacturaBorrador::class)->crear($cliente, $parametros['lineas']);

        return [
            'id' => $factura->id,
            'mensaje' => 'Factura creada en borrador.',
            'url' => route('facturas.index'),
            'descripcion' => "Creó la factura en borrador #{$factura->id}",
        ];
    }
}
