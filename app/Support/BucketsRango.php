<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Divide un {@see RangoFechas} en sub-periodos ("buckets") para series temporales y comparativos:
 * un bucket por día en rangos cortos, o por mes (recortado a los límites del rango) en rangos
 * largos. Extraído de `DashboardEstadisticas` (research.md D6 de la feature 033) para que el
 * informe comercial reutilice la misma lógica sin duplicarla; el dashboard financiero sigue
 * consumiéndolo a través de este mismo soporte, sin cambio de comportamiento.
 */
class BucketsRango
{
    /**
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string}>
     */
    public static function bucketsDelRango(RangoFechas $rango): array
    {
        return $rango->granularidad() === 'dia'
            ? self::bucketsDiarios($rango)
            : self::bucketsMensuales($rango);
    }

    /**
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string}>
     */
    public static function bucketsDiarios(RangoFechas $rango): array
    {
        $buckets = [];
        $cursor = $rango->desde->copy();

        while ($cursor->lte($rango->hasta)) {
            $buckets[] = [
                'inicio' => $cursor->copy(),
                'fin' => $cursor->copy(),
                'etiqueta' => $cursor->translatedFormat('d M'),
            ];
            $cursor->addDay();
        }

        return $buckets;
    }

    /**
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string}>
     */
    public static function bucketsMensuales(RangoFechas $rango): array
    {
        $buckets = [];
        $cursor = $rango->desde->copy()->startOfMonth();

        while ($cursor->lte($rango->hasta)) {
            $inicio = $cursor->copy()->max($rango->desde);
            $fin = $cursor->copy()->endOfMonth()->min($rango->hasta);

            $buckets[] = [
                'inicio' => $inicio,
                'fin' => $fin,
                'etiqueta' => $cursor->translatedFormat('M Y'),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }
}
