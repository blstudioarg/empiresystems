<?php

namespace App\Ia\Tools;

use App\Models\Presupuesto;

/**
 * Tool de lectura: busca presupuestos del tenant (US2).
 */
class BuscarPresupuestos extends ToolAsistente
{
    public function nombre(): string
    {
        return 'buscar_presupuestos';
    }

    public function descripcion(): string
    {
        return 'Busca presupuestos por número o nombre del receptor, y opcionalmente por estado (borrador, enviado, aceptado, rechazado, caducado, facturado).';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto' => [
                    'type' => 'string',
                    'description' => 'Texto a buscar en número o nombre del receptor.',
                ],
                'estado' => [
                    'type' => 'string',
                    'enum' => ['borrador', 'enviado', 'aceptado', 'rechazado', 'caducado', 'facturado'],
                ],
            ],
            'required' => [],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-presupuestos';
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

        $query = Presupuesto::query()->latest('id');

        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('numero', 'like', "%{$texto}%")
                    ->orWhere('receptor_nombre', 'like', "%{$texto}%");
            });
        }

        if ($estado) {
            $query->where('estado', $estado);
        }

        $presupuestos = $query->limit($limite)->get();

        return [
            'total' => $presupuestos->count(),
            'presupuestos' => $presupuestos->map(fn (Presupuesto $p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'estado' => $p->estado->value,
                'receptor' => $p->receptor_nombre,
                'fecha' => optional($p->fecha_emision)->format('Y-m-d'),
                'total' => $p->total,
            ])->all(),
        ];
    }
}
