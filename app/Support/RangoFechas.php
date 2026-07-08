<?php

namespace App\Support;

use App\Enums\PresetRango;
use Carbon\Carbon;

/**
 * Rango de fechas de calendario (inclusive) para el filtro del dashboard, con su preset de
 * origen y el cálculo del periodo comparable inmediatamente anterior. Los presets "en curso"
 * cubren desde el inicio del periodo natural hasta hoy (no hasta el fin del periodo).
 */
class RangoFechas
{
    private function __construct(
        public readonly Carbon $desde,
        public readonly Carbon $hasta,
        public readonly PresetRango $preset,
    ) {}

    public static function mesEnCurso(?Carbon $hoy = null): self
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();

        return new self($hoy->copy()->startOfMonth(), $hoy, PresetRango::Mes);
    }

    public static function trimestreEnCurso(?Carbon $hoy = null): self
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();

        return new self($hoy->copy()->firstOfQuarter(), $hoy, PresetRango::Trimestre);
    }

    public static function anioEnCurso(?Carbon $hoy = null): self
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();

        return new self($hoy->copy()->startOfYear(), $hoy, PresetRango::Anio);
    }

    public static function personalizado(Carbon $desde, Carbon $hasta): self
    {
        return new self($desde->copy()->startOfDay(), $hasta->copy()->startOfDay(), PresetRango::Personalizado);
    }

    /**
     * Mapea el request validado (o cualquier array de filtros) a un rango. Ante `preset`
     * desconocido o un `personalizado` con fechas inválidas / `hasta < desde`, cae a
     * `mesEnCurso()` — nunca lanza.
     */
    public static function desdePeticion(array $filtros, ?Carbon $hoy = null): self
    {
        $preset = PresetRango::tryFrom((string) ($filtros['preset'] ?? ''));

        return match ($preset) {
            PresetRango::Trimestre => self::trimestreEnCurso($hoy),
            PresetRango::Anio => self::anioEnCurso($hoy),
            PresetRango::Personalizado => self::personalizadoDesdeFiltros($filtros, $hoy),
            PresetRango::Mes, null => self::mesEnCurso($hoy),
        };
    }

    private static function personalizadoDesdeFiltros(array $filtros, ?Carbon $hoy): self
    {
        $desde = self::parsearFecha($filtros['desde'] ?? null);
        $hasta = self::parsearFecha($filtros['hasta'] ?? null);

        if ($desde === null || $hasta === null || $hasta->lt($desde)) {
            return self::mesEnCurso($hoy);
        }

        return self::personalizado($desde, $hasta);
    }

    /**
     * Parsea una fecha `Y-m-d` sin arriesgarse a los warnings de `Carbon::parse()` ante strings
     * arbitrarios (el filtro puede venir de un query param manipulado a mano).
     */
    private static function parsearFecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        [$anio, $mes, $dia] = array_map('intval', explode('-', $valor));

        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        return Carbon::createFromDate($anio, $mes, $dia)->startOfDay();
    }

    /**
     * Periodo inmediatamente anterior, de igual número de días, terminando el día antes de
     * `desde`. Se usa solo para comparar magnitudes (variación %), no para pintar un selector.
     */
    public function anterior(): self
    {
        $hasta = $this->desde->copy()->subDay();
        $desde = $hasta->copy()->subDays($this->dias() - 1);

        return new self($desde, $hasta, PresetRango::Personalizado);
    }

    public function dias(): int
    {
        return (int) $this->desde->diffInDays($this->hasta) + 1;
    }

    public function granularidad(): string
    {
        return $this->dias() <= 62 ? 'dia' : 'mes';
    }

    public function contiene(Carbon $fecha): bool
    {
        $fecha = $fecha->copy()->startOfDay();

        return $fecha->gte($this->desde) && $fecha->lte($this->hasta);
    }

    public function etiqueta(): string
    {
        return $this->desde->translatedFormat('d/m/Y').' - '.$this->hasta->translatedFormat('d/m/Y');
    }
}
