<?php

namespace App\Console\Commands;

use App\Traduccion\Bilingue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

/**
 * Paso del deploy (feature 050, research D9): genera una vez la caché de métricas de Noto Sans SC
 * en `storage/fonts`. dompdf la crea al cargar la fuente por primera vez, y con una fuente CJK eso
 * cuesta varios segundos y bastante memoria: mejor en el deploy que en el primer ticket en chino.
 * Idempotente: si la caché ya existe, el render es inmediato.
 */
class PrepararFuentesPos extends Command
{
    protected $signature = 'pos:preparar-fuentes';

    protected $description = 'Genera la caché de métricas de la fuente CJK (Noto Sans SC) para los PDF del POS';

    public function handle(): int
    {
        Bilingue::prepararCarpetaFuentes();

        $inicio = microtime(true);
        $html = '<html><head><meta charset="utf-8">'.Bilingue::estiloFuenteCjk().'</head>'
            .'<body><p>Total / 合计</p><p><strong>预结单</strong></p></body></html>';

        $pdf = Pdf::loadHTML($html)->setOption('isFontSubsettingEnabled', true)->output();

        $this->info(sprintf(
            'Fuente CJK lista en %s (%.1f s, PDF de prueba de %d KB).',
            storage_path('fonts'), microtime(true) - $inicio, intdiv(strlen($pdf), 1024),
        ));

        return self::SUCCESS;
    }
}
