<?php

namespace App\Support;

use App\Enums\EntornoVerifactu;
use App\Models\Factura;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR de cotejo AEAT (docs/02-facturacion-espana.md §1.2, contracts/qr-cotejo.md). Genera la imagen
 * en SVG (chillerlan/php-qrcode, sin `ext-gd`/`imagick`, Principio V) embebida como data URI.
 */
class QrVerifactu
{
    private const URL_PRODUCCION = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    private const URL_PRUEBAS = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    /**
     * Compone la URL oficial de cotejo con los parámetros del registro, en el orden fijado por la
     * AEAT: nif, numserie, fecha (DD-MM-AAAA), importe (con punto decimal).
     */
    public static function url(Factura $factura): string
    {
        $base = $factura->verifactu_entorno === EntornoVerifactu::Produccion
            ? self::URL_PRODUCCION
            : self::URL_PRUEBAS;

        $parametros = [
            'nif' => (string) $factura->tenant->nif,
            'numserie' => (string) $factura->numero_completo,
            'fecha' => $factura->fecha_expedicion->format('d-m-Y'),
            'importe' => number_format((float) $factura->total, 2, '.', ''),
        ];

        return $base.'?'.http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Renderiza el QR de la URL dada como SVG embebido en un data URI, listo para un `<img>` en el
     * PDF (nivel de corrección M y margen de silencio, docs/02 §1.2).
     */
    public static function imagen(string $url): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::M,
        ]);

        $svg = (new QRCode($options))->render($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
