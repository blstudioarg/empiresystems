<?php

namespace App\Support;

use App\Models\Configuracion;

class VencimientoFactura
{
    public static function diasPorDefecto(): int
    {
        $configuracion = Configuracion::where('clave', 'factura.dias_vencimiento')->first();

        return $configuracion ? (int) $configuracion->valor : 30;
    }

    public static function calcular(string $fechaExpedicion): string
    {
        return date('Y-m-d', strtotime($fechaExpedicion.' + '.self::diasPorDefecto().' days'));
    }
}
