<?php

namespace App\Support;

class Haversine
{
    /** Radio terrestre medio en metros. */
    private const RADIO_TIERRA_METROS = 6_371_000;

    public static function metros(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round(self::RADIO_TIERRA_METROS * $c);
    }
}
