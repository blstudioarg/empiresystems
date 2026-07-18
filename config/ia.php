<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelo del sistema
    |--------------------------------------------------------------------------
    |
    | Modelo fijo del asistente (decisión D2 de la feature 030). No lo elige el
    | tenant: lo fija el operador del SaaS a nivel de instalación vía IA_MODELO.
    | Proveedor: OpenAI (Chat Completions). Default: gpt-4o-mini.
    |
    */

    'modelo' => env('IA_MODELO', 'gpt-4o-mini'),

    /*
    |--------------------------------------------------------------------------
    | Límites de generación y conversación
    |--------------------------------------------------------------------------
    */

    // Máximo de tokens de salida por respuesta.
    'max_tokens' => 4096,

    // Umbral de truncado de la conversación (número de mensajes en sesión).
    'max_mensajes' => 30,

    // Tope de resultados que devuelve cada tool de lectura (minimización de datos).
    'max_resultados_tool' => 10,

    // Tope de iteraciones del loop de tool use por mensaje del usuario.
    'max_iteraciones_tools' => 8,

];
