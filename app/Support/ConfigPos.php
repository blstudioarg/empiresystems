<?php

namespace App\Support;

use App\Models\Configuracion;

/**
 * Flags del módulo de hostelería del POS por tenant (feature 038, research.md D1), clonando el
 * patrón clave/valor de `configuraciones` que ya usa {@see ConfigFichajes}.
 *
 * **Los defaults viven aquí, no en filas sembradas.** La ausencia de la clave equivale a
 * "apagado", que es exactamente lo que hace que FR-002 ("desactivado por defecto en todo tenant,
 * existente o nuevo") se cumpla sin ninguna migración de datos para los tenants ya creados.
 *
 * `hosteleriaActivo()` es el interruptor maestro: las tres capacidades secundarias solo cuentan
 * como activas si además él lo está, para que apagar el módulo apague todo de una vez sin tener
 * que reescribir las otras filas.
 */
class ConfigPos
{
    public const CLAVE_HOSTELERIA_ACTIVO = 'pos.hosteleria_activo';

    public const CLAVE_OPCIONES_ACTIVO = 'pos.opciones_activo';

    public const CLAVE_COBRO_DIVIDIDO_ACTIVO = 'pos.cobro_dividido_activo';

    public const CLAVE_SUPLEMENTO_ZONA_ACTIVO = 'pos.suplemento_zona_activo';

    public const CLAVE_MESA_OLVIDADA_MIN = 'pos.mesa_olvidada_min';

    public const DEFAULT_MESA_OLVIDADA_MIN = 45;

    public const GRUPO = 'pos';

    public static function hosteleriaActivo(int $tenantId): bool
    {
        return self::flag($tenantId, self::CLAVE_HOSTELERIA_ACTIVO);
    }

    public static function opcionesActivo(int $tenantId): bool
    {
        return self::hosteleriaActivo($tenantId) && self::flag($tenantId, self::CLAVE_OPCIONES_ACTIVO);
    }

    public static function cobroDivididoActivo(int $tenantId): bool
    {
        return self::hosteleriaActivo($tenantId) && self::flag($tenantId, self::CLAVE_COBRO_DIVIDIDO_ACTIVO);
    }

    public static function suplementoZonaActivo(int $tenantId): bool
    {
        return self::hosteleriaActivo($tenantId) && self::flag($tenantId, self::CLAVE_SUPLEMENTO_ZONA_ACTIVO);
    }

    public static function mesaOlvidadaMin(int $tenantId): int
    {
        return self::entero($tenantId, self::CLAVE_MESA_OLVIDADA_MIN, self::DEFAULT_MESA_OLVIDADA_MIN);
    }

    /**
     * Estado completo del módulo, para la pestaña de configuración y para el payload de las
     * vistas del POS. Las capacidades se devuelven **crudas** (tal cual están guardadas), no
     * cruzadas con el interruptor maestro: la pantalla de configuración tiene que poder mostrar
     * cómo quedarán al reactivar el módulo (FR-007).
     *
     * @return array{hosteleria_activo: bool, opciones_activo: bool, cobro_dividido_activo: bool, suplemento_zona_activo: bool, mesa_olvidada_min: int}
     */
    public static function todo(int $tenantId): array
    {
        return [
            'hosteleria_activo' => self::flag($tenantId, self::CLAVE_HOSTELERIA_ACTIVO),
            'opciones_activo' => self::flag($tenantId, self::CLAVE_OPCIONES_ACTIVO),
            'cobro_dividido_activo' => self::flag($tenantId, self::CLAVE_COBRO_DIVIDIDO_ACTIVO),
            'suplemento_zona_activo' => self::flag($tenantId, self::CLAVE_SUPLEMENTO_ZONA_ACTIVO),
            'mesa_olvidada_min' => self::mesaOlvidadaMin($tenantId),
        ];
    }

    /**
     * Persiste los flags del módulo. Nunca borra zonas, mesas, grupos ni opciones: apagar una
     * capacidad solo la oculta (FR-007), de modo que reactivarla recupera los datos intactos.
     *
     * @param  array{hosteleria_activo?: bool, opciones_activo?: bool, cobro_dividido_activo?: bool, suplemento_zona_activo?: bool, mesa_olvidada_min?: int}  $valores
     */
    public static function guardar(int $tenantId, array $valores): void
    {
        $booleanos = [
            self::CLAVE_HOSTELERIA_ACTIVO => 'hosteleria_activo',
            self::CLAVE_OPCIONES_ACTIVO => 'opciones_activo',
            self::CLAVE_COBRO_DIVIDIDO_ACTIVO => 'cobro_dividido_activo',
            self::CLAVE_SUPLEMENTO_ZONA_ACTIVO => 'suplemento_zona_activo',
        ];

        foreach ($booleanos as $clave => $campo) {
            if (array_key_exists($campo, $valores)) {
                self::escribir($tenantId, $clave, $valores[$campo] ? '1' : '0', 'boolean');
            }
        }

        if (array_key_exists('mesa_olvidada_min', $valores)) {
            self::escribir($tenantId, self::CLAVE_MESA_OLVIDADA_MIN, (string) (int) $valores['mesa_olvidada_min'], 'integer');
        }
    }

    /**
     * Consulta acotada **explícitamente** al tenant pedido, sin el global scope de tenancy.
     *
     * El `tenant_id` llega siempre por parámetro, así que el `where` de abajo es más estricto que
     * el scope, no más laxo (Principio I intacto). Dejar el scope puesto sería peor: filtraría
     * además por el tenant *activo del request*, de modo que preguntar por otro tenant devolvería
     * silenciosamente el valor por defecto en vez del guardado — un falso "módulo apagado" que
     * solo se nota cuando ya está en producción.
     */
    private static function fila(int $tenantId, string $clave): ?string
    {
        $valor = Configuracion::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');

        return $valor !== null ? (string) $valor : null;
    }

    private static function escribir(int $tenantId, string $clave, string $valor, string $tipo): void
    {
        Configuracion::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => $clave],
            ['valor' => $valor, 'tipo' => $tipo, 'grupo' => self::GRUPO],
        );
    }

    private static function flag(int $tenantId, string $clave): bool
    {
        $valor = self::fila($tenantId, $clave);

        return $valor !== null ? (bool) (int) $valor : false;
    }

    private static function entero(int $tenantId, string $clave, int $default): int
    {
        $valor = self::fila($tenantId, $clave);

        return $valor !== null ? (int) $valor : $default;
    }
}
