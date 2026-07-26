<?php

namespace App\Support;

/**
 * Objeto de valor inmutable con los filtros del informe comercial (contracts/informes-comerciales.md
 * de la feature 033). `canalId` admite tres estados: `null` (sin filtrar), `'sin_especificar'`
 * (solo leads sin canal) o un entero (canal concreto, forma corta de `leads.canal_captacion_id`).
 */
class FiltrosInforme
{
    private function __construct(
        public readonly int|string|null $canalId,
        public readonly ?int $comercialId,
        public readonly ?string $fase,
        public readonly bool $comparar,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros  request validado (o cualquier array de filtros)
     */
    public static function desdePeticion(array $filtros): self
    {
        $canalId = $filtros['canal_id'] ?? null;

        if ($canalId !== null && $canalId !== '' && $canalId !== 'sin_especificar') {
            $canalId = (int) $canalId;
        } elseif ($canalId === '') {
            $canalId = null;
        }

        $comercialId = isset($filtros['comercial_id']) && $filtros['comercial_id'] !== ''
            ? (int) $filtros['comercial_id']
            : null;

        $fase = $filtros['fase'] ?? null;
        $fase = is_string($fase) && $fase !== '' ? $fase : null;

        $comparar = filter_var($filtros['comparar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self($canalId, $comercialId, $fase, $comparar);
    }

    /**
     * Nueva instancia con `comercialId` forzado (o descartado), usada por
     * `AlcanceInformeComercial` para imponer el alcance del usuario en servidor (FR-024).
     */
    public function conComercialId(?int $comercialId): self
    {
        return new self($this->canalId, $comercialId, $this->fase, $this->comparar);
    }
}
