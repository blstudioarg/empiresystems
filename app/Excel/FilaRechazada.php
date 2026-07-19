<?php

namespace App\Excel;

/**
 * Una fila rechazada al previsualizar o confirmar una importación (data-model.md §5). Mismo
 * contrato que las `rechazadas` de `ResultadoImportacionLeads`, para que los mensajes sean
 * coherentes entre ambos importadores.
 */
class FilaRechazada
{
    public function __construct(
        public readonly int $fila,
        public readonly string $motivo,
    ) {}

    /**
     * @return array{fila: int, motivo: string}
     */
    public function toArray(): array
    {
        return ['fila' => $this->fila, 'motivo' => $this->motivo];
    }
}
