<?php

namespace App\Excel;

/**
 * Resultado del análisis de un fichero subido, antes de confirmar (data-model.md §4). No se
 * persiste en base de datos: vive entre la respuesta de `previsualizar` y la petición de
 * `confirmar`, referenciada por `token`.
 */
class PrevisualizacionImportacion
{
    /**
     * @param  FilaRechazada[]  $rechazadas
     * @param  array<int, array<string, mixed>>  $muestra  Primeras 10 filas válidas ya normalizadas.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $modulo,
        public readonly int $totalFilas,
        public readonly int $validas,
        public readonly array $rechazadas,
        public readonly array $muestra,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'modulo' => $this->modulo,
            'total_filas' => $this->totalFilas,
            'validas' => $this->validas,
            'rechazadas' => array_map(fn (FilaRechazada $f) => $f->toArray(), $this->rechazadas),
            'muestra' => $this->muestra,
        ];
    }
}
