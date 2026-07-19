<?php

/**
 * Identificación del sistema informático de facturación (SIF) exigida por el bloque
 * `SistemaInformatico` del registro Verifactu (Orden HAC/1177/2024, docs/02-facturacion-espana.md
 * §1.3). Identifica al PRODUCTOR del software (esta aplicación), no al tenant/obligado tributario.
 *
 * IMPORTANTE: `nif` y `numero_registro` son datos que se obtienen al darse de alta como productor
 * de un SIF ante la AEAT (declaración responsable). Los valores de aquí son placeholders hasta que
 * exista ese alta real — deben completarse antes de activar el flag `verifactu.activo` en
 * producción para cualquier tenant. `id_sistema_informatico` es el código de máx. 2 caracteres que
 * el propio productor asigna a su sistema (no lo asigna la AEAT).
 */
return [
    'sistema_informatico' => [
        'nombre_razon' => env('VERIFACTU_SIF_NOMBRE_RAZON', 'Empire Systems'),
        'nif' => env('VERIFACTU_SIF_NIF'),
        'nombre_sistema_informatico' => env('VERIFACTU_SIF_NOMBRE', 'Empire Systems CRM'),
        'id_sistema_informatico' => env('VERIFACTU_SIF_ID', '01'),
        'version' => env('VERIFACTU_SIF_VERSION', '1.0'),
    ],
];
