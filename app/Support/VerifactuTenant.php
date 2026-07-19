<?php

namespace App\Support;

use App\Enums\EntornoVerifactu;
use App\Models\Configuracion;
use App\Models\FacturaEvento;

/**
 * Configuración Verifactu por tenant (clave/valor sobre `configuraciones`, grupo `verifactu`),
 * siguiendo el patrón de `ConfigTenant`/`CertificadoTenant`. Flag único todo-o-nada (FR-000,
 * research R5): con `activo = false` no se genera huella, QR ni envío. Default `false` para no
 * alterar el comportamiento de ningún tenant existente al desplegar la feature.
 */
class VerifactuTenant
{
    public const CLAVE_ACTIVO = 'verifactu.activo';

    public const CLAVE_ENTORNO = 'verifactu.entorno';

    public const DEFAULT_ENTORNO = EntornoVerifactu::Pruebas;

    public static function activo(int $tenantId): bool
    {
        $valor = self::valor($tenantId, self::CLAVE_ACTIVO);

        return $valor !== null ? (bool) (int) $valor : false;
    }

    public static function entorno(int $tenantId): EntornoVerifactu
    {
        $valor = self::valor($tenantId, self::CLAVE_ENTORNO);

        return $valor !== null && $valor !== ''
            ? EntornoVerifactu::from($valor)
            : self::DEFAULT_ENTORNO;
    }

    public static function activar(int $tenantId, bool $activo): void
    {
        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_ACTIVO],
            ['valor' => $activo ? '1' : '0', 'tipo' => 'boolean', 'grupo' => 'verifactu'],
        );
    }

    /**
     * Cambia el entorno configurado. El llamador (`VerifactuController`/`ConfiguracionController`)
     * es responsable de impedir el cambio cuando la cadena del tenant ya está iniciada (FR-020) —
     * este método no lo valida para mantener el helper puro de lectura/escritura.
     */
    public static function establecerEntorno(int $tenantId, EntornoVerifactu $entorno): void
    {
        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_ENTORNO],
            ['valor' => $entorno->value, 'tipo' => 'string', 'grupo' => 'verifactu'],
        );
    }

    /**
     * ¿Ya existe al menos un eslabón en la cadena Verifactu del tenant? Usado para impedir el
     * cambio de entorno una vez iniciada (FR-020): mezclaría registros de pruebas con la cadena
     * fiscal real.
     */
    public static function cadenaIniciada(int $tenantId): bool
    {
        return FacturaEvento::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('tipo_evento', ['verifactu_alta', 'verifactu_anulacion'])
            ->whereNotNull('huella')
            ->exists();
    }

    private static function valor(int $tenantId, string $clave): ?string
    {
        return Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');
    }
}
