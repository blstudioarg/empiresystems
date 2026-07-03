<?php

namespace App\Support;

class DominioTenant
{
    /**
     * Normaliza un dominio para almacenamiento/comparación (research.md D6): trim, minúsculas,
     * sin esquema http(s)://, sin path/query, sin puerto, sin barra final.
     *
     * El puerto se descarta porque `Request::getHost()` (usado por SetTenantContext para resolver
     * el tenant activo) nunca lo incluye — guardarlo en `domains.domain` haría que ese dominio no
     * pudiera resolver nunca. Si el usuario pega una URL con puerto (típico en desarrollo local,
     * ej. `http://midemo.localhost:8080/`), se descarta el `:8080` en vez de rechazar el valor.
     */
    public static function normalizar(?string $valor): string
    {
        $valor = trim((string) $valor);
        $valor = strtolower($valor);
        $valor = preg_replace('#^https?://#', '', $valor) ?? $valor;
        $valor = explode('/', $valor)[0];
        $valor = explode('?', $valor)[0];
        $valor = preg_replace('/:\d+$/', '', $valor) ?? $valor;

        return rtrim($valor, '/');
    }
}
