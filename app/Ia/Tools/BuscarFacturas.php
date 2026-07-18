<?php

namespace App\Ia\Tools;

use App\Models\Factura;

/**
 * Tool de lectura: busca facturas del tenant (US2).
 */
class BuscarFacturas extends ToolAsistente
{
    public function nombre(): string
    {
        return 'buscar_facturas';
    }

    public function descripcion(): string
    {
        return 'Busca facturas por número, nombre de cliente o estado (borrador, emitida, pagada, vencida, anulada, rectificada). Úsala cuando el usuario pregunte por facturas concretas o quiera un listado acotado.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => [
                    'type' => 'string',
                    'description' => 'Texto a buscar en número o nombre de cliente.',
                ],
                'estado' => [
                    'type' => 'string',
                    'description' => 'Filtra por estado exacto.',
                    'enum' => ['borrador', 'emitida', 'pagada', 'vencida', 'anulada', 'rectificada'],
                ],
            ],
            'required' => [],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-facturas';
    }

    public function esLectura(): bool
    {
        return true;
    }

    public function ejecutar(array $parametros): array
    {
        $texto = trim((string) ($parametros['texto'] ?? ''));
        $estado = $parametros['estado'] ?? null;
        $limite = (int) config('ia.max_resultados_tool', 10);

        $query = Factura::query()->latest('id');

        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('numero_completo', 'like', "%{$texto}%")
                    ->orWhere('cliente_nombre', 'like', "%{$texto}%")
                    ->orWhere('cliente_razon_social', 'like', "%{$texto}%");
            });
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        $facturas = $query->limit($limite)->get();

        return [
            'total' => $facturas->count(),
            'facturas' => $facturas->map(fn (Factura $f) => [
                'id' => $f->id,
                'numero' => $f->numero_completo,
                'estado' => $f->estado->value,
                'cliente' => $f->cliente_razon_social ?: $f->cliente_nombre,
                'fecha' => optional($f->fecha_expedicion)->format('Y-m-d'),
                'total' => $f->total,
            ])->all(),
        ];
    }
}
