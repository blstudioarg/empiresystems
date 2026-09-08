<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Material aportado al asistente (feature 046)
    |--------------------------------------------------------------------------
    |
    | Tipos admitidos y tope de páginas del material que hay que interpretar con
    | IA. El tope existe porque el coste y la latencia de la interpretación
    | crecen con el documento y no con las filas resultantes (research D9): un
    | PDF de 40 páginas puede producir 30 filas y costar cuarenta veces más que
    | un Excel de 2.000. Va en config y no hardcodeado para poder ajustarlo sin
    | tocar código.
    |
    | El tope de 2.000 filas y los 5 MB por fichero son de la feature 031 y los
    | sigue aplicando el importador: no se duplican aquí.
    |
    */

    'material' => [

        'tipos' => explode(',', (string) env('IMPORTACION_MATERIAL_TIPOS', 'xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp')),

        'max_paginas' => (int) env('IMPORTACION_MATERIAL_MAX_PAGINAS', 20),

    ],

];
