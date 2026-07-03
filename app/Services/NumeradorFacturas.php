<?php

namespace App\Services;

use App\Models\Serie;
use Illuminate\Support\Facades\DB;

class NumeradorFacturas
{
    /**
     * @return array{numero: int, numeroCompleto: string}
     */
    public function siguienteNumero(Serie $serie): array
    {
        return DB::transaction(function () use ($serie) {
            $serieBloqueada = Serie::where('id', $serie->id)->lockForUpdate()->first();

            $numero = $serieBloqueada->proximo_numero;
            $numeroCompleto = $this->formatear($serieBloqueada, $numero);

            $serieBloqueada->proximo_numero = $numero + 1;
            $serieBloqueada->save();

            return ['numero' => $numero, 'numeroCompleto' => $numeroCompleto];
        });
    }

    private function formatear(Serie $serie, int $numero): string
    {
        return str_replace(
            ['{serie}', '{anio}', '{numero:0000}'],
            [$serie->codigo, (string) ($serie->ejercicio ?? now()->year), str_pad((string) $numero, 4, '0', STR_PAD_LEFT)],
            $serie->formato,
        );
    }
}
