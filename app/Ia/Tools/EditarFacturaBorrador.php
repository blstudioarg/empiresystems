<?php

namespace App\Ia\Tools;

use App\Enums\EstadoFactura;
use App\Ia\Tools\Concerns\ResuelveLineasDocumento;
use App\Models\Factura;
use App\Services\RegistroFacturaBorrador;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: reemplaza las líneas de una factura que sigue EN BORRADOR (US3). Aborta si la
 * factura ya no está en borrador (inmutabilidad de emitidas, Principio II).
 */
class EditarFacturaBorrador extends ToolAsistente
{
    use ResuelveLineasDocumento;

    public function nombre(): string
    {
        return 'editar_factura_borrador';
    }

    public function descripcion(): string
    {
        return 'Reemplaza las líneas de una factura que todavía está en borrador. No funciona sobre facturas ya emitidas. Requiere confirmación del usuario.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID de la factura en borrador.'],
                'lineas' => $this->schemaLineas(),
            ],
            'required' => ['id', 'lineas'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-facturas';
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $factura = Factura::query()->find($parametros['id'] ?? 0);

        if ($factura === null) {
            throw ValidationException::withMessages(['id' => 'No existe una factura con ese ID.']);
        }

        if ($factura->estado !== EstadoFactura::Borrador) {
            throw ValidationException::withMessages(['id' => 'Esa factura ya no está en borrador; no se puede editar.']);
        }

        $lineas = $this->resolverLineas($parametros['lineas'] ?? []);
        $resumen = "Editar factura en borrador #{$factura->id}: nuevas líneas → ".$this->resumenLineas($lineas);

        return ['resumen' => $resumen, 'parametros' => ['id' => $factura->id, 'lineas' => $lineas]];
    }

    public function ejecutar(array $parametros): array
    {
        $factura = Factura::query()->findOrFail($parametros['id']);

        if ($factura->estado !== EstadoFactura::Borrador) {
            throw ValidationException::withMessages(['id' => 'Esa factura ya no está en borrador.']);
        }

        $factura = app(RegistroFacturaBorrador::class)->crear($factura->cliente, $parametros['lineas'], $factura);

        return [
            'id' => $factura->id,
            'mensaje' => 'Factura en borrador actualizada.',
            'url' => route('facturas.index'),
            'descripcion' => "Editó la factura en borrador #{$factura->id}",
        ];
    }
}
