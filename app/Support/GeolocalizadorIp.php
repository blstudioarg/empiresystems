<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resuelve "Ciudad, País" a partir de una IP pública, vía ip-api.com (gratis, sin API key).
 * Se llama únicamente al RENDERIZAR el listado de logs (nunca al escribir el evento): así ningún
 * login/alta/modificación del CRM paga la latencia de una llamada externa. El resultado se
 * cachea por IP (las IPs no cambian de ubicación de un momento a otro) para no repetir la
 * consulta ni pegarle al límite de la API gratuita (45 req/min).
 */
class GeolocalizadorIp
{
    private const CACHE_TTL_DIAS = 30;

    private const TIMEOUT_SEGUNDOS = 2;

    public static function ubicacion(?string $ip): ?string
    {
        if (! $ip || self::esIpPrivadaOInvalida($ip)) {
            return null;
        }

        return Cache::remember("geoip.{$ip}", now()->addDays(self::CACHE_TTL_DIAS), function () use ($ip) {
            try {
                $respuesta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                    ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,city']);

                if (! $respuesta->successful() || $respuesta->json('status') !== 'success') {
                    return null;
                }

                $partes = collect([$respuesta->json('city'), $respuesta->json('country')])
                    ->filter()
                    ->implode(', ');

                return $partes !== '' ? $partes : null;
            } catch (\Throwable) {
                // Timeout/red caída: no debe romper la vista del log por esto.
                return null;
            }
        });
    }

    private static function esIpPrivadaOInvalida(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
