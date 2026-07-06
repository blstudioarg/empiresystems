<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifica un NIF-IVA intracomunitario contra el servicio REST de VIES (Comisión Europea) para
 * justificar entregas exentas (E5, docs/02 §6/FR-022). Sin dependencia de la extensión `soap`
 * (R4): HTTP puro, timeout corto, degrada a `verificado=false` sin excepción si el servicio falla
 * o no responde. Resultado cacheado un plazo corto por NIF-IVA para no repetir la llamada.
 */
class VerificadorVies
{
    private const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    private const TIMEOUT_SEGUNDOS = 5;

    private const CACHE_TTL_MINUTOS = 60;

    /**
     * @return array{valido: bool, nombre: ?string, verificado: bool}
     */
    public static function verificar(string $nifIva, string $pais): array
    {
        $clave = 'vies.'.strtoupper($pais).'.'.strtoupper($nifIva);

        return Cache::remember($clave, now()->addMinutes(self::CACHE_TTL_MINUTOS), function () use ($nifIva, $pais) {
            try {
                $respuesta = Http::timeout(self::TIMEOUT_SEGUNDOS)->post(self::ENDPOINT, [
                    'countryCode' => strtoupper($pais),
                    'vatNumber' => strtoupper($nifIva),
                ]);

                if (! $respuesta->successful()) {
                    return ['valido' => false, 'nombre' => null, 'verificado' => false];
                }

                return [
                    'valido' => (bool) $respuesta->json('valid'),
                    'nombre' => $respuesta->json('name'),
                    'verificado' => true,
                ];
            } catch (\Throwable) {
                // VIES indisponible (timeout/caída): no bloquea, se informa sin verificar (FR-022).
                return ['valido' => false, 'nombre' => null, 'verificado' => false];
            }
        });
    }
}
