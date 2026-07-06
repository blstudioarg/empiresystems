<?php

namespace App\Support;

use App\Models\Configuracion;
use Carbon\Carbon;

/**
 * Configuración general por tenant (clave/valor sobre `configuraciones`, grupo `general`).
 *
 * Hoy alberga la zona horaria del tenant: es la zona ÚNICA de toda la aplicación para ese
 * tenant (fichajes, logs, campañas, timestamps mostrados a personas), no una config específica
 * de un módulo. Antes vivía en el grupo `fichajes` (`fichajes.zona_horaria`); se promovió a
 * `general.zona_horaria` para que aplique a todo el front del tenant.
 */
class ConfigTenant
{
    public const CLAVE_ZONA_HORARIA = 'general.zona_horaria';

    /**
     * España-first (Principio II): Madrid es el default si el tenant no eligió otra. Las 3
     * opciones ofrecidas están fijas a propósito (ver ZONAS_HORARIAS_DISPONIBLES) — no es un
     * selector de cualquier zona IANA, es a medida de dónde opera el proyecto hoy.
     */
    public const DEFAULT_ZONA_HORARIA = 'Europe/Madrid';

    /**
     * @var array<string, string> identificador IANA => etiqueta legible para el <select>
     */
    public const ZONAS_HORARIAS_DISPONIBLES = [
        'Europe/Madrid' => 'Madrid (España peninsular/Baleares)',
        'Atlantic/Canary' => 'Canarias',
        'America/Argentina/Buenos_Aires' => 'Argentina',
    ];

    public static function zonaHoraria(int $tenantId): string
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_ZONA_HORARIA)
            ->value('valor');

        return $valor !== null && $valor !== '' ? $valor : self::DEFAULT_ZONA_HORARIA;
    }

    /**
     * Los timestamps se guardan y calculan siempre en `config('app.timezone')` (UTC) — fuente de
     * verdad (Principio III: hora de servidor, no del cliente). Esto NO cambia esa fuente: solo
     * convierte una copia a la hora local del tenant para mostrarla. Usar siempre antes de
     * `->format()` en cualquier vista/JSON que muestre un datetime a una persona. Para código de
     * vista con tenant activo, preferir la macro Carbon `->enZonaTenant()`.
     *
     * OJO: solo aplicar a datetimes reales. Los campos `date` puros (p. ej. `fecha_expedicion`
     * de una factura) NO llevan hora; convertirlos desplazaría el día.
     */
    public static function paraMostrar(Carbon $fecha, int $tenantId): Carbon
    {
        return $fecha->copy()->setTimezone(self::zonaHoraria($tenantId));
    }
}
