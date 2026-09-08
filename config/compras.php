<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Importación de documentos (feature 044)
    |--------------------------------------------------------------------------
    |
    | Límites del lote que se manda a interpretar por IA. El máximo de páginas
    | acota lo que viaja en base64 al proveedor: un PDF largo dispara el coste y
    | el tiempo de la petición, que corre síncrona dentro del request porque el
    | hosting compartido no tiene colas (Principio V de la constitución).
    |
    */

    'documentos' => [

        'max_ficheros' => (int) env('COMPRAS_DOCUMENTOS_MAX_FICHEROS', 10),

        'max_mb' => (int) env('COMPRAS_DOCUMENTOS_MAX_MB', 10),

        'max_paginas_pdf' => (int) env('COMPRAS_DOCUMENTOS_MAX_PAGINAS_PDF', 10),

        'tipos' => explode(',', (string) env('COMPRAS_DOCUMENTOS_TIPOS', 'pdf,jpg,jpeg,png,webp')),

        // Horas que sobrevive el fichero temporal de una propuesta sin confirmar.
        // Es dato personal del proveedor: retención acotada + purga diaria (RGPD,
        // Principio II), mismo patrón que las importaciones de Excel.
        'horas_retencion' => (int) env('COMPRAS_DOCUMENTOS_HORAS_RETENCION', 24),

    ],

];
