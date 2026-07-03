<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\Serie;
use Illuminate\Support\Facades\DB;

class NumeradorFacturas
{
    /**
     * Asigna el siguiente número correlativo de la serie para el año de `$fecha`, derivándolo de
     * `MAX(numero)` de las facturas ya emitidas de esa serie en ese año (fuente de verdad), bajo
     * bloqueo de fila de la serie para serializar emisiones concurrentes.
     *
     * @return array{numero: int, numeroCompleto: string}
     */
    public function siguienteNumero(Serie $serie, \DateTimeInterface $fecha): array
    {
        return DB::transaction(function () use ($serie, $fecha) {
            $serieBloqueada = Serie::where('id', $serie->id)->lockForUpdate()->first();

            $anio = (int) $fecha->format('Y');

            $ultimoNumero = Factura::where('serie_id', $serieBloqueada->id)
                ->whereYear('fecha_expedicion', $anio)
                ->whereNotNull('numero')
                ->max('numero');

            $numero = ($ultimoNumero ?? 0) + 1;
            $numeroCompleto = $this->formatear($serieBloqueada, $anio, $numero);

            $serieBloqueada->proximo_numero = $numero + 1;
            $serieBloqueada->save();

            return ['numero' => $numero, 'numeroCompleto' => $numeroCompleto];
        });
    }

    private function formatear(Serie $serie, int $anio, int $numero): string
    {
        return str_replace(
            ['{serie}', '{anio}', '{numero:0000}'],
            [$serie->codigo, (string) $anio, str_pad((string) $numero, 4, '0', STR_PAD_LEFT)],
            $serie->formato,
        );
    }
}
