<?php

namespace App\Excel;

/**
 * Resultado de una importación confirmada (data-model.md §6).
 */
class ResultadoImportacion
{
    /**
     * @param  FilaRechazada[]  $rechazadas
     */
    public function __construct(
        public readonly int $importados,
        public readonly array $rechazadas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'importados' => $this->importados,
            'rechazadas' => array_map(fn (FilaRechazada $f) => $f->toArray(), $this->rechazadas),
        ];
    }
}
