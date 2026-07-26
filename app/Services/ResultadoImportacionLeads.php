<?php

namespace App\Services;

class ResultadoImportacionLeads
{
    /**
     * @param  array<int, array{fila: int, motivo: string}>  $rechazadas  filas NO importadas
     * @param  array<int, array{fila: int, motivo: string}>  $avisos  filas sí importadas, con una advertencia (p. ej. canal desconocido)
     */
    public function __construct(
        public readonly int $importados,
        public readonly array $rechazadas,
        public readonly array $avisos = [],
    ) {}
}
