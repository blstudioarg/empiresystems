<?php

namespace App\Services;

class ResultadoImportacionLeads
{
    /**
     * @param  array<int, array{fila: int, motivo: string}>  $rechazadas
     */
    public function __construct(
        public readonly int $importados,
        public readonly array $rechazadas,
    ) {}
}
